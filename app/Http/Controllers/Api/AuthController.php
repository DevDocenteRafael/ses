<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pessoa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'senha' => ['required', 'string'],
        ]);

        $pessoa = Pessoa::with(['administrativo', 'empresa', 'candidato'])
            ->where('email', $dados['email'])
            ->first();

        if (! $pessoa || ! Hash::check($dados['senha'], $pessoa->senha)) {
            return response()->json([
                'message' => 'Credenciais invalidas.',
            ], 422);
        }

        $tipo = $this->resolverTipo($pessoa);

        if ($tipo === 'candidato' && !$pessoa->candidato->status) {
            return response()->json(['message' => 'Conta bloqueada.'], 403);
        }
        
        if ($tipo === 'empresa' && !$pessoa->empresa->status) {
            return response()->json(['message' => 'Conta bloqueada.'], 403);
        }

        $token = Str::random(64);

        Cache::put($this->cacheKey($token), $pessoa->id_pessoa, now()->addDay());

        return response()->json([
            'token' => $token,
            'tipo' => $tipo,
            'pessoa' => $this->pessoaParaResposta($pessoa, $tipo),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $pessoa = $this->pessoaAutenticada($request);

        if (! $pessoa) {
            return response()->json([
                'message' => 'Nao autenticado.',
            ], 401);
        }

        $tipo = $this->resolverTipo($pessoa);

        return response()->json([
            'token' => $this->tokenFromRequest($request),
            'tipo' => $tipo,
            'pessoa' => $this->pessoaParaResposta($pessoa, $tipo),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $this->tokenFromRequest($request);

        if ($token) {
            Cache::forget($this->cacheKey($token));
        }

        return response()->json([
            'message' => 'Sessao encerrada.',
        ]);
    }

    private function tokenFromRequest(Request $request): ?string
    {
        $header = $request->bearerToken();

        if ($header) {
            return $header;
        }

        return $request->input('token');
    }

    private function cacheKey(string $token): string
    {
        return 'auth_token:' . $token;
    }

    private function resolverTipo(Pessoa $pessoa): string
    {
        if ($pessoa->administrativo) {
            return 'administrativo';
        }

        if ($pessoa->empresa) {
            return 'empresa';
        }

        return 'candidato';
    }

    private function pessoaParaResposta(Pessoa $pessoa, string $tipo): array
    {
        return [
            'id_pessoa' => $pessoa->id_pessoa,
            'matricula' => $pessoa->candidato?->matricula,
            'nome' => $pessoa->nome,
            'email' => $pessoa->email,
            'telefone' => $pessoa->telefone,
            'tipo' => $tipo,
        ];
    }
}
