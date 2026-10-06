<?php

namespace Tests\Feature;

use App\Models\Administrativo;
use App\Models\Candidato;
use App\Models\Contratacao;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Models\ResponsavelContratual;
use App\Models\Vaga;
use App\Models\VisualizacaoPerfil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardRelatorioTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_gera_todos_os_dados_em_pdf(): void
    {
        [, $token] = $this->criarAdminAutenticado();
        $this->criarCandidato();
        $this->criarEmpresaAutenticada();

        $response = $this->withToken($token)->post('/api/administrativo/relatorios/dashboard', [
            'modo' => 'todos',
        ]);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('Relatorio_Geral_', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $texto = $this->normalizarPdfParaTeste($response->getContent());
        $this->assertStringContainsString('PERFIS ATIVOS', $texto);
        $this->assertStringContainsString('CONTRATADOS', $texto);
        $this->assertStringContainsString('ACESSOS DE CANDIDATOS', $texto);
        $this->assertStringContainsString('EMPRESAS ATIVAS', $texto);
        $this->assertStringNotContainsString('<style>', $response->getContent());
        $this->assertStringNotContainsString('font-family', $response->getContent());
    }

    public function test_admin_gera_somente_perfis_ativos(): void
    {
        [, $token] = $this->criarAdminAutenticado();
        $this->criarCandidato();

        $response = $this->withToken($token)->post('/api/administrativo/relatorios/dashboard', [
            'modo' => 'especificos',
            'secoes' => ['perfis_ativos'],
        ]);

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $texto = $this->normalizarPdfParaTeste($response->getContent());
        $this->assertStringContainsString('PERFIS ATIVOS', $texto);
        $this->assertStringNotContainsString('EMPRESAS ATIVAS', $texto);
    }

    public function test_admin_gera_perfis_ativos_e_contratados(): void
    {
        [, $token] = $this->criarAdminAutenticado();
        $this->criarCandidato();

        $response = $this->withToken($token)->post('/api/administrativo/relatorios/dashboard', [
            'modo' => 'especificos',
            'secoes' => ['perfis_ativos', 'contratados'],
        ]);

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $texto = $this->normalizarPdfParaTeste($response->getContent());
        $this->assertStringContainsString('PERFIS ATIVOS', $texto);
        $this->assertStringContainsString('CONTRATADOS', $texto);
        $this->assertStringNotContainsString('ACESSOS DE CANDIDATOS', $texto);
        $this->assertStringNotContainsString('EMPRESAS ATIVAS', $texto);
    }

    public function test_modo_especifico_sem_secao_retorna_validacao(): void
    {
        [, $token] = $this->criarAdminAutenticado();

        $this->withToken($token)->postJson('/api/administrativo/relatorios/dashboard', [
            'modo' => 'especificos',
            'secoes' => [],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('secoes');
    }

    public function test_secao_invalida_e_rejeitada(): void
    {
        [, $token] = $this->criarAdminAutenticado();

        $this->withToken($token)->postJson('/api/administrativo/relatorios/dashboard', [
            'modo' => 'especificos',
            'secoes' => ['perfis_ativos', 'cpf_candidatos'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('secoes.1');
    }

    public function test_nao_autenticado_recebe_401(): void
    {
        $this->postJson('/api/administrativo/relatorios/dashboard', [
            'modo' => 'todos',
        ])->assertUnauthorized();
    }

    public function test_usuario_nao_admin_recebe_403(): void
    {
        [, $tokenEmpresa] = $this->criarEmpresaAutenticada();

        $this->withToken($tokenEmpresa)->postJson('/api/administrativo/relatorios/dashboard', [
            'modo' => 'todos',
        ])->assertForbidden();
    }

    public function test_dashboard_calcula_indicadores_reais_e_periodo_de_30_dias(): void
    {
        [, $token] = $this->criarAdminAutenticado();
        $candidatoAtivo = $this->criarCandidato(status: true);
        $this->criarCandidato(status: true);
        $this->criarCandidato(status: false);
        [$empresaAtiva] = $this->criarEmpresaAutenticada(status: true);
        $this->criarEmpresaAutenticada(status: false);

        Contratacao::query()->create([
            'candidato_matricula' => $candidatoAtivo->matricula,
            'empresa_cnpj' => $empresaAtiva->cnpj,
            'registrado_por_pessoa_id' => $empresaAtiva->pessoa_id_pessoa,
            'origem' => 'empresa',
            'contratado_em' => now('America/Sao_Paulo')->subDays(29)->toDateString(),
        ]);

        VisualizacaoPerfil::query()->create([
            'candidato_matricula' => $candidatoAtivo->matricula,
            'empresa_cnpj' => $empresaAtiva->cnpj,
            'visualizado_em' => now('America/Sao_Paulo')->subDays(29),
        ]);
        VisualizacaoPerfil::query()->create([
            'candidato_matricula' => $candidatoAtivo->matricula,
            'empresa_cnpj' => $empresaAtiva->cnpj,
            'visualizado_em' => now('America/Sao_Paulo')->subDays(31),
        ]);

        Vaga::query()->create([
            'titulo' => 'Vaga Teste',
            'tipo' => 1,
            'area' => 'Tecnologia',
            'status' => true,
            'data_publicacao' => now('America/Sao_Paulo')->toDateString(),
            'empresa_cnpj' => $empresaAtiva->cnpj,
        ]);

        $this->withToken($token)->getJson('/api/administrativo/dashboard')
            ->assertOk()
            ->assertJsonPath('perfisAtivos.total', 2)
            ->assertJsonPath('perfisAtivos.variacaoPercentualVsMesAnterior', null)
            ->assertJsonPath('perfisAtivos.subtitulo', 'Candidatos com status ativo')
            ->assertJsonPath('contratados.ultimos30Dias', 1)
            ->assertJsonPath('acessosCandidatos.ultimos30Dias', 1)
            ->assertJsonPath('empresasAtivas.total', 1)
            ->assertJsonPath('empresasAtivas.deUmTotalDe', 2)
            ->assertJsonPath('empresasAtivas.engajamentoPercentual', 100);
    }

    public function test_dashboard_e_pdf_usam_mesma_fonte_dos_indicadores(): void
    {
        [, $token] = $this->criarAdminAutenticado();
        $this->criarCandidato(status: true);
        $this->criarCandidato(status: false);
        $this->criarEmpresaAutenticada(status: true);

        $dashboard = $this->withToken($token)->getJson('/api/administrativo/dashboard')
            ->assertOk()
            ->json();

        $pdf = $this->withToken($token)->post('/api/administrativo/relatorios/dashboard', [
            'modo' => 'todos',
        ])->assertOk();

        $texto = $this->normalizarPdfParaTeste($pdf->getContent());
        $this->assertStringContainsString((string) $dashboard['perfisAtivos']['total'], $texto);
        $this->assertStringContainsString((string) $dashboard['contratados']['ultimos30Dias'], $texto);
        $this->assertStringContainsString((string) $dashboard['acessosCandidatos']['ultimos30Dias'], $texto);
        $this->assertStringContainsString((string) $dashboard['empresasAtivas']['total'], $texto);
        $this->assertStringContainsString('Candidatos com status ativo', $texto);
    }

    private function criarAdminAutenticado(): array
    {
        $pessoa = $this->criarPessoa('admin');
        $admin = Administrativo::query()->create(['pessoa_id_pessoa' => $pessoa->id_pessoa]);

        return [$admin, $this->token($pessoa)];
    }

    private function criarEmpresaAutenticada(bool $status = true): array
    {
        $pessoa = $this->criarPessoa('empresa');
        $responsavelPessoa = $this->criarPessoa('responsavel');
        $responsavel = ResponsavelContratual::query()->create(['pessoa_id_pessoa' => $responsavelPessoa->id_pessoa]);
        $empresa = Empresa::query()->create([
            'cnpj' => (string) random_int(10000000000000, 99999999999999),
            'razao_social' => 'Empresa Teste',
            'atividade_economica' => 'Tecnologia',
            'status' => $status,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
            'responsavel_contratual_id_responsavel_contratual' => $responsavel->id_responsavel_contratual,
        ]);

        return [$empresa, $this->token($pessoa)];
    }

    private function criarCandidato(bool $status = true): Candidato
    {
        $pessoa = $this->criarPessoa('candidato');

        return Candidato::query()->create([
            'matricula' => str_pad((string) random_int(1, 999999999), 15, '0', STR_PAD_LEFT),
            'cpf' => (string) random_int(10000000000, 99999999999),
            'status' => $status,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);
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
        $token = 'relatorio-token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return $token;
    }

    private function normalizarPdfParaTeste(string $pdf): string
    {
        return iconv('Windows-1252', 'UTF-8//IGNORE', $pdf) ?: $pdf;
    }
}
