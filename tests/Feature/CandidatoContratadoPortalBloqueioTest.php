<?php

namespace Tests\Feature;

use App\Models\Candidato;
use App\Models\Contratacao;
use App\Models\CursoExterno;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Models\ResponsavelContratual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Support\GeneratesMatricula;
use Tests\TestCase;

class CandidatoContratadoPortalBloqueioTest extends TestCase
{
    use RefreshDatabase;
    use GeneratesMatricula;

    public function test_candidato_sem_contratacao_pode_editar_perfil(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();

        $this->withToken($token)->putJson("/api/candidatos/{$candidato->matricula}", [
            'nome' => 'Aluno Livre',
            'telefone' => '61987654321',
        ])->assertOk();

        $this->withToken($token)->postJson("/api/candidatos/{$candidato->matricula}/perfil/preferencias", [
            'tipo_de_contratacao' => 1,
            'disponibilidade_de_horario' => ['Integral'],
            'aceita_todas_regioes' => true,
        ])->assertCreated();
    }

    public function test_candidato_contratado_autentica_e_sessao_informa_restricao(): void
    {
        [$pessoa, $candidato] = $this->criarCandidatoAutenticado();
        $this->registrarContratacao($candidato);

        $login = $this->postJson('/api/auth/login', [
            'identificador' => $pessoa->email,
            'senha' => '123456',
        ])->assertOk()
            ->assertJsonPath('tipo', 'candidato')
            ->assertJsonPath('restricoes.contratado', true)
            ->assertJsonPath('restricoes.pode_editar_perfil', false)
            ->assertJsonPath('restricoes.code', 'CANDIDATO_CONTRATADO');

        $this->withToken($login->json('token'))->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('restricoes.contratado', true);
    }

    public function test_candidato_contratado_recebe_403_em_alteracoes_de_perfil(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();
        $curso = CursoExterno::query()->create([
            'nome_curso' => 'Excel',
            'instituicao' => 'Senac',
            'carga_horaria' => 20,
            'concluido_em' => now()->toDateString(),
            'candidato_matricula' => $candidato->matricula,
        ]);

        $this->registrarContratacao($candidato);

        $endpoints = [
            ['putJson', "/api/candidatos/{$candidato->matricula}", ['telefone' => '61987654321']],
            ['postJson', "/api/candidatos/{$candidato->matricula}/perfil/links", ['linkedin' => 'https://linkedin.com/in/teste']],
            ['postJson', "/api/candidatos/{$candidato->matricula}/perfil/profissional", ['area_de_atuacao' => 'Tecnologia da Informação']],
            ['postJson', "/api/candidatos/{$candidato->matricula}/perfil/preferencias", ['tipo_de_contratacao' => 1, 'disponibilidade_de_horario' => ['Integral'], 'aceita_todas_regioes' => true]],
            ['postJson', "/api/candidatos/{$candidato->matricula}/perfil/cursos-externos", ['nome_curso' => 'Inglês', 'instituicao' => 'Senac', 'concluido_em' => now()->toDateString()]],
            ['deleteJson', "/api/candidatos/{$candidato->matricula}/perfil/cursos-externos/{$curso->id}", []],
            ['postJson', "/api/candidatos/{$candidato->matricula}/perfil/experiencias", ['tipo' => 'CLT', 'cargo' => 'Dev', 'empresa' => 'Empresa', 'data_inicio' => now()->subYear()->toDateString()]],
        ];

        foreach ($endpoints as [$metodo, $url, $payload]) {
            $this->withToken($token)->{$metodo}($url, $payload)
                ->assertForbidden()
                ->assertJsonPath('code', 'CANDIDATO_CONTRATADO');
        }
    }

    public function test_logout_e_admin_empresa_nao_sao_afetados(): void
    {
        [, $candidato, $tokenCandidato] = $this->criarCandidatoAutenticado();
        $this->registrarContratacao($candidato);
        [$admin, $tokenAdmin] = $this->criarAdministrativoAutenticado();
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();

        $this->withToken($tokenCandidato)->postJson('/api/auth/logout')->assertOk();

        $this->withToken($tokenAdmin)->putJson("/api/candidatos/{$candidato->matricula}", [
            'nome' => 'Atualizado pelo Admin',
        ])->assertOk();

        $this->withToken($tokenEmpresa)->getJson('/api/candidatos?page=1&per_page=10')->assertOk();
        $this->assertNotNull($admin);
    }

    public function test_contratacao_criada_apos_login_bloqueia_requisicoes_seguintes(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('restricoes.contratado', false);

        $this->registrarContratacao($candidato);

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('restricoes.contratado', true);

        $this->withToken($token)->putJson("/api/candidatos/{$candidato->matricula}", [
            'telefone' => '61987654321',
        ])->assertForbidden()
            ->assertJsonPath('code', 'CANDIDATO_CONTRATADO');
    }

    private function criarCandidatoAutenticado(): array
    {
        $pessoa = Pessoa::query()->create([
            'nome' => 'Aluno Teste',
            'email' => 'aluno' . Str::random(8) . '@teste.com',
            'telefone' => '61' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);

        $candidato = Candidato::query()->create([
            'matricula' => $this->gerarMatricula(),
            'cpf' => (string) random_int(10000000000, 99999999999),
            'status' => true,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);

        $token = 'teste-token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return [$pessoa, $candidato, $token];
    }

    private function criarEmpresaAutenticada(): array
    {
        $pessoa = Pessoa::query()->create([
            'nome' => 'Empresa Teste',
            'email' => 'empresa' . Str::random(8) . '@teste.com',
            'telefone' => '61' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);

        $empresa = Empresa::query()->create([
            'cnpj' => (string) random_int(10000000000000, 99999999999999),
            'razao_social' => 'Empresa Teste Ltda',
            'atividade_economica' => 'Tecnologia',
            'status' => true,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
            'responsavel_contratual_id_responsavel_contratual' => ResponsavelContratual::query()->create([
                'pessoa_id_pessoa' => $pessoa->id_pessoa,
            ])->id_responsavel_contratual,
        ]);

        $token = 'teste-token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return [$pessoa, $empresa, $token];
    }

    private function criarAdministrativoAutenticado(): array
    {
        $pessoa = Pessoa::query()->create([
            'nome' => 'Admin Teste',
            'email' => 'admin' . Str::random(8) . '@teste.com',
            'telefone' => '61' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);

        \App\Models\Administrativo::query()->create([
            'cargo' => 'Administrador',
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);

        $token = 'teste-token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return [$pessoa, $token];
    }

    private function registrarContratacao(Candidato $candidato): Contratacao
    {
        [, $empresa] = $this->criarEmpresaAutenticada();
        [$admin] = $this->criarAdministrativoAutenticado();

        return Contratacao::query()->create([
            'candidato_matricula' => $candidato->matricula,
            'empresa_cnpj' => $empresa->cnpj,
            'registrado_por_pessoa_id' => $admin->id_pessoa,
            'origem' => 'administrativo',
            'contratado_em' => now()->toDateString(),
        ]);
    }
}
