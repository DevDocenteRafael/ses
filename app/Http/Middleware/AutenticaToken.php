<?php

namespace App\Http\Middleware;

use App\Models\Pessoa;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve a Pessoa autenticada a partir do token opaco emitido pelo
 * AuthController (armazenado em cache) e disponibiliza em
 * $request->pessoaAutenticada. Aborta com 401 se o token for ausente/inválido.
 *
 * Uso: Route::middleware('auth.token') ou 'auth.token:administrativo,empresa'
 * para também restringir por tipo de pessoa.
 */
class AutenticaToken
{
    public function handle(Request $request, Closure $next, string ...$tiposPermitidos): Response
    {
        $token = $request->bearerToken() ?? $request->input('token');

        if (! $token) {
            return response()->json(['message' => 'Nao autenticado.'], 401);
        }

        $pessoaId = Cache::get('auth_token:' . $token);

        if (! $pessoaId) {
            return response()->json(['message' => 'Token invalido ou expirado.'], 401);
        }

        $pessoa = Pessoa::with(['administrativo', 'empresa', 'candidato'])->find($pessoaId);

        if (! $pessoa) {
            return response()->json(['message' => 'Nao autenticado.'], 401);
        }

        if ($pessoa->candidato && !$pessoa->candidato->status) {
            return response()->json(['message' => 'Conta bloqueada.'], 403);
        }

        if ($pessoa->empresa && !$pessoa->empresa->status) {
            return response()->json(['message' => 'Conta bloqueada.'], 403);
        }

        if ($tiposPermitidos && ! in_array($pessoa->tipo(), $tiposPermitidos, true)) {
            return response()->json(['message' => 'Acesso nao autorizado para este perfil.'], 403);
        }

        $request->attributes->set('pessoa_autenticada', $pessoa);

        return $next($request);
    }
}
