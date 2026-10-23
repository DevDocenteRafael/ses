<?php

namespace Tests\Feature;

use App\Models\Administrativo;
use App\Models\Candidato;
use App\Models\Contratacao;
use App\Models\Empresa;
use App\Models\InformacoesProfissionais;
use App\Models\Pessoa;
use App\Models\PreferenciasDeTrabalho;
use App\Models\ResponsavelContratual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pesquisa de candidatos elegíveis na tela "Indicações de Candidatos"
 * (GET /api/candidatos?status=ativo&busca=...) e diagnóstico de lista vazia.
 */
class BuscaCandidatosIndicacaoTest extends TestCase
{
    use RefreshDatabase;

    private const LISTA = '/api/candidatos?status=ativo&per_page=10&busca=';
    private const DIAGNOSTICO = '/api/administrativo/indicacoes/diagnostico-candidatos?busca=';

    private string $tokenAdmin;

    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['candidato.inatividade_habilitada' => true, 'candidato.inatividade_modo_teste' => true, 'candidato.inatividade_segundos' => 30, 'candidato.perguntas_modo_teste' => false]);
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00'));
        Mail::fake();

        $admin = $this->pessoa('Admin Busca', '61900000000');
        Administrativo::query()->create(['pessoa_id_pessoa' => $admin->id_pessoa]);
        $this->tokenAdmin = $this->token($admin);
        $this->adminId = $admin->id_pessoa;
    }

    public function test_a_b_busca_por_nome_completo_e_parcial(): void
    {
        $alvo = $this->candidato('1000000001', '52998224725', 'Mariana Fictícia Albuquerque');
        $this->candidato('1000000002', '11144477735', 'Roberto Fictício Lima');

        $this->buscar('Mariana Fictícia Albuquerque')->assertJsonPath('total', 1)->assertJsonPath('data.0.matricula', $alvo->matricula);
        $this->buscar('fictícia alb')->assertJsonPath('total', 1)->assertJsonPath('data.0.matricula', $alvo->matricula);
        $this->buscar('Fictíci')->assertJsonPath('total', 2);
    }

    public function test_c_d_e_busca_por_cpf_completo_com_e_sem_pontuacao(): void
    {
        $alvo = $this->candidato('1000000001', '52998224725', 'Candidata CPF');
        $this->candidato('1000000002', '11144477735', 'Outro CPF');

        foreach (['52998224725', '529.982.247-25', ' 529 982 247 25 ', '529.982.247/25'] as $termo) {
            $this->buscar($termo)->assertJsonPath('total', 1)->assertJsonPath('data.0.matricula', $alvo->matricula);
        }

        // CPF com dígito a mais ou a menos não "chuta" resultados por conter os dígitos.
        $this->buscar('5299822472500')->assertJsonPath('total', 0);
        $this->buscar('98224725')->assertJsonPath('total', 0);
    }

    public function test_f_busca_por_matricula_sem_confundir_com_cpf(): void
    {
        $alvo = $this->candidato('5299822472', '22233344405', 'Dono da Matrícula');
        // O CPF desta candidata começa com os mesmos 10 dígitos da matrícula acima.
        $this->candidato('1000000009', '52998224725', 'Dona do CPF Parecido');

        $this->buscar('5299822472')->assertJsonPath('total', 1)->assertJsonPath('data.0.matricula', $alvo->matricula);
        // Os 11 dígitos são um CPF: só a dona do CPF aparece, não o dono da matrícula.
        $this->buscar('52998224725')->assertJsonPath('total', 1)->assertJsonPath('data.0.pessoa.nome', 'Dona do CPF Parecido');

        // Digitação progressiva (4 a 9 dígitos): início de matrícula ou de CPF, exatos primeiro.
        $this->buscar('52998')->assertJsonPath('total', 2);
    }

    public function test_g_nome_com_numero_nao_vira_busca_por_documento(): void
    {
        $alvo = $this->candidato('2000000001', '22233344405', 'Ana 1 Fictícia');
        // CPF, matrícula e telefone repletos do dígito 1: antes do ajuste, "Ana 1" trazia estes.
        $this->candidato('1111111111', '11111111111', 'Bruno Sem Número', telefone: '61911111111');
        $this->candidato('1000000001', '52998224725', 'Ana Souza Fictícia');

        $this->buscar('Ana 1')->assertJsonPath('total', 1)->assertJsonPath('data.0.matricula', $alvo->matricula);
        $this->buscar('Turma 1')->assertJsonPath('total', 0);
    }

    public function test_h_i_j_k_somente_candidatos_elegiveis_e_diagnostico_de_indisponiveis(): void
    {
        $ativo = $this->candidato('3000000001', '52998224725', 'Elegível Ativa');
        $inativo = $this->candidato('3000000002', '11144477735', 'Bloqueado Inatividade', ultimaAtividade: now()->subMinutes(5));
        $bloqueado = $this->candidato('3000000003', '22233344405', 'Bloqueado Administrativo', status: false);
        $contratado = $this->candidato('3000000004', '33344455566', 'Já Contratado');
        $this->contratar($contratado);

        // H: o ativo é encontrado por nome, CPF e matrícula.
        foreach (['Elegível Ativa', '529.982.247-25', '3000000001'] as $termo) {
            $this->buscar($termo)->assertJsonPath('total', 1)->assertJsonPath('data.0.matricula', $ativo->matricula);
        }

        // I, J, K: existem, mas não aparecem como elegíveis por nenhum critério.
        $esperados = [
            [$inativo, '11144477735', 'BLOQUEADO_POR_INATIVIDADE', 'Bloqueado Inatividade'],
            [$bloqueado, '22233344405', 'BLOQUEADO_MANUALMENTE', 'Bloqueado Administrativo'],
            [$contratado, '33344455566', 'CONTRATADO', 'Já Contratado'],
        ];
        foreach ($esperados as [$candidato, $cpf, $estado, $nome]) {
            foreach ([$nome, $cpf, $candidato->matricula] as $termo) {
                $this->buscar($termo)->assertJsonPath('total', 0);
            }

            // Diagnóstico: candidato encontrado, mas inelegível (só nome e estado, nunca CPF).
            $diagnostico = $this->withToken($this->tokenAdmin)->getJson(self::DIAGNOSTICO . urlencode($candidato->matricula))
                ->assertOk()
                ->assertJsonPath('total', 1)
                ->assertJsonPath('candidatos.0.nome', $nome)
                ->assertJsonPath('candidatos.0.estado_efetivo', $estado);
            $this->assertStringNotContainsString($cpf, $diagnostico->getContent());
        }

        // A: candidato inexistente.
        $this->withToken($this->tokenAdmin)->getJson(self::DIAGNOSTICO . '99999999999')
            ->assertOk()->assertJsonPath('total', 0)->assertJsonPath('candidatos', []);

        // O diagnóstico é somente leitura: não altera status, atividade nem envia e-mail.
        $this->assertFalse((bool) $bloqueado->fresh()->status);
        $this->assertTrue($inativo->fresh()->ultima_atividade_em->equalTo($inativo->ultima_atividade_em));
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_diagnostico_restrito_ao_administrativo(): void
    {
        $empresa = $this->pessoa('Empresa Busca', '61900000001');
        $responsavel = ResponsavelContratual::query()->create(['pessoa_id_pessoa' => $empresa->id_pessoa]);
        Empresa::query()->create([
            'cnpj' => '12345678000195',
            'razao_social' => 'Empresa Fictícia',
            'atividade_economica' => 'Tecnologia',
            'status' => true,
            'pessoa_id_pessoa' => $empresa->id_pessoa,
            'responsavel_contratual_id_responsavel_contratual' => $responsavel->id_responsavel_contratual,
        ]);

        $this->withToken($this->token($empresa))->getJson(self::DIAGNOSTICO . 'Ana')->assertForbidden();
        $this->assertContains($this->getJson(self::DIAGNOSTICO . 'Ana')->status(), [401, 403]);
        $this->withToken($this->tokenAdmin)->getJson(self::DIAGNOSTICO)->assertUnprocessable();
    }

    public function test_l_m_busca_combinada_com_segmento_e_habilidade(): void
    {
        $gestao = $this->candidato('4000000001', '52998224725', 'Paula Combinada Gestão', area: 'Administração', habilidades: ['Excel']);
        $saude = $this->candidato('4000000002', '11144477735', 'Paula Combinada Saúde', area: 'Saúde', habilidades: ['Primeiros socorros']);

        $base = '/api/candidatos?status=ativo&busca=' . urlencode('Paula Combinada');
        $this->withToken($this->tokenAdmin)->getJson($base)->assertOk()->assertJsonPath('total', 2);

        // L: segmento.
        $this->withToken($this->tokenAdmin)->getJson($base . '&segmento=' . urlencode('Saúde'))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.matricula', $saude->matricula);

        // M: habilidade.
        $this->withToken($this->tokenAdmin)->getJson($base . '&habilidades[]=Excel')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.matricula', $gestao->matricula);

        // Busca por CPF combinada com segmento incompatível: nenhum resultado.
        $this->withToken($this->tokenAdmin)->getJson('/api/candidatos?status=ativo&busca=52998224725&segmento=' . urlencode('Saúde'))
            ->assertOk()->assertJsonPath('total', 0);
    }

    public function test_n_paginacao_dos_resultados_da_busca(): void
    {
        foreach (range(1, 12) as $i) {
            $this->candidato((string) (5000000000 + $i), (string) (60000000000 + $i), "Lote Paginado {$i}");
        }
        $this->candidato('5999999999', '69999999999', 'Fora do Lote');

        $pagina1 = $this->buscar('Lote Paginado')->assertJsonPath('total', 12)->assertJsonPath('last_page', 2)->assertJsonCount(10, 'data');
        $pagina2 = $this->withToken($this->tokenAdmin)->getJson(self::LISTA . urlencode('Lote Paginado') . '&page=2')
            ->assertOk()->assertJsonCount(2, 'data');

        $todas = collect($pagina1->json('data'))->merge($pagina2->json('data'))->pluck('matricula');
        $this->assertCount(12, $todas->unique());
    }

    public function test_o_busca_vazia_ou_so_espacos_lista_todos_os_elegiveis(): void
    {
        $this->candidato('6000000001', '52998224725', 'Primeiro Elegível');
        $this->candidato('6000000002', '11144477735', 'Segundo Elegível');
        $this->candidato('6000000003', '22233344405', 'Inelegível', status: false);

        $this->withToken($this->tokenAdmin)->getJson('/api/candidatos?status=ativo')->assertOk()->assertJsonPath('total', 2);
        $this->buscar('')->assertJsonPath('total', 2);
        $this->buscar('   ')->assertJsonPath('total', 2);
    }

    public function test_indicacao_continua_validando_elegibilidade_no_backend(): void
    {
        $bloqueado = $this->candidato('7000000001', '52998224725', 'Bloqueado na Confirmação', status: false);
        $empresa = $this->pessoa('Empresa Validação', '61900000002');
        $responsavel = ResponsavelContratual::query()->create(['pessoa_id_pessoa' => $empresa->id_pessoa]);
        Empresa::query()->create([
            'cnpj' => '12345678000195',
            'razao_social' => 'Empresa Validação',
            'atividade_economica' => 'Tecnologia',
            'status' => true,
            'pessoa_id_pessoa' => $empresa->id_pessoa,
            'responsavel_contratual_id_responsavel_contratual' => $responsavel->id_responsavel_contratual,
        ]);

        $this->withToken($this->tokenAdmin)->postJson('/api/administrativo/indicacoes', [
            'candidatos' => [$bloqueado->matricula],
            'empresas' => ['12345678000195'],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('indicacoes_senac', 0);
    }

    private function buscar(string $termo)
    {
        return $this->withToken($this->tokenAdmin)->getJson(self::LISTA . urlencode($termo))->assertOk();
    }

    private function candidato(
        string $matricula,
        string $cpf,
        string $nome,
        bool $status = true,
        ?Carbon $ultimaAtividade = null,
        ?string $telefone = null,
        string $area = 'Administração',
        array $habilidades = ['Excel', 'Atendimento'],
    ): Candidato {
        $pessoa = $this->pessoa($nome, $telefone ?? '6' . str_pad($matricula, 10, '0', STR_PAD_LEFT));
        $candidato = Candidato::query()->create([
            'matricula' => $matricula,
            'cpf' => $cpf,
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
        InformacoesProfissionais::query()->create([
            'area_de_atuacao' => $area,
            'habilidades' => $habilidades,
            'candidato_matricula' => $candidato->matricula,
        ]);

        return $candidato;
    }

    private function contratar(Candidato $candidato): void
    {
        $responsavelPessoa = $this->pessoa('Empresa Contratante', '61900000009');
        $responsavel = ResponsavelContratual::query()->create(['pessoa_id_pessoa' => $responsavelPessoa->id_pessoa]);
        $empresa = Empresa::query()->create([
            'cnpj' => '98765432000198',
            'razao_social' => 'Contratante Fictícia',
            'atividade_economica' => 'Comércio',
            'status' => true,
            'pessoa_id_pessoa' => $responsavelPessoa->id_pessoa,
            'responsavel_contratual_id_responsavel_contratual' => $responsavel->id_responsavel_contratual,
        ]);
        Contratacao::query()->create([
            'candidato_matricula' => $candidato->matricula,
            'empresa_cnpj' => $empresa->cnpj,
            'registrado_por_pessoa_id' => $this->adminId,
            'origem' => 'administrativo',
            'contratado_em' => now()->toDateString(),
        ]);
    }

    private function pessoa(string $nome, string $telefone): Pessoa
    {
        return Pessoa::query()->create([
            'nome' => $nome,
            'email' => Str::lower(Str::random(10)) . '@teste.com',
            'telefone' => $telefone,
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);
    }

    private function token(Pessoa $pessoa): string
    {
        $token = 'busca-' . Str::random(16);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addDays(30));

        return $token;
    }
}
