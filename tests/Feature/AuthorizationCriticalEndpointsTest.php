<?php

namespace Tests\Feature;

use App\Models\Administrativo;
use App\Models\Candidato;
use App\Models\Convite;
use App\Models\CursoSenac;
use App\Models\DadosAcademicos;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Models\ResponsavelContratual;
use App\Models\Vaga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Support\GeneratesMatricula;
use Tests\TestCase;

class AuthorizationCriticalEndpointsTest extends TestCase
{
    use RefreshDatabase;
    use GeneratesMatricula;

    public function test_admin_acessa_endpoint_administrativo(): void
    {
        [$admin, $token] = $this->criarAdministrativoAutenticado();

        $this->withToken($token)
            ->getJson('/api/administrativo')
            ->assertOk();

        $this->withToken($token)
            ->getJson("/api/administrativo/{$admin->pessoa_id_pessoa}")
            ->assertOk();
    }

    public function test_aluno_recebe_403_em_endpoint_administrativo(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();

        $this->withToken($token)
            ->getJson('/api/administrativo/dashboard')
            ->assertForbidden();

        $this->withToken($token)
            ->postJson('/api/administrativo/sincronizar-alunos', [
                'status_ativacao' => true,
            ])
            ->assertForbidden();
    }

    public function test_empresa_recebe_403_em_endpoint_administrativo(): void
    {
        [, $empresa, $token] = $this->criarEmpresaAutenticada();

        $this->withToken($token)
            ->getJson('/api/administrativo/engajamento')
            ->assertForbidden();

        $this->withToken($token)
            ->postJson('/api/administrativo/engajamento', [
                'unidade' => 'Asa Sul',
                'elegibilidade' => true,
                'status' => true,
            ])
            ->assertForbidden();
    }

    public function test_sem_token_recebe_401_em_endpoint_administrativo(): void
    {
        $this->getJson('/api/administrativo')
            ->assertUnauthorized();
    }

    public function test_empresa_cria_vaga_propria_com_cnpj_do_token(): void
    {
        [, $empresa, $token] = $this->criarEmpresaAutenticada();
        [, $outraEmpresa] = $this->criarEmpresaAutenticada();

        $response = $this->withToken($token)->postJson('/api/vagas', [
            'titulo' => 'Pessoa Desenvolvedora',
            'tipo' => 1,
            'area' => 'Tecnologia',
            'status' => true,
            'data_publicacao' => '2026-09-01',
            'empresa_cnpj' => $outraEmpresa->cnpj,
        ]);

        $response->assertCreated()
            ->assertJsonPath('empresa_cnpj', $empresa->cnpj);

        $this->assertDatabaseHas('vagas', [
            'titulo' => 'Pessoa Desenvolvedora',
            'empresa_cnpj' => $empresa->cnpj,
        ]);
    }

    public function test_aluno_nao_pode_criar_vaga(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();

        $this->withToken($token)->postJson('/api/vagas', [
            'titulo' => 'Pessoa Desenvolvedora',
            'tipo' => 1,
            'area' => 'Tecnologia',
            'status' => true,
            'data_publicacao' => '2026-09-01',
        ])->assertForbidden();
    }

    public function test_empresa_a_nao_pode_editar_vaga_da_empresa_b(): void
    {
        [, , $tokenEmpresaA] = $this->criarEmpresaAutenticada();
        [, $empresaB] = $this->criarEmpresaAutenticada();

        $vaga = Vaga::query()->create([
            'titulo' => 'Vaga Empresa B',
            'tipo' => 1,
            'area' => 'Financeiro',
            'status' => true,
            'data_publicacao' => '2026-09-01',
            'empresa_cnpj' => $empresaB->cnpj,
        ]);

        $this->withToken($tokenEmpresaA)
            ->putJson("/api/vagas/{$vaga->id_vaga}", [
                'titulo' => 'Tentativa indevida',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('vagas', [
            'id_vaga' => $vaga->id_vaga,
            'titulo' => 'Vaga Empresa B',
        ]);
    }

    public function test_empresa_a_nao_pode_excluir_vaga_da_empresa_b(): void
    {
        [, , $tokenEmpresaA] = $this->criarEmpresaAutenticada();
        [, $empresaB] = $this->criarEmpresaAutenticada();

        $vaga = Vaga::query()->create([
            'titulo' => 'Vaga Empresa B',
            'tipo' => 1,
            'area' => 'Financeiro',
            'status' => true,
            'data_publicacao' => '2026-09-01',
            'empresa_cnpj' => $empresaB->cnpj,
        ]);

        $this->withToken($tokenEmpresaA)
            ->deleteJson("/api/vagas/{$vaga->id_vaga}")
            ->assertForbidden();

        $this->assertDatabaseHas('vagas', [
            'id_vaga' => $vaga->id_vaga,
        ]);
    }

    public function test_empresa_cria_convite_proprio(): void
    {
        [, $empresa, $tokenEmpresa] = $this->criarEmpresaAutenticada();
        [, $candidato] = $this->criarCandidatoAutenticado();

        $vaga = Vaga::query()->create([
            'titulo' => 'Vaga Convite',
            'tipo' => 1,
            'area' => 'Tecnologia',
            'status' => true,
            'data_publicacao' => '2026-09-01',
            'empresa_cnpj' => $empresa->cnpj,
        ]);

        $response = $this->withToken($tokenEmpresa)->postJson('/api/convites', [
            'descricao' => 'Convite para processo seletivo',
            'empresa_cnpj' => '99999999999999',
            'candidatos_matricula' => $candidato->matricula,
            'vagas_id_vaga' => $vaga->id_vaga,
        ]);

        $response->assertCreated()
            ->assertJsonPath('empresa_cnpj', $empresa->cnpj);

        $this->assertDatabaseHas('convites', [
            'empresa_cnpj' => $empresa->cnpj,
            'candidatos_matricula' => $candidato->matricula,
            'vagas_id_vaga' => $vaga->id_vaga,
        ]);
    }

    public function test_aluno_nao_pode_criar_convite(): void
    {
        [, $candidato, $tokenAluno] = $this->criarCandidatoAutenticado();
        [, $empresa] = $this->criarEmpresaAutenticada();

        $vaga = Vaga::query()->create([
            'titulo' => 'Vaga Convite',
            'tipo' => 1,
            'area' => 'Tecnologia',
            'status' => true,
            'data_publicacao' => '2026-09-01',
            'empresa_cnpj' => $empresa->cnpj,
        ]);

        $this->withToken($tokenAluno)->postJson('/api/convites', [
            'descricao' => 'Tentativa indevida',
            'candidatos_matricula' => $candidato->matricula,
            'vagas_id_vaga' => $vaga->id_vaga,
        ])->assertForbidden();
    }

    public function test_empresa_a_nao_pode_excluir_convite_da_empresa_b(): void
    {
        [, , $tokenEmpresaA] = $this->criarEmpresaAutenticada();
        [, $empresaB] = $this->criarEmpresaAutenticada();
        [, $candidato] = $this->criarCandidatoAutenticado();

        $vaga = Vaga::query()->create([
            'titulo' => 'Vaga Empresa B',
            'tipo' => 1,
            'area' => 'Tecnologia',
            'status' => true,
            'data_publicacao' => '2026-09-01',
            'empresa_cnpj' => $empresaB->cnpj,
        ]);

        $convite = Convite::query()->create([
            'descricao' => 'Convite Empresa B',
            'data_envio' => now(),
            'status' => Convite::STATUS_PENDENTE,
            'empresa_cnpj' => $empresaB->cnpj,
            'candidatos_matricula' => $candidato->matricula,
            'vagas_id_vaga' => $vaga->id_vaga,
        ]);

        $this->withToken($tokenEmpresaA)
            ->deleteJson("/api/convites/{$convite->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('convites', [
            'id' => $convite->id,
        ]);
    }

    public function test_candidato_remove_recurso_proprio(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();

        $dadoAcademico = DadosAcademicos::query()->create([
            'instituicao' => 'Senac',
            'curso' => 'ADS',
            'segmento' => 'Tecnologia',
            'tipo_curso' => 'Tecnico',
            'unidade' => 'Asa Sul',
            'ano_de_conclusao' => '2026-12-01',
            'candidato_matricula' => $candidato->matricula,
        ]);

        $cursoSenac = CursoSenac::query()->create([
            'nome_curso' => 'Excel',
            'unidade' => 'Taguatinga',
            'carga_horaria' => 40,
            'concluido_em' => '2026-08-01',
            'candidato_matricula' => $candidato->matricula,
        ]);

        $this->withToken($token)
            ->deleteJson("/api/academico/{$dadoAcademico->id}")
            ->assertOk();

        $this->withToken($token)
            ->deleteJson("/api/cursos-senac/{$cursoSenac->id}")
            ->assertOk();
    }

    public function test_candidato_nao_remove_recurso_de_outro_candidato(): void
    {
        [, $candidatoA, $tokenA] = $this->criarCandidatoAutenticado();
        [, $candidatoB] = $this->criarCandidatoAutenticado();

        $dadoAcademico = DadosAcademicos::query()->create([
            'instituicao' => 'Senac',
            'curso' => 'ADS',
            'segmento' => 'Tecnologia',
            'tipo_curso' => 'Tecnico',
            'unidade' => 'Asa Sul',
            'ano_de_conclusao' => '2026-12-01',
            'candidato_matricula' => $candidatoB->matricula,
        ]);

        $cursoSenac = CursoSenac::query()->create([
            'nome_curso' => 'Excel',
            'unidade' => 'Taguatinga',
            'carga_horaria' => 40,
            'concluido_em' => '2026-08-01',
            'candidato_matricula' => $candidatoB->matricula,
        ]);

        $this->withToken($tokenA)
            ->deleteJson("/api/academico/{$dadoAcademico->id}")
            ->assertForbidden();

        $this->withToken($tokenA)
            ->deleteJson("/api/cursos-senac/{$cursoSenac->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('dados_academicos', [
            'id' => $dadoAcademico->id,
            'candidato_matricula' => $candidatoB->matricula,
        ]);

        $this->assertDatabaseHas('cursos_senac', [
            'id' => $cursoSenac->id,
            'candidato_matricula' => $candidatoB->matricula,
        ]);
    }

    private function criarAdministrativoAutenticado(): array
    {
        $pessoa = $this->criarPessoa('admin');

        $administrativo = Administrativo::query()->create([
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);

        return [$administrativo, $this->gerarTokenParaPessoa($pessoa)];
    }

    private function criarCandidatoAutenticado(): array
    {
        $pessoa = $this->criarPessoa('aluno');

        $candidato = Candidato::query()->create([
            'matricula' => $this->gerarMatricula(),
            'cpf' => (string) random_int(10000000000, 99999999999),
            'status' => true,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);

        return [$pessoa, $candidato, $this->gerarTokenParaPessoa($pessoa)];
    }

    private function criarEmpresaAutenticada(): array
    {
        $pessoaEmpresa = $this->criarPessoa('empresa');
        $pessoaResponsavel = $this->criarPessoa('responsavel');

        $responsavel = ResponsavelContratual::query()->create([
            'pessoa_id_pessoa' => $pessoaResponsavel->id_pessoa,
        ]);

        $empresa = Empresa::query()->create([
            'cnpj' => (string) random_int(10000000000000, 99999999999999),
            'razao_social' => 'Empresa ' . Str::random(5),
            'atividade_economica' => 'Tecnologia',
            'status' => true,
            'pessoa_id_pessoa' => $pessoaEmpresa->id_pessoa,
            'responsavel_contratual_id_responsavel_contratual' => $responsavel->id_responsavel_contratual,
        ]);

        return [$pessoaEmpresa, $empresa, $this->gerarTokenParaPessoa($pessoaEmpresa)];
    }

    private function criarPessoa(string $prefixo): Pessoa
    {
        return Pessoa::query()->create([
            'nome' => ucfirst($prefixo) . ' Teste',
            'email' => $prefixo . Str::random(8) . '@teste.com',
            'telefone' => (string) random_int(10000000000, 99999999999),
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);
    }

    private function gerarTokenParaPessoa(Pessoa $pessoa): string
    {
        $token = 'teste-token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return $token;
    }
}
