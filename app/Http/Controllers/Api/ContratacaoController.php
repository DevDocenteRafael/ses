<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidato;
use App\Models\Contratacao;
use App\Models\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContratacaoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->garantirAdministrativo($request);

        $validated = $request->validate([
            'empresa' => ['nullable', 'string', 'max:100'],
            'nome' => ['nullable', 'string', 'max:100'],
            'cpf' => ['nullable', 'string', 'max:14'],
            'curso' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $query = Contratacao::query()->with([
            'empresa.pessoa:id_pessoa,nome,email,telefone',
            'candidato.pessoa:id_pessoa,nome,email,telefone',
            'candidato.dadosAcademicos',
            'registradoPor:id_pessoa,nome',
        ]);

        if (! empty($validated['empresa'])) {
            $query->whereHas('empresa', fn ($empresa) => $empresa->where('razao_social', 'like', '%' . $validated['empresa'] . '%'));
        }

        if (! empty($validated['nome'])) {
            $query->whereHas('candidato.pessoa', fn ($pessoa) => $pessoa->where('nome', 'like', '%' . $validated['nome'] . '%'));
        }

        if (! empty($validated['cpf'])) {
            $cpf = preg_replace('/\D+/', '', $validated['cpf']) ?? '';
            $query->whereHas('candidato', fn ($candidato) => $candidato->where('cpf', 'like', '%' . $cpf . '%'));
        }

        if (! empty($validated['curso'])) {
            $query->whereHas('candidato.dadosAcademicos', fn ($curso) => $curso->where('curso', 'like', '%' . $validated['curso'] . '%'));
        }

        return response()->json($query->latest('contratado_em')->paginate((int) ($validated['per_page'] ?? 10)));
    }

    public function store(Request $request, string $matricula): JsonResponse
    {
        $solicitante = $this->pessoaAutenticada($request);

        if (! $solicitante || ! in_array($solicitante->tipo(), ['administrativo', 'empresa'], true)) {
            abort(403, 'Apenas empresas e administradores podem registrar contratações.');
        }

        $validated = $request->validate([
            'empresa_cnpj' => [Rule::requiredIf($solicitante->tipo() === 'administrativo'), 'nullable', 'string', 'exists:empresa,cnpj'],
            'contratado_em' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $empresa = $solicitante->tipo() === 'empresa'
            ? $this->empresaAutenticada($request)
            : Empresa::query()->findOrFail($validated['empresa_cnpj']);

        $candidato = Candidato::query()->findOrFail($matricula);

        if ($solicitante->tipo() === 'empresa' && ! $candidato->status) {
            abort(403, 'Não é possível registrar contratação para um candidato inativo.');
        }

        $contratacao = DB::transaction(function () use ($matricula, $candidato, $empresa, $solicitante, $validated) {
            $candidatoBloqueado = Candidato::query()->lockForUpdate()->findOrFail($matricula);

            if ($candidatoBloqueado->contratacao()->exists()) {
                abort(409, 'Este candidato já possui uma contratação registrada.');
            }

            return Contratacao::query()->create([
                'candidato_matricula' => $candidato->matricula,
                'empresa_cnpj' => $empresa->cnpj,
                'registrado_por_pessoa_id' => $solicitante->id_pessoa,
                'origem' => $solicitante->tipo(),
                'contratado_em' => $validated['contratado_em'] ?? today(),
            ]);
        });

        return response()->json($contratacao->load([
            'empresa.pessoa:id_pessoa,nome,email,telefone',
            'candidato.pessoa:id_pessoa,nome,email,telefone',
            'candidato.dadosAcademicos',
            'registradoPor:id_pessoa,nome',
        ]), 201);
    }
}
