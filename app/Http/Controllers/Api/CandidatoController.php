<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidato;
use App\Models\Convite;
use App\Models\Pessoa;
use App\Models\VisualizacaoPerfil;
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
     * Exibe um candidato específico. Se quem pede for uma empresa (não o
     * próprio candidato), registra uma visualização de perfil (FR9).
     */
    public function show(Request $request, int $matricula): JsonResponse
    {
        $candidato = Candidato::with([
            'pessoa',
            'linkExterno',
            'informacoesProfissionais',
            'preferenciasDeTrabalho',
            'dadosAcademicos',
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
    public function update(Request $request, int $matricula): JsonResponse
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
    public function destroy(Request $request, int $matricula): JsonResponse
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
    public function dashboard(Request $request, int $matricula): JsonResponse
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
