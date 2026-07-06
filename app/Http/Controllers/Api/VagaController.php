<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vaga;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VagaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Vaga::with('empresa');

        if ($request->has('area')) {
            $query->where('area', $request->area);
        }
        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'titulo'          => 'required|string|max:100',
            'tipo'            => 'required|integer',
            'area'            => 'required|string|max:45',
            'status'          => 'required|boolean',
            'data_publicacao' => 'required|date',
            'empresa_cnpj'    => 'required|integer|exists:empresa,cnpj',
        ]);

        $vaga = Vaga::create($validated);

        return response()->json($vaga->load('empresa'), 201);
    }

    public function show(int $id): JsonResponse
    {
        $vaga = Vaga::with(['empresa', 'convites.candidato.pessoa'])->findOrFail($id);

        return response()->json($vaga);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $vaga = Vaga::findOrFail($id);

        $validated = $request->validate([
            'titulo'  => 'sometimes|string|max:100',
            'tipo'    => 'sometimes|integer',
            'area'    => 'sometimes|string|max:45',
            'status'  => 'sometimes|boolean',
        ]);

        $vaga->update($validated);

        return response()->json($vaga);
    }

    public function destroy(int $id): JsonResponse
    {
        Vaga::findOrFail($id)->delete();

        return response()->json(['message' => 'Vaga removida com sucesso.']);
    }
}
