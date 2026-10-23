<?php

namespace Tests\Feature;

use App\Models\Administrativo;
use App\Models\Candidato;
use App\Models\CandidatoReativacaoQuestionario;
use App\Models\Contratacao;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Models\PreferenciasDeTrabalho;
use App\Models\ResponsavelContratual;
use App\Services\Candidatos\CandidatoStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regra normal de inatividade (3 meses de calendário) versus o modo de teste de 30 s,
 * que só vale com CANDIDATO_INATIVIDADE_MODO_TESTE=true em local/testing.
 */
class PrazoInatividadeRegraNormalTest extends TestCase
{
    use RefreshDatabase;

    private CandidatoStatusService $status;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00'));
        // .env antigo das máquinas da equipe: prazo de 30 s informado, mas sem modo de teste.
        config([
            'candidato.inatividade_habilitada' => true,
            'candidato.inatividade_modo_teste' => false,
            'candidato.inatividade_segundos' => 30,
            'candidato.perguntas_modo_teste' => false,
        ]);
        $this->status = app(CandidatoStatusService::class);
    }

    public function test_regra_normal_usa_3_meses_de_calendario_e_nao_90_dias(): void
    {
        $this->assertFalse($this->status->modoTesteInatividade());
        $this->assertTrue($this->status->limiteAtividade()->equalTo(Carbon::parse('2026-07-22 12:00:00')));

        $exato = $this->candidato(atividade: Carbon::parse('2026-07-22 12:00:00'));
        $umSegundoDepois = $this->candidato(atividade: Carbon::parse('2026-07-22 12:00:01'));
        // Aqui 3 meses de calendário = 92 dias: com 91 dias seria inativo por "90 dias", mas segue ativo.
        $noventaEUmDias = $this->candidato(atividade: now()->subDays(91));

        $this->assertSame(CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE, $this->status->estadoEfetivo($exato));
        $this->assertSame(CandidatoStatusService::ATIVO, $this->status->estadoEfetivo($umSegundoDepois));
        $this->assertSame(CandidatoStatusService::ATIVO, $this->status->estadoEfetivo($noventaEUmDias));
    }

    public function test_fim_de_mes_nao_transborda(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-31 10:00:00'));

        $this->assertTrue($this->status->limiteAtividade()->equalTo(Carbon::parse('2026-02-28 10:00:00')));
    }

    public function test_modo_teste_de_30_segundos_so_com_flag_explicita(): void
    {
        $recente = $this->candidato(atividade: now()->subSeconds(31));
        $this->assertSame(CandidatoStatusService::ATIVO, $this->status->estadoEfetivo($recente));

        config(['candidato.inatividade_modo_teste' => true]);
        $this->assertTrue($this->status->modoTesteInatividade());
        $this->assertSame(CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE, $this->status->estadoEfetivo($recente));
        $this->assertSame(CandidatoStatusService::ATIVO, $this->status->estadoEfetivo($this->candidato(atividade: now()->subSeconds(29))));
    }

    public function test_modo_teste_e_ignorado_fora_de_local_e_testing(): void
    {
        config(['candidato.inatividade_modo_teste' => true]);
        $recente = $this->candidato(atividade: now()->subSeconds(31));

        foreach (['production', 'staging'] as $ambiente) {
            $this->app['env'] = $ambiente;
            $this->assertFalse($this->status->modoTesteInatividade(), $ambiente);
            $this->assertSame(CandidatoStatusService::ATIVO, $this->status->estadoEfetivo($recente), $ambiente);
        }
    }

    public function test_bloqueio_indevido_de_teste_se_desfaz_sem_alterar_dados(): void
    {
        $candidato = $this->candidato(atividade: now()->subHour());
        $antes = $candidato->fresh()->getAttributes();

        config(['candidato.inatividade_modo_teste' => true]);
        $this->assertSame(CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE, $this->status->estadoEfetivo($candidato));

        config(['candidato.inatividade_modo_teste' => false]);
        $this->assertSame(CandidatoStatusService::ATIVO, $this->status->estadoEfetivo($candidato));
        $this->assertSame($antes, $candidato->fresh()->getAttributes());
    }

    public function test_inatividade_legitima_bloqueio_administrativo_e_contratado_sao_preservados(): void
    {
        $inativo = $this->candidato(atividade: now()->subMonths(4));
        $semAtividade = $this->candidato();
        $admin = $this->candidato(status: false, atividade: now()->subDay());
        $contratado = $this->candidato(atividade: now()->subDay());
        $this->contratar($contratado);

        $this->assertSame(CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE, $this->status->estadoEfetivo($inativo));
        $this->assertSame(CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE, $this->status->estadoEfetivo($semAtividade));
        $this->assertSame(CandidatoStatusService::BLOQUEADO_MANUALMENTE, $this->status->estadoEfetivo($admin));
        $this->assertSame(CandidatoStatusService::CONTRATADO, $this->status->estadoEfetivo($contratado->fresh()));
    }

    public function test_encerramento_voluntario_continua_valendo_com_atividade_recente(): void
    {
        foreach ([[1, 'encerrar_cadastro'], [2, 'buscando_qualquer_area'], [3, 'trabalhando_nao_busco'], [6, 'sem_disponibilidade']] as [$numero, $valor]) {
            $candidato = $this->candidato(atividade: now()->subDays(5));
            $this->resposta($candidato, $numero, $valor, CandidatoReativacaoQuestionario::RESULTADO_ENCERRAMENTO_SOLICITADO);

            $this->assertSame(CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE, $this->status->estadoEfetivo($candidato), "P{$numero}");
            $this->assertFalse($this->status->estaDisponivel($candidato), "P{$numero}");
        }
    }

    public function test_reconsideracao_na_p1_reativa_e_encerramento_de_outro_ciclo_nao_vale(): void
    {
        $reconsiderou = $this->candidato(atividade: now()->subDays(5));
        $this->resposta($reconsiderou, 1, 'encerrar_cadastro', CandidatoReativacaoQuestionario::RESULTADO_ENCERRAMENTO_SOLICITADO);
        $this->resposta($reconsiderou, 1, 'manter_ativo', CandidatoReativacaoQuestionario::RESULTADO_REATIVADO, sequencia: 2);

        $cicloAntigo = $this->candidato(atividade: now()->subDays(5));
        $this->resposta($cicloAntigo, 1, 'encerrar_cadastro', CandidatoReativacaoQuestionario::RESULTADO_ENCERRAMENTO_SOLICITADO, ciclo: now()->subMonths(7));

        $this->assertSame(CandidatoStatusService::ATIVO, $this->status->estadoEfetivo($reconsiderou));
        $this->assertSame(CandidatoStatusService::ATIVO, $this->status->estadoEfetivo($cicloAntigo));
    }

    public function test_questionario_periodico_segue_3_meses_e_p1_de_reconsideracao_para_encerrado(): void
    {
        $recente = $this->candidato(atividade: now()->subDays(10));
        $vencido = $this->candidato(atividade: now()->subMonths(3)->subDay());
        $encerrado = $this->candidato(atividade: now()->subDays(10));
        $this->resposta($encerrado, 1, 'encerrar_cadastro', CandidatoReativacaoQuestionario::RESULTADO_ENCERRAMENTO_SOLICITADO);

        $this->withToken($this->token($recente->pessoa))->getJson('/api/me/reativacao-questionario')->assertOk()->assertJsonPath('pergunta', null);
        $this->withToken($this->token($vencido->pessoa))->getJson('/api/me/reativacao-questionario')->assertOk()->assertJsonPath('pergunta.numero', 1);
        $this->withToken($this->token($encerrado->pessoa))->getJson('/api/me/reativacao-questionario')->assertOk()
            ->assertJsonPath('pergunta.numero', 1)
            ->assertJsonPath('pergunta.reconsideracao', true);
    }

    public function test_perfis_ativos_busca_e_filtro_inativo_usam_o_mesmo_criterio(): void
    {
        $ativo = $this->candidato(atividade: now()->subHour());
        $this->candidato(atividade: now()->subMonths(4));
        $this->candidato(status: false, atividade: now()->subDay());
        $this->contratar($this->candidato(atividade: now()->subDay()));
        $encerrado = $this->candidato(atividade: now()->subDays(2));
        $this->resposta($encerrado, 6, 'sem_disponibilidade', CandidatoReativacaoQuestionario::RESULTADO_ENCERRAMENTO_SOLICITADO);

        [, $tokenA] = $this->admin();
        [, $tokenB] = $this->admin();
        [, $tokenEmpresa] = $this->empresa();

        $a = $this->withToken($tokenA)->getJson('/api/administrativo/dashboard')->assertOk()->json('perfisAtivos');
        $b = $this->withToken($tokenB)->getJson('/api/administrativo/dashboard')->assertOk()->json('perfisAtivos');
        $this->assertSame(1, $a['total']);
        $this->assertFalse($a['inatividadeModoTeste']);
        $this->assertNull($a['prazoInatividadeSegundos']);
        $this->assertSame($a, $b);

        $this->withToken($tokenEmpresa)->getJson('/api/candidatos?per_page=10')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.matricula', (string) $ativo->matricula);

        $inativos = collect($this->withToken($tokenA)->getJson('/api/candidatos?status=inativo&per_page=10')->assertOk()->json('data'))
            ->pluck('matricula');
        $this->assertCount(2, $inativos);
        $this->assertContains((string) $encerrado->matricula, $inativos);
    }

    public function test_indicacoes_senac_recusam_encerrado_e_aceitam_ativo(): void
    {
        $ativo = $this->candidato(atividade: now()->subHour());
        $encerrado = $this->candidato(atividade: now()->subHour());
        $this->resposta($encerrado, 3, 'trabalhando_nao_busco', CandidatoReativacaoQuestionario::RESULTADO_ENCERRAMENTO_SOLICITADO);
        [$empresa] = $this->empresa();
        [, $token] = $this->admin();

        $this->withToken($token)->postJson('/api/administrativo/indicacoes', [
            'candidatos' => [$encerrado->matricula],
            'empresas' => [$empresa->cnpj],
        ])->assertUnprocessable()->assertJsonValidationErrors('candidatos');

        $this->withToken($token)->postJson('/api/administrativo/indicacoes', [
            'candidatos' => [$ativo->matricula],
            'empresas' => [$empresa->cnpj],
        ])->assertCreated()->assertJsonPath('criadas', 1);
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
            'ultima_atualizacao_real_em' => $atividade,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);
        if ($atividade === null) {
            $candidato->forceFill(['ultima_atividade_em' => null, 'ultima_atualizacao_real_em' => null])->saveQuietly();
        }
        PreferenciasDeTrabalho::query()->create([
            'tipo_de_contratacao' => 1,
            'disponibilidade_de_horario' => ['Integral'],
            'regiao_administrativa' => 'Todas as regiões',
            'aceita_todas_regioes' => true,
            'candidato_matricula' => $candidato->matricula,
        ]);

        return $candidato->fresh();
    }

    private function resposta(Candidato $candidato, int $numero, string $valor, string $resultado, int $sequencia = 1, ?Carbon $ciclo = null): void
    {
        $ciclo ??= $candidato->ultima_atualizacao_real_em;
        CandidatoReativacaoQuestionario::query()->create([
            'candidato_matricula' => $candidato->matricula,
            'bloqueado_em' => $ciclo->copy()->startOfSecond(),
            'ciclo_inicio_em' => $ciclo->copy()->startOfSecond(),
            'pergunta_numero' => $numero,
            'sequencia' => $sequencia,
            'resultado' => $resultado,
            'concluido_em' => now(),
            'resposta_' . $numero => $valor,
        ]);
    }

    private function contratar(Candidato $candidato): void
    {
        [$empresa] = $this->empresa();
        Contratacao::query()->create([
            'candidato_matricula' => $candidato->matricula,
            'empresa_cnpj' => $empresa->cnpj,
            'registrado_por_pessoa_id' => $empresa->pessoa_id_pessoa,
            'origem' => 'empresa',
            'contratado_em' => now()->toDateString(),
            'status' => Contratacao::STATUS_VIGENTE,
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
        $token = 'prazo-token-' . Str::random(12);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return $token;
    }
}
