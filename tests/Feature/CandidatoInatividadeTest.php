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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\GeneratesMatricula;
use Tests\TestCase;
use App\Services\Candidatos\CandidatoStatusService;

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

        $this->withToken($token)->postJson('/api/me/reativacao-questionario', ['resposta_1' => 'encerrar_cadastro'])
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

    private function criarCandidato(bool $status = true, ?Carbon $ultimaAtividade = null): array
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
}
