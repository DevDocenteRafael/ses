<?php

namespace Tests\Feature;

use App\Events\IndicacaoSenacStatusAlterado;
use App\Events\IndicacoesSenacCriadas;
use App\Models\Administrativo;
use App\Models\Candidato;
use App\Models\Contratacao;
use App\Models\Empresa;
use App\Models\IndicacaoSenac;
use App\Models\IndicacaoSenacEvento;
use App\Models\InformacoesProfissionais;
use App\Models\Pessoa;
use App\Models\PreferenciasDeTrabalho;
use App\Models\ResponsavelContratual;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\GeneratesMatricula;
use Tests\TestCase;

class IndicacaoSenacTest extends TestCase
{
    use RefreshDatabase;
    use GeneratesMatricula;

    private const API = '/api/administrativo/indicacoes';

    protected function setUp(): void
    {
        parent::setUp();
        config(['candidato.inatividade_habilitada' => true, 'candidato.inatividade_modo_teste' => true, 'candidato.inatividade_segundos' => 30, 'candidato.perguntas_modo_teste' => false]);
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00'));
        Mail::fake();
    }

    public function test_a_admin_indica_um_candidato_para_uma_empresa_com_observacao(): void
    {
        [, $tokenAdmin, $admin] = $this->admin();
        $candidato = $this->candidato();
        [$empresa] = $this->empresa();

        $this->withToken($tokenAdmin)->postJson(self::API, [
            'candidatos' => [$candidato->matricula],
            'empresas' => [$empresa->cnpj],
            'observacao' => '  Ótimo desempenho no curso.  ',
        ])->assertCreated()
            ->assertJsonPath('criadas', 1)
            ->assertJsonPath('total_solicitado', 1)
            ->assertJsonPath('ignoradas', []);

        $indicacao = IndicacaoSenac::query()->sole();
        $this->assertSame((string) $candidato->matricula, $indicacao->candidato_matricula);
        $this->assertSame($empresa->cnpj, $indicacao->empresa_cnpj);
        $this->assertSame($admin->id_pessoa, $indicacao->indicado_por_pessoa_id);
        $this->assertSame('Ótimo desempenho no curso.', $indicacao->observacao);
        $this->assertSame(IndicacaoSenac::STATUS_NOVA, $indicacao->status);
        $this->assertTrue($indicacao->indicado_em->equalTo(now()));
        $this->assertSame([IndicacaoSenacEvento::TIPO_CRIADA], $indicacao->eventos()->pluck('tipo')->all());
        // Não altera o status do candidato.
        $this->assertTrue($candidato->fresh()->ultima_atividade_em->equalTo($candidato->ultima_atividade_em));
    }

    public function test_b_c_d_r_combinacoes_criam_um_registro_por_par(): void
    {
        [, $tokenAdmin] = $this->admin();
        $candidatos = collect(range(1, 3))->map(fn () => $this->candidato());
        $empresas = collect(range(1, 2))->map(fn () => $this->empresa()[0]);

        // B: vários candidatos -> uma empresa.
        $this->withToken($tokenAdmin)->postJson(self::API, ['candidatos' => $candidatos->pluck('matricula')->all(), 'empresas' => [$empresas[0]->cnpj]])
            ->assertCreated()->assertJsonPath('criadas', 3)->assertJsonPath('total_solicitado', 3);

        // C: um candidato -> várias empresas (par já vigente com a empresa 1 é ignorado, não duplicado).
        $this->withToken($tokenAdmin)->postJson(self::API, ['candidatos' => [$candidatos[0]->matricula], 'empresas' => $empresas->pluck('cnpj')->all()])
            ->assertCreated()->assertJsonPath('criadas', 1)->assertJsonPath('total_solicitado', 2)->assertJsonCount(1, 'ignoradas');

        // D + R: vários x vários = 3 x 2 = 6 pares; os 4 já vigentes são ignorados.
        $resposta = $this->withToken($tokenAdmin)->postJson(self::API, ['candidatos' => $candidatos->pluck('matricula')->all(), 'empresas' => $empresas->pluck('cnpj')->all()])
            ->assertCreated()->assertJsonPath('total_solicitado', 6)->assertJsonPath('criadas', 2)->assertJsonCount(4, 'ignoradas');
        $this->assertStringContainsString('2 indicações registradas', $resposta->json('message'));

        $this->assertDatabaseCount('indicacoes_senac', 6);
        foreach ($candidatos as $candidato) {
            foreach ($empresas as $empresa) {
                $this->assertSame(1, IndicacaoSenac::query()->where('candidato_matricula', $candidato->matricula)->where('empresa_cnpj', $empresa->cnpj)->count());
            }
        }
    }

    public function test_e_f_empresa_ve_somente_as_proprias_indicacoes(): void
    {
        [, $tokenAdmin] = $this->admin();
        [$empresaA, $tokenA] = $this->empresa();
        [$empresaB, $tokenB] = $this->empresa();
        $joao = $this->candidato('João Indicado');
        $maria = $this->candidato('Maria Indicada');

        $this->withToken($tokenAdmin)->postJson(self::API, ['candidatos' => [$joao->matricula], 'empresas' => [$empresaA->cnpj]])->assertCreated();
        $this->withToken($tokenAdmin)->postJson(self::API, ['candidatos' => [$maria->matricula], 'empresas' => [$empresaB->cnpj]])->assertCreated();

        $listaA = $this->withToken($tokenA)->getJson('/api/empresa/indicacoes')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('novas', 1)
            ->assertJsonPath('data.0.candidato.nome', 'João Indicado')
            ->assertJsonPath('data.0.candidato.disponivel', true)
            ->assertJsonPath('data.0.status_rotulo', 'Nova indicação')
            ->assertJsonPath('data.0.candidato.habilidades', ['Excel', 'Atendimento'])
            ->assertJsonPath('data.0.candidato.tipos_contratacao', ['CLT']);
        $this->assertStringNotContainsString('Maria', $listaA->getContent());
        $this->assertArrayNotHasKey('email', $listaA->json('data.0.candidato'));
        $this->assertArrayNotHasKey('cpf', $listaA->json('data.0.candidato'));

        // Mesmo tentando "escolher" outra empresa pelo frontend, o filtro é sempre a empresa do token.
        $this->withToken($tokenB)->getJson('/api/empresa/indicacoes?empresa_cnpj=' . $empresaA->cnpj)->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.candidato.nome', 'Maria Indicada');
    }

    public function test_g_l_m_empresa_atualiza_somente_a_propria_e_admin_acompanha_historico(): void
    {
        Event::fake([IndicacaoSenacStatusAlterado::class]);
        [, $tokenAdmin] = $this->admin();
        [$empresaA, $tokenA, $pessoaA] = $this->empresa();
        [, $tokenB] = $this->empresa();
        $candidato = $this->candidato();
        $this->withToken($tokenAdmin)->postJson(self::API, ['candidatos' => [$candidato->matricula], 'empresas' => [$empresaA->cnpj]])->assertCreated();
        $indicacao = IndicacaoSenac::query()->sole();

        // G: outra empresa não modifica (e nem descobre que a indicação existe).
        $this->withToken($tokenB)->patchJson('/api/empresa/indicacoes/' . $indicacao->id, ['status' => 'TENHO_INTERESSE'])->assertNotFound();
        $this->assertSame('NOVA', $indicacao->fresh()->status);

        // L: a própria empresa atualiza; "Nova indicação" não pode ser escolhida de volta.
        $this->withToken($tokenA)->patchJson('/api/empresa/indicacoes/' . $indicacao->id, ['status' => 'NOVA'])->assertUnprocessable();
        Carbon::setTestNow(now()->addHour());
        $this->withToken($tokenA)->patchJson('/api/empresa/indicacoes/' . $indicacao->id, ['status' => 'EM_ANALISE'])
            ->assertOk()->assertJsonPath('indicacao.status', 'EM_ANALISE')->assertJsonPath('indicacao.status_rotulo', 'Em análise');
        $this->withToken($tokenA)->patchJson('/api/empresa/indicacoes/' . $indicacao->id, ['status' => 'TENHO_INTERESSE'])->assertOk();
        $this->withToken($tokenA)->patchJson('/api/empresa/indicacoes/' . $indicacao->id, ['status' => 'TENHO_INTERESSE'])->assertOk();
        Event::assertDispatchedTimes(IndicacaoSenacStatusAlterado::class, 2);

        // M: o administrativo vê o status atual e o histórico completo, sem eventos sobrescritos.
        $this->withToken($tokenAdmin)->getJson(self::API)->assertOk()
            ->assertJsonPath('data.0.status', 'TENHO_INTERESSE')
            ->assertJsonPath('data.0.empresa.razao_social', $empresaA->razao_social);
        $detalhe = $this->withToken($tokenAdmin)->getJson(self::API . '/' . $indicacao->id)->assertOk();
        $this->assertSame(
            [['CRIADA', null, 'NOVA'], ['STATUS_ALTERADO', 'NOVA', 'EM_ANALISE'], ['STATUS_ALTERADO', 'EM_ANALISE', 'TENHO_INTERESSE']],
            collect($detalhe->json('eventos'))->map(fn ($e) => [$e['tipo'], $e['status_anterior'], $e['status_novo']])->all()
        );
        $this->assertSame($pessoaA->nome, $detalhe->json('eventos.2.responsavel'));
        // Status da indicação não muda o status profissional do candidato.
        $this->assertTrue((bool) $candidato->fresh()->status);
    }

    public function test_h_i_j_candidatos_indisponiveis_nao_sao_indicados_e_nada_e_gravado(): void
    {
        [, $tokenAdmin] = $this->admin();
        [$empresa] = $this->empresa();
        $ativo = $this->candidato();
        $inativo = $this->candidato('Inativo', ultimaAtividade: now()->subMinutes(5));
        $bloqueado = $this->candidato('Bloqueado', status: false);
        $contratado = $this->candidato('Contratado');
        $this->contratar($contratado, $empresa);

        foreach ([$inativo, $bloqueado, $contratado] as $indisponivel) {
            $this->withToken($tokenAdmin)->postJson(self::API, ['candidatos' => [$ativo->matricula, $indisponivel->matricula], 'empresas' => [$empresa->cnpj]])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('candidatos');
        }

        $this->assertDatabaseCount('indicacoes_senac', 0);
        $this->assertTrue((bool) $bloqueado->fresh()->status === false && $contratado->fresh()->estaContratado());
    }

    public function test_empresa_bloqueada_ou_inexistente_nao_recebe_indicacao(): void
    {
        [, $tokenAdmin] = $this->admin();
        $candidato = $this->candidato();
        [$bloqueada] = $this->empresa(status: false);

        $this->withToken($tokenAdmin)->postJson(self::API, ['candidatos' => [$candidato->matricula], 'empresas' => [$bloqueada->cnpj]])
            ->assertUnprocessable()->assertJsonValidationErrors('empresas');
        $this->withToken($tokenAdmin)->postJson(self::API, ['candidatos' => [$candidato->matricula], 'empresas' => ['00000000000000']])
            ->assertUnprocessable()->assertJsonValidationErrors('empresas');
        $this->withToken($tokenAdmin)->postJson(self::API, ['candidatos' => ['999999999'], 'empresas' => [$bloqueada->cnpj]])
            ->assertUnprocessable()->assertJsonValidationErrors('candidatos');
        $this->assertDatabaseCount('indicacoes_senac', 0);
    }

    public function test_k_clique_duplo_nao_duplica_e_banco_garante_unicidade_do_par_vigente(): void
    {
        Event::fake([IndicacoesSenacCriadas::class]);
        [, $tokenAdmin] = $this->admin();
        $candidato = $this->candidato();
        [$empresa] = $this->empresa();
        $payload = ['candidatos' => [$candidato->matricula], 'empresas' => [$empresa->cnpj]];

        $this->withToken($tokenAdmin)->postJson(self::API, $payload)->assertCreated()->assertJsonPath('criadas', 1);
        $this->withToken($tokenAdmin)->postJson(self::API, $payload)->assertOk()->assertJsonPath('criadas', 0)->assertJsonCount(1, 'ignoradas');
        $this->assertDatabaseCount('indicacoes_senac', 1);
        Event::assertDispatchedTimes(IndicacoesSenacCriadas::class, 1);

        $this->expectException(UniqueConstraintViolationException::class);
        IndicacaoSenac::query()->create(['candidato_matricula' => $candidato->matricula, 'empresa_cnpj' => $empresa->cnpj, 'status' => 'NOVA', 'ativa' => 1, 'indicado_em' => now()]);
    }

    public function test_reindicacao_apos_sem_interesse_cria_novo_registro_preservando_o_anterior(): void
    {
        [, $tokenAdmin] = $this->admin();
        [$empresa, $tokenEmpresa] = $this->empresa();
        $candidato = $this->candidato();
        $payload = ['candidatos' => [$candidato->matricula], 'empresas' => [$empresa->cnpj]];

        $this->withToken($tokenAdmin)->postJson(self::API, $payload)->assertCreated();
        $primeira = IndicacaoSenac::query()->sole();
        $this->withToken($tokenEmpresa)->patchJson('/api/empresa/indicacoes/' . $primeira->id, ['status' => 'SEM_INTERESSE'])->assertOk();

        $this->withToken($tokenAdmin)->postJson(self::API, $payload)->assertCreated()->assertJsonPath('criadas', 1);
        $this->assertDatabaseCount('indicacoes_senac', 2);
        $this->assertSame('SEM_INTERESSE', $primeira->fresh()->status);
        $this->assertNull($primeira->fresh()->getRawOriginal('ativa'), 'Indicação encerrada sai do índice de vigentes (ativa = NULL).');

        // Reabrir a antiga com uma nova já vigente é recusado com mensagem clara (sem erro 500).
        $this->withToken($tokenEmpresa)->patchJson('/api/empresa/indicacoes/' . $primeira->id, ['status' => 'EM_ANALISE'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_n_candidato_indisponivel_depois_da_indicacao_nao_fica_exposto(): void
    {
        [, $tokenAdmin] = $this->admin();
        [$empresa, $tokenEmpresa] = $this->empresa();
        $candidato = $this->candidato('Ficou Inativo');
        $this->withToken($tokenAdmin)->postJson(self::API, ['candidatos' => [$candidato->matricula], 'empresas' => [$empresa->cnpj]])->assertCreated();

        Carbon::setTestNow(now()->addMinutes(5));

        $item = $this->withToken($tokenEmpresa)->getJson('/api/empresa/indicacoes')->assertOk()->json('data.0');
        $this->assertSame(['disponivel' => false, 'nome' => 'Ficou Inativo'], $item['candidato']);
        $this->withToken($tokenEmpresa)->getJson('/api/candidatos/' . $candidato->matricula)->assertForbidden();
        // O registro histórico continua para o administrativo.
        $this->withToken($tokenAdmin)->getJson(self::API)->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.candidato.estado_efetivo', 'BLOQUEADO_POR_INATIVIDADE');
    }

    public function test_o_somente_administrativo_cria_e_consulta_e_somente_empresa_ve_a_propria_area(): void
    {
        $candidato = $this->candidato();
        [$empresa, $tokenEmpresa] = $this->empresa();
        $tokenCandidato = $this->token($candidato->pessoa);
        $payload = ['candidatos' => [$candidato->matricula], 'empresas' => [$empresa->cnpj]];

        $this->postJson(self::API, $payload)->assertUnauthorized();
        $this->withToken($tokenEmpresa)->postJson(self::API, $payload)->assertForbidden();
        $this->withToken($tokenCandidato)->postJson(self::API, $payload)->assertForbidden();
        $this->withToken($tokenEmpresa)->getJson(self::API)->assertForbidden();
        $this->withToken($tokenCandidato)->getJson('/api/empresa/indicacoes')->assertForbidden();
        [, $tokenAdmin] = $this->admin();
        $this->withToken($tokenAdmin)->getJson('/api/empresa/indicacoes')->assertForbidden();
        $this->assertDatabaseCount('indicacoes_senac', 0);
    }

    public function test_p_falha_de_validacao_nao_gera_sucesso_nem_registro(): void
    {
        [, $tokenAdmin] = $this->admin();
        $this->withToken($tokenAdmin)->postJson(self::API, ['candidatos' => [], 'empresas' => []])
            ->assertUnprocessable()->assertJsonValidationErrors(['candidatos', 'empresas']);
        $this->withToken($tokenAdmin)->postJson(self::API, ['candidatos' => ['1'], 'empresas' => ['1'], 'observacao' => str_repeat('a', 1001)])
            ->assertUnprocessable()->assertJsonValidationErrors('observacao');
        $this->assertDatabaseCount('indicacoes_senac', 0);
        $this->assertDatabaseCount('indicacao_senac_eventos', 0);
    }

    public function test_q_filtros_e_paginacao_do_historico(): void
    {
        [, $tokenAdmin] = $this->admin();
        [$empresaA, $tokenA] = $this->empresa('Alfa Tecnologia');
        [$empresaB] = $this->empresa('Beta Serviços');
        $candidatos = collect(range(1, 12))->map(fn ($i) => $this->candidato("Pessoa {$i}"));

        $this->withToken($tokenAdmin)->postJson(self::API, ['candidatos' => $candidatos->pluck('matricula')->all(), 'empresas' => [$empresaA->cnpj]])->assertCreated();
        Carbon::setTestNow(Carbon::parse('2026-10-25 09:00:00'));
        $candidatos[0]->forceFill(['ultima_atividade_em' => now()])->save(); // continua elegível 3 dias depois
        $this->withToken($tokenAdmin)->postJson(self::API, ['candidatos' => [$candidatos[0]->matricula], 'empresas' => [$empresaB->cnpj]])->assertCreated();
        $alvo = IndicacaoSenac::query()->where('candidato_matricula', $candidatos[1]->matricula)->sole();
        $this->withToken($tokenA)->patchJson('/api/empresa/indicacoes/' . $alvo->id, ['status' => 'SEM_INTERESSE'])->assertOk();

        $this->withToken($tokenAdmin)->getJson(self::API . '?per_page=10')->assertOk()
            ->assertJsonPath('total', 13)->assertJsonPath('last_page', 2)->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.empresa.razao_social', 'Beta Serviços');
        $this->withToken($tokenAdmin)->getJson(self::API . '?per_page=10&page=2')->assertOk()->assertJsonCount(3, 'data');
        $this->withToken($tokenAdmin)->getJson(self::API . '?empresa=Beta')->assertOk()->assertJsonPath('total', 1);
        $this->withToken($tokenAdmin)->getJson(self::API . '?empresa=' . $empresaA->cnpj)->assertOk()->assertJsonPath('total', 12);
        $this->withToken($tokenAdmin)->getJson(self::API . '?candidato=Pessoa 12')->assertOk()->assertJsonPath('total', 1);
        $this->withToken($tokenAdmin)->getJson(self::API . '?candidato=' . $candidatos[0]->matricula)->assertOk()->assertJsonPath('total', 2);
        $this->withToken($tokenAdmin)->getJson(self::API . '?status=SEM_INTERESSE')->assertOk()->assertJsonPath('total', 1);
        $this->withToken($tokenAdmin)->getJson(self::API . '?de=2026-10-23&ate=2026-10-31')->assertOk()->assertJsonPath('total', 1);
        $this->withToken($tokenAdmin)->getJson(self::API . '?de=2026-10-22&ate=2026-10-22')->assertOk()->assertJsonPath('total', 12);
        $this->withToken($tokenAdmin)->getJson(self::API . '?de=2026-10-25&ate=2026-10-01')->assertUnprocessable();

        $this->withToken($tokenA)->getJson('/api/empresa/indicacoes?status=SEM_INTERESSE')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('novas', 11);
    }

    public function test_paginacao_da_empresa_e_real_isolada_e_consistente_com_filtro_e_status(): void
    {
        [, $tokenAdmin] = $this->admin();
        [$empresaA, $tokenA] = $this->empresa('Alfa Paginada');
        [$empresaB, $tokenB] = $this->empresa('Beta Paginada');
        $candidatos = collect(range(1, 13))->map(fn ($i) => $this->candidato("Paginado {$i}"));

        $this->withToken($tokenAdmin)->postJson(self::API, ['candidatos' => $candidatos->pluck('matricula')->all(), 'empresas' => [$empresaA->cnpj]])->assertCreated();
        $this->withToken($tokenAdmin)->postJson(self::API, ['candidatos' => $candidatos->take(2)->pluck('matricula')->all(), 'empresas' => [$empresaB->cnpj]])->assertCreated();

        // 10 por página no backend; o limite não pode ser ampliado pelo frontend.
        $pagina1 = $this->withToken($tokenA)->getJson('/api/empresa/indicacoes?page=1')->assertOk()
            ->assertJsonPath('total', 13)->assertJsonPath('per_page', 10)->assertJsonPath('last_page', 2)
            ->assertJsonPath('current_page', 1)->assertJsonCount(10, 'data')->assertJsonPath('novas', 13);
        $pagina2 = $this->withToken($tokenA)->getJson('/api/empresa/indicacoes?page=2')->assertOk()
            ->assertJsonPath('current_page', 2)->assertJsonCount(3, 'data');
        $this->assertCount(13, collect($pagina1->json('data'))->merge($pagina2->json('data'))->pluck('id')->unique());
        $this->withToken($tokenA)->getJson('/api/empresa/indicacoes?per_page=100')->assertUnprocessable();

        // Empresa B só vê as suas 2, em uma página.
        $this->withToken($tokenB)->getJson('/api/empresa/indicacoes?page=1')->assertOk()
            ->assertJsonPath('total', 2)->assertJsonPath('last_page', 1)->assertJsonPath('novas', 2);
        $this->withToken($tokenB)->getJson('/api/empresa/indicacoes?page=2')->assertOk()->assertJsonCount(0, 'data');

        // Mudança de status: total do filtro, páginas e contador de novas acompanham o servidor.
        $ids = IndicacaoSenac::query()->where('empresa_cnpj', $empresaA->cnpj)->orderBy('id')->pluck('id');
        foreach ($ids->take(4) as $id) {
            $this->withToken($tokenA)->patchJson('/api/empresa/indicacoes/' . $id, ['status' => 'EM_ANALISE'])->assertOk();
        }
        $this->withToken($tokenA)->getJson('/api/empresa/indicacoes?status=NOVA&page=1')->assertOk()
            ->assertJsonPath('total', 9)->assertJsonPath('last_page', 1)->assertJsonPath('novas', 9);
        $this->withToken($tokenA)->getJson('/api/empresa/indicacoes?status=EM_ANALISE')->assertOk()->assertJsonPath('total', 4);
        $this->withToken($tokenB)->getJson('/api/empresa/indicacoes')->assertOk()->assertJsonPath('novas', 2);
    }

    // ----------------------------------------------------------------- helpers

    private function admin(): array
    {
        $pessoa = $this->pessoa('Admin Senac');
        Administrativo::query()->create(['pessoa_id_pessoa' => $pessoa->id_pessoa]);

        return [$pessoa, $this->token($pessoa), $pessoa];
    }

    private function empresa(?string $razao = null, bool $status = true): array
    {
        $pessoa = $this->pessoa('Responsável ' . Str::random(4));
        $responsavel = ResponsavelContratual::query()->create(['pessoa_id_pessoa' => $pessoa->id_pessoa]);
        $empresa = Empresa::query()->create([
            'cnpj' => (string) random_int(10000000000000, 99999999999999),
            'razao_social' => $razao ?? 'Empresa ' . Str::random(6),
            'atividade_economica' => 'Tecnologia',
            'status' => $status,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
            'responsavel_contratual_id_responsavel_contratual' => $responsavel->id_responsavel_contratual,
        ]);

        return [$empresa, $this->token($pessoa), $pessoa];
    }

    private function candidato(?string $nome = null, bool $status = true, ?Carbon $ultimaAtividade = null): Candidato
    {
        $pessoa = $this->pessoa($nome ?? 'Candidato ' . Str::random(5));
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
        InformacoesProfissionais::query()->create([
            'area_de_atuacao' => 'Administração',
            'habilidades' => ['Excel', 'Atendimento'],
            'candidato_matricula' => $candidato->matricula,
        ]);

        return $candidato;
    }

    private function contratar(Candidato $candidato, Empresa $empresa): void
    {
        [$admin] = $this->admin();
        Contratacao::query()->create([
            'candidato_matricula' => $candidato->matricula,
            'empresa_cnpj' => $empresa->cnpj,
            'registrado_por_pessoa_id' => $admin->id_pessoa,
            'origem' => 'administrativo',
            'contratado_em' => now()->toDateString(),
        ]);
    }

    private function pessoa(string $nome): Pessoa
    {
        return Pessoa::query()->create([
            'nome' => $nome,
            'email' => Str::lower(Str::random(10)) . '@teste.com',
            'telefone' => (string) random_int(10000000000, 99999999999),
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);
    }

    private function token(Pessoa $pessoa): string
    {
        $token = 'indicacao-' . Str::random(16);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addDays(30));

        return $token;
    }
}
