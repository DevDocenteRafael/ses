<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IndicacaoSenac;
use App\Services\Indicacoes\IndicacaoSenacService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Indicações Senac vistas pela empresa. A empresa é sempre a do token autenticado;
 * nenhum CNPJ enviado pelo frontend é usado para decidir o acesso.
 */
class IndicacaoSenacEmpresaController extends Controller
{
    public function __construct(private readonly IndicacaoSenacService $service) {}

    public function index(Request $request): JsonResponse
    {
        $empresa = $this->empresaAutenticada($request);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(IndicacaoSenac::ROTULOS_STATUS))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $query = IndicacaoSenac::query()
            ->with(IndicacaoSenacService::RELACOES_CANDIDATO)
            ->where('empresa_cnpj', $empresa->cnpj);

        $novas = (clone $query)->where('status', IndicacaoSenac::STATUS_NOVA)->count();

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $paginacao = $query->orderByDesc('indicado_em')->orderByDesc('id')->paginate((int) ($validated['per_page'] ?? 10));
        $paginacao->getCollection()->transform(fn (IndicacaoSenac $indicacao) => $this->service->formatarParaEmpresa($indicacao));

        return response()->json([...$paginacao->toArray(), 'novas' => $novas]);
    }

    public function update(Request $request, string $indicacao): JsonResponse
    {
        $empresa = $this->empresaAutenticada($request);

        $validated = $request->validate([
            'status' => ['required', Rule::in(IndicacaoSenac::STATUS_ATUALIZAVEIS_PELA_EMPRESA)],
        ], [
            'status.in' => 'Status inválido para a indicação.',
        ]);

        // Indicação de outra empresa responde como inexistente, sem revelar que ela existe.
        $registro = IndicacaoSenac::query()
            ->where('empresa_cnpj', $empresa->cnpj)
            ->findOrFail($indicacao);

        $atualizada = $this->service->atualizarStatus($registro, $validated['status'], $this->pessoaAutenticada($request));

        return response()->json([
            'message' => 'Status da indicação atualizado.',
            'indicacao' => $this->service->formatarParaEmpresa($atualizada),
        ]);
    }
}
