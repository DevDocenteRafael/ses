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
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PerfilCandidatoController extends Controller
{
    // ── Links Externos ───────────────────────────────────────────

    public function storeLink(Request $request, int $matricula): JsonResponse
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

        return response()->json($link, 201);
    }

    // ── Informações Profissionais ────────────────────────────────

    public function storeInfoProfissional(Request $request, int $matricula): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        $validated = $request->validate([
            'sobre_mim'          => 'nullable|string|max:200',
            'cargo_de_interesse' => 'nullable|string|max:45',
            'area_de_atuacao'    => 'required|string|max:45',
            'habilidades'        => 'nullable|array',
            'habilidades.*'      => 'string|max:45',
        ]);

        $info = InformacoesProfissionais::updateOrCreate(
            ['candidato_matricula' => $matricula],
            $validated
        );

        return response()->json($info, 201);
    }

    // ── Preferências de Trabalho ─────────────────────────────────

    public function storePreferencias(Request $request, int $matricula): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        $validated = $request->validate([
            'tipo_de_contratacao'        => 'nullable|integer|min:0',
            'disponibilidade_de_horario' => ['nullable', 'string', 'in:Manhã,Tarde,Noite,Integral'],
            'regiao_administrativa'      => 'required|string|max:100',
            'pretensao_salarial'         => 'nullable|numeric|min:0',
        ]);

        $pref = PreferenciasDeTrabalho::updateOrCreate(
            ['candidato_matricula' => $matricula],
            $validated
        );

        return response()->json($pref, 201);
    }

    // ── Dados Acadêmicos ─────────────────────────────────────────
    // Nota: sincronizados via API do SIG (FR4) — mantido aqui apenas
    // como fallback manual, não é o fluxo principal de preenchimento.

    public function storeDadosAcademicos(Request $request, int $matricula): JsonResponse
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

        return response()->json($academico, 201);
    }

    public function destroyDadosAcademicos(int $id): JsonResponse
    {
        DadosAcademicos::findOrFail($id)->delete();

        return response()->json(['message' => 'Dado acadêmico removido com sucesso.']);
    }

    // ── Cursos Realizados no Senac ───────────────────────────────
    // Nota: sincronizados via API do SIG (FR4), assim como os dados
    // acadêmicos. Mantido aqui apenas como fallback manual — a tela
    // de perfil exibe esta seção como somente leitura para o candidato.

    public function storeCursoSenac(Request $request, int $matricula): JsonResponse
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

        return response()->json($curso, 201);
    }

    public function destroyCursoSenac(int $id): JsonResponse
    {
        CursoSenac::findOrFail($id)->delete();

        return response()->json(['message' => 'Curso removido com sucesso.']);
    }

    // ── Cursos Externos ───────────────────────────────────────────

    public function storeCursoExterno(Request $request, int $matricula): JsonResponse
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

        return response()->json($curso, 201);
    }

    public function destroyCursoExterno(Request $request, int $matricula, int $id): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        CursoExterno::where('candidato_matricula', $matricula)->findOrFail($id)->delete();

        return response()->json(['message' => 'Curso removido com sucesso.']);
    }

    // ── Experiências Profissionais ────────────────────────────────

    public function storeExperiencia(Request $request, int $matricula): JsonResponse
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

        return response()->json($experiencia, 201);
    }

    public function updateExperiencia(Request $request, int $matricula, int $id): JsonResponse
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

        return response()->json($experiencia);
    }

    public function destroyExperiencia(Request $request, int $matricula, int $id): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        ExperienciaProfissional::where('candidato_matricula', $matricula)->findOrFail($id)->delete();

        return response()->json(['message' => 'Experiência removida com sucesso.']);
    }
}
