<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmpresaController extends Controller
{
    public function index(): JsonResponse
    {
        $empresas = Empresa::with([
            'pessoa',
            'responsavelContratual.pessoa',
            'vagas',
            'historicoDeEngajamento',
        ])->get();

        return response()->json($empresas);
    }

    public function show(string $cnpj): JsonResponse
    {
        $empresa = Empresa::with([
            'pessoa',
            'responsavelContratual.pessoa',
            'vagas',
            'convites',
            'historicoDeEngajamento',
            'candidatos.pessoa',
            'candidatos.informacoesProfissionais',
            'candidatos.preferenciasDeTrabalho',
            'candidatos.dadosAcademicos',
        ])->findOrFail($cnpj);

        return response()->json($empresa);
    }

    public function update(Request $request, string $cnpj): JsonResponse
    {
        $solicitante = $this->pessoaAutenticada($request);
        if (! $solicitante || $solicitante->tipo() !== 'administrativo') {
            abort(403, 'Apenas o administrativo pode alterar dados de empresas.');
        }

        $empresa = Empresa::findOrFail($cnpj);

        $validated = $request->validate([
            'razao_social'        => 'sometimes|string|max:45',
            'atividade_economica' => 'sometimes|string|max:45',
            'status'              => 'sometimes|boolean',
        ]);

        $empresa->update($validated);

        return response()->json($empresa->load('pessoa'));
    }

    public function destroy(Request $request, string $cnpj): JsonResponse
    {
        $solicitante = $this->pessoaAutenticada($request);
        if (! $solicitante || $solicitante->tipo() !== 'administrativo') {
            abort(403, 'Apenas o administrativo pode remover empresas.');
        }

        $empresa = Empresa::findOrFail($cnpj);
        $empresa->delete();

        return response()->json(['message' => 'Empresa removida com sucesso.']);
    }

    // ── Favoritos (candidatos salvos pela empresa) ───────────────
    // Usa a tabela pivô `empresa_has_candidatos` (relação Empresa::candidatos()).

    public function favoritos(Request $request): JsonResponse
    {
        $empresa = $this->empresaAutenticada($request);

        $favoritos = $empresa->candidatos()
            ->with(['pessoa', 'informacoesProfissionais', 'preferenciasDeTrabalho', 'dadosAcademicos'])
            ->get();

        return response()->json($favoritos);
    }

    public function favoritar(Request $request, int $matricula): JsonResponse
    {
        $empresa = $this->empresaAutenticada($request);

        $empresa->candidatos()->syncWithoutDetaching([$matricula]);

        return response()->json(['message' => 'Candidato favoritado com sucesso.'], 201);
    }

    public function desfavoritar(Request $request, int $matricula): JsonResponse
    {
        $empresa = $this->empresaAutenticada($request);

        $empresa->candidatos()->detach($matricula);

        return response()->json(['message' => 'Candidato removido dos favoritos.']);
    }
}
