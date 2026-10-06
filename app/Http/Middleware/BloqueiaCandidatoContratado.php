<?php

namespace App\Http\Middleware;

use App\Services\Candidatos\CandidatoStatusService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BloqueiaCandidatoContratado
{
    public const CODE = 'CANDIDATO_CONTRATADO';

    public function __construct(private readonly CandidatoStatusService $statusService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $pessoa = $request->attributes->get('pessoa_autenticada');
        $candidato = $pessoa?->candidato;

        if ($pessoa?->tipo() !== 'candidato' || ! $candidato) {
            return $next($request);
        }

        $estado = $this->statusService->estadoEfetivo($candidato);

        if ($estado === CandidatoStatusService::CONTRATADO) {
            return response()->json([
                'message' => 'Seu perfil está indisponível porque você já foi contratado.',
                'code' => self::CODE,
            ], 403);
        }

        if ($estado === CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE) {
            return response()->json([
                'message' => 'Seu perfil foi bloqueado por inatividade. Faça login novamente para reativá-lo.',
                'code' => CandidatoStatusService::INATIVIDADE_CODE,
            ], 403);
        }

        return $next($request);
    }
}
