<?php

namespace Tests\Feature;

use App\Models\Administrativo;
use App\Models\Candidato;
use App\Mail\CandidatoBloqueadoMail;
use App\Models\CandidatoBloqueioNotificacao;
use App\Models\Contratacao;
use App\Models\CursoExterno;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Models\PreferenciasDeTrabalho;
use App\Models\ResponsavelContratual;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\GeneratesMatricula;
use Tests\TestCase;
use App\Services\Candidatos\CandidatoStatusService;
use App\Models\CandidatoReativacaoQuestionario;

class CandidatoInatividadeTest extends TestCase
{
    use RefreshDatabase;
    use GeneratesMatricula;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'candidato.inatividade_habilitada' => true,
            'candidato.inatividade_segundos' => 30,
            // Não herdar PERGUNTAS_MODO_TESTE do .env local; testes do modo 30 s ativam explicitamente.
            'candidato.perguntas_modo_teste' => false,
        ]);
    }

    public function test_fronteiras_29_30_31_segundos_na_busca_da_empresa(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();
        [, $ativo29] = $this->criarCandidato(ultimaAtividade: now()->subSeconds(29));
        $this->criarCandidato(ultimaAtividade: now()->subSeconds(30));
        $this->criarCandidato(ultimaAtividade: now()->subSeconds(31));

        $this->withToken($tokenEmpresa)->getJson('/api/candidatos?per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.matricula', (string) $ativo29->matricula);
    }

    public function test_prazo_configurado_para_300_segundos_e_usado_na_busca_da_empresa(): void
    {
        config(['candidato.inatividade_segundos' => 300]);
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();
        [, $ativo299] = $this->criarCandidato(ultimaAtividade: now()->subSeconds(299));
        $this->criarCandidato(ultimaAtividade: now()->subSeconds(300));

        $this->withToken($tokenEmpresa)->getJson('/api/candidatos?per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.matricula', (string) $ativo299->matricula);
    }

    public function test_inatividade_desativada_mantem_candidato_antigo_disponivel_sem_liberar_manual_ou_contratado(): void
    {
        config(['candidato.inatividade_habilitada' => false]);
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();
        [, $antigo] = $this->criarCandidato(ultimaAtividade: now()->subYear());
        $this->criarCandidato(status: false, ultimaAtividade: now()->subYear());
        [, $contratado] = $this->criarCandidato(ultimaAtividade: now()->subYear());
        $this->registrarContratacao($contratado);

        $this->withToken($tokenEmpresa)->getJson('/api/candidatos?per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.matricula', (string) $antigo->matricula);
    }

    public function test_inatividade_desativada_nao_lista_candidatos_no_filtro_inativo(): void
    {
        config(['candidato.inatividade_habilitada' => false]);
        [, $tokenAdmin] = $this->criarAdminAutenticado();
        $this->criarCandidato(ultimaAtividade: now()->subYear());

        $this->withToken($tokenAdmin)->getJson('/api/candidatos?status=inativo&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_candidato_contratado_continua_restrito_com_inatividade_desativada(): void
    {
        config(['candidato.inatividade_habilitada' => false]);
        [$pessoa, $candidato] = $this->criarCandidato(ultimaAtividade: now()->subYear());
        $this->registrarContratacao($candidato);

        $this->postJson('/api/auth/login', [
            'identificador' => $pessoa->email,
            'senha' => '123456',
        ])->assertOk()
            ->assertJsonPath('restricoes.estado', 'CONTRATADO')
            ->assertJsonPath('restricoes.contratado', true)
            ->assertJsonPath('restricoes.pode_editar_perfil', false);
    }

    public function test_login_nao_reativa_candidato_bloqueado_por_inatividade_e_exige_questionario(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        [$pessoa, $candidato] = $this->criarCandidato(ultimaAtividade: now()->subSeconds(31));

        $this->postJson('/api/auth/login', [
            'identificador' => $pessoa->email,
            'senha' => '123456',
        ])->assertOk()
            ->assertJsonPath('restricoes.estado', 'BLOQUEADO_POR_INATIVIDADE')
            ->assertJsonPath('restricoes.inativo', true)
            ->assertJsonPath('restricoes.pode_editar_perfil', false);

        $this->assertTrue($candidato->refresh()->ultima_atividade_em->equalTo(now()->subSeconds(31)));
        Mail::assertSent(CandidatoBloqueadoMail::class);
    }

    public function test_questionario_completo_reativa_candidato_inativo_e_salva_historico(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        [, $candidato, $token] = $this->criarCandidato(ultimaAtividade: now()->subSeconds(31));

        $this->withToken($token)->postJson('/api/me/reativacao-questionario', $this->respostasQuestionario())
            ->assertOk()
            ->assertJsonPath('reativado', true)
            ->assertJsonPath('resultado', 'REATIVADO')
            ->assertJsonPath('restricoes.estado', 'ATIVO');

        $this->assertTrue($candidato->refresh()->ultima_atividade_em->equalTo(now()));
        $this->assertDatabaseHas('candidato_reativacao_questionarios', [
            'candidato_matricula' => $candidato->matricula,
            'resposta_1' => 'manter_ativo',
            'resultado' => 'REATIVADO',
        ]);
    }

    public function test_resposta_negativa_registra_decisao_sem_reativar(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        [, $candidato, $token] = $this->criarCandidato(ultimaAtividade: now()->subSeconds(31));

        $this->withToken($token)->postJson('/api/me/reativacao-questionario', ['resposta_1' => 'encerrar_cadastro', 'confirmar_encerramento' => true])
            ->assertOk()
            ->assertJsonPath('reativado', false)
            ->assertJsonPath('resultado', 'ENCERRAMENTO_SOLICITADO');

        $this->assertTrue($candidato->refresh()->ultima_atividade_em->equalTo(now()->subSeconds(31)));
        $this->assertDatabaseHas('candidato_reativacao_questionarios', [
            'candidato_matricula' => $candidato->matricula,
            'resposta_1' => 'encerrar_cadastro',
            'resultado' => 'ENCERRAMENTO_SOLICITADO',
        ]);
    }

    public function test_questionario_incompleto_ou_manipulado_e_recusado(): void
    {
        [, $candidato, $token] = $this->criarCandidato(ultimaAtividade: now()->subSeconds(31));

        $this->withToken($token)->postJson('/api/me/reativacao-questionario', [
            'resposta_1' => 'manter_ativo',
            'resposta_4' => ['valor_invalido'],
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('candidato_reativacao_questionarios', [
            'candidato_matricula' => $candidato->matricula,
        ]);
    }

    public function test_questionario_nao_remove_bloqueio_administrativo_nem_contratacao(): void
    {
        [, $manual, $tokenManual] = $this->criarCandidato(status: false, ultimaAtividade: now()->subSeconds(31));
        [, $contratado, $tokenContratado] = $this->criarCandidato(ultimaAtividade: now()->subSeconds(31));
        $this->registrarContratacao($contratado);

        $this->withToken($tokenManual)->postJson('/api/me/reativacao-questionario', $this->respostasQuestionario())
            ->assertForbidden();

        $this->withToken($tokenContratado)->postJson('/api/me/reativacao-questionario', $this->respostasQuestionario())
            ->assertUnprocessable();

        $this->assertFalse($manual->refresh()->status);
        $this->assertTrue($contratado->refresh()->estaContratado());
    }

    public function test_requisicoes_duplicadas_registram_apenas_uma_conclusao(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        [, $candidato, $token] = $this->criarCandidato(ultimaAtividade: now()->subSeconds(31));
        $respostas = $this->respostasQuestionario();

        $this->withToken($token)->postJson('/api/me/reativacao-questionario', $respostas)->assertOk();
        $this->withToken($token)->postJson('/api/me/reativacao-questionario', $respostas)->assertOk();

        $this->assertDatabaseCount('candidato_reativacao_questionarios', 1);
    }

    public function test_admin_notifica_inatividade_uma_unica_vez_sem_disparo_por_empresa(): void
    {
        Mail::fake();
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();
        [, $tokenAdmin] = $this->criarAdminAutenticado();
        [, $candidato] = $this->criarCandidato(ultimaAtividade: now()->subSeconds(31));

        $this->withToken($tokenEmpresa)->getJson('/api/candidatos?per_page=10')->assertOk();
        Mail::assertNothingSent();

        $this->withToken($tokenAdmin)->getJson('/api/candidatos?status=inativo&per_page=10')->assertOk();
        $this->withToken($tokenAdmin)->getJson('/api/candidatos?status=inativo&per_page=10')->assertOk();
        app(\App\Services\Candidatos\CandidatoStatusService::class)->registrarBloqueioPorInatividade($candidato->fresh(['pessoa', 'contratacao']));

        Mail::assertSent(CandidatoBloqueadoMail::class, 1);
        $this->assertDatabaseCount('candidato_bloqueio_notificacoes', 1);
        $this->assertDatabaseHas('candidato_bloqueio_notificacoes', [
            'candidato_matricula' => $candidato->matricula,
            'tipo_bloqueio' => 'BLOQUEADO_POR_INATIVIDADE',
            'status' => CandidatoBloqueioNotificacao::STATUS_ENVIADO,
        ]);
    }

    public function test_email_de_bloqueio_renderiza_com_logo_inline_e_link_de_login(): void
    {
        [$pessoa, $candidato] = $this->criarCandidato(ultimaAtividade: now()->subSeconds(31));
        $loginUrl = 'http://localhost:5173/login';

        $mail = new CandidatoBloqueadoMail($candidato->load('pessoa'), 'BLOQUEADO_POR_INATIVIDADE', loginUrl: $loginUrl);
        $html = $mail->render();

        $this->assertFileExists(public_path(CandidatoBloqueadoMail::LOGO_PATH));
        $this->assertStringContainsString('cid:' . CandidatoBloqueadoMail::LOGO_CID, $html);
        $this->assertStringContainsString('Olá, ' . $pessoa->nome . '!', $html);
        $this->assertStringContainsString($loginUrl, $html);
        $this->assertStringContainsString('REATIVAR MEU CADASTRO', $html);
        $this->assertCount(1, $mail->callbacks);
    }

    public function test_bloqueio_manual_envia_email_com_motivo_publico_e_preserva_login_bloqueado(): void
    {
        Mail::fake();
        [$pessoaAdmin, $tokenAdmin] = $this->criarAdminAutenticado();
        [$pessoa, $candidato] = $this->criarCandidato(ultimaAtividade: now());

        $this->withToken($tokenAdmin)->putJson("/api/candidatos/{$candidato->matricula}", [
            'status' => false,
            'motivo_bloqueio_publico' => 'Cadastro com pendência documental.',
        ])->assertOk();

        Mail::assertSent(CandidatoBloqueadoMail::class, function (CandidatoBloqueadoMail $mail) {
            return $mail->tipoBloqueio === 'BLOQUEADO_MANUALMENTE'
                && $mail->motivoPublico === 'Cadastro com pendência documental.';
        });

        $this->postJson('/api/auth/login', [
            'identificador' => $pessoa->email,
            'senha' => '123456',
        ])->assertForbidden();

        $this->assertDatabaseHas('candidato_bloqueio_notificacoes', [
            'candidato_matricula' => $candidato->matricula,
            'tipo_bloqueio' => 'BLOQUEADO_MANUALMENTE',
            'origem' => CandidatoBloqueioNotificacao::ORIGEM_MANUAL_INDIVIDUAL,
            'administrador_pessoa_id' => $pessoaAdmin->id_pessoa,
        ]);
    }

    public function test_bloqueio_manual_de_candidato_inativo_envia_somente_email_administrativo(): void
    {
        Mail::fake();
        [, $tokenAdmin] = $this->criarAdminAutenticado();
        [, $candidato] = $this->criarCandidato(ultimaAtividade: now()->subSeconds(31));

        $this->withToken($tokenAdmin)->putJson("/api/candidatos/{$candidato->matricula}", [
            'status' => false,
            'motivo_bloqueio_publico' => 'Bloqueio administrativo válido.',
        ])->assertOk();

        Mail::assertSent(CandidatoBloqueadoMail::class, 1);
        Mail::assertSent(CandidatoBloqueadoMail::class, function (CandidatoBloqueadoMail $mail) {
            return $mail->tipoBloqueio === 'BLOQUEADO_MANUALMENTE'
                && $mail->motivoPublico === 'Bloqueio administrativo válido.';
        });
        Mail::assertNotSent(CandidatoBloqueadoMail::class, function (CandidatoBloqueadoMail $mail) {
            return $mail->tipoBloqueio === 'BLOQUEADO_POR_INATIVIDADE';
        });

        $this->assertDatabaseCount('candidato_bloqueio_notificacoes', 1);
        $this->assertDatabaseHas('candidato_bloqueio_notificacoes', [
            'candidato_matricula' => $candidato->matricula,
            'tipo_bloqueio' => 'BLOQUEADO_MANUALMENTE',
            'status' => CandidatoBloqueioNotificacao::STATUS_ENVIADO,
        ]);
    }

    public function test_repeticao_de_bloqueio_manual_nao_reenvia_email(): void
    {
        Mail::fake();
        [, $tokenAdmin] = $this->criarAdminAutenticado();
        [, $candidato] = $this->criarCandidato(ultimaAtividade: now());

        $this->withToken($tokenAdmin)->putJson("/api/candidatos/{$candidato->matricula}", [
            'status' => false,
            'motivo_bloqueio_publico' => 'Primeiro bloqueio.',
        ])->assertOk();

        $this->withToken($tokenAdmin)->putJson("/api/candidatos/{$candidato->matricula}", [
            'status' => false,
            'motivo_bloqueio_publico' => 'Clique repetido.',
        ])->assertOk();

        Mail::assertSent(CandidatoBloqueadoMail::class, 1);
        $this->assertDatabaseCount('candidato_bloqueio_notificacoes', 1);
    }

    public function test_rotina_de_inatividade_nao_afeta_bloqueio_manual_existente(): void
    {
        Mail::fake();
        [, $candidato] = $this->criarCandidato(status: false, ultimaAtividade: now()->subSeconds(31));

        app(\App\Services\Candidatos\CandidatoStatusService::class)->registrarBloqueioPorInatividade($candidato->fresh(['pessoa', 'contratacao']));

        Mail::assertNothingSent();
        $this->assertDatabaseCount('candidato_bloqueio_notificacoes', 0);
        $this->assertFalse($candidato->refresh()->status);
    }

    public function test_job_reprocessado_nao_reenvia_notificacao_enviada(): void
    {
        Mail::fake();
        [, $candidato] = $this->criarCandidato(ultimaAtividade: now()->subSeconds(31));

        $service = app(\App\Services\Candidatos\CandidatoStatusService::class);
        $service->registrarBloqueioPorInatividade($candidato->fresh(['pessoa', 'contratacao']));
        $notificacao = CandidatoBloqueioNotificacao::query()->firstOrFail();
        app(\App\Services\Candidatos\CandidatoBloqueioNotificationService::class)->enviar($notificacao->fresh(), $candidato->fresh(['pessoa']), 'BLOQUEADO_POR_INATIVIDADE');

        Mail::assertSent(CandidatoBloqueadoMail::class, 1);
        $this->assertDatabaseCount('candidato_bloqueio_notificacoes', 1);
    }

    public function test_falha_smtp_nao_desfaz_bloqueio_manual(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
        [$pessoaAdmin, $tokenAdmin] = $this->criarAdminAutenticado();
        [, $candidato] = $this->criarCandidato(ultimaAtividade: now());

        $this->withToken($tokenAdmin)->putJson("/api/candidatos/{$candidato->matricula}", [
            'status' => false,
        ])->assertOk();

        $this->assertFalse($candidato->refresh()->status);
        $this->assertDatabaseHas('candidato_bloqueio_notificacoes', [
            'candidato_matricula' => $candidato->matricula,
            'tipo_bloqueio' => 'BLOQUEADO_MANUALMENTE',
            'status' => CandidatoBloqueioNotificacao::STATUS_FALHOU,
        ]);
    }

    public function test_login_nao_libera_bloqueio_manual(): void
    {
        [$pessoa] = $this->criarCandidato(status: false, ultimaAtividade: now()->subSeconds(31));

        $this->postJson('/api/auth/login', [
            'identificador' => $pessoa->email,
            'senha' => '123456',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Conta bloqueada.');
    }

    public function test_login_nao_remove_restricao_de_contratado(): void
    {
        [$pessoa, $candidato] = $this->criarCandidato(ultimaAtividade: now()->subYear());
        $this->registrarContratacao($candidato);

        $this->postJson('/api/auth/login', [
            'identificador' => $pessoa->email,
            'senha' => '123456',
        ])->assertOk()
            ->assertJsonPath('restricoes.estado', 'CONTRATADO')
            ->assertJsonPath('restricoes.contratado', true)
            ->assertJsonPath('restricoes.pode_editar_perfil', false);
    }

    public function test_atualizacao_valida_reinicia_prazo_se_a_sessao_ainda_nao_expirou(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:20'));
        [, $candidato, $token] = $this->criarCandidato(ultimaAtividade: now()->subSeconds(20));

        $this->withToken($token)->postJson("/api/candidatos/{$candidato->matricula}/perfil/preferencias", [
            'tipo_de_contratacao' => 1,
            'disponibilidade_de_horario' => ['Integral'],
            'aceita_todas_regioes' => true,
        ])->assertCreated();

        $this->assertTrue($candidato->refresh()->ultima_atividade_em->equalTo(now()));
    }

    public function test_atualizacao_apos_expiracao_exige_novo_login(): void
    {
        [, $candidato, $token] = $this->criarCandidato(ultimaAtividade: now()->subSeconds(31));

        $this->withToken($token)->postJson("/api/candidatos/{$candidato->matricula}/perfil/links", [
            'linkedin' => 'https://linkedin.com/in/inativo',
        ])->assertForbidden()
            ->assertJsonPath('code', 'CANDIDATO_INATIVO');
    }

    public function test_empresa_exclui_inativo_contratado_bloqueado_e_pagina_somente_elegiveis(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();
        for ($i = 0; $i < 12; $i++) {
            $this->criarCandidato(ultimaAtividade: now()->subSeconds(10));
        }
        $this->criarCandidato(ultimaAtividade: now()->subSeconds(31));
        $this->criarCandidato(status: false, ultimaAtividade: now());
        [, $contratado] = $this->criarCandidato(ultimaAtividade: now());
        $this->registrarContratacao($contratado);

        $this->withToken($tokenEmpresa)->getJson('/api/candidatos?per_page=10&page=1')
            ->assertOk()
            ->assertJsonPath('total', 12)
            ->assertJsonCount(10, 'data');
    }

    public function test_admin_gestao_candidatos_exclui_contratados_e_dashboard_usa_estado_efetivo(): void
    {
        [, $tokenAdmin] = $this->criarAdminAutenticado();
        $ativo = $this->criarCandidato(ultimaAtividade: now()->subSeconds(10))[1];
        $inativo = $this->criarCandidato(ultimaAtividade: now()->subSeconds(31))[1];
        $manual = $this->criarCandidato(status: false, ultimaAtividade: now())[1];
        $contratado = $this->criarCandidato(ultimaAtividade: now())[1];
        $this->registrarContratacao($contratado);

        $lista = $this->withToken($tokenAdmin)->getJson('/api/candidatos?per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->json('data');

        $estados = collect($lista)->pluck('estado_efetivo', 'matricula');
        $this->assertSame('ATIVO', $estados[(string) $ativo->matricula]);
        $this->assertSame('BLOQUEADO_POR_INATIVIDADE', $estados[(string) $inativo->matricula]);
        $this->assertSame('BLOQUEADO_MANUALMENTE', $estados[(string) $manual->matricula]);
        $this->assertArrayNotHasKey((string) $contratado->matricula, $estados->all());

        $this->withToken($tokenAdmin)->getJson('/api/administrativo/dashboard')
            ->assertOk()
            ->assertJsonPath('perfisAtivos.total', 1);
    }

    public function test_admin_gestao_candidatos_remove_contratados_no_backend_antes_da_paginacao_e_busca(): void
    {
        [, $tokenAdmin] = $this->criarAdminAutenticado();

        for ($i = 0; $i < 5; $i++) {
            $this->criarCandidato(ultimaAtividade: now());
        }

        for ($i = 0; $i < 2; $i++) {
            $this->criarCandidato(ultimaAtividade: now()->subSeconds(31));
        }

        $contratados = [];
        for ($i = 0; $i < 12; $i++) {
            [$pessoa, $candidato] = $this->criarCandidato(ultimaAtividade: now()->subYear());
            $pessoa->update(['nome' => $i === 0 ? 'Arlinson Contratado' : 'Contratado ' . $i]);
            $this->registrarContratacao($candidato);
            $contratados[] = $candidato;
        }

        $this->withToken($tokenAdmin)->getJson('/api/candidatos?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 7)
            ->assertJsonPath('per_page', 10)
            ->assertJsonCount(7, 'data');

        $this->withToken($tokenAdmin)->getJson('/api/candidatos?page=2&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 7)
            ->assertJsonCount(0, 'data');

        $this->withToken($tokenAdmin)->getJson('/api/candidatos?busca=Arlinson&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 0);

        $this->withToken($tokenAdmin)->getJson('/api/contratacoes?nome=Arlinson&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.candidato.matricula', (string) $contratados[0]->matricula)
            ->assertJsonPath('data.0.empresa.cnpj', $contratados[0]->contratacao->empresa_cnpj);
    }

    public function test_admin_filtros_estado_efetivo_respeitam_prioridade_contratado(): void
    {
        [, $tokenAdmin] = $this->criarAdminAutenticado();
        $ativo = $this->criarCandidato(ultimaAtividade: now())[1];
        $manual = $this->criarCandidato(status: false, ultimaAtividade: now())[1];
        $inativo = $this->criarCandidato(ultimaAtividade: now()->subSeconds(31))[1];
        $contratadoInativo = $this->criarCandidato(ultimaAtividade: now()->subSeconds(31))[1];
        $this->registrarContratacao($contratadoInativo);

        $this->withToken($tokenAdmin)->getJson('/api/candidatos?status=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.matricula', (string) $ativo->matricula);

        $this->withToken($tokenAdmin)->getJson('/api/candidatos?status=0&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.matricula', (string) $manual->matricula);

        $this->withToken($tokenAdmin)->getJson('/api/candidatos?status=inativo&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.matricula', (string) $inativo->matricula);

        $this->withToken($tokenAdmin)->getJson('/api/candidatos?status=contratado&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 0);

        $this->withToken($tokenAdmin)->getJson('/api/contratacoes?per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.candidato.matricula', (string) $contratadoInativo->matricula);
    }

    private function criarCandidato(bool $status = true, ?Carbon $ultimaAtividade = null, ?Carbon $ultimaAtualizacaoReal = null): array
    {
        $pessoa = Pessoa::query()->create([
            'nome' => 'Aluno Teste',
            'email' => 'aluno' . Str::random(8) . '@teste.com',
            'telefone' => (string) random_int(10000000000, 99999999999),
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);

        $candidato = Candidato::query()->create([
            'matricula' => $this->gerarMatricula(),
            'cpf' => (string) random_int(10000000000, 99999999999),
            'status' => $status,
            'ultima_atividade_em' => $ultimaAtividade ?? now(),
            'ultima_atualizacao_real_em' => $ultimaAtualizacaoReal ?? ($ultimaAtividade ?? now()),
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);

        PreferenciasDeTrabalho::query()->create([
            'tipo_de_contratacao' => 1,
            'disponibilidade_de_horario' => ['Integral'],
            'regiao_administrativa' => 'Todas as regiões',
            'aceita_todas_regioes' => true,
            'candidato_matricula' => $candidato->matricula,
        ]);

        $token = 'inatividade-token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return [$pessoa, $candidato, $token];
    }

    private function criarEmpresaAutenticada(): array
    {
        $pessoa = Pessoa::query()->create([
            'nome' => 'Empresa Teste',
            'email' => 'empresa' . Str::random(8) . '@teste.com',
            'telefone' => (string) random_int(10000000000, 99999999999),
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);
        $responsavel = ResponsavelContratual::query()->create(['pessoa_id_pessoa' => $pessoa->id_pessoa]);
        $empresa = Empresa::query()->create([
            'cnpj' => (string) random_int(10000000000000, 99999999999999),
            'razao_social' => 'Empresa Teste',
            'atividade_economica' => 'Tecnologia',
            'status' => true,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
            'responsavel_contratual_id_responsavel_contratual' => $responsavel->id_responsavel_contratual,
        ]);

        return [$pessoa, $empresa, $this->token($pessoa)];
    }

    private function criarAdminAutenticado(): array
    {
        $pessoa = Pessoa::query()->create([
            'nome' => 'Admin Teste',
            'email' => 'admin' . Str::random(8) . '@teste.com',
            'telefone' => (string) random_int(10000000000, 99999999999),
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);
        Administrativo::query()->create(['pessoa_id_pessoa' => $pessoa->id_pessoa]);

        return [$pessoa, $this->token($pessoa)];
    }

    private function registrarContratacao(Candidato $candidato): Contratacao
    {
        [, $empresa] = $this->criarEmpresaAutenticada();
        [$admin] = $this->criarAdminAutenticado();

        return Contratacao::query()->create([
            'candidato_matricula' => $candidato->matricula,
            'empresa_cnpj' => $empresa->cnpj,
            'registrado_por_pessoa_id' => $admin->id_pessoa,
            'origem' => 'administrativo',
            'contratado_em' => now()->toDateString(),
        ]);
    }

    private function token(Pessoa $pessoa): string
    {
        $token = 'token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return $token;
    }

    private function respostasQuestionario(array $sobrescrever = []): array
    {
        return array_merge([
            'resposta_1' => 'manter_ativo',
            'resposta_2' => 'buscando_area_senac',
            'resposta_3' => 'nao_trabalhando',
            'resposta_4' => ['estagio', 'emprego_efetivo'],
            'resposta_5' => 'integral',
            'resposta_6' => 'presencial_online',
            'resposta_7' => 'atualizados',
            'resposta_8' => 'mesmas_informacoes',
        ], $sobrescrever);
    }

    public function test_email_de_atualizacao_periodica_usa_assunto_e_mensagem_corretos(): void
    {
        $pessoa = Pessoa::query()->create([
            'nome' => 'Maria Souza',
            'email' => 'maria.souza@teste.com',
            'telefone' => '61999990000',
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);

        $candidato = Candidato::query()->create([
            'matricula' => '2026000001',
            'cpf' => '12345678909',
            'status' => true,
            'ultima_atividade_em' => now(),
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);

        Mail::fake();

        Mail::to($pessoa->email)->send(new CandidatoBloqueadoMail(
            $candidato,
            CandidatoStatusService::ATIVO,
            'Atualização periódica do cadastro.',
            'https://portal.test/login',
            true,
        ));

        Mail::assertSent(CandidatoBloqueadoMail::class, function (CandidatoBloqueadoMail $mail) use ($pessoa) {
            $html = $mail->render();

            $this->assertStringContainsString('Senac DF — Atualização Periódica do seu Cadastro de Empregabilidade', $mail->subject);
            $this->assertStringContainsString('Olá, Maria Souza!', $html);
            $this->assertStringContainsString('a cada três meses', $html);
            $this->assertStringContainsString('oito perguntas', $html);
            $this->assertStringContainsString('ATUALIZAR MEU CADASTRO', $html);
            $this->assertStringContainsString('https://portal.test/login', $html);
            $this->assertStringNotContainsString('bloqueado por inatividade', strtolower($html));
            $this->assertSame('Maria Souza', $mail->candidato->pessoa->nome);
            $this->assertSame($pessoa->email, $mail->candidato->pessoa->email);

            return true;
        });
    }

    public function test_get_pergunta_trimestral_retorna_estrutura_esperada_para_frontend(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        [, , $token] = $this->criarCandidato(ultimaAtividade: now()->subMonths(3)->subDay());

        $this->withToken($token)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('tem_pergunta', true)
            ->assertJsonPath('pergunta.numero', 1)
            ->assertJsonPath('pergunta.campo', 'resposta_1')
            ->assertJsonPath('pergunta.titulo', 'Você deseja manter seu cadastro ativo no Sistema de Empregabilidade do Senac?')
            ->assertJsonCount(2, 'pergunta.opcoes');
    }

    public function test_get_pergunta_trimestral_sem_pendencia_retorna_estado_explicito(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        [, , $token] = $this->criarCandidato(ultimaAtividade: now()->subMonths(2));

        $this->withToken($token)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('tem_pergunta', false)
            ->assertJsonPath('pergunta', null);
    }

    public function test_pergunta_trimestral_primeira_aparece_apos_3_meses_e_nao_reaparece_apos_resposta(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        [$pessoa, $candidato, $token] = $this->criarCandidato(
            ultimaAtividade: now()->subMonths(3)->subDay(),
            ultimaAtualizacaoReal: now()->subMonths(3)->subDay(),
        );

        $this->withToken($token)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('tem_pergunta', true)
            ->assertJsonPath('pergunta.numero', 1)
            ->assertJsonPath('pergunta.titulo', 'Você deseja manter seu cadastro ativo no Sistema de Empregabilidade do Senac?')
            ->assertJsonPath('pergunta.opcoes.0.valor', 'manter_ativo')
            ->assertJsonPath('pergunta.opcoes.1.valor', 'encerrar_cadastro');

        $this->withToken($token)->postJson('/api/me/reativacao-questionario', [
            'pergunta_numero' => 1,
            'resposta_1' => 'manter_ativo',
        ])->assertOk()
            ->assertJsonPath('resultado', 'REATIVADO')
            ->assertJsonPath('restricoes.inativo', false)
            ->assertJsonPath('message', 'Cadastro reativado com sucesso.');

        $this->assertDatabaseHas('candidato_reativacao_questionarios', [
            'candidato_matricula' => $candidato->matricula,
            'pergunta_numero' => 1,
            'resposta_1' => 'manter_ativo',
        ]);

        $this->assertTrue($candidato->fresh()->ultima_atualizacao_real_em->equalTo(now()->subMonths(3)->subDay()));

        $this->withToken($token)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('tem_pergunta', false)
            ->assertJsonPath('pergunta', null);
    }

    public function test_pergunta_2_aparece_apos_6_meses_sem_reapresentar_pergunta_1(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        $ultimaAtualizacaoReal = now()->subMonths(6);
        [, $candidato, $token] = $this->criarCandidato(
            ultimaAtividade: $ultimaAtualizacaoReal,
            ultimaAtualizacaoReal: $ultimaAtualizacaoReal,
        );

        CandidatoReativacaoQuestionario::query()->create([
            'candidato_matricula' => $candidato->matricula,
            'bloqueado_em' => $ultimaAtualizacaoReal->copy()->startOfSecond(),
            'ciclo_inicio_em' => $ultimaAtualizacaoReal->copy()->startOfSecond(),
            'pergunta_numero' => 1,
            'resultado' => 'RESPONDIDA',
            'concluido_em' => now(),
            'resposta_1' => 'manter_ativo',
        ]);

        $this->withToken($token)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('tem_pergunta', true)
            ->assertJsonPath('pergunta.numero', 2)
            ->assertJsonPath('pergunta.titulo', 'Atualmente, você está buscando uma oportunidade de emprego ou estágio na área em que se formou no Senac?');
    }

    public function test_modo_teste_30_segundos_apresenta_pergunta_1_e_2_respeitando_historico(): void
    {
        config([
            'candidato.perguntas_modo_teste' => true,
            'candidato.perguntas_intervalo_teste_segundos' => 30,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        [, $candidato, $token] = $this->criarCandidato(
            ultimaAtividade: now()->subSeconds(31),
            ultimaAtualizacaoReal: now()->subSeconds(31),
        );

        $this->withToken($token)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('tem_pergunta', true)
            ->assertJsonPath('pergunta.numero', 1);

        $this->withToken($token)->postJson('/api/me/reativacao-questionario', [
            'pergunta_numero' => 1,
            'resposta_1' => 'manter_ativo',
        ])->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:30'));

        $this->withToken($token)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('tem_pergunta', true)
            ->assertJsonPath('pergunta.numero', 2)
            ->assertJsonPath('pergunta.titulo', 'Atualmente, você está buscando uma oportunidade de emprego ou estágio na área em que se formou no Senac?');

        $this->assertDatabaseHas('candidato_reativacao_questionarios', [
            'candidato_matricula' => $candidato->matricula,
            'pergunta_numero' => 1,
            'resposta_1' => 'manter_ativo',
        ]);
    }

    public static function respostasPorPergunta(): array
    {
        return [
            'pergunta 1' => [1, 'encerrar_cadastro'],
            'pergunta 2' => [2, 'buscando_area_senac'],
            'pergunta 3' => [3, 'trabalhando_busco'],
            'pergunta 4' => [4, ['estagio', 'temporario']],
            'pergunta 5' => [5, 'manha'],
            'pergunta 6' => [6, 'apenas_online'],
            'pergunta 7' => [7, 'atualizar_contato'],
            'pergunta 8' => [8, 'desejo_atualizar'],
        ];
    }

    #[DataProvider('respostasPorPergunta')]
    public function test_cada_pergunta_e_salva_individualmente_preservando_historico(int $numero, string|array $valor): void
    {
        $this->ativarModoTestePerguntas();
        $agora = Carbon::parse('2026-10-06 14:00:00');
        Carbon::setTestNow($agora);
        $marco = $agora->copy()->subSeconds($numero * 30 + 1);
        [, $candidato, $token] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);

        $cicloAnterior = $this->registrarRespostaHistorica($candidato, $marco->copy()->subDay(), $numero, $valor);
        foreach ($numero > 1 ? range(1, $numero - 1) : [] as $anterior) {
            $this->registrarRespostaHistorica($candidato, $marco, $anterior, $anterior === 1 ? 'manter_ativo' : self::respostasPorPergunta()['pergunta ' . $anterior][1]);
        }
        $historicoAntes = CandidatoReativacaoQuestionario::query()->orderBy('id')->get()->map->getAttributes()->all();

        $this->withToken($token)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('pergunta.numero', $numero)
            ->assertJsonPath('pergunta.campo', 'resposta_' . $numero);

        $this->withToken($token)->postJson('/api/me/reativacao-questionario', [
            'pergunta_numero' => $numero,
            'resposta_' . $numero => $valor,
            ...($valor === 'encerrar_cadastro' ? ['confirmar_encerramento' => true] : []),
        ])->assertOk()
            ->assertJsonPath('pergunta.numero', $numero);

        $salvo = CandidatoReativacaoQuestionario::query()
            ->where('candidato_matricula', $candidato->matricula)
            ->where('ciclo_inicio_em', $marco->copy()->startOfSecond())
            ->where('pergunta_numero', $numero)
            ->sole();

        $this->assertSame($valor, $salvo->{'resposta_' . $numero});
        $this->assertTrue($salvo->concluido_em->equalTo($agora));
        foreach (range(1, 8) as $outra) {
            if ($outra !== $numero) {
                $this->assertNull($salvo->{'resposta_' . $outra}, "resposta_{$outra} deveria ficar NULL");
            }
        }

        $this->assertDatabaseCount('candidato_reativacao_questionarios', count($historicoAntes) + 1);
        $historicoDepois = CandidatoReativacaoQuestionario::query()
            ->whereKey(array_column($historicoAntes, 'id'))
            ->orderBy('id')
            ->get()->map->getAttributes()->all();
        $this->assertSame($historicoAntes, $historicoDepois);
        $this->assertNotNull($cicloAnterior->fresh());

        $seguinte = $this->withToken($token)->getJson('/api/me/reativacao-questionario')->assertOk();
        if ($valor === 'encerrar_cadastro') {
            // Encerramento vigente: só a P1 de reconsideração pode reaparecer.
            $seguinte->assertJsonPath('pergunta.numero', 1)->assertJsonPath('pergunta.reconsideracao', true);
        } else {
            $seguinte->assertJsonPath('tem_pergunta', false);
        }
    }

    public function test_modo_teste_apresenta_as_8_perguntas_a_cada_30_segundos_sem_reiniciar_contagem(): void
    {
        $this->ativarModoTestePerguntas();
        $marco = Carbon::parse('2026-10-06 14:00:00');
        Carbon::setTestNow($marco);
        [, $candidato, $token] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);

        Carbon::setTestNow($marco->copy()->addSeconds(29));
        $this->withToken($token)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('tem_pergunta', false);

        foreach (self::respostasPorPergunta() as [$numero, $valor]) {
            Carbon::setTestNow($marco->copy()->addSeconds($numero * 30));

            $this->withToken($token)->getJson('/api/me/reativacao-questionario')
                ->assertOk()
                ->assertJsonPath('tem_pergunta', true)
                ->assertJsonPath('pergunta.numero', $numero);

            $this->withToken($token)->postJson('/api/me/reativacao-questionario', [
                'pergunta_numero' => $numero,
                'resposta_' . $numero => $numero === 1 ? 'manter_ativo' : $valor,
            ])->assertOk();

            $this->withToken($token)->getJson('/api/me/reativacao-questionario')
                ->assertOk()
                ->assertJsonPath('tem_pergunta', false);

            $this->assertTrue($candidato->fresh()->ultima_atualizacao_real_em->equalTo($marco));
        }

        $registros = CandidatoReativacaoQuestionario::query()
            ->where('candidato_matricula', $candidato->matricula)
            ->orderBy('pergunta_numero')
            ->get();

        $this->assertSame(range(1, 8), $registros->pluck('pergunta_numero')->all());
        $this->assertSame('manter_ativo', $registros[0]->resposta_1);
        $this->assertSame(['estagio', 'temporario'], $registros[3]->resposta_4);
        $this->assertSame('manha', $registros[4]->resposta_5);
        $this->assertSame('desejo_atualizar', $registros[7]->resposta_8);

        Carbon::setTestNow($marco->copy()->addSeconds(600));
        $this->withToken($token)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('tem_pergunta', false);
    }

    public function test_modo_teste_libera_no_maximo_uma_resposta_por_intervalo_vencido(): void
    {
        $this->ativarModoTestePerguntas();
        $marco = Carbon::parse('2026-10-06 14:00:00');
        Carbon::setTestNow($marco->copy()->addSeconds(61));
        [, $candidato, $token] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);
        $this->registrarRespostaHistorica($candidato, $marco, 1, 'manter_ativo');
        $this->registrarRespostaHistorica($candidato, $marco, 2, 'buscando_area_senac');

        // 2 intervalos vencidos e 2 respostas: nada pendente até o 3º intervalo.
        $this->withToken($token)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('tem_pergunta', false);

        Carbon::setTestNow($marco->copy()->addSeconds(90));
        $this->withToken($token)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('pergunta.numero', 3);
    }

    public function test_modo_teste_atrasado_confirmando_cadastro_segue_p1_p2_p3_e_para(): void
    {
        $this->ativarModoTestePerguntas();
        $marco = Carbon::parse('2026-10-06 14:00:00');
        Carbon::setTestNow($marco->copy()->addSeconds(91));
        [, $candidato, $token] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);

        $this->responderPerguntaPendente($token, 1, 'manter_ativo')->assertJsonPath('reativado', true);
        $this->assertSame(CandidatoStatusService::ATIVO, app(CandidatoStatusService::class)->estadoEfetivo($candidato->fresh()));

        // Volta a ficar inativo (30 s sem atividade) aos 121 s: 4 intervalos vencidos, 1 respondido → P2.
        Carbon::setTestNow($marco->copy()->addSeconds(121));
        $this->responderPerguntaPendente($token, 2, 'buscando_area_senac')->assertJsonPath('reativado', true);

        Carbon::setTestNow($marco->copy()->addSeconds(151));
        $this->responderPerguntaPendente($token, 3, 'nao_trabalhando');

        $this->assertSame([1, 2, 3], CandidatoReativacaoQuestionario::query()
            ->where('candidato_matricula', $candidato->matricula)->orderBy('pergunta_numero')->pluck('pergunta_numero')->all());
        $this->assertTrue($candidato->fresh()->ultima_atualizacao_real_em->equalTo($marco));
    }

    public function test_dois_candidatos_com_mesmo_marco_tem_historico_e_sequencia_independentes(): void
    {
        $this->ativarModoTestePerguntas();
        $marco = Carbon::parse('2026-10-06 14:00:00');
        Carbon::setTestNow($marco);
        [, $candidatoA, $tokenA] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);
        [, $candidatoB, $tokenB] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);

        // A responde P1 (30 s) e P2 (60 s).
        Carbon::setTestNow($marco->copy()->addSeconds(30));
        $this->responderPerguntaPendente($tokenA, 1, 'manter_ativo');
        Carbon::setTestNow($marco->copy()->addSeconds(60));
        $this->responderPerguntaPendente($tokenA, 2, 'buscando_area_senac');

        // B acessa aos 90 s, quando o relógio dele permitiria até a P3: continua na P1.
        Carbon::setTestNow($marco->copy()->addSeconds(90));
        $historicoA = $this->historico($candidatoA);
        $this->withToken($tokenB)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('pergunta.numero', 1);
        $this->assertSame([], $this->historico($candidatoB));

        // Alternância A -> B -> A -> B.
        $this->responderPerguntaPendente($tokenA, 3, 'nao_trabalhando');
        $this->responderPerguntaPendente($tokenB, 1, 'encerrar_cadastro');
        $this->withToken($tokenA)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('tem_pergunta', false);
        $this->withToken($tokenB)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('pergunta.numero', 1)
            ->assertJsonPath('pergunta.reconsideracao', true);

        $this->assertSame(array_merge($historicoA, [[3, 'nao_trabalhando']]), $this->historico($candidatoA));
        $this->assertSame([[1, 'encerrar_cadastro']], $this->historico($candidatoB));
        $this->assertSame(CandidatoStatusService::ATIVO, app(CandidatoStatusService::class)->estadoEfetivo($candidatoA->fresh()));
        $this->assertSame(CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE, app(CandidatoStatusService::class)->estadoEfetivo($candidatoB->fresh()));
    }

    public function test_cenario_a_candidato_ativo_responde_e_permanece_ativo_sem_reativacao(): void
    {
        $this->ativarModoTestePerguntas();
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        [, $candidato, $token] = $this->criarCandidato(ultimaAtividade: now()->subSeconds(5), ultimaAtualizacaoReal: now()->subSeconds(31));

        $this->withToken($token)->postJson('/api/me/reativacao-questionario', ['pergunta_numero' => 1, 'resposta_1' => 'manter_ativo'])
            ->assertOk()
            ->assertJsonPath('reativado', false)
            ->assertJsonPath('resultado', 'RESPONDIDA')
            ->assertJsonPath('restricoes.estado', 'ATIVO');

        $this->assertTrue($candidato->fresh()->ultima_atividade_em->equalTo(now()->subSeconds(5)));
    }

    public function test_cenario_b_pergunta_comum_sem_confirmacao_nao_reativa_e_confirmacao_reativa(): void
    {
        $this->ativarModoTestePerguntas();
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        $marco = now()->subSeconds(241);
        [, $candidato, $token] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);
        // Caso real observado: P8 respondida sem P1 no ciclo.
        $this->registrarRespostaHistorica($candidato, $marco, 8, 'mesmas_informacoes');

        $this->withToken($token)->postJson('/api/me/reativacao-questionario', ['pergunta_numero' => 2, 'resposta_2' => 'buscando_area_senac'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pergunta_numero');
        $this->assertSame(CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE, app(CandidatoStatusService::class)->estadoEfetivo($candidato->fresh()));

        $this->responderPerguntaPendente($token, 1, 'manter_ativo')
            ->assertJsonPath('reativado', true)
            ->assertJsonPath('restricoes.estado', 'ATIVO');
        $this->assertTrue((bool) $candidato->fresh()->status);
    }

    public function test_cenario_c_bloqueio_administrativo_nao_e_removido_pelo_questionario(): void
    {
        $this->ativarModoTestePerguntas();
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        $marco = now()->subSeconds(31);
        [, $candidato, $token] = $this->criarCandidato(status: false, ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);

        $resposta = $this->withToken($token)->postJson('/api/me/reativacao-questionario', ['pergunta_numero' => 1, 'resposta_1' => 'manter_ativo']);
        $this->assertContains($resposta->status(), [403, 422]);

        $this->assertFalse((bool) $candidato->fresh()->status);
        $this->assertSame(CandidatoStatusService::BLOQUEADO_MANUALMENTE, app(CandidatoStatusService::class)->estadoEfetivo($candidato->fresh()));
        $this->assertDatabaseCount('candidato_reativacao_questionarios', 0);
    }

    public function test_cenario_d_encerrar_cadastro_mantem_inativo_e_so_reapresenta_p1(): void
    {
        $this->ativarModoTestePerguntas();
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        $marco = now()->subSeconds(31);
        [, $candidato, $token] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);

        $this->responderPerguntaPendente($token, 1, 'encerrar_cadastro')
            ->assertJsonPath('reativado', false)
            ->assertJsonPath('resultado', 'ENCERRAMENTO_SOLICITADO')
            ->assertJsonPath('restricoes.estado', 'BLOQUEADO_POR_INATIVIDADE');

        // Mesmo com mais intervalos vencidos, nunca avança para a P2 enquanto o encerramento vigorar.
        Carbon::setTestNow(now()->addSeconds(120));
        $this->withToken($token)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('pergunta.numero', 1)
            ->assertJsonPath('pergunta.reconsideracao', true);
        $this->assertTrue($candidato->fresh()->ultima_atividade_em->equalTo($marco));
    }

    public function test_marco_nulo_e_congelado_na_reativacao_e_ciclo_nao_reinicia(): void
    {
        $this->ativarModoTestePerguntas();
        $marco = Carbon::parse('2026-10-06 14:00:00');
        Carbon::setTestNow($marco->copy()->addSeconds(31));
        [, $candidato, $token] = $this->criarCandidato(ultimaAtividade: $marco);
        // Mesmo estado dos candidatos semeados no banco local (insert direto, sem o hook do model).
        Candidato::query()->whereKey($candidato->matricula)->update(['ultima_atualizacao_real_em' => null]);

        $this->responderPerguntaPendente($token, 1, 'manter_ativo')->assertJsonPath('reativado', true);
        $this->assertTrue($candidato->fresh()->ultima_atualizacao_real_em->equalTo($marco));

        Carbon::setTestNow($marco->copy()->addSeconds(61));
        $this->withToken($token)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('pergunta.numero', 2);
    }

    public function test_resposta_recente_de_ciclo_anterior_nao_impede_gravacao_no_novo_ciclo(): void
    {
        $this->ativarModoTestePerguntas();
        $agora = Carbon::parse('2026-10-06 14:00:00');
        Carbon::setTestNow($agora);
        $marco = $agora->copy()->subSeconds(31);
        [, $candidato, $token] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);
        $anterior = $this->registrarRespostaHistorica($candidato, $marco->copy()->subMinutes(2), 1, 'manter_ativo');
        $anterior->forceFill(['concluido_em' => $agora->copy()->subMinute()])->save();

        $this->withToken($token)->postJson('/api/me/reativacao-questionario', [
            'pergunta_numero' => 1,
            'resposta_1' => 'encerrar_cadastro',
            'confirmar_encerramento' => true,
        ])->assertOk()
            ->assertJsonPath('resultado', 'ENCERRAMENTO_SOLICITADO');

        $this->assertDatabaseCount('candidato_reativacao_questionarios', 2);
        $this->assertSame('manter_ativo', $anterior->fresh()->resposta_1);
    }

    public function test_encerrar_participacao_registra_decisao_mantem_indisponivel_e_logout_invalida_sessao(): void
    {
        $this->ativarModoTestePerguntas();
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        $marco = now()->subSeconds(31);
        [, $candidato, $token] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);
        [, $outro] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);
        $historicoOutro = $this->registrarRespostaHistorica($outro, $marco, 1, 'manter_ativo')->fresh()->getAttributes();
        $outroAntes = $outro->fresh()->getAttributes();

        $this->responderPerguntaPendente($token, 1, 'encerrar_cadastro')
            ->assertJsonPath('resultado', 'ENCERRAMENTO_SOLICITADO')
            ->assertJsonPath('reativado', false)
            ->assertJsonPath('restricoes.estado', 'BLOQUEADO_POR_INATIVIDADE');

        $this->assertDatabaseHas('candidato_reativacao_questionarios', [
            'candidato_matricula' => $candidato->matricula,
            'pergunta_numero' => 1,
            'resposta_1' => 'encerrar_cadastro',
            'resultado' => 'ENCERRAMENTO_SOLICITADO',
        ]);
        $this->assertNotNull($candidato->fresh(), 'Os dados do candidato não podem ser excluídos.');
        $this->assertFalse(app(CandidatoStatusService::class)->estaDisponivel($candidato->fresh()));
        $this->assertTrue($candidato->fresh()->ultima_atualizacao_real_em->equalTo($marco));
        $this->assertSame($outroAntes, $outro->fresh()->getAttributes());
        $this->assertSame($historicoOutro, CandidatoReativacaoQuestionario::query()->findOrFail($historicoOutro['id'])->getAttributes());

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->withToken($token)->getJson('/api/me/reativacao-questionario')->assertUnauthorized();
    }

    public function test_falha_ao_registrar_saida_nao_grava_decisao_e_permite_nova_tentativa(): void
    {
        $this->ativarModoTestePerguntas();
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        $marco = now()->subSeconds(31);
        [, $candidato, $token] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);

        $this->withToken($token)->postJson('/api/me/reativacao-questionario', ['pergunta_numero' => 1, 'resposta_1' => 'valor_manipulado'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('candidato_reativacao_questionarios', 0);

        $this->responderPerguntaPendente($token, 1, 'encerrar_cadastro')
            ->assertJsonPath('resultado', 'ENCERRAMENTO_SOLICITADO');
    }

    public function test_teste_i_empregado_buscando_oportunidades_nao_encerra_participacao(): void
    {
        $this->ativarModoTestePerguntas();
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        $marco = now()->subSeconds(91);
        [, $candidato, $token] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);
        $this->registrarRespostaHistorica($candidato, $marco, 1, 'manter_ativo');
        $this->registrarRespostaHistorica($candidato, $marco, 2, 'nao_busco_mas_manter');

        $this->responderPerguntaPendente($token, 3, 'trabalhando_busco')
            ->assertJsonPath('resultado', 'REATIVADO')
            ->assertJsonPath('restricoes.estado', 'ATIVO');

        $this->assertDatabaseMissing('candidato_reativacao_questionarios', [
            'candidato_matricula' => $candidato->matricula,
            'resultado' => 'ENCERRAMENTO_SOLICITADO',
        ]);
    }

    public function test_encerramento_relogin_reapresenta_p1_e_retorno_positivo_reativa_preservando_historico(): void
    {
        Mail::fake();
        $this->ativarModoTestePerguntas();
        $agora = Carbon::parse('2026-10-06 14:00:00');
        Carbon::setTestNow($agora);
        $marco = $agora->copy()->subSeconds(31);
        [$pessoa, $candidato, $token] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);

        // Teste A: encerrar mantém inativo.
        $this->responderPerguntaPendente($token, 1, 'encerrar_cadastro')
            ->assertJsonPath('resultado', 'ENCERRAMENTO_SOLICITADO')
            ->assertJsonPath('restricoes.estado', 'BLOQUEADO_POR_INATIVIDADE');
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        // Teste B + H: novo login autentica, mas não reativa nem mexe nas datas.
        Carbon::setTestNow($agora->copy()->addSeconds(20));
        $novoToken = $this->postJson('/api/auth/login', ['identificador' => $pessoa->email, 'senha' => '123456'])
            ->assertOk()
            ->assertJsonPath('restricoes.estado', 'BLOQUEADO_POR_INATIVIDADE')
            ->assertJsonPath('restricoes.inativo', true)
            ->assertJsonPath('restricoes.pode_editar_perfil', false)
            ->json('token');

        $this->withToken($novoToken)->getJson('/api/auth/me')->assertOk()->assertJsonPath('restricoes.inativo', true);
        $this->withToken($novoToken)->getJson('/api/me/curriculo');
        $this->withToken($novoToken)->getJson('/api/candidatos/' . $candidato->matricula . '/dashboard');
        $this->withToken($novoToken)->postJson('/api/candidatos/' . $candidato->matricula . '/perfil/links', ['linkedin' => 'https://linkedin.com/in/teste'])
            ->assertForbidden();
        $this->assertTrue($candidato->fresh()->ultima_atividade_em->equalTo($marco));
        $this->assertTrue($candidato->fresh()->ultima_atualizacao_real_em->equalTo($marco));

        $this->withToken($novoToken)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('pergunta.numero', 1)
            ->assertJsonPath('pergunta.reconsideracao', true)
            ->assertJsonPath('pergunta.opcoes.0.label', 'Sim, desejo continuar recebendo oportunidades.')
            ->assertJsonPath('pergunta.opcoes.1.label', 'Não, desejo encerrar meu cadastro.');

        // Teste C: decisão positiva reativa sem reiniciar o prazo trimestral.
        $this->withToken($novoToken)->postJson('/api/me/reativacao-questionario', ['pergunta_numero' => 1, 'resposta_1' => 'manter_ativo'])
            ->assertOk()
            ->assertJsonPath('resultado', 'REATIVADO')
            ->assertJsonPath('reativado', true)
            ->assertJsonPath('restricoes.estado', 'ATIVO')
            ->assertJsonPath('restricoes.pode_editar_perfil', true);
        $this->assertTrue($candidato->fresh()->ultima_atualizacao_real_em->equalTo($marco));

        // Teste G: as duas decisões continuam registradas, em ordem.
        $decisoes = CandidatoReativacaoQuestionario::query()
            ->where('candidato_matricula', $candidato->matricula)
            ->orderBy('sequencia')
            ->get(['pergunta_numero', 'sequencia', 'resposta_1', 'resultado', 'ciclo_inicio_em', 'concluido_em']);
        $this->assertSame(
            [[1, 1, 'encerrar_cadastro', 'ENCERRAMENTO_SOLICITADO'], [1, 2, 'manter_ativo', 'REATIVADO']],
            $decisoes->map(fn ($d) => [$d->pergunta_numero, $d->sequencia, $d->resposta_1, $d->resultado])->all()
        );
        $this->assertTrue($decisoes[0]->concluido_em->equalTo($agora));
        $this->assertTrue($decisoes[1]->ciclo_inicio_em->equalTo($marco));

        // Depois da reativação, segue a sequência trimestral individual (P2), não a P1.
        Carbon::setTestNow($agora->copy()->addSeconds(50));
        $this->withToken($novoToken)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('pergunta.numero', 2)
            ->assertJsonPath('pergunta.reconsideracao', false);
    }

    public function test_encerramento_reconfirmado_mantem_inativo_sem_duplicar_por_clique_duplo(): void
    {
        $this->ativarModoTestePerguntas();
        $agora = Carbon::parse('2026-10-06 14:00:00');
        Carbon::setTestNow($agora);
        $marco = $agora->copy()->subSeconds(31);
        [, $candidato, $token] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);
        $this->responderPerguntaPendente($token, 1, 'encerrar_cadastro');

        // Teste D: volta depois e encerra de novo (duas requisições = clique duplo).
        Carbon::setTestNow($agora->copy()->addMinute());
        $payload = ['pergunta_numero' => 1, 'resposta_1' => 'encerrar_cadastro', 'confirmar_encerramento' => true];
        $this->withToken($token)->postJson('/api/me/reativacao-questionario', $payload)
            ->assertOk()
            ->assertJsonPath('resultado', 'ENCERRAMENTO_SOLICITADO')
            ->assertJsonPath('restricoes.estado', 'BLOQUEADO_POR_INATIVIDADE');
        $this->withToken($token)->postJson('/api/me/reativacao-questionario', $payload)
            ->assertOk()
            ->assertJsonPath('resultado', 'ENCERRAMENTO_SOLICITADO');

        $this->assertSame([1, 2], CandidatoReativacaoQuestionario::query()
            ->where('candidato_matricula', $candidato->matricula)->orderBy('sequencia')->pluck('sequencia')->all());
        $this->assertTrue($candidato->fresh()->ultima_atividade_em->equalTo($marco));

        Carbon::setTestNow($agora->copy()->addMinutes(5));
        $this->withToken($token)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('pergunta.numero', 1)
            ->assertJsonPath('pergunta.reconsideracao', true);
        $this->withToken($token)->postJson('/api/me/reativacao-questionario', ['pergunta_numero' => 2, 'resposta_2' => 'buscando_area_senac'])
            ->assertUnprocessable();
    }

    public function test_bloqueio_administrativo_apos_encerramento_nao_e_removido_pela_reconsideracao(): void
    {
        Mail::fake();
        $this->ativarModoTestePerguntas();
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        $marco = now()->subSeconds(31);
        [$pessoa, $candidato, $token] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);
        $this->responderPerguntaPendente($token, 1, 'encerrar_cadastro');
        $candidato->forceFill(['status' => false])->save();

        // Teste E.
        $this->postJson('/api/auth/login', ['identificador' => $pessoa->email, 'senha' => '123456'])->assertForbidden();
        $this->withToken($token)->postJson('/api/me/reativacao-questionario', ['pergunta_numero' => 1, 'resposta_1' => 'manter_ativo'])
            ->assertForbidden();

        $this->assertFalse((bool) $candidato->fresh()->status);
        $this->assertSame(CandidatoStatusService::BLOQUEADO_MANUALMENTE, app(CandidatoStatusService::class)->estadoEfetivo($candidato->fresh()));
        $this->assertDatabaseCount('candidato_reativacao_questionarios', 1);
    }

    public function test_candidato_com_encerramento_nao_aparece_na_busca_de_talentos(): void
    {
        $this->ativarModoTestePerguntas();
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        $marco = now()->subSeconds(31);
        [, $encerrado, $token] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);
        [, $ativo] = $this->criarCandidato();
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();

        // Teste F.
        $this->responderPerguntaPendente($token, 1, 'encerrar_cadastro');
        $this->withToken($tokenEmpresa)->getJson('/api/candidatos?per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.matricula', (string) $ativo->matricula);
        $this->assertFalse(app(CandidatoStatusService::class)->estaDisponivel($encerrado->fresh()));
    }

    public function test_outro_candidato_nao_herda_estado_de_encerramento(): void
    {
        $this->ativarModoTestePerguntas();
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        $marco = now()->subSeconds(31);
        [, $candidatoA, $tokenA] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);
        [, $candidatoB, $tokenB] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);

        // Teste I.
        $this->responderPerguntaPendente($tokenA, 1, 'encerrar_cadastro');
        $this->withToken($tokenB)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('pergunta.numero', 1)
            ->assertJsonPath('pergunta.reconsideracao', false);
        $this->withToken($tokenB)->postJson('/api/me/reativacao-questionario', ['pergunta_numero' => 1, 'resposta_1' => 'manter_ativo'])
            ->assertOk()
            ->assertJsonPath('resultado', 'REATIVADO');

        $this->assertSame([[1, 'manter_ativo']], $this->historico($candidatoB));
        $this->assertSame([[1, 'encerrar_cadastro']], $this->historico($candidatoA));
        $this->withToken($tokenA)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('pergunta.reconsideracao', true);
        $this->assertSame(CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE, app(CandidatoStatusService::class)->estadoEfetivo($candidatoA->fresh()));
    }

    public static function respostasQueDesativam(): array
    {
        return [
            'teste B: P2 qualquer área' => [2, 'buscando_qualquer_area'],
            'teste C: P3 trabalhando sem buscar mudança' => [3, 'trabalhando_nao_busco'],
            'teste G: P6 sem disponibilidade' => [6, 'sem_disponibilidade'],
        ];
    }

    #[DataProvider('respostasQueDesativam')]
    public function test_resposta_de_saida_exige_confirmacao_e_encerra_preservando_resposta_original(int $numero, string $valor): void
    {
        Mail::fake();
        [$pessoa, $candidato, $token, $marco] = $this->candidatoComPerguntaPendente($numero);
        $historicoAntes = CandidatoReativacaoQuestionario::query()->orderBy('id')->get()->map(fn ($r) => $r->fresh()->getAttributes())->all();

        $opcao = collect($this->withToken($token)->getJson('/api/me/reativacao-questionario')->json('pergunta.opcoes'))->firstWhere('valor', $valor);
        $this->assertSame('confirmar', $opcao['saida']);

        // Teste O (backend): sem a confirmação do candidato, nada é gravado.
        $this->withToken($token)->postJson('/api/me/reativacao-questionario', ['pergunta_numero' => $numero, 'resposta_' . $numero => $valor])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirmar_encerramento');
        $this->assertDatabaseCount('candidato_reativacao_questionarios', count($historicoAntes));

        $this->responderPerguntaPendente($token, $numero, $valor)
            ->assertJsonPath('resultado', 'ENCERRAMENTO_SOLICITADO')
            ->assertJsonPath('reativado', false)
            ->assertJsonPath('restricoes.estado', 'BLOQUEADO_POR_INATIVIDADE');

        $registro = CandidatoReativacaoQuestionario::query()
            ->where('candidato_matricula', $candidato->matricula)->where('pergunta_numero', $numero)->sole();
        $this->assertSame($valor, $registro->{'resposta_' . $numero}, 'A resposta original deve ser preservada.');
        $this->assertSame('ENCERRAMENTO_SOLICITADO', $registro->resultado);
        $this->assertFalse(app(CandidatoStatusService::class)->estaDisponivel($candidato->fresh()));
        $this->assertTrue($candidato->fresh()->ultima_atualizacao_real_em->equalTo($marco));
        $this->assertSame($historicoAntes, CandidatoReativacaoQuestionario::query()->whereKey(array_column($historicoAntes, 'id'))->orderBy('id')->get()->map->getAttributes()->all());

        // Logout e novo login (teste K): continua inativo e recebe a P1 para reconsiderar, nunca a próxima pergunta.
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $novoToken = $this->postJson('/api/auth/login', ['identificador' => $pessoa->email, 'senha' => '123456'])
            ->assertOk()
            ->assertJsonPath('restricoes.inativo', true)
            ->json('token');
        $this->withToken($novoToken)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('pergunta.numero', 1)
            ->assertJsonPath('pergunta.reconsideracao', true);
    }

    public function test_teste_d_e_autonomo_e_perguntado_e_ao_continuar_nao_encerra(): void
    {
        [, $candidato, $token] = $this->candidatoComPerguntaPendente(3);

        $opcao = collect($this->withToken($token)->getJson('/api/me/reativacao-questionario')->json('pergunta.opcoes'))->firstWhere('valor', 'autonomo_empreendedor');
        $this->assertSame('perguntar', $opcao['saida']);

        $this->responderPerguntaPendente($token, 3, 'autonomo_empreendedor', sairNaCondicional: false)
            ->assertJsonPath('resultado', 'REATIVADO')
            ->assertJsonPath('restricoes.estado', 'ATIVO');

        $this->assertSame('autonomo_empreendedor', CandidatoReativacaoQuestionario::query()->where('candidato_matricula', $candidato->matricula)->where('pergunta_numero', 3)->sole()->resposta_3);
        $this->assertDatabaseMissing('candidato_reativacao_questionarios', ['candidato_matricula' => $candidato->matricula, 'resultado' => 'ENCERRAMENTO_SOLICITADO']);
    }

    public function test_teste_f_autonomo_que_escolhe_sair_e_encerrado(): void
    {
        [, $candidato, $token] = $this->candidatoComPerguntaPendente(3);

        $this->responderPerguntaPendente($token, 3, 'autonomo_empreendedor', sairNaCondicional: true)
            ->assertJsonPath('resultado', 'ENCERRAMENTO_SOLICITADO')
            ->assertJsonPath('restricoes.estado', 'BLOQUEADO_POR_INATIVIDADE');

        $registro = CandidatoReativacaoQuestionario::query()->where('candidato_matricula', $candidato->matricula)->where('pergunta_numero', 3)->sole();
        $this->assertSame('autonomo_empreendedor', $registro->resposta_3);
        $this->assertFalse(app(CandidatoStatusService::class)->estaDisponivel($candidato->fresh()));
    }

    public static function respostasQueNaoDesativam(): array
    {
        return [
            'teste H: P2 não procuro agora mas quero manter' => [2, 'nao_busco_mas_manter'],
            'teste I: P3 trabalhando buscando oportunidades' => [3, 'trabalhando_busco'],
            'P6 apenas on-line' => [6, 'apenas_online'],
            'P7 atualizar informações profissionais' => [7, 'atualizar_profissionais'],
            'P8 desejo atualizar' => [8, 'desejo_atualizar'],
        ];
    }

    #[DataProvider('respostasQueNaoDesativam')]
    public function test_respostas_comuns_nao_encerram_e_recusam_confirmacao_de_saida(int $numero, string $valor): void
    {
        [, $candidato, $token, $marco] = $this->candidatoComPerguntaPendente($numero);

        $this->withToken($token)->postJson('/api/me/reativacao-questionario', ['pergunta_numero' => $numero, 'resposta_' . $numero => $valor, 'confirmar_encerramento' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirmar_encerramento');

        $this->responderPerguntaPendente($token, $numero, $valor)
            ->assertJsonPath('resultado', 'REATIVADO')
            ->assertJsonPath('restricoes.estado', 'ATIVO');

        $this->assertDatabaseMissing('candidato_reativacao_questionarios', ['candidato_matricula' => $candidato->matricula, 'resultado' => 'ENCERRAMENTO_SOLICITADO']);
        // Responder (inclusive "preciso atualizar"/"desejo atualizar") não é atualização real do perfil.
        $this->assertTrue($candidato->fresh()->ultima_atualizacao_real_em->equalTo($marco));
    }

    public function test_teste_j_p4_salva_todas_as_opcoes_selecionadas_sem_encerrar(): void
    {
        [, $candidato, $token] = $this->candidatoComPerguntaPendente(4);
        $todas = ['jovem_aprendiz', 'estagio', 'emprego_efetivo', 'temporario', 'autonomo_servicos', 'outras'];

        $opcoes = $this->withToken($token)->getJson('/api/me/reativacao-questionario')->assertJsonPath('pergunta.multipla', true)->json('pergunta.opcoes');
        $this->assertSame([null], array_values(array_unique(array_column($opcoes, 'saida'))));

        $this->responderPerguntaPendente($token, 4, $todas)->assertJsonPath('resultado', 'REATIVADO');
        $this->assertSame($todas, CandidatoReativacaoQuestionario::query()->where('candidato_matricula', $candidato->matricula)->where('pergunta_numero', 4)->sole()->resposta_4);
    }

    public function test_teste_k_l_saida_pela_p2_reconsiderada_reativa_preserva_historico_e_segue_para_p3(): void
    {
        [, $candidato, $token, $marco] = $this->candidatoComPerguntaPendente(2);
        $agora = now()->copy();

        $this->responderPerguntaPendente($token, 2, 'buscando_qualquer_area')->assertJsonPath('resultado', 'ENCERRAMENTO_SOLICITADO');

        Carbon::setTestNow($agora->copy()->addSeconds(15));
        $this->withToken($token)->getJson('/api/me/reativacao-questionario')
            ->assertJsonPath('pergunta.numero', 1)
            ->assertJsonPath('pergunta.reconsideracao', true);
        $this->responderPerguntaPendente($token, 1, 'manter_ativo')
            ->assertJsonPath('resultado', 'REATIVADO')
            ->assertJsonPath('restricoes.estado', 'ATIVO');

        $this->assertSame(
            [[1, 1, 'manter_ativo', 'RESPONDIDA'], [2, 1, 'buscando_qualquer_area', 'ENCERRAMENTO_SOLICITADO'], [1, 2, 'manter_ativo', 'REATIVADO']],
            CandidatoReativacaoQuestionario::query()->where('candidato_matricula', $candidato->matricula)->orderBy('id')->get()
                ->map(fn ($r) => [$r->pergunta_numero, $r->sequencia, $r->{'resposta_' . $r->pergunta_numero}, $r->resultado])->all()
        );
        $this->assertTrue($candidato->fresh()->ultima_atualizacao_real_em->equalTo($marco));

        // Volta a ficar inativo: segue a sequência individual (P3), sem repetir P2 nem P1.
        Carbon::setTestNow($agora->copy()->addSeconds(45));
        $this->withToken($token)->getJson('/api/me/reativacao-questionario')
            ->assertJsonPath('pergunta.numero', 3)
            ->assertJsonPath('pergunta.reconsideracao', false);
    }

    public function test_teste_m_bloqueio_administrativo_apos_saida_pela_p6_nao_e_removido(): void
    {
        Mail::fake();
        [$pessoa, $candidato, $token] = $this->candidatoComPerguntaPendente(6);
        $this->responderPerguntaPendente($token, 6, 'sem_disponibilidade');
        $candidato->forceFill(['status' => false])->save();

        $this->postJson('/api/auth/login', ['identificador' => $pessoa->email, 'senha' => '123456'])->assertForbidden();
        $this->withToken($token)->postJson('/api/me/reativacao-questionario', ['pergunta_numero' => 1, 'resposta_1' => 'manter_ativo'])->assertForbidden();
        $this->assertSame(CandidatoStatusService::BLOQUEADO_MANUALMENTE, app(CandidatoStatusService::class)->estadoEfetivo($candidato->fresh()));
    }

    public function test_teste_n_saida_de_um_candidato_nao_afeta_historico_do_outro(): void
    {
        [, $candidatoA, $tokenA, $marco] = $this->candidatoComPerguntaPendente(2);
        [, $candidatoB, $tokenB] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);
        $this->registrarRespostaHistorica($candidatoB, $marco, 1, 'manter_ativo');

        $this->responderPerguntaPendente($tokenA, 2, 'buscando_qualquer_area');

        $this->withToken($tokenB)->getJson('/api/me/reativacao-questionario')
            ->assertJsonPath('pergunta.numero', 2)
            ->assertJsonPath('pergunta.reconsideracao', false);
        $this->responderPerguntaPendente($tokenB, 2, 'buscando_area_senac')->assertJsonPath('resultado', 'REATIVADO');

        $this->assertSame([[1, 'manter_ativo'], [2, 'buscando_area_senac']], $this->historico($candidatoB));
        $this->assertSame([[1, 'manter_ativo'], [2, 'buscando_qualquer_area']], $this->historico($candidatoA));
        $this->assertSame(CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE, app(CandidatoStatusService::class)->estadoEfetivo($candidatoA->fresh()));
    }

    public function test_candidato_ativo_nao_registra_encerramento_e_permanece_consistente(): void
    {
        $this->ativarModoTestePerguntas();
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        [, , $token] = $this->criarCandidato(ultimaAtividade: now()->subSeconds(5), ultimaAtualizacaoReal: now()->subSeconds(31));

        $this->withToken($token)->postJson('/api/me/reativacao-questionario', ['pergunta_numero' => 1, 'resposta_1' => 'encerrar_cadastro', 'confirmar_encerramento' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirmar_encerramento');
        $this->assertDatabaseCount('candidato_reativacao_questionarios', 0);
    }

    public function test_todas_as_alternativas_validas_das_8_perguntas_sao_aceitas_e_invalidas_recusadas(): void
    {
        $aceitas = 0;
        foreach (range(1, 8) as $numero) {
            [, , $tokenLeitura] = $this->candidatoComPerguntaPendente($numero);
            $pergunta = $this->withToken($tokenLeitura)->getJson('/api/me/reativacao-questionario')->assertOk()->json('pergunta');
            $this->withToken($tokenLeitura)->postJson('/api/me/reativacao-questionario', ['pergunta_numero' => $numero, 'resposta_' . $numero => $pergunta['multipla'] ? ['invalida'] : 'invalida'])
                ->assertUnprocessable();

            $envios = $pergunta['multipla']
                ? [array_column($pergunta['opcoes'], 'valor')]
                : array_column($pergunta['opcoes'], 'valor');

            foreach ($envios as $valor) {
                $saida = is_string($valor) ? collect($pergunta['opcoes'])->firstWhere('valor', $valor)['saida'] : null;
                // Condicional (autônomo): testa os dois caminhos, continuar e sair.
                foreach ($saida === 'perguntar' ? [false, true] : [false] as $sairNaCondicional) {
                    [, $candidato, $token] = $this->candidatoComPerguntaPendente($numero);
                    $resposta = $this->responderPerguntaPendente($token, $numero, $valor, $sairNaCondicional);

                    $encerra = $saida === 'confirmar' || $sairNaCondicional;
                    $this->assertSame($encerra ? 'ENCERRAMENTO_SOLICITADO' : 'REATIVADO', $resposta->json('resultado'), "P{$numero} = " . json_encode($valor));
                    $salvo = CandidatoReativacaoQuestionario::query()->where('candidato_matricula', $candidato->matricula)->where('pergunta_numero', $numero)->sole();
                    $this->assertSame($valor, $salvo->{'resposta_' . $numero});
                    $aceitas++;
                }
            }
        }

        // 2 + 3 + (4 + 1 do autônomo) + 1 (P4 múltipla) + 5 + 4 + 3 + 2
        $this->assertSame(25, $aceitas);
    }

    public function test_p1_sim_grava_em_todos_os_estados_do_candidato(): void
    {
        $this->ativarModoTestePerguntas();
        $agora = Carbon::parse('2026-10-06 14:00:00');
        Carbon::setTestNow($agora);
        $marco = $agora->copy()->subSeconds(31);
        $sim = ['pergunta_numero' => 1, 'resposta_1' => 'manter_ativo'];

        // "Sim" não exige confirmar_encerramento, e enviá-lo é recusado.
        [, $semHistorico, $tokenSemHistorico] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);
        $this->withToken($tokenSemHistorico)->postJson('/api/me/reativacao-questionario', [...$sim, 'confirmar_encerramento' => true])
            ->assertUnprocessable();

        // A + B: bloqueado por inatividade, sem respostas anteriores.
        $this->withToken($tokenSemHistorico)->postJson('/api/me/reativacao-questionario', $sim)
            ->assertOk()->assertJsonPath('resultado', 'REATIVADO')->assertJsonPath('restricoes.estado', 'ATIVO');

        // C: após encerramento anterior.
        [, $aposEncerrar, $tokenAposEncerrar] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);
        $this->registrarRespostaHistorica($aposEncerrar, $marco, 1, 'encerrar_cadastro')
            ->forceFill(['resultado' => 'ENCERRAMENTO_SOLICITADO'])->save();
        $this->withToken($tokenAposEncerrar)->postJson('/api/me/reativacao-questionario', $sim)
            ->assertOk()->assertJsonPath('resultado', 'REATIVADO');

        // D: histórico com várias sequências (encerrou duas vezes, depois "Sim").
        [, $variasSequencias, $tokenVarias] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);
        $this->withToken($tokenVarias)->postJson('/api/me/reativacao-questionario', ['pergunta_numero' => 1, 'resposta_1' => 'encerrar_cadastro', 'confirmar_encerramento' => true])->assertOk();
        Carbon::setTestNow($agora->copy()->addSeconds(15));
        $this->withToken($tokenVarias)->postJson('/api/me/reativacao-questionario', ['pergunta_numero' => 1, 'resposta_1' => 'encerrar_cadastro', 'confirmar_encerramento' => true])->assertOk();
        Carbon::setTestNow($agora->copy()->addSeconds(30));
        $this->withToken($tokenVarias)->postJson('/api/me/reativacao-questionario', $sim)
            ->assertOk()->assertJsonPath('resultado', 'REATIVADO');
        $this->assertSame(
            [[1, 'encerrar_cadastro', 'ENCERRAMENTO_SOLICITADO'], [2, 'encerrar_cadastro', 'ENCERRAMENTO_SOLICITADO'], [3, 'manter_ativo', 'REATIVADO']],
            CandidatoReativacaoQuestionario::query()->where('candidato_matricula', $variasSequencias->matricula)->orderBy('sequencia')->get()
                ->map(fn ($r) => [$r->sequencia, $r->resposta_1, $r->resultado])->all()
        );

        // E: sessões de candidatos diferentes não interferem entre si.
        $this->assertSame([[1, 'manter_ativo']], $this->historico($semHistorico));
        $this->assertSame(2, CandidatoReativacaoQuestionario::query()->where('candidato_matricula', $aposEncerrar->matricula)->count());
        foreach ([$semHistorico, $aposEncerrar, $variasSequencias] as $candidato) {
            $this->assertTrue($candidato->fresh()->ultima_atualizacao_real_em->equalTo($marco), 'O prazo trimestral não pode reiniciar.');
        }
    }

    public function test_banco_sem_ultima_atualizacao_real_em_falha_ao_reativar_e_migration_corrige(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $this->markTestSkipped('No MySQL o DROP COLUMN faz commit implícito e quebra a transação do RefreshDatabase; cenário validado no servidor isolado.');
        }

        $this->ativarModoTestePerguntas();
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        $marco = now()->subSeconds(31);
        [, $candidato, $token] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);

        // Banco que executou a 2026_10_06 original: a coluna nunca foi criada.
        Schema::table('candidato', function (Blueprint $table) {
            $table->dropIndex(['ultima_atualizacao_real_em']);
            $table->dropColumn('ultima_atualizacao_real_em');
        });

        $this->withToken($token)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('pergunta.numero', 1);
        $this->withToken($token)->postJson('/api/me/reativacao-questionario', ['pergunta_numero' => 1, 'resposta_1' => 'manter_ativo'])
            ->assertStatus(500);
        $this->assertDatabaseCount('candidato_reativacao_questionarios', 0);

        $migration = database_path('migrations/2026_10_21_000003_ensure_ultima_atualizacao_real_em_on_candidato_table.php');
        (require $migration)->up();
        (require $migration)->up(); // idempotente: rodar de novo não falha
        $this->assertTrue(Schema::hasColumn('candidato', 'ultima_atualizacao_real_em'));

        $this->withToken($token)->postJson('/api/me/reativacao-questionario', ['pergunta_numero' => 1, 'resposta_1' => 'manter_ativo'])
            ->assertOk()
            ->assertJsonPath('resultado', 'REATIVADO');
        // Sem dado prévio, o marco do ciclo é congelado a partir de ultima_atividade_em (prazo não reinicia).
        $this->assertTrue($candidato->fresh()->ultima_atualizacao_real_em->equalTo($marco));
    }

    /**
     * Candidato bloqueado por inatividade com a pergunta $numero pendente:
     * P1 = manter_ativo e as anteriores respondidas com alternativas comuns.
     */
    private function candidatoComPerguntaPendente(int $numero): array
    {
        $this->ativarModoTestePerguntas();
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        $marco = now()->subSeconds($numero * 30 + 1);
        [$pessoa, $candidato, $token] = $this->criarCandidato(ultimaAtividade: $marco, ultimaAtualizacaoReal: $marco);
        foreach ($numero > 1 ? range(1, $numero - 1) : [] as $anterior) {
            $this->registrarRespostaHistorica($candidato, $marco, $anterior, $anterior === 1 ? 'manter_ativo' : self::respostasPorPergunta()['pergunta ' . $anterior][1]);
        }

        return [$pessoa, $candidato, $token, $marco];
    }

    /**
     * Responde como o frontend: lê a classificação "saida" da opção na API e envia a
     * confirmação de encerramento quando ela é exigida (ou, na condicional, quando $sairNaCondicional).
     */
    private function responderPerguntaPendente(string $token, int $numero, string|array $valor, bool $sairNaCondicional = false): \Illuminate\Testing\TestResponse
    {
        $opcoes = $this->withToken($token)->getJson('/api/me/reativacao-questionario')
            ->assertOk()
            ->assertJsonPath('tem_pergunta', true)
            ->assertJsonPath('pergunta.numero', $numero)
            ->json('pergunta.opcoes');

        $saida = is_string($valor) ? (collect($opcoes)->firstWhere('valor', $valor)['saida'] ?? null) : null;
        $confirmar = $saida === 'confirmar' || ($saida === 'perguntar' && $sairNaCondicional);

        return $this->withToken($token)->postJson('/api/me/reativacao-questionario', array_filter([
            'pergunta_numero' => $numero,
            'resposta_' . $numero => $valor,
            'confirmar_encerramento' => $confirmar ?: null,
        ], fn ($v) => $v !== null))->assertOk();
    }

    private function historico(Candidato $candidato): array
    {
        return CandidatoReativacaoQuestionario::query()
            ->where('candidato_matricula', $candidato->matricula)
            ->orderBy('pergunta_numero')
            ->get()
            ->map(fn (CandidatoReativacaoQuestionario $r) => [$r->pergunta_numero, $r->{'resposta_' . $r->pergunta_numero}])
            ->all();
    }

    private function ativarModoTestePerguntas(): void
    {
        config([
            'candidato.perguntas_modo_teste' => true,
            'candidato.perguntas_intervalo_teste_segundos' => 30,
        ]);
    }

    private function registrarRespostaHistorica(Candidato $candidato, Carbon $cicloInicio, int $numero, string|array $valor): CandidatoReativacaoQuestionario
    {
        return CandidatoReativacaoQuestionario::query()->create([
            'candidato_matricula' => $candidato->matricula,
            'bloqueado_em' => $cicloInicio->copy()->startOfSecond(),
            'ciclo_inicio_em' => $cicloInicio->copy()->startOfSecond(),
            'pergunta_numero' => $numero,
            'resultado' => CandidatoReativacaoQuestionario::RESULTADO_RESPONDIDA,
            'concluido_em' => $cicloInicio->copy()->addSecond(),
            'resposta_' . $numero => $valor,
        ]);
    }
}
