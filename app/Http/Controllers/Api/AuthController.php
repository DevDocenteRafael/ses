<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pessoa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        if (! $request->filled('identificador') && $request->filled('email')) {
            $request->merge(['identificador' => $request->input('email')]);
        }

        $dados = $request->validate([
            'identificador' => ['required', 'string'],
            'senha' => ['required', 'string'],
        ]);

        $pessoa = $this->localizarPessoaPorIdentificador($dados['identificador']);

        if (! $pessoa) {
            return response()->json([
                'message' => 'Conta não encontrada.',
                'errors' => [
                    'identificador' => ['Não encontramos uma conta com este CPF ou e-mail.'],
                ],
            ], 422);
        }

        if (! Hash::check($dados['senha'], $pessoa->senha)) {
            return response()->json([
                'message' => 'Senha incorreta.',
                'errors' => [
                    'senha' => ['A senha informada está incorreta.'],
                ],
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

    private function localizarPessoaPorIdentificador(string $identificador): ?Pessoa
    {
        $identificador = trim($identificador);
        $identificadorEmail = mb_strtolower($identificador);
        $cpf = preg_replace('/\D+/', '', $identificador);

        return Pessoa::with(['administrativo', 'empresa', 'candidato'])
            ->where(function ($query) use ($identificadorEmail, $cpf) {
                $query->whereRaw('LOWER(email) = ?', [$identificadorEmail]);

                if (strlen($cpf) === 11) {
                    $query->orWhereHas('candidato', function ($query) use ($cpf) {
                        $query->where(DB::raw("REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), '/', '')"), $cpf);
                    });
                }
            })
            ->first();
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
        $resposta = [
            'id_pessoa' => $pessoa->id_pessoa,
            'matricula' => $pessoa->candidato?->matricula,
            'nome' => $pessoa->nome,
            'email' => $pessoa->email,
            'telefone' => $pessoa->telefone,
            'endereco' => [
                'cep' => $pessoa->endereco_cep,
                'logradouro' => $pessoa->endereco_logradouro,
                'numero' => $pessoa->endereco_numero,
                'complemento' => $pessoa->endereco_complemento,
                'bairro' => $pessoa->endereco_bairro,
                'cidade' => $pessoa->endereco_cidade,
                'uf' => $pessoa->endereco_uf,
            ],
            'tipo' => $tipo,
        ];

        if ($tipo === 'empresa') {
            $resposta['cnpj'] = $pessoa->empresa?->cnpj;
        }

        return $resposta;
    }
}
