<?php

namespace App\Http\Controllers;

use App\Models\LinkExterno;
use App\Models\InformacoesProfissionais;
use App\Models\PreferenciasDeTrabalho;
use App\Models\DadosAcademicos;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PerfilCandidatoController extends Controller
{
    // ── Links Externos ───────────────────────────────────────────

    public function storeLink(Request $request, int $matricula): JsonResponse
    {
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
        $validated = $request->validate([
            'sobre_mim'          => 'nullable|string|max:200',
            'cargo_de_interesse' => 'nullable|string|max:45',
            'area_de_atuacao'    => 'required|string|max:45',
            'habilidades_tags'   => 'nullable|integer',
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
        $validated = $request->validate([
            'tipo_de_contratacao'       => 'nullable|integer',
            'disponibilidade_de_horario' => 'required|date_format:H:i',
            'regiao_administrativa'     => 'required|string|max:100',
            'pretensao_salarial'        => 'nullable|integer',
        ]);

        $pref = PreferenciasDeTrabalho::updateOrCreate(
            ['candidato_matricula' => $matricula],
            $validated
        );

        return response()->json($pref, 201);
    }

    // ── Dados Acadêmicos ─────────────────────────────────────────

    public function storeDadosAcademicos(Request $request, int $matricula): JsonResponse
    {
        $validated = $request->validate([
            'instituicao'      => 'required|string|max:100',
            'curso'            => 'required|string|max:45',
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
}