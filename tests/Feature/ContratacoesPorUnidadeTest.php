<?php

namespace Tests\Feature;

use App\Models\Administrativo;
use App\Models\Candidato;
use App\Models\Contratacao;
use App\Models\DadosAcademicos;
use App\Models\Empresa;
use App\Models\IndicacaoSenac;
use App\Models\Pessoa;
use App\Models\ResponsavelContratual;
use App\Services\Admin\DashboardIndicadoresService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ranking "Contratações por Unidade Senac" (Relatórios Geral).
 */
class ContratacoesPorUnidadeTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/administrativo/relatorios/contratacoes-por-unidade';

    private string $tokenAdmin;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00', 'America/Sao_Paulo'));
        [, $this->tokenAdmin] = $this->criarAdmin();
        [$this->empresa] = $this->criarEmpresa();
    }

    public function test_ranking_conta_alunos_distintos_ordenado_com_percentuais(): void
    {
        foreach (range(1, 80) as $i) {
            $this->contratar($this->aluno(['Senac Taguatinga']), '2026-10-10');
        }
        foreach (range(1, 2) as $i) {
            $this->contratar($this->aluno(['Senac Gama']), '2026-10-01');
        }
        // Mesmo aluno com duas contratações vigentes: 1 aluno, 2 contratações.
        $duasVagas = $this->aluno(['Senac Gama']);
        $this->contratar($duasVagas, '2026-10-02');
        $this->contratar($duasVagas, '2026-10-15');
        // Aluno com cursos em duas unidades: conta só na do primeiro registro acadêmico.
        $this->contratar($this->aluno(['Senac Ceilândia', 'Senac Taguatinga', 'Senac Taguatinga']), '2026-10-05');

        // Fora da contagem: não contratado, apenas indicado, contratação cancelada e fora do período.
        $this->aluno(['Senac Taguatinga']);
        $this->indicar($this->aluno(['Senac Taguatinga']));
        $this->contratar($this->aluno(['Senac Taguatinga']), '2026-10-12', Contratacao::STATUS_CANCELADA);
        $this->contratar($this->aluno(['Senac Taguatinga']), '2026-08-01');
        // Unidade com alunos, mas sem contratação.
        $this->aluno(['Senac Planaltina']);

        $resposta = $this->withToken($this->tokenAdmin)->getJson(self::API)->assertOk()
            ->assertJsonPath('periodo.chave', 'ultimos_30_dias')
            ->assertJsonPath('periodo.inicio_data', '2026-09-22')
            ->assertJsonPath('periodo.fim_data', '2026-10-22')
            ->assertJsonPath('totalAlunos', 84)
            ->assertJsonPath('totalContratacoes', 85)
            ->assertJsonPath('totalUnidades', 3)
            ->assertJsonPath('unidadesSemContratacao', ['Senac Planaltina']);

        $this->assertSame([
            ['posicao' => 1, 'unidade' => 'Senac Taguatinga', 'alunos' => 80, 'contratacoes' => 80, 'percentual' => 95.2],
            ['posicao' => 2, 'unidade' => 'Senac Gama', 'alunos' => 3, 'contratacoes' => 4, 'percentual' => 3.6],
            ['posicao' => 3, 'unidade' => 'Senac Ceilândia', 'alunos' => 1, 'contratacoes' => 1, 'percentual' => 1.2],
        ], $resposta->json('unidades'));
        $this->assertEqualsWithDelta(100, array_sum(array_column($resposta->json('unidades'), 'percentual')), 0.1);
        $this->assertSame(84, array_sum(array_column($resposta->json('unidades'), 'alunos')));
    }

    public function test_filtros_de_periodo(): void
    {
        $this->contratar($this->aluno(['Senac Gama']), '2026-10-20');
        $this->contratar($this->aluno(['Senac Gama']), '2026-07-01');
        $this->contratar($this->aluno(['Senac Asa Sul']), '2025-11-30');
        $this->contratar($this->aluno(['Senac Asa Sul']), '2024-01-15');

        $this->withToken($this->tokenAdmin)->getJson(self::API . '?periodo=ultimos_30_dias')->assertOk()->assertJsonPath('totalAlunos', 1);
        $this->withToken($this->tokenAdmin)->getJson(self::API . '?periodo=ultimos_90_dias')->assertOk()->assertJsonPath('totalAlunos', 1);
        $this->withToken($this->tokenAdmin)->getJson(self::API . '?periodo=ultimos_12_meses')->assertOk()->assertJsonPath('totalAlunos', 3)
            ->assertJsonPath('unidades.0.unidade', 'Senac Gama')->assertJsonPath('unidades.0.alunos', 2);
        $this->withToken($this->tokenAdmin)->getJson(self::API . '?periodo=todo_periodo')->assertOk()->assertJsonPath('totalAlunos', 4)
            ->assertJsonPath('periodo.rotulo', 'Todo o período')->assertJsonPath('periodo.inicio_data', null);

        // Personalizado: limites inclusivos.
        $this->withToken($this->tokenAdmin)->getJson(self::API . '?periodo=personalizado&data_inicial=2024-01-15&data_final=2025-11-30')->assertOk()
            ->assertJsonPath('totalAlunos', 2)
            ->assertJsonPath('unidades', [['posicao' => 1, 'unidade' => 'Senac Asa Sul', 'alunos' => 2, 'contratacoes' => 2, 'percentual' => 100]])
            ->assertJsonPath('periodo.rotulo', '15/01/2024 a 30/11/2025');
    }

    public function test_sem_contratacoes_retorna_ranking_vazio(): void
    {
        $this->aluno(['Senac Gama']);
        $this->indicar($this->aluno(['Senac Gama']));

        $this->withToken($this->tokenAdmin)->getJson(self::API)->assertOk()
            ->assertJsonPath('totalAlunos', 0)
            ->assertJsonPath('totalContratacoes', 0)
            ->assertJsonPath('totalUnidades', 0)
            ->assertJsonPath('unidades', [])
            ->assertJsonPath('unidadesSemContratacao', ['Senac Gama']);
    }

    public function test_empates_compartilham_posicao_e_sem_unidade_aparece_separado(): void
    {
        $this->contratar($this->aluno(['Senac Gama']), '2026-10-20');
        $this->contratar($this->aluno(['Senac Asa Norte']), '2026-10-20');
        $this->contratar($this->aluno(['Senac Asa Norte']), '2026-10-20');
        $this->contratar($this->aluno(['Senac Recanto das Emas']), '2026-10-20');
        $this->contratar($this->aluno([]), '2026-10-20');

        $unidades = $this->withToken($this->tokenAdmin)->getJson(self::API)->assertOk()
            ->assertJsonPath('totalAlunos', 5)
            ->assertJsonPath('totalUnidades', 3)
            ->json('unidades');

        $this->assertSame(
            [[1, 'Senac Asa Norte'], [2, 'Senac Gama'], [2, 'Senac Recanto das Emas'], [2, DashboardIndicadoresService::UNIDADE_NAO_INFORMADA]],
            array_map(fn ($u) => [$u['posicao'], $u['unidade']], $unidades),
        );
    }

    public function test_quantidade_de_consultas_nao_cresce_com_os_dados(): void
    {
        foreach (range(1, 30) as $i) {
            $this->contratar($this->aluno(['Senac Unidade ' . ($i % 7), 'Senac Outra']), '2026-10-20');
        }
        $servico = app(DashboardIndicadoresService::class);
        $periodo = $servico->resolverPeriodoContratacoesUnidade('ultimos_30_dias');

        DB::enableQueryLog();
        $resultado = $servico->contratacoesPorUnidade($periodo);
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(30, $resultado['totalAlunos']);
        $this->assertSame(3, $consultas);
    }

    public function test_validacao_do_periodo(): void
    {
        $this->withToken($this->tokenAdmin)->getJson(self::API . '?periodo=semana')->assertUnprocessable()->assertJsonValidationErrors('periodo');
        $this->withToken($this->tokenAdmin)->getJson(self::API . '?periodo=personalizado')->assertUnprocessable()
            ->assertJsonValidationErrors(['data_inicial', 'data_final']);
        $this->withToken($this->tokenAdmin)->getJson(self::API . '?periodo=personalizado&data_inicial=2026-10-10&data_final=2026-10-01')
            ->assertUnprocessable()->assertJsonValidationErrors('data_final');
        $this->withToken($this->tokenAdmin)->getJson(self::API . '?periodo=personalizado&data_inicial=10/10/2026&data_final=2026-10-11')
            ->assertUnprocessable()->assertJsonValidationErrors('data_inicial');
    }

    public function test_somente_administrativo_acessa(): void
    {
        $this->getJson(self::API)->assertUnauthorized();

        [, $tokenEmpresa] = $this->criarEmpresa();
        $this->withToken($tokenEmpresa)->getJson(self::API)->assertForbidden();

        $candidato = $this->aluno(['Senac Gama']);
        $this->withToken($this->token($candidato->pessoa))->getJson(self::API)->assertForbidden();
    }

    public function test_pdf_inclui_secao_respeitando_o_periodo(): void
    {
        foreach (range(1, 3) as $i) {
            $this->contratar($this->aluno(['Senac Sobradinho']), '2026-09-10');
        }
        $this->contratar($this->aluno(['Senac Guará']), '2026-09-30');
        $this->contratar($this->aluno(['Senac Samambaia']), '2026-10-01');

        $pdf = $this->withToken($this->tokenAdmin)->post('/api/administrativo/relatorios/dashboard', [
            'modo' => 'especificos',
            'secoes' => ['contratacoes_unidade'],
            'data_inicial' => '2026-09-01',
            'data_final' => '2026-09-30',
        ])->assertOk();
        $texto = iconv('Windows-1252', 'UTF-8//IGNORE', $pdf->getContent());

        $this->assertStringContainsString('CONTRATAÇÕES POR UNIDADE SENAC', $texto);
        $this->assertStringContainsString('Alunos contratados: 4', $texto);
        $this->assertStringContainsString('1º  Senac Sobradinho', $texto);
        $this->assertStringContainsString('3  \(75,0%\)', $texto); // parênteses escapados no PDF
        $this->assertStringContainsString('2º  Senac Guará', $texto);
        $this->assertStringNotContainsString('Senac Samambaia', $texto);
        $this->assertStringNotContainsString('PERFIS ATIVOS', $texto);

        $vazio = $this->withToken($this->tokenAdmin)->post('/api/administrativo/relatorios/dashboard', [
            'modo' => 'todos', 'data_inicial' => '2020-01-01', 'data_final' => '2020-01-31',
        ])->assertOk();
        $this->assertStringContainsString('Nenhuma contratação vigente no período.', iconv('Windows-1252', 'UTF-8//IGNORE', $vazio->getContent()));
    }

    // ----------------------------------------------------------------- helpers

    /** @param  array<string>  $unidades  uma linha em dados_academicos por unidade, na ordem dada */
    private function aluno(array $unidades): Candidato
    {
        $pessoa = $this->pessoa('aluno');
        $candidato = Candidato::query()->create([
            'matricula' => str_pad((string) random_int(1, 999999999), 15, '0', STR_PAD_LEFT),
            'cpf' => (string) random_int(10000000000, 99999999999),
            'status' => true,
            'ultima_atividade_em' => now(),
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);
        foreach ($unidades as $unidade) {
            DadosAcademicos::query()->create([
                'instituicao' => 'Senac DF',
                'curso' => 'Técnico em Administração',
                'unidade' => $unidade,
                'ano_de_conclusao' => '2025-12-01',
                'candidato_matricula' => $candidato->matricula,
            ]);
        }

        return $candidato;
    }

    private function contratar(Candidato $candidato, string $data, string $status = Contratacao::STATUS_VIGENTE): void
    {
        Contratacao::query()->create([
            'candidato_matricula' => $candidato->matricula,
            'empresa_cnpj' => $this->empresa->cnpj,
            'registrado_por_pessoa_id' => $this->empresa->pessoa_id_pessoa,
            'origem' => 'empresa',
            'contratado_em' => $data,
            'status' => $status,
        ]);
    }

    private function indicar(Candidato $candidato): void
    {
        IndicacaoSenac::query()->forceCreate([
            'candidato_matricula' => $candidato->matricula,
            'empresa_cnpj' => $this->empresa->cnpj,
            'indicado_por_pessoa_id' => $this->empresa->pessoa_id_pessoa,
            'status' => IndicacaoSenac::STATUS_TENHO_INTERESSE,
            'ativa' => 1,
            'indicado_em' => now(),
        ]);
    }

    private function criarAdmin(): array
    {
        $pessoa = $this->pessoa('admin');
        $admin = Administrativo::query()->create(['pessoa_id_pessoa' => $pessoa->id_pessoa]);

        return [$admin, $this->token($pessoa)];
    }

    private function criarEmpresa(): array
    {
        $pessoa = $this->pessoa('empresa');
        $responsavel = ResponsavelContratual::query()->create(['pessoa_id_pessoa' => $this->pessoa('responsavel')->id_pessoa]);
        $empresa = Empresa::query()->create([
            'cnpj' => (string) random_int(10000000000000, 99999999999999),
            'razao_social' => 'Empresa Fictícia',
            'atividade_economica' => 'Tecnologia',
            'status' => true,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
            'responsavel_contratual_id_responsavel_contratual' => $responsavel->id_responsavel_contratual,
        ]);

        return [$empresa, $this->token($pessoa)];
    }

    private function pessoa(string $prefixo): Pessoa
    {
        return Pessoa::query()->create([
            'nome' => ucfirst($prefixo) . ' Fictício',
            'email' => $prefixo . Str::random(10) . '@teste.com',
            'telefone' => (string) random_int(10000000000, 99999999999),
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);
    }

    private function token(Pessoa $pessoa): string
    {
        $token = 'unidade-token-' . Str::random(12);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return $token;
    }
}
