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
        $solicitante = $this->pessoaAutenticada($request);

        if (! $solicitante || ! in_array($solicitante->tipo(), ['administrativo', 'empresa', 'candidato'], true)) {
            abort(403, 'Voce nao tem permissao para listar convites.');
        }

        $query = Convite::with(['empresa', 'candidato.pessoa', 'vaga']);

        if ($solicitante->tipo() === 'empresa') {
            $empresa = $this->empresaAutenticada($request);
            $query->where('empresa_cnpj', $empresa->cnpj);
        }

        if ($solicitante->tipo() === 'candidato') {
            $query->where('candidatos_matricula', $solicitante->candidato->matricula);
        }

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
            'candidatos_matricula' => ['required', 'string', 'min:1', 'max:15', 'regex:/^[0-9]+$/', 'exists:candidato,matricula'],
            'vagas_id_vaga'        => 'required|integer|exists:vagas,id_vaga',
        ], [
            'candidatos_matricula.regex' => 'O campo candidatos_matricula deve conter apenas números.',
            'candidatos_matricula.max' => 'O campo candidatos_matricula não pode ser maior que 15 caracteres.',
            'candidatos_matricula.string' => 'O campo candidatos_matricula deve ser um texto.',
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

    public function show(Request $request, int $id): JsonResponse
    {
        $solicitante = $this->pessoaAutenticada($request);

        if (! $solicitante || ! in_array($solicitante->tipo(), ['administrativo', 'empresa', 'candidato'], true)) {
            abort(403, 'Voce nao tem permissao para visualizar este convite.');
        }

        $convite = Convite::with(['empresa', 'candidato.pessoa', 'vaga'])->findOrFail($id);

        if ($solicitante->tipo() === 'empresa') {
            $this->garantirConviteDaEmpresa($request, $convite);
        }

        if ($solicitante->tipo() === 'candidato') {
            $this->garantirCandidatoDono($request, (string) $convite->candidatos_matricula);
        }

        return response()->json($convite);
    }

    /**
     * Atualiza o status do convite (aceitar/recusar/arquivar). Somente o
     * candidato dono do convite pode alterá-lo.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $convite = Convite::findOrFail($id);

        $this->garantirCandidatoDono($request, (string) $convite->candidatos_matricula);

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
