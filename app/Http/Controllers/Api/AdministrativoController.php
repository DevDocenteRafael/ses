<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Administrativo;
use App\Models\AlunoMigrado;
use App\Models\EngajamentoPorUnidadeSenac;
use App\Services\Admin\DashboardIndicadoresService;
use App\Services\Admin\RelatorioDashboardPdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AdministrativoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->garantirAdministrativo($request);

        return response()->json(
            Administrativo::with(['pessoa', 'alunosMigrados', 'engajamentoPorUnidade'])->get()
        );
    }

    public function show(Request $request, int $pessoaId): JsonResponse
    {
        $this->garantirAdministrativo($request);

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
    public function dashboard(Request $request, DashboardIndicadoresService $indicadores): JsonResponse
    {
        $this->garantirAdministrativo($request);

        return response()->json($indicadores->obter());
    }

    public function relatorioDashboard(
        Request $request,
        DashboardIndicadoresService $indicadores,
        RelatorioDashboardPdfRenderer $renderer
    ): Response {
        $this->garantirAdministrativo($request);

        $validated = $request->validate([
            'modo' => 'required|in:todos,especificos',
            'secoes' => 'required_if:modo,especificos|array|min:1',
            'secoes.*' => 'in:perfis_ativos,contratados,acessos_candidatos,empresas_ativas',
            'data_inicial' => 'nullable|date_format:Y-m-d',
            'data_final' => 'nullable|date_format:Y-m-d',
        ]);

        if (! empty($validated['data_inicial']) && ! empty($validated['data_final']) && $validated['data_inicial'] > $validated['data_final']) {
            throw ValidationException::withMessages([
                'data_inicial' => 'A data inicial não pode ser posterior à data final.',
                'data_final' => 'A data inicial não pode ser posterior à data final.',
            ]);
        }

        $secoes = ($validated['modo'] ?? null) === 'todos'
            ? ['perfis_ativos', 'contratados', 'acessos_candidatos', 'empresas_ativas']
            : array_values(array_unique($validated['secoes'] ?? []));

        $periodo = $indicadores->resolverPeriodoRelatorio($validated['data_inicial'] ?? null, $validated['data_final'] ?? null);
        $agora = Carbon::now(DashboardIndicadoresService::TIMEZONE);
        $pdf = $renderer->render($indicadores->obterParaRelatorio($periodo), $secoes, $agora, $periodo);
        $arquivo = 'Relatorio_Geral_' . $periodo['nome_arquivo'] . '.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $arquivo . '"; filename*=UTF-8\'\'' . rawurlencode($arquivo),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    // ── Alunos Migrados ──────────────────────────────────────────

    public function sincronizarAlunos(Request $request): JsonResponse
    {
        $administrativo = $this->garantirAdministrativo($request);

        $validated = $request->validate([
            'status_ativacao'                 => 'required|boolean',
        ]);

        $aluno = AlunoMigrado::create([
            'status_ativacao'                 => $validated['status_ativacao'],
            'ultima_sincronizacao'            => now(),
            'administrativo_pessoa_id_pessoa' => $administrativo->administrativo->pessoa_id_pessoa,
        ]);

        return response()->json($aluno, 201);
    }

    // ── Engajamento por Unidade ──────────────────────────────────

    public function listarEngajamento(Request $request): JsonResponse
    {
        $this->garantirAdministrativo($request);

        return response()->json(EngajamentoPorUnidadeSenac::with('administrativo.pessoa')->get());
    }

    public function storeEngajamento(Request $request): JsonResponse
    {
        $administrativo = $this->garantirAdministrativo($request);

        $validated = $request->validate([
            'unidade'                         => 'required|string|max:100|unique:engajamento_por_unidade_senac,unidade',
            'elegibilidade'                   => 'required|boolean',
            'status'                          => 'required|boolean',
        ]);

        $validated['administrativo_pessoa_id_pessoa'] = $administrativo->administrativo->pessoa_id_pessoa;

        $engajamento = EngajamentoPorUnidadeSenac::create($validated);

        return response()->json($engajamento, 201);
    }

    public function updateEngajamento(Request $request, string $unidade): JsonResponse
    {
        $this->garantirAdministrativo($request);

        $engajamento = EngajamentoPorUnidadeSenac::findOrFail($unidade);

        $validated = $request->validate([
            'elegibilidade' => 'sometimes|boolean',
            'status'        => 'sometimes|boolean',
        ]);

        $engajamento->update($validated);

        return response()->json($engajamento);
    }
}
