<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidato;
use App\Models\IndicacaoSenac;
use App\Services\Candidatos\CandidatoQueryService;
use App\Services\Candidatos\CandidatoStatusService;
use App\Services\Indicacoes\IndicacaoSenacService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IndicacaoSenacAdminController extends Controller
{
    public function __construct(
        private readonly IndicacaoSenacService $service,
        private readonly CandidatoStatusService $statusService,
    ) {}

    /**
     * Histórico de indicações com filtros por candidato, empresa, status e período.
     */
    public function index(Request $request): JsonResponse
    {
        $this->garantirAdministrativo($request);

        $validated = $request->validate([
            'candidato' => ['nullable', 'string', 'max:100'],
            'empresa' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(IndicacaoSenac::ROTULOS_STATUS))],
            'de' => ['nullable', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:de'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $query = IndicacaoSenac::query()->with([
            ...IndicacaoSenacService::RELACOES_CANDIDATO,
            'empresa:cnpj,razao_social,status',
            'indicadoPor:id_pessoa,nome',
        ]);

        if (! empty($validated['candidato'])) {
            $termo = trim($validated['candidato']);
            $numeros = $this->somenteDocumento($termo);
            $query->whereHas('candidato', fn ($candidato) => $candidato->where(function ($filtro) use ($termo, $numeros) {
                $filtro->whereHas('pessoa', fn ($pessoa) => $pessoa->where('nome', 'like', '%' . $termo . '%'));
                if ($numeros !== '') {
                    $filtro->orWhere('matricula', 'like', '%' . $numeros . '%')->orWhere('cpf', 'like', '%' . $numeros . '%');
                }
            }));
        }

        if (! empty($validated['empresa'])) {
            $termo = trim($validated['empresa']);
            $numeros = $this->somenteDocumento($termo);
            $query->whereHas('empresa', fn ($empresa) => $empresa->where(function ($filtro) use ($termo, $numeros) {
                $filtro->where('razao_social', 'like', '%' . $termo . '%');
                if ($numeros !== '') {
                    $filtro->orWhere('cnpj', 'like', '%' . $numeros . '%');
                }
            }));
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['de'])) {
            $query->where('indicado_em', '>=', $validated['de'] . ' 00:00:00');
        }

        if (! empty($validated['ate'])) {
            $query->where('indicado_em', '<=', $validated['ate'] . ' 23:59:59');
        }

        $paginacao = $query->orderByDesc('indicado_em')->orderByDesc('id')->paginate((int) ($validated['per_page'] ?? 10));
        $paginacao->getCollection()->transform(fn (IndicacaoSenac $indicacao) => $this->service->formatarParaAdministrativo($indicacao));

        return response()->json($paginacao);
    }

    /**
     * Dígitos do termo quando ele é só um documento (matrícula, CPF ou CNPJ, com ou sem máscara).
     * Termos com texto ("Pessoa 12") buscam apenas por nome.
     */
    private function somenteDocumento(string $termo): string
    {
        return preg_match('/^[\d.\-\/\s]+$/', $termo) ? (preg_replace('/\D+/', '', $termo) ?? '') : '';
    }

    /**
     * Quando a lista de elegíveis vem vazia, informa se o termo corresponde a candidatos
     * existentes porém indisponíveis (inativos, bloqueados ou contratados). Somente leitura:
     * não registra bloqueios nem altera candidatos. Expõe apenas nome e estado.
     */
    public function diagnosticoCandidatos(Request $request, CandidatoQueryService $queryService): JsonResponse
    {
        $this->garantirAdministrativo($request);

        $validated = $request->validate(['busca' => ['required', 'string', 'max:100']]);

        $query = Candidato::query()->with(['pessoa:id_pessoa,nome', 'contratacao']);
        $queryService->aplicarBusca($query, $validated['busca']);

        $total = (clone $query)->count();
        $candidatos = $query->orderBy('matricula')->limit(5)->get()->map(function (Candidato $candidato) {
            $estado = $this->statusService->estadoEfetivo($candidato);

            return [
                'nome' => $candidato->pessoa?->nome,
                'estado_efetivo' => $estado,
                'estado_efetivo_rotulo' => $this->statusService->rotulo($estado),
            ];
        });

        return response()->json(['total' => $total, 'candidatos' => $candidatos]);
    }

    public function show(Request $request, IndicacaoSenac $indicacao): JsonResponse
    {
        $this->garantirAdministrativo($request);

        $indicacao->load([
            ...IndicacaoSenacService::RELACOES_CANDIDATO,
            'empresa:cnpj,razao_social,status',
            'indicadoPor:id_pessoa,nome',
            'eventos.pessoa:id_pessoa,nome',
        ]);

        return response()->json($this->service->formatarParaAdministrativo($indicacao, comEventos: true));
    }

    /**
     * Cria uma indicação para cada combinação candidato + empresa selecionada.
     */
    public function store(Request $request): JsonResponse
    {
        $administrador = $this->garantirAdministrativo($request);

        $validated = $request->validate([
            'candidatos' => ['required', 'array', 'min:1', 'max:50'],
            'candidatos.*' => ['required', 'string', 'max:15', 'distinct'],
            'empresas' => ['required', 'array', 'min:1', 'max:20'],
            'empresas.*' => ['required', 'string', 'max:18', 'distinct'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ], [
            'candidatos.required' => 'Selecione pelo menos um candidato.',
            'empresas.required' => 'Selecione pelo menos uma empresa.',
            'candidatos.max' => 'Selecione no máximo 50 candidatos por envio.',
            'empresas.max' => 'Selecione no máximo 20 empresas por envio.',
            'observacao.max' => 'A observação deve ter no máximo 1000 caracteres.',
        ]);

        $resultado = $this->service->indicar($administrador, $validated['candidatos'], $validated['empresas'], $validated['observacao'] ?? null);

        return response()->json($resultado, $resultado['criadas'] > 0 ? 201 : 200);
    }
}
