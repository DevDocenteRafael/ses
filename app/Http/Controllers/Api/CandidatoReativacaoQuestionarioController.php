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

    public function store(Request $request): JsonResponse
    {
        $pessoa = $request->attributes->get('pessoa_autenticada');
        $candidato = $pessoa?->candidato;

        if ($pessoa?->tipo() !== 'candidato' || ! $candidato instanceof Candidato) {
            abort(403, 'Apenas o candidato autenticado pode responder este questionário.');
        }

        $validated = $request->validate([
            'resposta_1' => ['required', 'string'],
            'resposta_2' => ['nullable', 'string'],
            'resposta_3' => ['nullable', 'string'],
            'resposta_4' => ['nullable', 'array'],
            'resposta_4.*' => ['string'],
            'resposta_5' => ['nullable', 'string'],
            'resposta_6' => ['nullable', 'string'],
            'resposta_7' => ['nullable', 'string'],
            'resposta_8' => ['nullable', 'string'],
        ]);

        return response()->json($this->service->concluir($candidato, $validated));
    }
}
