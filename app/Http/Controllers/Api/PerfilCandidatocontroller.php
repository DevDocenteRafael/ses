<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LinkExterno;
use App\Models\InformacoesProfissionais;
use App\Models\PreferenciasDeTrabalho;
use App\Models\DadosAcademicos;
use App\Models\CursoSenac;
use App\Models\CursoExterno;
use App\Models\ExperienciaProfissional;
use App\Models\RegiaoPreferidaTrabalho;
use App\Models\Candidato;
use App\Services\Candidatos\CandidatoStatusService;
use App\Support\AreasAtuacaoCatalogo;
use App\Support\RegioesAdministrativasDf;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PerfilCandidatoController extends Controller
{
    public function __construct(private readonly CandidatoStatusService $statusService) {}

    // ── Links Externos ───────────────────────────────────────────

    public function storeLink(Request $request, string $matricula): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        $validated = $request->validate([
            'linkedin'  => 'nullable|url|max:100',
            'portfolio' => 'nullable|url|max:100',
            'github'    => 'nullable|url|max:100',
        ]);

        $link = LinkExterno::updateOrCreate(
            ['candidato_matricula' => $matricula],
            $validated
        );

        $this->registrarAtividade($matricula);

        return response()->json($link, 201);
    }

    // ── Informações Profissionais ────────────────────────────────

    public function storeInfoProfissional(Request $request, string $matricula): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        $validated = $request->validate([
            'sobre_mim'          => 'nullable|string|max:200',
            'cargo_de_interesse' => 'nullable|string|max:45',
            'area_de_atuacao'    => ['required', 'string', Rule::in(AreasAtuacaoCatalogo::areas())],
            'habilidades'        => 'nullable|array',
            'habilidades.*'      => 'string|max:45',
            'habilidades_por_area'        => 'nullable|array',
            'habilidades_por_area.*'      => 'array',
            'habilidades_por_area.*.*'    => 'string|max:45',
        ]);

        $habilidadesPorArea = $this->normalizarHabilidadesPorArea(
            $validated['habilidades_por_area'] ?? [],
            $validated['area_de_atuacao'],
            $validated['habilidades'] ?? []
        );

        $validated['habilidades_por_area'] = $habilidadesPorArea;
        $validated['habilidades'] = $this->habilidadesPlanas($habilidadesPorArea);

        $info = InformacoesProfissionais::updateOrCreate(
            ['candidato_matricula' => $matricula],
            $validated
        );

        $this->registrarAtividade($matricula);

        return response()->json($info, 201);
    }

    private function normalizarHabilidadesPorArea(array $habilidadesPorArea, string $areaPrincipal, array $habilidadesLegadas = []): array
    {
        if (empty($habilidadesPorArea) && ! empty($habilidadesLegadas)) {
            $habilidadesPorArea = [$areaPrincipal => $habilidadesLegadas];
        }

        $normalizadas = [];

        foreach ($habilidadesPorArea as $area => $habilidades) {
            $areaTratada = trim((string) $area);

            if ($areaTratada === '' || ! is_array($habilidades)) {
                continue;
            }

            $habilidadesUnicas = [];

            foreach ($habilidades as $habilidade) {
                $habilidadeTratada = trim((string) $habilidade);

                if ($habilidadeTratada === '') {
                    continue;
                }

                $chaveNormalizada = mb_strtolower($habilidadeTratada);

                if (! array_key_exists($chaveNormalizada, $habilidadesUnicas)) {
                    $habilidadesUnicas[$chaveNormalizada] = $habilidadeTratada;
                }
            }

            if (! empty($habilidadesUnicas)) {
                $normalizadas[$areaTratada] = array_values($habilidadesUnicas);
            }
        }

        return $normalizadas;
    }

    private function habilidadesPlanas(array $habilidadesPorArea): array
    {
        $habilidades = [];

        foreach ($habilidadesPorArea as $lista) {
            foreach ((array) $lista as $habilidade) {
                $habilidades[] = $habilidade;
            }
        }

        return array_values(array_unique($habilidades));
    }

    // ── Preferências de Trabalho ─────────────────────────────────

    public function storePreferencias(Request $request, string $matricula): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        if ($request->has('disponibilidade_de_horario') && ! is_array($request->input('disponibilidade_de_horario'))) {
            $request->merge([
                'disponibilidade_de_horario' => [$request->input('disponibilidade_de_horario')],
            ]);
        }

        $validated = $request->validate([
            'tipo_de_contratacao'        => ['required', 'integer', Rule::in([1, 2, 3])],
            'disponibilidade_de_horario' => ['required', 'array', 'min:1'],
            'disponibilidade_de_horario.*' => ['string', Rule::in(['Manhã', 'Tarde', 'Noite', 'Integral'])],
            'regiao_administrativa'      => ['nullable', 'string', 'max:100', Rule::in([...RegioesAdministrativasDf::nomes(), 'Todas as regiões'])],
            'aceita_todas_regioes'       => ['nullable', 'boolean'],
            'regioes_preferidas'         => ['nullable', 'array'],
            'regioes_preferidas.*'       => ['integer', Rule::in(RegioesAdministrativasDf::codigos())],
            'pretensao_salarial'         => 'nullable|numeric|min:0',
        ], [
            'tipo_de_contratacao.required' => 'Selecione pelo menos um tipo de contratação.',
            'tipo_de_contratacao.in' => 'O tipo de contratação informado não é permitido. Jovem Aprendiz não é mais uma opção válida.',
            'disponibilidade_de_horario.required' => 'Selecione pelo menos uma disponibilidade de horário.',
            'disponibilidade_de_horario.array' => 'Selecione pelo menos uma disponibilidade de horário.',
            'disponibilidade_de_horario.min' => 'Selecione pelo menos uma disponibilidade de horário.',
            'disponibilidade_de_horario.*.in' => 'A disponibilidade de horário informada não é permitida.',
        ]);
        $validated['disponibilidade_de_horario'] = $this->normalizarDisponibilidadesHorario(
            $validated['disponibilidade_de_horario'] ?? []
        );

        $aceitaTodasRegioes = (bool) ($validated['aceita_todas_regioes'] ?? false);
        $codigosRegioes = array_values(array_unique(array_map('intval', $validated['regioes_preferidas'] ?? [])));

        if (! $aceitaTodasRegioes && empty($codigosRegioes)) {
            $codigoLegado = RegioesAdministrativasDf::codigoPorNome($validated['regiao_administrativa'] ?? null);

            if ($codigoLegado !== null) {
                $codigosRegioes = [$codigoLegado];
            }
        }

        if (! $aceitaTodasRegioes && empty($codigosRegioes)) {
            return response()->json([
                'message' => 'Os dados informados são inválidos.',
                'errors' => [
                    'regiao_administrativa' => ['Selecione pelo menos uma Região Administrativa ou Todas as regiões.'],
                ],
            ], 422);
        }

        if ($aceitaTodasRegioes) {
            $codigosRegioes = [];
        }

        unset($validated['regioes_preferidas']);
        $validated['aceita_todas_regioes'] = $aceitaTodasRegioes;
        $validated['regiao_administrativa'] = $aceitaTodasRegioes
            ? 'Todas as regiões'
            : RegioesAdministrativasDf::nome($codigosRegioes[0]);

        $pref = DB::transaction(function () use ($matricula, $validated, $codigosRegioes) {
            $preferencia = PreferenciasDeTrabalho::updateOrCreate(
                ['candidato_matricula' => $matricula],
                $validated
            );

            RegiaoPreferidaTrabalho::query()
                ->where('candidato_matricula', $matricula)
                ->delete();

            foreach ($codigosRegioes as $codigoRegiao) {
                RegiaoPreferidaTrabalho::query()->create([
                    'candidato_matricula' => $matricula,
                    'codigo_regiao' => $codigoRegiao,
                ]);
            }

            return $preferencia;
        });

        $this->registrarAtividade($matricula);

        $regioesPreferidas = RegiaoPreferidaTrabalho::query()
            ->where('candidato_matricula', $matricula)
            ->orderBy('codigo_regiao')
            ->get()
            ->map(fn (RegiaoPreferidaTrabalho $regiao): array => [
                'codigo' => (int) $regiao->codigo_regiao,
                'nome' => RegioesAdministrativasDf::nome((int) $regiao->codigo_regiao),
            ])
            ->values();

        return response()->json(array_merge($pref->toArray(), [
            'regioes_preferidas' => $regioesPreferidas,
        ]), 201);
    }

    private function normalizarDisponibilidadesHorario(mixed $valor): array
    {
        $itens = is_array($valor) ? $valor : [$valor];
        $permitidos = ['Manhã', 'Tarde', 'Noite', 'Integral'];

        return array_values(array_intersect($permitidos, array_unique(array_filter(array_map(
            fn ($item) => trim((string) $item),
            $itens
        )))));
    }

    // ── Dados Acadêmicos ─────────────────────────────────────────
    // Nota: sincronizados via API do SIG (FR4) — mantido aqui apenas
    // como fallback manual, não é o fluxo principal de preenchimento.

    public function storeDadosAcademicos(Request $request, string $matricula): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        $validated = $request->validate([
            'instituicao'      => 'required|string|max:100',
            'curso'            => 'required|string|max:45',
            'segmento'         => 'nullable|string|max:60',
            'tipo_curso'       => 'nullable|string|max:30',
            'unidade'          => 'required|string|max:45',
            'ano_de_conclusao' => 'required|date',
        ]);

        $validated['candidato_matricula'] = $matricula;

        $academico = DadosAcademicos::create($validated);

        $this->registrarAtividade($matricula);

        return response()->json($academico, 201);
    }

    public function destroyDadosAcademicos(Request $request, int $id): JsonResponse
    {
        $dadoAcademico = DadosAcademicos::findOrFail($id);

        $this->garantirCandidatoDono($request, (string) $dadoAcademico->candidato_matricula);

        $dadoAcademico->delete();

        $this->registrarAtividade((string) $dadoAcademico->candidato_matricula);

        return response()->json(['message' => 'Dado acadêmico removido com sucesso.']);
    }

    // ── Cursos Realizados no Senac ───────────────────────────────
    // Nota: sincronizados via API do SIG (FR4), assim como os dados
    // acadêmicos. Mantido aqui apenas como fallback manual — a tela
    // de perfil exibe esta seção como somente leitura para o candidato.

    public function storeCursoSenac(Request $request, string $matricula): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        $validated = $request->validate([
            'nome_curso'     => 'required|string|max:100',
            'unidade'        => 'required|string|max:45',
            'carga_horaria'  => 'nullable|integer|min:1',
            'concluido_em'   => 'required|date',
        ]);

        $validated['candidato_matricula'] = $matricula;

        $curso = CursoSenac::create($validated);

        $this->registrarAtividade($matricula);

        return response()->json($curso, 201);
    }

    public function destroyCursoSenac(Request $request, int $id): JsonResponse
    {
        $curso = CursoSenac::findOrFail($id);

        $this->garantirCandidatoDono($request, (string) $curso->candidato_matricula);

        $curso->delete();

        $this->registrarAtividade((string) $curso->candidato_matricula);

        return response()->json(['message' => 'Curso removido com sucesso.']);
    }

    // ── Cursos Externos ───────────────────────────────────────────

    public function storeCursoExterno(Request $request, string $matricula): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        $validated = $request->validate([
            'nome_curso'    => 'required|string|max:100',
            'instituicao'   => 'required|string|max:100',
            'carga_horaria' => 'nullable|integer|min:1',
            'concluido_em'  => 'required|date',
        ]);

        $validated['candidato_matricula'] = $matricula;

        $curso = CursoExterno::create($validated);

        $this->registrarAtividade($matricula);

        return response()->json($curso, 201);
    }

    public function destroyCursoExterno(Request $request, string $matricula, int $id): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        CursoExterno::where('candidato_matricula', $matricula)->findOrFail($id)->delete();

        $this->registrarAtividade($matricula);

        return response()->json(['message' => 'Curso removido com sucesso.']);
    }

    // ── Experiências Profissionais ────────────────────────────────

    public function storeExperiencia(Request $request, string $matricula): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        $validated = $request->validate([
            'tipo'        => 'required|string|max:30',
            'cargo'       => 'required|string|max:100',
            'empresa'     => 'required|string|max:100',
            'local'       => 'nullable|string|max:100',
            'data_inicio' => 'required|date',
            'data_fim'    => 'nullable|date|after_or_equal:data_inicio',
            'descricao'   => 'nullable|string',
        ]);

        $validated['candidato_matricula'] = $matricula;

        $experiencia = ExperienciaProfissional::create($validated);

        $this->registrarAtividade($matricula);

        return response()->json($experiencia, 201);
    }

    public function updateExperiencia(Request $request, string $matricula, int $id): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        $experiencia = ExperienciaProfissional::where('candidato_matricula', $matricula)->findOrFail($id);

        $validated = $request->validate([
            'tipo'        => 'sometimes|string|max:30',
            'cargo'       => 'sometimes|string|max:100',
            'empresa'     => 'sometimes|string|max:100',
            'local'       => 'nullable|string|max:100',
            'data_inicio' => 'sometimes|date',
            'data_fim'    => 'nullable|date|after_or_equal:data_inicio',
            'descricao'   => 'nullable|string',
        ]);

        $experiencia->update($validated);

        $this->registrarAtividade($matricula);

        return response()->json($experiencia);
    }

    public function destroyExperiencia(Request $request, string $matricula, int $id): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        ExperienciaProfissional::where('candidato_matricula', $matricula)->findOrFail($id)->delete();

        $this->registrarAtividade($matricula);

        return response()->json(['message' => 'Experiência removida com sucesso.']);
    }

    private function registrarAtividade(string $matricula): void
    {
        $this->statusService->registrarAtividade(Candidato::query()->findOrFail($matricula));
    }
}
