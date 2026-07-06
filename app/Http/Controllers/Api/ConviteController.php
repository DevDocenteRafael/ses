<?php

namespace App\Http\Controllers;

use App\Models\Convite;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ConviteController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Convite::with(['empresa', 'candidato.pessoa', 'vaga'])->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'descricao'           => 'required|string|max:150',
            'status'              => 'required|boolean',
            'empresa_cnpj'        => 'required|integer|exists:empresa,cnpj',
            'candidatos_matricula' => 'required|integer|exists:candidato,matricula',
            'vagas_id_vaga'       => 'required|integer|exists:vagas,id_vaga',
        ]);

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

    public function update(Request $request, int $id): JsonResponse
    {
        $convite = Convite::findOrFail($id);

        $validated = $request->validate([
            'status'    => 'required|boolean',
            'descricao' => 'sometimes|string|max:150',
        ]);

        $convite->update($validated);

        return response()->json($convite);
    }

    public function destroy(int $id): JsonResponse
    {
        Convite::findOrFail($id)->delete();

        return response()->json(['message' => 'Convite removido com sucesso.']);
    }
}