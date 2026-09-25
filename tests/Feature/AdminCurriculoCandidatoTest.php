<?php

namespace Tests\Feature;

use App\Models\Administrativo;
use App\Models\Candidato;
use App\Models\CursoExterno;
use App\Models\DadosAcademicos;
use App\Models\Empresa;
use App\Models\ExperienciaProfissional;
use App\Models\InformacoesProfissionais;
use App\Models\Pessoa;
use App\Models\PreferenciasDeTrabalho;
use App\Models\ResponsavelContratual;
use App\Services\Curriculo\CurriculoCandidatoBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Support\GeneratesMatricula;
use Tests\TestCase;

class AdminCurriculoCandidatoTest extends TestCase
{
    use RefreshDatabase;
    use GeneratesMatricula;

    public function test_admin_autorizado_baixa_curriculo_em_pdf(): void
    {
        [, $token] = $this->criarAdminAutenticado();
        $candidato = $this->criarCandidatoCompleto();

        $response = $this->withToken($token)->get("/api/administrativo/candidatos/{$candidato->matricula}/curriculo");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment; filename="Curriculo_Joao_da_Silva.pdf"', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_sem_autenticacao_recebe_401(): void
    {
        $candidato = $this->criarCandidatoCompleto();

        $this->getJson("/api/administrativo/candidatos/{$candidato->matricula}/curriculo")
            ->assertUnauthorized();
    }

    public function test_usuario_nao_admin_recebe_403(): void
    {
        [, $tokenEmpresa] = $this->criarEmpresaAutenticada();
        $candidato = $this->criarCandidatoCompleto();

        $this->withToken($tokenEmpresa)
            ->getJson("/api/administrativo/candidatos/{$candidato->matricula}/curriculo")
            ->assertForbidden();
    }

    public function test_candidato_inexistente_retorna_404(): void
    {
        [, $token] = $this->criarAdminAutenticado();

        $this->withToken($token)
            ->getJson('/api/administrativo/candidatos/999999999/curriculo')
            ->assertNotFound();
    }

    public function test_candidato_com_dados_incompletos_ainda_gera_pdf(): void
    {
        [, $token] = $this->criarAdminAutenticado();
        $candidato = $this->criarCandidatoBasico();

        $this->withToken($token)
            ->get("/api/administrativo/candidatos/{$candidato->matricula}/curriculo")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_builder_inclui_somente_habilidades_da_area_atual(): void
    {
        $candidato = $this->criarCandidatoCompleto();

        $curriculo = app(CurriculoCandidatoBuilder::class)->montar($candidato->matricula);

        $this->assertSame(['PHP', 'Laravel', 'Docker Personalizado'], $curriculo['habilidades']);
        $this->assertNotContains('Excel', $curriculo['habilidades']);
    }

    private function criarCandidatoCompleto(): Candidato
    {
        $candidato = $this->criarCandidatoBasico('João da Silva');

        DadosAcademicos::query()->create([
            'instituicao' => 'Senac DF',
            'curso' => 'Técnico em Desenvolvimento de Sistemas',
            'segmento' => 'tecnologia-da-informacao',
            'tipo_curso' => 'tecnico',
            'unidade' => 'Taguatinga',
            'ano_de_conclusao' => '2026-12-01',
            'candidato_matricula' => $candidato->matricula,
        ]);

        InformacoesProfissionais::query()->create([
            'sobre_mim' => 'Profissional com experiência em sistemas web.',
            'cargo_de_interesse' => 'Desenvolvedor PHP',
            'area_de_atuacao' => 'Tecnologia da Informação',
            'habilidades' => ['Legado'],
            'habilidades_por_area' => [
                'Tecnologia da Informação' => ['PHP', 'Laravel', 'Docker Personalizado'],
                'Administração' => ['Excel'],
            ],
            'candidato_matricula' => $candidato->matricula,
        ]);

        PreferenciasDeTrabalho::query()->create([
            'tipo_de_contratacao' => 3,
            'disponibilidade_de_horario' => ['Manhã', 'Noite'],
            'regiao_administrativa' => 'Brasília',
            'aceita_todas_regioes' => false,
            'pretensao_salarial' => 3500,
            'candidato_matricula' => $candidato->matricula,
        ]);

        ExperienciaProfissional::query()->create([
            'tipo' => 'CLT',
            'cargo' => 'Analista de Sistemas',
            'empresa' => 'Empresa XYZ',
            'local' => 'Brasília',
            'data_inicio' => '2025-01-01',
            'descricao' => 'Desenvolvimento de aplicações Laravel.',
            'candidato_matricula' => $candidato->matricula,
        ]);

        CursoExterno::query()->create([
            'nome_curso' => 'Laravel Avançado',
            'instituicao' => 'Escola Online',
            'carga_horaria' => 40,
            'concluido_em' => '2026-08-01',
            'candidato_matricula' => $candidato->matricula,
        ]);

        return $candidato;
    }

    private function criarCandidatoBasico(string $nome = 'Candidato Básico'): Candidato
    {
        $pessoa = Pessoa::query()->create([
            'nome' => $nome,
            'email' => Str::slug($nome) . Str::random(6) . '@teste.com',
            'telefone' => '61999999999',
            'endereco_cidade' => 'Brasília',
            'endereco_uf' => 'DF',
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);

        return Candidato::query()->create([
            'matricula' => $this->gerarMatricula(),
            'cpf' => (string) random_int(10000000000, 99999999999),
            'status' => true,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);
    }

    private function criarAdminAutenticado(): array
    {
        $pessoa = $this->criarPessoa('admin');
        $admin = Administrativo::query()->create(['pessoa_id_pessoa' => $pessoa->id_pessoa]);

        return [$admin, $this->token($pessoa)];
    }

    private function criarEmpresaAutenticada(): array
    {
        $pessoa = $this->criarPessoa('empresa');
        $responsavelPessoa = $this->criarPessoa('responsavel');
        $responsavel = ResponsavelContratual::query()->create(['pessoa_id_pessoa' => $responsavelPessoa->id_pessoa]);
        $empresa = Empresa::query()->create([
            'cnpj' => (string) random_int(10000000000000, 99999999999999),
            'razao_social' => 'Empresa Teste',
            'atividade_economica' => 'Tecnologia',
            'status' => true,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
            'responsavel_contratual_id_responsavel_contratual' => $responsavel->id_responsavel_contratual,
        ]);

        return [$empresa, $this->token($pessoa)];
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

    private function token(Pessoa $pessoa): string
    {
        $token = 'curriculo-token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return $token;
    }
}
