<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCursoExternoRequest;
use App\Http\Requests\StoreCursoSenacRequest;
use App\Http\Requests\StoreDadosAcademicosRequest;
use App\Http\Requests\StoreExperienciaRequest;
use App\Http\Requests\StoreInfoProfissionalRequest;
use App\Http\Requests\StoreLinkRequest;
use App\Http\Requests\StorePreferenciasRequest;
use App\Http\Requests\UpdateExperienciaRequest;
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

    public function storeLink(StoreLinkRequest $request, int $matricula): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        $validated = $request->validated();

        $link = LinkExterno::updateOrCreate(
            ['candidato_matricula' => $matricula],
            $validated
        );

        return response()->json($link, 201);
    }

    // ── Informações Profissionais ────────────────────────────────

    public function storeInfoProfissional(StoreInfoProfissionalRequest $request, int $matricula): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        $validated = $request->validated();

        $info = InformacoesProfissionais::updateOrCreate(
            ['candidato_matricula' => $matricula],
            $validated
        );

        return response()->json($info, 201);
    }

    // ── Preferências de Trabalho ─────────────────────────────────

    public function storePreferencias(StorePreferenciasRequest $request, int $matricula): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        $validated = $request->validated();

        $pref = PreferenciasDeTrabalho::updateOrCreate(
            ['candidato_matricula' => $matricula],
            $validated
        );

        return response()->json($pref, 201);
    }

    // ── Dados Acadêmicos ─────────────────────────────────────────
    // Nota: sincronizados via API do SIG (FR4) — mantido aqui apenas
    // como fallback manual, não é o fluxo principal de preenchimento.

    public function storeDadosAcademicos(StoreDadosAcademicosRequest $request, int $matricula): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        $validated = $request->validated();
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

    public function storeCursoSenac(StoreCursoSenacRequest $request, int $matricula): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        $validated = $request->validated();
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

    public function storeCursoExterno(StoreCursoExternoRequest $request, int $matricula): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        $validated = $request->validated();
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

    public function storeExperiencia(StoreExperienciaRequest $request, int $matricula): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        $validated = $request->validated();
        $validated['candidato_matricula'] = $matricula;

        $experiencia = ExperienciaProfissional::create($validated);

        return response()->json($experiencia, 201);
    }

    public function updateExperiencia(UpdateExperienciaRequest $request, int $matricula, int $id): JsonResponse
    {
        $this->garantirCandidatoDono($request, $matricula);

        $experiencia = ExperienciaProfissional::where('candidato_matricula', $matricula)->findOrFail($id);

        $validated = $request->validated();

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
