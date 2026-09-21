<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuscaTalento;
use App\Models\Candidato;
use App\Models\Convite;
use App\Models\CursoSenac;
use App\Models\DadosAcademicos;
use App\Models\InformacoesProfissionais;
use App\Models\Pessoa;
use App\Models\VisualizacaoPerfil;
use App\Support\CatalogoAcademicoSenacDf;
use App\Support\HabilidadesCatalogo;
use App\Support\RegioesAdministrativasDf;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

            Log::error('Falha ao cadastrar candidato.', [
                'exception' => $e,
                'matricula' => $validated['matricula'] ?? null,
                'email' => $validated['email'] ?? null,
            ]);

            return response()->json([
                'error' => 'Nao foi possivel cadastrar o candidato.',
            ], 500);
        }
    }

    /**
     * Lista candidatos. Uso principal: busca de talentos pela empresa —
     * por isso os filtros (FR16/17/18 + segmento/tipo de curso) são
     * aplicados aqui no servidor, e não no cliente. O filtro "segmento"
     * representa a classificação acadêmica/curricular em dados_academicos,
     * não a área de atuação profissional do candidato.
     */
    public function index(Request $request): JsonResponse
    {
        $solicitante = $this->pessoaAutenticada($request);

        if (! $solicitante || ! in_array($solicitante->tipo(), ['administrativo', 'empresa'], true)) {
            abort(403, 'Voce nao tem permissao para listar candidatos.');
        }

        $query = Candidato::query()
            ->with([
                'pessoa',
                'linkExterno',
                'informacoesProfissionais',
                'preferenciasDeTrabalho',
                'regioesPreferidasTrabalho',
                'dadosAcademicos',
            ]);

        // Empresas só devem ver candidatos com acesso liberado (FR16-20).
        // O administrativo precisa ver todos, inclusive os bloqueados, para
        // poder geri-los na tela "Gestão dos Candidatos" (FR37).
        if ($solicitante->tipo() === 'empresa') {
            $query->where('status', true);
        }

        if ($request->filled('busca')) {
            $termo = trim((string) $request->query('busca'));
            $termoNumerico = preg_replace('/\D+/', '', $termo) ?? '';

            $query->where(function ($q) use ($termo, $termoNumerico) {
                $q->whereHas('pessoa', function ($pessoa) use ($termo) {
                    $pessoa->where('nome', 'like', '%' . $termo . '%');
                });

                if ($termoNumerico !== '') {
                    $q->orWhere('cpf', 'like', '%' . $termoNumerico . '%');
                } else {
                    $q->orWhere('cpf', 'like', '%' . $termo . '%');
                }
            });
        }

        if ($request->filled('status')) {
            $request->validate([
                'status' => ['boolean'],
            ]);

            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('unidade')) {
            $request->validate([
                'unidade' => ['string', 'max:100'],
            ]);

            $unidade = trim((string) $request->query('unidade'));

            $query->where(function ($q) use ($unidade) {
                $q->whereHas('dadosAcademicos', function ($academico) use ($unidade) {
                    $academico->where('unidade', $unidade);
                })->orWhereHas('cursosSenac', function ($cursoSenac) use ($unidade) {
                    $cursoSenac->where('unidade', $unidade);
                });
            });
        }

        $tipoCurso = $request->filled('tipo_curso') ? trim((string) $request->query('tipo_curso')) : null;
        $segmento = $request->filled('segmento') ? trim((string) $request->query('segmento')) : null;

        if ($tipoCurso !== null && ! CatalogoAcademicoSenacDf::tipoExiste($tipoCurso)) {
            return response()->json(['message' => 'Tipo de curso inválido.'], 422);
        }

        if ($segmento !== null) {
            if (! CatalogoAcademicoSenacDf::segmentoExiste($segmento)) {
                return response()->json(['message' => 'Segmento inválido.'], 422);
            }

            if ($tipoCurso === null || ! CatalogoAcademicoSenacDf::segmentoPertenceAoTipo($segmento, $tipoCurso)) {
                return response()->json(['message' => 'Segmento não pertence ao tipo de curso informado.'], 422);
            }
        }

        if ($tipoCurso !== null || $segmento !== null) {
            $query->whereHas('dadosAcademicos', function ($q) use ($tipoCurso, $segmento) {
                if ($tipoCurso !== null) {
                    $q->whereIn('tipo_curso', CatalogoAcademicoSenacDf::valoresLegadosTipo($tipoCurso));
                }

                if ($segmento !== null) {
                    $q->whereIn('segmento', CatalogoAcademicoSenacDf::valoresLegadosSegmento($segmento));
                }
            });
        }

        if ($request->filled('disponibilidade')) {
            $query->whereHas('preferenciasDeTrabalho', function ($q) use ($request) {
                $q->whereJsonContains('disponibilidade_de_horario', $request->query('disponibilidade'));
            });
        }

        // Bitmask permitido: CLT=1, Estágio=2. Valores com o bit 4 são inválidos.
        if ($request->filled('tipo_contratacao')) {
            $request->validate([
                'tipo_contratacao' => ['integer', 'in:1,2,3'],
            ], [
                'tipo_contratacao.in' => 'O tipo de contratação informado não é permitido. Jovem Aprendiz não é mais uma opção válida.',
            ]);

            $mascara = (int) $request->query('tipo_contratacao');
            $query->whereHas('preferenciasDeTrabalho', function ($q) use ($mascara) {
                $q->whereRaw('(tipo_de_contratacao & ?) != 0', [$mascara]);
            });
        }

        if ($request->filled('regioes_administrativas')) {
            $request->validate([
                'regioes_administrativas' => ['array'],
                'regioes_administrativas.*' => ['integer', Rule::in(RegioesAdministrativasDf::codigos())],
            ]);

            $codigosRegioes = array_values(array_unique(array_map('intval', (array) $request->query('regioes_administrativas'))));
            $nomesRegioes = array_filter(array_map(fn (int $codigo) => RegioesAdministrativasDf::nome($codigo), $codigosRegioes));

            $query->where(function ($q) use ($codigosRegioes, $nomesRegioes) {
                $q->whereHas('preferenciasDeTrabalho', function ($preferencias) {
                    $preferencias->where('aceita_todas_regioes', true);
                })
                    ->orWhereHas('regioesPreferidasTrabalho', function ($regioes) use ($codigosRegioes) {
                        $regioes->whereIn('codigo_regiao', $codigosRegioes);
                    })
                    ->orWhereHas('preferenciasDeTrabalho', function ($preferencias) use ($nomesRegioes) {
                        $preferencias->whereIn('regiao_administrativa', $nomesRegioes);
                    });
            });
        }

        if ($request->filled('habilidades')) {
            $habilidades = array_filter((array) $request->query('habilidades'));
            foreach ($habilidades as $habilidade) {
                $this->aplicarFiltroHabilidadeNaAreaAtiva($query, $habilidade);
            }
        }

        $this->registrarBuscaDeTalentos($request, $solicitante);

        if ($solicitante->tipo() === 'empresa') {
            $perPage = (int) $request->query('per_page', 10);
            $perPage = min(max($perPage, 1), 10);

            return response()->json(
                $query->orderBy('matricula')->paginate($perPage)
            );
        }

        return response()->json($query->orderBy('matricula')->get());
    }

    public function tiposCurso(Request $request): JsonResponse
    {
        $solicitante = $this->pessoaAutenticada($request);

        if (! $solicitante || ! in_array($solicitante->tipo(), ['administrativo', 'empresa'], true)) {
            abort(403, 'Voce nao tem permissao para listar tipos de curso.');
        }

        return response()->json(CatalogoAcademicoSenacDf::tipos());
    }

    public function segmentosAcademicos(Request $request): JsonResponse
    {
        $solicitante = $this->pessoaAutenticada($request);

        if (! $solicitante || ! in_array($solicitante->tipo(), ['administrativo', 'empresa'], true)) {
            abort(403, 'Voce nao tem permissao para listar segmentos acadêmicos.');
        }

        $request->validate([
            'tipo_curso' => ['required', 'string', Rule::in(array_column(CatalogoAcademicoSenacDf::tipos(), 'id'))],
        ]);

        return response()->json(CatalogoAcademicoSenacDf::segmentos((string) $request->query('tipo_curso')));
    }

    /**
     * Lista unidades reais vinculadas aos candidatos, obtidas dos registros
     * acadêmicos e dos cursos Senac já persistidos, sem catálogo hardcoded.
     */
    public function unidades(Request $request): JsonResponse
    {
        $solicitante = $this->pessoaAutenticada($request);

        if (! $solicitante || ! in_array($solicitante->tipo(), ['administrativo', 'empresa'], true)) {
            abort(403, 'Voce nao tem permissao para listar unidades de candidatos.');
        }

        $unidadesAcademicas = DadosAcademicos::query()
            ->whereNotNull('unidade')
            ->where('unidade', '<>', '')
            ->when($solicitante->tipo() === 'empresa', function ($query) {
                $query->whereHas('candidato', fn ($candidato) => $candidato->where('status', true));
            })
            ->pluck('unidade');

        $unidadesCursosSenac = CursoSenac::query()
            ->whereNotNull('unidade')
            ->where('unidade', '<>', '')
            ->when($solicitante->tipo() === 'empresa', function ($query) {
                $query->whereHas('candidato', fn ($candidato) => $candidato->where('status', true));
            })
            ->pluck('unidade');

        $unidades = $unidadesAcademicas
            ->merge($unidadesCursosSenac)
            ->map(fn ($unidade) => trim((string) $unidade))
            ->filter()
            ->unique()
            ->sort(fn ($a, $b) => strcasecmp($a, $b))
            ->values();

        return response()->json($unidades);
    }

    /**
     * Lista apenas os nomes únicos de habilidades persistidas nos perfis dos
     * candidatos, sem expor dados pessoais. Empresas recebem habilidades de
     * candidatos liberados, coerente com a busca de talentos.
     */
    public function habilidades(Request $request): JsonResponse
    {
        $solicitante = $this->pessoaAutenticada($request);

        if (! $solicitante || ! in_array($solicitante->tipo(), ['administrativo', 'empresa'], true)) {
            abort(403, 'Voce nao tem permissao para listar habilidades de candidatos.');
        }

        $query = InformacoesProfissionais::query()
            ->whereNotNull('habilidades')
            ->select('habilidades', 'candidato_matricula');

        if ($solicitante->tipo() === 'empresa') {
            $query->whereHas('candidato', function ($q) {
                $q->where('status', true);
            });
        }

        $habilidades = HabilidadesCatalogo::padrao();

        $query->get()->each(function (InformacoesProfissionais $info) use (&$habilidades) {
            foreach ($this->habilidadesDaInfoProfissional($info) as $habilidade) {
                $habilidades[] = $habilidade;
            }
        });

        $habilidades = HabilidadesCatalogo::ordenar(HabilidadesCatalogo::deduplicar($habilidades));

        return response()->json($habilidades);
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
            'regioes_administrativas' => $request->query('regioes_administrativas'),
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
        $solicitante = $this->pessoaAutenticada($request);

        if (! $solicitante || ! in_array($solicitante->tipo(), ['administrativo', 'empresa', 'candidato'], true)) {
            abort(403, 'Voce nao tem permissao para visualizar este candidato.');
        }

        $candidato = Candidato::with([
            'pessoa',
            'linkExterno',
            'informacoesProfissionais',
            'preferenciasDeTrabalho',
            'regioesPreferidasTrabalho',
            'dadosAcademicos',
            'cursosSenac',
            'cursosExternos',
            'experienciasProfissionais',
            'convites.vaga',
            'empresas',
        ])->findOrFail($matricula);

        if ($solicitante->tipo() === 'candidato') {
            $this->garantirCandidatoDono($request, $matricula);
        }

        if ($solicitante->tipo() === 'empresa' && ! $candidato->status) {
            abort(403, 'Voce nao tem permissao para visualizar este candidato.');
        }

        if ($solicitante && $solicitante->tipo() === 'empresa' && $solicitante->empresa) {
            VisualizacaoPerfil::create([
                'candidato_matricula' => $matricula,
                'empresa_cnpj'        => $solicitante->empresa->cnpj,
                'visualizado_em'      => now(),
            ]);
        }

        return response()->json($this->formatarCandidato($candidato));
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

            Log::error('Falha ao atualizar candidato.', [
                'exception' => $e,
                'matricula' => $matricula,
            ]);

            return response()->json(['error' => 'Nao foi possivel atualizar o candidato.'], 500);
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
            ! empty($this->habilidadesDaInfoProfissional($candidato->informacoesProfissionais)),
            (bool) $candidato->preferenciasDeTrabalho,
        ];

        $preenchidos = count(array_filter($itens));

        return (int) round(($preenchidos / count($itens)) * 100);
    }

    private function formatarCandidato(Candidato $candidato): Candidato
    {
        $candidato->setRelation(
            'regioesPreferidasTrabalho',
            $candidato->regioesPreferidasTrabalho
                ->sortBy('codigo_regiao')
                ->values()
                ->map(fn ($regiao) => [
                    'codigo' => (int) $regiao->codigo_regiao,
                    'nome' => RegioesAdministrativasDf::nome((int) $regiao->codigo_regiao),
                ])
        );

        return $candidato;
    }

    private function habilidadesDaInfoProfissional(?InformacoesProfissionais $info): array
    {
        if (! $info) {
            return [];
        }

        $habilidadesPorArea = (array) ($info->habilidades_por_area ?? []);

        if (! empty($habilidadesPorArea)) {
            return array_values(array_unique(array_merge(...array_map(
                fn ($habilidades) => (array) $habilidades,
                array_values($habilidadesPorArea)
            ))));
        }

        return (array) ($info->habilidades ?? []);
    }

    private function aplicarFiltroHabilidadeNaAreaAtiva($query, string $habilidade): void
    {
        $query->whereHas('informacoesProfissionais', function ($q) use ($habilidade) {
            $q->where('habilidades', 'like', '%' . $habilidade . '%')
                ->where(function ($ativo) use ($habilidade) {
                    $ativo->whereNull('habilidades_por_area')
                        ->orWhere('habilidades_por_area', '')
                        ->orWhere('habilidades_por_area', '[]')
                        ->orWhere('habilidades_por_area', '{}')
                        ->orWhere(function ($json) use ($habilidade) {
                            foreach (HabilidadesCatalogo::areas() as $area) {
                                $json->orWhere(function ($areaAtiva) use ($area, $habilidade) {
                                    $areaAtiva->where('area_de_atuacao', $area)
                                        ->whereJsonContains('habilidades_por_area->' . $area, $habilidade);
                                });
                            }
                        });
                });
        });
    }
}
