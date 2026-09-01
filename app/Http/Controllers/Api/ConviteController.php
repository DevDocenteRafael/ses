<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Convite;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ConviteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Convite::with(['empresa', 'candidato.pessoa', 'vaga']);

        if ($request->has('candidatos_matricula')) {
            $query->where('candidatos_matricula', $request->query('candidatos_matricula'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->latest('data_envio')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $empresa = $this->empresaAutenticada($request);

        $validated = $request->validate([
            'descricao'            => 'required|string|max:150',
            'candidatos_matricula' => 'required|integer|exists:candidato,matricula',
            'vagas_id_vaga'        => 'required|integer|exists:vagas,id_vaga',
        ]);

        $validated['empresa_cnpj'] = $empresa->cnpj;

        $vagaPertenceEmpresa = \App\Models\Vaga::query()
            ->where('id_vaga', $validated['vagas_id_vaga'])
            ->where('empresa_cnpj', $empresa->cnpj)
            ->exists();

        if (! $vagaPertenceEmpresa) {
            abort(403, 'Voce nao tem permissao para criar convite para esta vaga.');
        }

        $validated['status'] = Convite::STATUS_PENDENTE;
        $validated['data_envio'] = now();

        $convite = Convite::create($validated);

        return response()->json($convite->load(['empresa', 'candidato.pessoa', 'vaga']), 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(
            Convite::with(['empresa', 'candidato.pessoa', 'vaga'])->findOrFail($id)
        );
    }

    /**
     * Atualiza o status do convite (aceitar/recusar/arquivar). Somente o
     * candidato dono do convite pode alterá-lo.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $convite = Convite::findOrFail($id);

        $this->garantirCandidatoDono($request, (int) $convite->candidatos_matricula);

        $validated = $request->validate([
            'status'    => 'required|integer|in:' . implode(',', [
                Convite::STATUS_PENDENTE,
                Convite::STATUS_ACEITO,
                Convite::STATUS_RECUSADO,
                Convite::STATUS_ARQUIVADO,
            ]),
            'descricao' => 'sometimes|string|max:150',
        ]);

        $convite->update($validated);

        return response()->json($convite->load(['empresa', 'candidato.pessoa', 'vaga']));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $convite = Convite::findOrFail($id);

        $this->garantirConviteDaEmpresa($request, $convite);

        $convite->delete();

        return response()->json(['message' => 'Convite removido com sucesso.']);
    }
}
