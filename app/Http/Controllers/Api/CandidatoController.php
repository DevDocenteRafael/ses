<?php

namespace App\Http\Controllers;

use App\Models\Candidato;
use App\Models\Pessoa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class CandidatoController extends Controller
{
    /**
     * Lista todos os candidatos.
     */
    public function index(): JsonResponse
    {
        $candidatos = Candidato::with([
            'pessoa',
            'linkExterno',
            'informacoesProfissionais',
            'preferenciasDeTrabalho',
            'dadosAcademicos',
        ])->get();

        return response()->json($candidatos);
    }

    /**
     * Cria um novo candidato (junto com pessoa).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'matricula'            => 'required|integer|unique:candidato,matricula',
            'cpf'                  => 'required|string|max:14|unique:candidato,cpf',
            'status'               => 'required|boolean',
            // Dados da Pessoa
            'nome'                 => 'required|string|max:100',
            'email'                => 'required|email|unique:pessoa,email',
            'telefone'             => 'required|string|max:11|unique:pessoa,telefone',
            'senha'                => 'required|string|min:6',
        ]);

        DB::beginTransaction();
        try {
            $pessoa = Pessoa::create([
                'id_pessoa'      => $validated['matricula'],
                'nome'           => $validated['nome'],
                'email'          => $validated['email'],
                'telefone'       => $validated['telefone'],
                'senha'          => Hash::make($validated['senha']),
                'data_cadastro'  => now(),
            ]);

            $candidato = Candidato::create([
                'matricula'       => $validated['matricula'],
                'cpf'             => $validated['cpf'],
                'status'          => $validated['status'],
                'pessoa_id_pessoa' => $pessoa->id_pessoa,
            ]);

            DB::commit();
            return response()->json($candidato->load('pessoa'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Exibe um candidato específico.
     */
    public function show(int $matricula): JsonResponse
    {
        $candidato = Candidato::with([
            'pessoa',
            'linkExterno',
            'informacoesProfissionais',
            'preferenciasDeTrabalho',
            'dadosAcademicos',
            'convites.vaga',
        ])->findOrFail($matricula);

        return response()->json($candidato);
    }

    /**
     * Atualiza dados do candidato.
     */
    public function update(Request $request, int $matricula): JsonResponse
    {
        $candidato = Candidato::findOrFail($matricula);

        $validated = $request->validate([
            'status'   => 'sometimes|boolean',
            'nome'     => 'sometimes|string|max:100',
            'email'    => 'sometimes|email|unique:pessoa,email,' . $candidato->pessoa_id_pessoa . ',id_pessoa',
            'telefone' => 'sometimes|string|max:11|unique:pessoa,telefone,' . $candidato->pessoa_id_pessoa . ',id_pessoa',
        ]);

        DB::beginTransaction();
        try {
            if (isset($validated['status'])) {
                $candidato->update(['status' => $validated['status']]);
            }

            $pessoaData = array_filter([
                'nome'     => $validated['nome'] ?? null,
                'email'    => $validated['email'] ?? null,
                'telefone' => $validated['telefone'] ?? null,
            ]);

            if (!empty($pessoaData)) {
                $candidato->pessoa->update($pessoaData);
            }

            DB::commit();
            return response()->json($candidato->load('pessoa'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove um candidato.
     */
    public function destroy(int $matricula): JsonResponse
    {
        $candidato = Candidato::findOrFail($matricula);
        $candidato->delete();

        return response()->json(['message' => 'Candidato removido com sucesso.']);
    }
}