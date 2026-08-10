<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Administrativo;
use App\Models\AlunoMigrado;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\EngajamentoPorUnidadeSenac;
use App\Models\VisualizacaoPerfil;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AdministrativoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Administrativo::with(['pessoa', 'alunosMigrados', 'engajamentoPorUnidade'])->get()
        );
    }

    public function show(int $pessoaId): JsonResponse
    {
        return response()->json(
            Administrativo::with(['pessoa', 'alunosMigrados', 'engajamentoPorUnidade'])->findOrFail($pessoaId)
        );
    }

    /**
     * Indicadores da tela "Relatórios Geral" / "Indicadores de
     * Empregabilidade" (FR38). Cada número aqui vem de uma contagem
     * real no banco — nada é estimado ou fixo.
     *
     * Observação: o protótipo da tela também previa um histórico de
     * "Buscas Realizadas" e uma tabela de "Filtros Mais Acessados
     * pelas Empresas". Isso exigiria registrar cada busca/filtro que
     * uma empresa faz em Buscar Talentos — não existe hoje nenhuma
     * tabela de log para isso, então esses dois pontos não são
     * retornados aqui (ver observação no card do frontend).
     */
    public function dashboard(): JsonResponse
    {
        $inicioDoMes = Carbon::now()->startOfMonth();
        $inicioDoMesAnterior = (clone $inicioDoMes)->subMonth();

        $totalCandidatos = Candidato::count();
        $candidatosMesAtual = Candidato::whereHas(
            'pessoa',
            fn ($q) => $q->where('data_cadastro', '>=', $inicioDoMes)
        )->count();
        $candidatosMesAnterior = Candidato::whereHas(
            'pessoa',
            fn ($q) => $q->whereBetween('data_cadastro', [$inicioDoMesAnterior, $inicioDoMes])
        )->count();

        $variacaoPerfis = $candidatosMesAnterior > 0
            ? round((($candidatosMesAtual - $candidatosMesAnterior) / $candidatosMesAnterior) * 100, 1)
            : null;

        $acessosUltimos30Dias = VisualizacaoPerfil::where('visualizado_em', '>=', now()->subDays(30))->count();

        $totalEmpresas = Empresa::count();
        $empresasAtivas = Empresa::where('status', true)->count();
        $empresasComVagaAtiva = Empresa::where('status', true)
            ->whereHas('vagas', fn ($q) => $q->where('status', true))
            ->count();
        $engajamentoEmpresas = $empresasAtivas > 0
            ? round(($empresasComVagaAtiva / $empresasAtivas) * 100)
            : 0;

        // Donut "Acessos por Área de Interesse": visualizações de perfil
        // agrupadas pelo segmento acadêmico do candidato visualizado.
        $acessosPorSegmento = VisualizacaoPerfil::query()
            ->join('candidato', 'visualizacoes_perfil.candidato_matricula', '=', 'candidato.matricula')
            ->join('dados_academicos', 'dados_academicos.candidato_matricula', '=', 'candidato.matricula')
            ->selectRaw('dados_academicos.segmento as segmento, count(*) as total')
            ->groupBy('dados_academicos.segmento')
            ->orderByDesc('total')
            ->get();

        // Linha "Visualizações de Perfil" nos últimos 6 meses.
        $visualizacoesPorMes = VisualizacaoPerfil::query()
            ->selectRaw("strftime('%Y-%m', visualizado_em) as mes, count(*) as total")
            ->where('visualizado_em', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();

        return response()->json([
            'perfisAtivos' => [
                'total' => $totalCandidatos,
                'variacaoPercentualVsMesAnterior' => $variacaoPerfis,
            ],
            'acessosCandidatos' => [
                'ultimos30Dias' => $acessosUltimos30Dias,
            ],
            'empresasAtivas' => [
                'total' => $empresasAtivas,
                'deUmTotalDe' => $totalEmpresas,
                'engajamentoPercentual' => $engajamentoEmpresas,
            ],
            'acessosPorSegmento' => $acessosPorSegmento,
            'visualizacoesPorMes' => $visualizacoesPorMes,
        ]);
    }

    // ── Alunos Migrados ──────────────────────────────────────────

    public function sincronizarAlunos(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'administrativo_pessoa_id_pessoa' => 'required|integer|exists:administrativo,pessoa_id_pessoa',
            'status_ativacao'                 => 'required|boolean',
        ]);

        $aluno = AlunoMigrado::create([
            'status_ativacao'                 => $validated['status_ativacao'],
            'ultima_sincronizacao'            => now(),
            'administrativo_pessoa_id_pessoa' => $validated['administrativo_pessoa_id_pessoa'],
        ]);

        return response()->json($aluno, 201);
    }

    // ── Engajamento por Unidade ──────────────────────────────────

    public function listarEngajamento(): JsonResponse
    {
        return response()->json(EngajamentoPorUnidadeSenac::with('administrativo.pessoa')->get());
    }

    public function storeEngajamento(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unidade'                         => 'required|string|max:100|unique:engajamento_por_unidade_senac,unidade',
            'elegibilidade'                   => 'required|boolean',
            'status'                          => 'required|boolean',
            'administrativo_pessoa_id_pessoa' => 'required|integer|exists:administrativo,pessoa_id_pessoa',
        ]);

        $engajamento = EngajamentoPorUnidadeSenac::create($validated);

        return response()->json($engajamento, 201);
    }

    public function updateEngajamento(Request $request, string $unidade): JsonResponse
    {
        $engajamento = EngajamentoPorUnidadeSenac::findOrFail($unidade);

        $validated = $request->validate([
            'elegibilidade' => 'sometimes|boolean',
            'status'        => 'sometimes|boolean',
        ]);

        $engajamento->update($validated);

        return response()->json($engajamento);
    }
}
