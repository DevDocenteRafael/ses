<?php

namespace App\Http\Controllers;

use App\Models\Administrativo;
use App\Models\AlunoMigrado;
use App\Models\EngajamentoPorUnidadeSenac;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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