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
use Illuminate\Support\Carbon;
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
            ->assertJsonPath('perfisAtivos.total', 1)
            ->assertJsonPath('perfisAtivos.variacaoPercentualVsMesAnterior', null)
            ->assertJsonPath('perfisAtivos.subtitulo', 'Candidatos disponíveis pelo estado efetivo')
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
        $this->assertStringContainsString('Candidatos cadastrados no período que estão ativos atualmente', $texto);
    }

    public function test_dashboard_nao_quebra_com_candidato_legado_sem_ultima_atividade(): void
    {
        [, $token] = $this->criarAdminAutenticado();
        $legado = $this->criarCandidato(status: true);
        $legado->forceFill(['ultima_atividade_em' => null])->save();
        $this->criarCandidato(status: true);

        $this->withToken($token)->getJson('/api/administrativo/dashboard')
            ->assertOk()
            ->assertJsonPath('perfisAtivos.total', 1)
            ->assertJsonStructure([
                'perfisAtivos' => ['total', 'variacaoPercentualVsMesAnterior', 'periodoComparado', 'subtitulo'],
                'contratados',
                'acessosCandidatos',
                'empresasAtivas',
            ]);
    }

    public function test_relatorio_com_periodo_informado_filtra_indicadores_pela_semantica_correta(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07 10:00:00', 'America/Sao_Paulo'));
        [, $token] = $this->criarAdminAutenticado();
        $candidato = $this->criarCandidato(status: true, criadoEm: '2026-09-05 12:00:00');
        $candidatoFora = $this->criarCandidato(status: true, criadoEm: '2026-08-31 23:59:59');
        [$empresa] = $this->criarEmpresaAutenticada(status: true, criadoEm: '2026-09-10 09:00:00');

        $this->criarContratacao($candidato, $empresa, '2026-09-20');
        $this->criarContratacao($candidatoFora, $empresa, '2026-08-31');
        $this->criarVisualizacao($candidato, $empresa, '2026-09-30 23:59:59');
        $this->criarVisualizacao($candidato, $empresa, '2026-10-01 00:00:00');

        $response = $this->withToken($token)->post('/api/administrativo/relatorios/dashboard', [
            'modo' => 'todos',
            'data_inicial' => '2026-09-01',
            'data_final' => '2026-09-30',
        ])->assertOk();

        $texto = $this->normalizarPdfParaTeste($response->getContent());
        $this->assertStringContainsString('Período: 01/09/2026 a 30/09/2026', $texto);
        $this->assertStringContainsString('Coluna temporal: contratado_em', $texto);
        $this->assertStringContainsString('Coluna temporal: visualizado_em', $texto);
        $this->assertStringContainsString('Candidatos cadastrados no período que estão ativos atualmente', $texto);
        $this->assertStringContainsString('Empresas cadastradas no período que estão ativas atualmente', $texto);
        $this->assertStringContainsString('Relatorio_Geral_01-09-2026_a_30-09-2026.pdf', $response->headers->get('content-disposition'));
    }

    public function test_relatorio_sem_datas_usa_mes_atual_calendario(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07 10:00:00', 'America/Sao_Paulo'));
        [, $token] = $this->criarAdminAutenticado();

        $response = $this->withToken($token)->post('/api/administrativo/relatorios/dashboard', [
            'modo' => 'todos',
        ])->assertOk();

        $texto = $this->normalizarPdfParaTeste($response->getContent());
        $this->assertStringContainsString('Período: 01/10/2026 a 31/10/2026', $texto);
        $this->assertStringContainsString('Relatorio_Geral_01-10-2026_a_31-10-2026.pdf', $response->headers->get('content-disposition'));
    }

    public function test_relatorio_somente_data_inicial_usa_mes_atual(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07 10:00:00', 'America/Sao_Paulo'));
        [, $token] = $this->criarAdminAutenticado();

        $response = $this->withToken($token)->post('/api/administrativo/relatorios/dashboard', [
            'modo' => 'todos',
            'data_inicial' => '2026-09-01',
        ])->assertOk();

        $this->assertStringContainsString('Período: 01/10/2026 a 31/10/2026', $this->normalizarPdfParaTeste($response->getContent()));
    }

    public function test_relatorio_somente_data_final_usa_mes_atual(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07 10:00:00', 'America/Sao_Paulo'));
        [, $token] = $this->criarAdminAutenticado();

        $response = $this->withToken($token)->post('/api/administrativo/relatorios/dashboard', [
            'modo' => 'todos',
            'data_final' => '2026-09-30',
        ])->assertOk();

        $this->assertStringContainsString('Período: 01/10/2026 a 31/10/2026', $this->normalizarPdfParaTeste($response->getContent()));
    }

    public function test_relatorio_rejeita_data_inicial_posterior_a_final(): void
    {
        [, $token] = $this->criarAdminAutenticado();

        $this->withToken($token)->postJson('/api/administrativo/relatorios/dashboard', [
            'modo' => 'todos',
            'data_inicial' => '2026-09-30',
            'data_final' => '2026-09-01',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['data_inicial', 'data_final'])
            ->assertJsonFragment(['A data inicial não pode ser posterior à data final.']);
    }

    public function test_relatorio_mesmo_dia_inclui_todo_o_dia(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07 10:00:00', 'America/Sao_Paulo'));
        [, $token] = $this->criarAdminAutenticado();
        $candidato = $this->criarCandidato(status: true, criadoEm: '2026-10-07 12:00:00');
        [$empresa] = $this->criarEmpresaAutenticada(status: true, criadoEm: '2026-10-07 12:00:00');
        $this->criarVisualizacao($candidato, $empresa, '2026-10-07 23:59:59');

        $response = $this->withToken($token)->post('/api/administrativo/relatorios/dashboard', [
            'modo' => 'especificos',
            'secoes' => ['acessos_candidatos'],
            'data_inicial' => '2026-10-07',
            'data_final' => '2026-10-07',
        ])->assertOk();

        $texto = $this->normalizarPdfParaTeste($response->getContent());
        $this->assertStringContainsString('Período: 07/10/2026 a 07/10/2026', $texto);
        $this->assertStringContainsString('ACESSOS DE CANDIDATOS', $texto);
        $this->assertStringNotContainsString('CONTRATADOS', $texto);
    }

    public function test_relatorio_limites_primeiro_e_ultimo_dia_do_mes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07 10:00:00', 'America/Sao_Paulo'));
        [, $token] = $this->criarAdminAutenticado();
        $candidato = $this->criarCandidato(status: true, criadoEm: '2026-10-01 00:00:00');
        [$empresa] = $this->criarEmpresaAutenticada(status: true, criadoEm: '2026-10-01 00:00:00');
        $this->criarVisualizacao($candidato, $empresa, '2026-09-30 23:59:59');
        $this->criarVisualizacao($candidato, $empresa, '2026-10-01 00:00:00');
        $this->criarVisualizacao($candidato, $empresa, '2026-10-31 23:59:59');
        $this->criarVisualizacao($candidato, $empresa, '2026-11-01 00:00:00');

        $response = $this->withToken($token)->post('/api/administrativo/relatorios/dashboard', [
            'modo' => 'especificos',
            'secoes' => ['acessos_candidatos'],
        ])->assertOk();

        $texto = $this->normalizarPdfParaTeste($response->getContent());
        $this->assertStringContainsString('ACESSOS DE CANDIDATOS', $texto);
        $this->assertStringContainsString('2', $texto);
    }

    private function criarAdminAutenticado(): array
    {
        $pessoa = $this->criarPessoa('admin');
        $admin = Administrativo::query()->create(['pessoa_id_pessoa' => $pessoa->id_pessoa]);

        return [$admin, $this->token($pessoa)];
    }

    private function criarEmpresaAutenticada(bool $status = true, ?string $criadoEm = null): array
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

        if ($criadoEm) {
            $empresa->forceFill(['created_at' => Carbon::parse($criadoEm, 'America/Sao_Paulo'), 'updated_at' => Carbon::parse($criadoEm, 'America/Sao_Paulo')])->save();
        }

        return [$empresa, $this->token($pessoa)];
    }

    private function criarCandidato(bool $status = true, ?string $criadoEm = null): Candidato
    {
        $pessoa = $this->criarPessoa('candidato');

        $candidato = Candidato::query()->create([
            'matricula' => str_pad((string) random_int(1, 999999999), 15, '0', STR_PAD_LEFT),
            'cpf' => (string) random_int(10000000000, 99999999999),
            'status' => $status,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);

        if ($criadoEm) {
            $data = Carbon::parse($criadoEm, 'America/Sao_Paulo');
            $candidato->forceFill(['created_at' => $data, 'updated_at' => $data, 'ultima_atividade_em' => Carbon::now('America/Sao_Paulo')])->save();
        }

        return $candidato;
    }

    private function criarContratacao(Candidato $candidato, Empresa $empresa, string $contratadoEm): Contratacao
    {
        return Contratacao::query()->create([
            'candidato_matricula' => $candidato->matricula,
            'empresa_cnpj' => $empresa->cnpj,
            'registrado_por_pessoa_id' => $empresa->pessoa_id_pessoa,
            'origem' => 'empresa',
            'contratado_em' => $contratadoEm,
        ]);
    }

    private function criarVisualizacao(Candidato $candidato, Empresa $empresa, string $visualizadoEm): VisualizacaoPerfil
    {
        return VisualizacaoPerfil::query()->create([
            'candidato_matricula' => $candidato->matricula,
            'empresa_cnpj' => $empresa->cnpj,
            'visualizado_em' => Carbon::parse($visualizadoEm, 'America/Sao_Paulo'),
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
