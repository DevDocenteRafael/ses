<?php

namespace Tests\Feature;

use App\Models\Administrativo;
use App\Models\Candidato;
use App\Models\Contratacao;
use App\Models\CursoExterno;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Models\PreferenciasDeTrabalho;
use App\Models\ResponsavelContratual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Support\GeneratesMatricula;
use Tests\TestCase;

class CandidatoInatividadeTest extends TestCase
{
    use RefreshDatabase;
    use GeneratesMatricula;

    protected function setUp(): void
    {
        parent::setUp();
        config(['candidato.inatividade_segundos' => 30]);
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

    public function test_login_reativa_candidato_bloqueado_somente_por_inatividade(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 14:00:00'));
        [$pessoa, $candidato] = $this->criarCandidato(ultimaAtividade: now()->subSeconds(31));

        $this->postJson('/api/auth/login', [
            'identificador' => $pessoa->email,
            'senha' => '123456',
        ])->assertOk()
            ->assertJsonPath('restricoes.estado', 'ATIVO')
            ->assertJsonPath('restricoes.pode_editar_perfil', true);

        $this->assertTrue($candidato->refresh()->ultima_atividade_em->equalTo(now()));
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

    public function test_admin_visualiza_motivo_e_dashboard_usa_estado_efetivo(): void
    {
        [, $tokenAdmin] = $this->criarAdminAutenticado();
        $ativo = $this->criarCandidato(ultimaAtividade: now()->subSeconds(10))[1];
        $inativo = $this->criarCandidato(ultimaAtividade: now()->subSeconds(31))[1];
        $manual = $this->criarCandidato(status: false, ultimaAtividade: now())[1];
        $contratado = $this->criarCandidato(ultimaAtividade: now())[1];
        $this->registrarContratacao($contratado);

        $lista = $this->withToken($tokenAdmin)->getJson('/api/candidatos?per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 4)
            ->json('data');

        $estados = collect($lista)->pluck('estado_efetivo', 'matricula');
        $this->assertSame('ATIVO', $estados[(string) $ativo->matricula]);
        $this->assertSame('BLOQUEADO_POR_INATIVIDADE', $estados[(string) $inativo->matricula]);
        $this->assertSame('BLOQUEADO_MANUALMENTE', $estados[(string) $manual->matricula]);
        $this->assertSame('CONTRATADO', $estados[(string) $contratado->matricula]);

        $this->withToken($tokenAdmin)->getJson('/api/administrativo/dashboard')
            ->assertOk()
            ->assertJsonPath('perfisAtivos.total', 1);
    }

    public function test_admin_filtra_contratados_no_backend_antes_da_paginacao_e_busca(): void
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

        $this->withToken($tokenAdmin)->getJson('/api/candidatos?status=contratado&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 12)
            ->assertJsonPath('per_page', 10)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.estado_efetivo', 'CONTRATADO');

        $this->withToken($tokenAdmin)->getJson('/api/candidatos?status=contratado&page=2&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 12)
            ->assertJsonCount(2, 'data');

        $this->withToken($tokenAdmin)->getJson('/api/candidatos?busca=Arlinson&status=contratado&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.matricula', (string) $contratados[0]->matricula)
            ->assertJsonPath('data.0.estado_efetivo', 'CONTRATADO');
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
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.matricula', (string) $contratadoInativo->matricula)
            ->assertJsonPath('data.0.estado_efetivo', 'CONTRATADO');
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
}
