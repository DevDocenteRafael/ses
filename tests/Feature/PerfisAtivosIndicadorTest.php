<?php

namespace Tests\Feature;

use App\Models\Administrativo;
use App\Models\Candidato;
use App\Models\Contratacao;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Models\ResponsavelContratual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Card "Perfis Ativos" (GET /api/administrativo/dashboard): a contagem depende só do banco e do
 * prazo de inatividade do servidor, nunca de qual administrador consulta.
 */
class PerfisAtivosIndicadorTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/administrativo/dashboard';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00'));
        config(['candidato.inatividade_habilitada' => true, 'candidato.inatividade_segundos' => 7889400]);
    }

    public function test_k_l_m_n_conta_somente_candidatos_efetivamente_ativos(): void
    {
        $this->candidato(atividade: now()->subDay());                       // K. ativo
        $this->candidato(atividade: now()->subDays(10));                    // K. ativo
        $this->candidato(atividade: now()->subMonths(4));                   // L. bloqueado por inatividade
        $this->candidato();                                                 // L. legado sem atividade registrada
        $this->candidato(status: false, atividade: now()->subDay());        // M. bloqueado administrativamente
        $this->contratar($this->candidato(atividade: now()->subDay()));     // N. contratado
        $cancelada = $this->candidato(atividade: now()->subDay());          // contratação cancelada volta a contar
        $this->contratar($cancelada, Contratacao::STATUS_CANCELADA);

        [, $token] = $this->admin();
        $this->withToken($token)->getJson(self::API)->assertOk()
            ->assertJsonPath('perfisAtivos.total', 3)
            ->assertJsonPath('perfisAtivos.inatividadeHabilitada', true)
            ->assertJsonPath('perfisAtivos.prazoInatividadeSegundos', 7889400);
    }

    public function test_a_b_o_administradores_e_computadores_diferentes_veem_o_mesmo_numero(): void
    {
        foreach (range(1, 5) as $dias) {
            $this->candidato(atividade: now()->subDays($dias));
        }
        [, $tokenA] = $this->admin();
        [, $tokenB] = $this->admin();

        // Mesmo servidor, dois administradores, "computadores" diferentes (IP e navegador distintos).
        $a = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.11'])->withHeader('User-Agent', 'PC-A Chrome')
            ->withToken($tokenA)->getJson(self::API)->assertOk()->json('perfisAtivos');
        $b = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.27'])->withHeader('User-Agent', 'PC-B Edge')
            ->withToken($tokenB)->getJson(self::API)->assertOk()->json('perfisAtivos');

        $this->assertSame(5, $a['total']);
        $this->assertSame($a, $b);
    }

    public function test_causa_prazo_de_inatividade_de_teste_zera_o_indicador_com_o_mesmo_banco(): void
    {
        // Candidatos que usaram o portal há 1 hora (como no ses_db: atividade do dia anterior).
        foreach (range(1, 4) as $i) {
            $this->candidato(atividade: now()->subHour());
        }
        [, $token] = $this->admin();

        $this->withToken($token)->getJson(self::API)->assertOk()->assertJsonPath('perfisAtivos.total', 4);

        // Mesmo banco, servidor configurado com CANDIDATO_INATIVIDADE_SEGUNDOS=30 (valor antigo do .env.example).
        config(['candidato.inatividade_segundos' => 30]);
        $this->withToken($token)->getJson(self::API)->assertOk()
            ->assertJsonPath('perfisAtivos.total', 0)
            ->assertJsonPath('perfisAtivos.prazoInatividadeSegundos', 30);

        // Nenhum dado de candidato foi alterado pela consulta.
        $this->assertSame(4, Candidato::query()->where('status', true)->count());
        $this->assertSame(4, Candidato::query()->where('ultima_atividade_em', now()->subHour())->count());
    }

    public function test_c_g_h_somente_administrativo_acessa(): void
    {
        $this->getJson(self::API)->assertUnauthorized();

        [, $tokenEmpresa] = $this->empresa();
        $this->withToken($tokenEmpresa)->getJson(self::API)->assertForbidden();

        $candidato = $this->candidato(atividade: now()->subMinute());
        $this->withToken($this->token($candidato->pessoa))->getJson(self::API)->assertForbidden();
    }

    public function test_j_sem_cache_valor_acompanha_o_banco_entre_consultas(): void
    {
        [, $token] = $this->admin();
        $this->candidato(atividade: now()->subDay());
        $this->withToken($token)->getJson(self::API)->assertOk()->assertJsonPath('perfisAtivos.total', 1);

        $this->candidato(atividade: now()->subDay());
        $this->withToken($token)->getJson(self::API)->assertOk()->assertJsonPath('perfisAtivos.total', 2);
    }

    public function test_d_e_mesmos_filtros_mesmo_resultado_e_periodo_diferente_no_pdf(): void
    {
        [, $tokenA] = $this->admin();
        [, $tokenB] = $this->admin();
        $setembro = $this->candidato(atividade: now()->subDay());
        $setembro->forceFill(['created_at' => Carbon::parse('2026-09-10 10:00:00')])->save();
        $this->candidato(atividade: now()->subDay());

        $pdf = fn ($token, $inicio, $fim) => iconv('Windows-1252', 'UTF-8//IGNORE', $this->withToken($token)->post('/api/administrativo/relatorios/dashboard', [
            'modo' => 'especificos', 'secoes' => ['perfis_ativos'], 'data_inicial' => $inicio, 'data_final' => $fim,
        ])->assertOk()->getContent());

        $a = $pdf($tokenA, '2026-09-01', '2026-09-30');
        $b = $pdf($tokenB, '2026-09-01', '2026-09-30');
        $this->assertStringContainsString('Total: 1', $a);
        $this->assertSame(preg_replace('/Gerado em: [^)]*/', '', $a), preg_replace('/Gerado em: [^)]*/', '', $b));

        $this->assertStringContainsString('Total: 1', $pdf($tokenA, '2026-10-01', '2026-10-31'));
        $this->assertStringContainsString('Total: 2', $pdf($tokenA, '2026-09-01', '2026-10-31'));
    }

    // ----------------------------------------------------------------- helpers

    private function candidato(bool $status = true, ?Carbon $atividade = null): Candidato
    {
        $pessoa = $this->pessoa('candidato');

        $candidato = Candidato::query()->forceCreate([
            'matricula' => str_pad((string) random_int(1, 999999999), 15, '0', STR_PAD_LEFT),
            'cpf' => (string) random_int(10000000000, 99999999999),
            'status' => $status,
            'ultima_atividade_em' => $atividade,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);
        // O model preenche a atividade ao criar; um cadastro legado sem atividade é simulado depois.
        if ($atividade === null) {
            $candidato->forceFill(['ultima_atividade_em' => null])->saveQuietly();
        }

        return $candidato;
    }

    private function contratar(Candidato $candidato, string $status = Contratacao::STATUS_VIGENTE): void
    {
        [$empresa] = $this->empresa();
        Contratacao::query()->create([
            'candidato_matricula' => $candidato->matricula,
            'empresa_cnpj' => $empresa->cnpj,
            'registrado_por_pessoa_id' => $empresa->pessoa_id_pessoa,
            'origem' => 'empresa',
            'contratado_em' => now()->toDateString(),
            'status' => $status,
        ]);
    }

    private function admin(): array
    {
        $pessoa = $this->pessoa('admin');
        Administrativo::query()->create(['pessoa_id_pessoa' => $pessoa->id_pessoa]);

        return [$pessoa, $this->token($pessoa)];
    }

    private function empresa(): array
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
        $token = 'perfis-token-' . Str::random(12);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return $token;
    }
}
