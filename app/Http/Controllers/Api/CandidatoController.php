<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuscaTalento;
use App\Models\Candidato;
use App\Models\Convite;
use App\Models\DadosAcademicos;
use App\Models\Pessoa;
use App\Models\VisualizacaoPerfil;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CandidatoController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $solicitante = $request->attributes->get('pessoa_autenticada');

        if ($solicitante && $solicitante->tipo() !== 'administrativo') {
            abort(403, 'Apenas o administrativo pode cadastrar candidatos manualmente.');
        }

        $validated = $request->validate([
            'matricula' => ['required', 'string', 'min:1', 'max:15', 'regex:/^[0-9]+$/', Rule::unique('candidato', 'matricula')],
            'cpf' => ['required', 'string', 'max:14'],
            'status' => ['sometimes', 'boolean'],
            'nome' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:pessoa,email'],
            'telefone' => ['required', 'string', 'max:14'],
            'senha' => ['required', 'string', 'min:6'],
            'curso' => ['nullable', 'string', 'max:45'],
            'unidade' => ['nullable', 'string', 'max:45'],
        ]);

        $request->validate([
            'matricula' => ['required', 'regex:/^[0-9]+$/'],
        ], [
            'matricula.regex' => 'O campo matricula deve conter apenas números.',
            'matricula.max' => 'O campo matricula não pode ser maior que 15 caracteres.',
            'matricula.string' => 'O campo matricula deve ser um texto.',
            'matricula.unique' => 'A matrícula informada já está em uso.',
        ]);

        $cpf = preg_replace('/\D+/', '', $validated['cpf']) ?? $validated['cpf'];
        $telefone = preg_replace('/\D+/', '', $validated['telefone']) ?? $validated['telefone'];

        if (strlen($cpf) !== 11) {
            return response()->json([
                'errors' => ['cpf' => ['O campo cpf deve conter 11 dígitos.']],
            ], 422);
        }

        if (strlen($telefone) !== 11) {
            return response()->json([
                'errors' => ['telefone' => ['O campo telefone deve conter 11 dígitos.']],
            ], 422);
        }

        if (Candidato::query()->where('cpf', $cpf)->exists()) {
            return response()->json([
                'errors' => ['cpf' => ['O cpf informado já está em uso.']],
            ], 422);
        }

        if (Pessoa::query()->where('telefone', $telefone)->exists()) {
            return response()->json([
                'errors' => ['telefone' => ['O telefone informado já está em uso.']],
            ], 422);
        }

        $status = $solicitante && $solicitante->tipo() === 'administrativo'
            ? ($validated['status'] ?? true)
            : true;

        DB::beginTransaction();

        try {
            $pessoa = Pessoa::query()->create([
                'nome' => $validated['nome'],
                'email' => $validated['email'],
                'telefone' => $telefone,
                'senha' => Hash::make($validated['senha']),
                'data_cadastro' => now(),
            ]);

            $candidato = Candidato::query()->create([
                'matricula' => $validated['matricula'],
                'cpf' => $cpf,
                'status' => $status,
                'pessoa_id_pessoa' => $pessoa->id_pessoa,
            ]);

            if (! empty($validated['curso']) || ! empty($validated['unidade'])) {
                DadosAcademicos::query()->create([
                    'instituicao' => 'Senac DF',
                    'curso' => $validated['curso'] ?? 'Não informado',
                    'unidade' => $validated['unidade'] ?? 'Não informado',
                    'ano_de_conclusao' => now()->toDateString(),
                    'candidato_matricula' => $candidato->matricula,
                ]);
            }

            DB::commit();

            return response()->json($candidato->load(['pessoa', 'dadosAcademicos']), 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Nao foi possivel cadastrar o candidato.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lista candidatos. Uso principal: busca de talentos pela empresa —
     * por isso os filtros (FR16/17/18 + segmento/tipo de curso) são
     * aplicados aqui no servidor, e não no cliente.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Candidato::query()
            ->with([
                'pessoa',
                'linkExterno',
                'informacoesProfissionais',
                'preferenciasDeTrabalho',
                'dadosAcademicos',
            ]);

        // Empresas só devem ver candidatos com acesso liberado (FR16-20).
        // O administrativo precisa ver todos, inclusive os bloqueados, para
        // poder geri-los na tela "Gestão dos Candidatos" (FR37).
        $solicitante = $this->pessoaAutenticada($request);
        if (! $solicitante || $solicitante->tipo() !== 'administrativo') {
            $query->where('status', true);
        }

        if ($request->filled('segmento')) {
            $query->whereHas('dadosAcademicos', function ($q) use ($request) {
                $q->where('segmento', $request->query('segmento'));
            });
        }

        if ($request->filled('tipo_curso')) {
            $query->whereHas('dadosAcademicos', function ($q) use ($request) {
                $q->where('tipo_curso', $request->query('tipo_curso'));
            });
        }

        if ($request->filled('disponibilidade')) {
            $query->whereHas('preferenciasDeTrabalho', function ($q) use ($request) {
                $q->where('disponibilidade_de_horario', $request->query('disponibilidade'));
            });
        }

        // Bitmask: CLT=1, Estagio=2, Jovem Aprendiz=4 (ver PreferenciasDeTrabalho).
        if ($request->filled('tipo_contratacao')) {
            $mascara = (int) $request->query('tipo_contratacao');
            $query->whereHas('preferenciasDeTrabalho', function ($q) use ($mascara) {
                $q->whereRaw('(tipo_de_contratacao & ?) != 0', [$mascara]);
            });
        }

        if ($request->filled('habilidades')) {
            $habilidades = array_filter((array) $request->query('habilidades'));
            foreach ($habilidades as $habilidade) {
                $query->whereHas('informacoesProfissionais', function ($q) use ($habilidade) {
                    $q->where('habilidades', 'like', '%' . $habilidade . '%');
                });
            }
        }

        $this->registrarBuscaDeTalentos($request, $solicitante);

        return response()->json($query->get());
    }

    /**
     * Loga os filtros usados por uma empresa ao buscar talentos, para
     * alimentar "Filtros Mais Acessados" e "Buscas Realizadas" no
     * relatório administrativo (FR38). Silencioso para quem não é empresa
     * ou não aplicou nenhum filtro (evita logar toda listagem genérica).
     */
    private function registrarBuscaDeTalentos(Request $request, ?Pessoa $solicitante): void
    {
        if (! $solicitante || $solicitante->tipo() !== 'empresa') {
            return;
        }

        $filtros = array_filter([
            'segmento'         => $request->query('segmento'),
            'tipo_curso'       => $request->query('tipo_curso'),
            'disponibilidade'  => $request->query('disponibilidade'),
            'tipo_contratacao' => $request->query('tipo_contratacao'),
            'habilidades'      => $request->query('habilidades'),
        ]);

        if (! $filtros) {
            return;
        }

        BuscaTalento::create([
            'empresa_cnpj' => $solicitante->empresa?->cnpj,
            'filtros'      => $filtros,
            'buscado_em'   => now(),
        ]);
    }

    /**
     * Exibe um candidato específico. Se quem pede for uma empresa (não o
     * próprio candidato), registra uma visualização de perfil (FR9).
     */
    public function show(Request $request, string $matricula): JsonResponse
    {
        $candidato = Candidato::with([
            'pessoa',
            'linkExterno',
            'informacoesProfissionais',
            'preferenciasDeTrabalho',
            'dadosAcademicos',
            'cursosSenac',
            'cursosExternos',
            'experienciasProfissionais',
            'convites.vaga',
            'empresas',
        ])->findOrFail($matricula);

        $solicitante = $this->pessoaAutenticada($request);

        if ($solicitante && $solicitante->tipo() === 'empresa' && $solicitante->empresa) {
            VisualizacaoPerfil::create([
                'candidato_matricula' => $matricula,
                'empresa_cnpj'        => $solicitante->empresa->cnpj,
                'visualizado_em'      => now(),
            ]);
        }

        return response()->json($candidato);
    }

    /**
     * Atualiza dados do candidato (somente o próprio candidato ou administrativo).
     */
    public function update(Request $request, string $matricula): JsonResponse
    {
        $solicitante = $this->pessoaAutenticada($request);
        if (! $solicitante || ! in_array($solicitante->tipo(), ['candidato', 'administrativo'], true)) {
            abort(403, 'Voce nao tem permissao para atualizar este candidato.');
        }
        if ($solicitante->tipo() === 'candidato') {
            $this->garantirCandidatoDono($request, $matricula);
        }

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
    public function destroy(Request $request, string $matricula): JsonResponse
    {
        $solicitante = $this->pessoaAutenticada($request);
        if (! $solicitante || $solicitante->tipo() !== 'administrativo') {
            abort(403, 'Apenas o administrativo pode remover candidatos.');
        }

        $candidato = Candidato::findOrFail($matricula);
        $candidato->delete();

        return response()->json(['message' => 'Candidato removido com sucesso.']);
    }

    /**
     * Indicadores do painel do aluno (FR9): visualizações, convites
     * pendentes, completude do perfil e últimas visualizações.
     */
    public function dashboard(Request $request, string $matricula): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        $candidato = Candidato::with([
            'linkExterno',
            'informacoesProfissionais',
            'preferenciasDeTrabalho',
            'dadosAcademicos',
        ])->findOrFail($matricula);

        $convitesPendentes = Convite::where('candidatos_matricula', $matricula)
            ->where('status', Convite::STATUS_PENDENTE)
            ->count();

        $visualizacoes = VisualizacaoPerfil::where('candidato_matricula', $matricula);

        $ultimasVisualizacoes = (clone $visualizacoes)
            ->with('empresa')
            ->latest('visualizado_em')
            ->take(5)
            ->get()
            ->map(fn ($v) => [
                'empresa' => $v->empresa->razao_social ?? 'Empresa',
                'tempo'   => $v->visualizado_em->diffForHumans(),
            ]);

        return response()->json([
            'visualizacoes'        => $visualizacoes->count(),
            'convitesPendentes'    => $convitesPendentes,
            'perfilCompleto'       => $this->calcularPerfilCompleto($candidato),
            'ultimasVisualizacoes' => $ultimasVisualizacoes,
        ]);
    }

    private function calcularPerfilCompleto(Candidato $candidato): int
    {
        $itens = [
            (bool) $candidato->dadosAcademicos()->exists(),
            (bool) $candidato->linkExterno?->linkedin,
            (bool) $candidato->linkExterno?->portfolio,
            (bool) $candidato->linkExterno?->github,
            (bool) $candidato->informacoesProfissionais?->sobre_mim,
            (bool) $candidato->informacoesProfissionais?->cargo_de_interesse,
            ! empty($candidato->informacoesProfissionais?->habilidades),
            (bool) $candidato->preferenciasDeTrabalho,
        ];

        $preenchidos = count(array_filter($itens));

        return (int) round(($preenchidos / count($itens)) * 100);
    }
}
