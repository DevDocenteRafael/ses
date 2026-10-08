<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidato;
use App\Models\Contratacao;
use App\Services\Candidatos\CandidatoStatusService;
use App\Models\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class ContratacaoController extends Controller
{
    public function __construct(private readonly CandidatoStatusService $statusService) {}

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

        $query = Contratacao::query()->vigentes()->with([
            'empresa.pessoa:id_pessoa,nome,email,telefone',
            'candidato.pessoa:id_pessoa,nome,email,telefone,endereco_cep,endereco_logradouro,endereco_numero,endereco_complemento,endereco_bairro,endereco_cidade,endereco_uf',
            'candidato.linkExterno',
            'candidato.informacoesProfissionais',
            'candidato.preferenciasDeTrabalho',
            'candidato.regioesPreferidasTrabalho',
            'candidato.dadosAcademicos',
            'candidato.cursosSenac',
            'candidato.cursosExternos',
            'candidato.experienciasProfissionais',
            'registradoPor:id_pessoa,nome',
            'canceladoPor:id_pessoa,nome',
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
            $filtrarCurso = fn ($curso) => $curso->where('curso', 'like', '%' . trim($validated['curso']) . '%');
            $query->whereHas('candidato.dadosAcademicos', $filtrarCurso)
                ->with(['candidato.dadosAcademicos' => $filtrarCurso]);
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

        if ($solicitante->tipo() === 'empresa' && ! $this->statusService->estaDisponivel($candidato)) {
            abort(403, 'Não é possível registrar contratação para um candidato inativo.');
        }

        try {
            $contratacao = DB::transaction(function () use ($matricula, $candidato, $empresa, $solicitante, $validated) {
                $candidatoBloqueado = Candidato::query()->lockForUpdate()->findOrFail($matricula);

                if ($candidatoBloqueado->contratacao()->exists()) {
                    abort(409, 'Este candidato já possui uma contratação ativa.');
                }

                return Contratacao::query()->create([
                    'candidato_matricula' => $candidato->matricula,
                    'empresa_cnpj' => $empresa->cnpj,
                    'registrado_por_pessoa_id' => $solicitante->id_pessoa,
                    'origem' => $solicitante->tipo(),
                    'contratado_em' => $validated['contratado_em'] ?? today(),
                    'status' => Contratacao::STATUS_VIGENTE,
                    'historico_alteracoes' => [[
                        'acao' => 'registrada',
                        'em' => now()->toIso8601String(),
                        'por_pessoa_id' => $solicitante->id_pessoa,
                        'origem' => $solicitante->tipo(),
                    ]],
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'Este candidato já possui uma contratação ativa.');
        }

        return response()->json($contratacao->load([
            'empresa.pessoa:id_pessoa,nome,email,telefone',
            'candidato.pessoa:id_pessoa,nome,email,telefone',
            'candidato.dadosAcademicos',
            'registradoPor:id_pessoa,nome',
        ]), 201);
    }

    public function cancelar(Request $request, Contratacao $contratacao): JsonResponse
    {
        $solicitante = $this->pessoaAutenticada($request);

        if (! $solicitante || $solicitante->tipo() !== 'administrativo') {
            abort(403, 'Apenas administradores podem cancelar contratações.');
        }

        $motivos = [
            'Contratação registrada por engano',
            'Empresa selecionada incorretamente',
            'Candidato selecionado incorretamente',
            'Outro motivo',
        ];

        $validated = $request->validate([
            'motivo_cancelamento' => ['required', 'string', Rule::in($motivos)],
            'observacao_cancelamento' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['motivo_cancelamento'] === 'Outro motivo' && trim((string) ($validated['observacao_cancelamento'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'observacao_cancelamento' => 'Informe uma justificativa para outro motivo.',
            ]);
        }

        $contratacaoCancelada = DB::transaction(function () use ($contratacao, $solicitante, $validated) {
            $bloqueada = Contratacao::query()
                ->whereKey($contratacao->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($bloqueada->status === Contratacao::STATUS_CANCELADA) {
                return $bloqueada;
            }

            $historico = $bloqueada->historico_alteracoes ?? [];
            $historico[] = [
                'acao' => 'cancelada',
                'em' => now()->toIso8601String(),
                'por_pessoa_id' => $solicitante->id_pessoa,
                'motivo' => $validated['motivo_cancelamento'],
                'observacao' => $validated['observacao_cancelamento'] ?? null,
            ];

            $bloqueada->forceFill([
                'status' => Contratacao::STATUS_CANCELADA,
                'cancelado_em' => now(),
                'cancelado_por_pessoa_id' => $solicitante->id_pessoa,
                'motivo_cancelamento' => $validated['motivo_cancelamento'],
                'observacao_cancelamento' => $validated['observacao_cancelamento'] ?? null,
                'historico_alteracoes' => $historico,
            ])->save();

            return $bloqueada;
        });

        return response()->json([
            'message' => $contratacaoCancelada->wasChanged('status') ? 'Contratação cancelada com sucesso.' : 'Esta contratação já estava cancelada.',
            'contratacao' => $contratacaoCancelada->load([
                'empresa.pessoa:id_pessoa,nome,email,telefone',
                'candidato.pessoa:id_pessoa,nome,email,telefone',
                'registradoPor:id_pessoa,nome',
                'canceladoPor:id_pessoa,nome',
            ]),
            'candidato_contratado' => $contratacaoCancelada->candidato?->contratacao()->exists() ?? false,
        ]);
    }
}
