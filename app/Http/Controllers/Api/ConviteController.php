<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConviteRequest;
use App\Http\Requests\UpdateConviteRequest;
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

    public function store(StoreConviteRequest $request): JsonResponse
    {
        $validated = $request->validated();

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
    public function update(UpdateConviteRequest $request, int $id): JsonResponse
    {
        $convite = Convite::findOrFail($id);

        $this->garantirCandidatoDono($request, (int) $convite->candidatos_matricula);

        $validated = $request->validated();

        $convite->update($validated);

        return response()->json($convite->load(['empresa', 'candidato.pessoa', 'vaga']));
    }

    public function destroy(int $id): JsonResponse
    {
        Convite::findOrFail($id)->delete();

        return response()->json(['message' => 'Convite removido com sucesso.']);
    }
}
