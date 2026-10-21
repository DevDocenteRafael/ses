<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidato;
use App\Services\Candidatos\CandidatoReativacaoQuestionarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidatoReativacaoQuestionarioController extends Controller
{
    public function __construct(private readonly CandidatoReativacaoQuestionarioService $service) {}

    public function show(Request $request): JsonResponse
    {
        $pessoa = $request->attributes->get('pessoa_autenticada');
        $candidato = $pessoa?->candidato;

        if ($pessoa?->tipo() !== 'candidato' || ! $candidato instanceof Candidato) {
            abort(403, 'Apenas o candidato autenticado pode consultar este questionário.');
        }

        $pergunta = $this->service->perguntaAtual($candidato);

        return response()->json([
            'pergunta' => $pergunta,
            'tem_pergunta' => $pergunta !== null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $pessoa = $request->attributes->get('pessoa_autenticada');
        $candidato = $pessoa?->candidato;

        if ($pessoa?->tipo() !== 'candidato' || ! $candidato instanceof Candidato) {
            abort(403, 'Apenas o candidato autenticado pode responder este questionário.');
        }

        $validated = $request->validate([
            'pergunta_numero' => ['nullable', 'integer', 'min:1', 'max:8'],
            'resposta_1' => ['nullable', 'string'],
            'resposta_2' => ['nullable', 'string'],
            'resposta_3' => ['nullable', 'string'],
            'resposta_4' => ['nullable', 'array'],
            'resposta_4.*' => ['string'],
            'resposta_5' => ['nullable', 'string'],
            'resposta_6' => ['nullable', 'string'],
            'resposta_7' => ['nullable', 'string'],
            'resposta_8' => ['nullable', 'string'],
            'resposta' => ['nullable', 'string'],
            'confirmar_encerramento' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->service->concluir($candidato, $validated));
    }
}
