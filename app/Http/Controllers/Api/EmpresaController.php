<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Pessoa;
use App\Models\ResponsavelContratual;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cnpj'                   => 'required|integer|unique:empresa,cnpj',
            'razao_social'           => 'required|string|max:45',
            'atividade_economica'    => 'required|string|max:45',
            // Dados da pessoa da empresa
            'nome'                   => 'required|string|max:100',
            'email'                  => 'required|email|unique:pessoa,email',
            'telefone'               => 'required|string|max:11|unique:pessoa,telefone',
            'senha'                  => 'required|string|min:6',
            // Responsável contratual
            'responsavel_nome'       => 'required|string|max:100',
            'responsavel_email'      => 'required|email|unique:pessoa,email',
            'responsavel_telefone'   => 'required|string|max:11|unique:pessoa,telefone',
            'responsavel_senha'      => 'required|string|min:6',
        ]);

        DB::beginTransaction();
        try {
            // Pessoa da empresa
            $pessoaEmpresa = Pessoa::create([
                'id_pessoa'     => $validated['cnpj'],
                'nome'          => $validated['nome'],
                'email'         => $validated['email'],
                'telefone'      => $validated['telefone'],
                'senha'         => Hash::make($validated['senha']),
                'data_cadastro' => now(),
            ]);

            // Pessoa do responsável
            $pessoaResp = Pessoa::create([
                'id_pessoa'     => $validated['cnpj'] + 1, // ajuste conforme sua regra de negócio
                'nome'          => $validated['responsavel_nome'],
                'email'         => $validated['responsavel_email'],
                'telefone'      => $validated['responsavel_telefone'],
                'senha'         => Hash::make($validated['responsavel_senha']),
                'data_cadastro' => now(),
            ]);

            $responsavel = ResponsavelContratual::create([
                'pessoa_id_pessoa' => $pessoaResp->id_pessoa,
            ]);

            $empresa = Empresa::create([
                'cnpj'                                           => $validated['cnpj'],
                'razao_social'                                   => $validated['razao_social'],
                'atividade_economica'                            => $validated['atividade_economica'],
                'pessoa_id_pessoa'                               => $pessoaEmpresa->id_pessoa,
                'responsavel_contratual_id_responsavel_contratual' => $responsavel->id_responsavel_contratual,
            ]);

            DB::commit();
            return response()->json($empresa->load(['pessoa', 'responsavelContratual.pessoa']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show(int $cnpj): JsonResponse
    {
        $empresa = Empresa::with([
            'pessoa',
            'responsavelContratual.pessoa',
            'vagas',
            'convites',
            'historicoDeEngajamento',
            'candidatos.pessoa',
        ])->findOrFail($cnpj);

        return response()->json($empresa);
    }

    public function update(Request $request, int $cnpj): JsonResponse
    {
        $empresa = Empresa::findOrFail($cnpj);

        $validated = $request->validate([
            'razao_social'        => 'sometimes|string|max:45',
            'atividade_economica' => 'sometimes|string|max:45',
        ]);

        $empresa->update($validated);

        return response()->json($empresa->load('pessoa'));
    }

    public function destroy(int $cnpj): JsonResponse
    {
        $empresa = Empresa::findOrFail($cnpj);
        $empresa->delete();

        return response()->json(['message' => 'Empresa removida com sucesso.']);
    }
}