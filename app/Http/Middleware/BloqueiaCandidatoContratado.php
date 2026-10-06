<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BloqueiaCandidatoContratado
{
    public const CODE = 'CANDIDATO_CONTRATADO';

    public function handle(Request $request, Closure $next): Response
    {
        $pessoa = $request->attributes->get('pessoa_autenticada');
        $candidato = $pessoa?->candidato;

        if ($pessoa?->tipo() === 'candidato' && $candidato?->estaContratado()) {
            return response()->json([
                'message' => 'Seu perfil está indisponível porque você já foi contratado.',
                'code' => self::CODE,
            ], 403);
        }

        return $next($request);
    }
}
