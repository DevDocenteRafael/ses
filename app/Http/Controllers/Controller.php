<?php

namespace App\Http\Controllers;

use App\Models\Pessoa;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Pessoa autenticada, resolvida pelo middleware AutenticaToken.
     * Null se a rota não passar por esse middleware.
     */
    protected function pessoaAutenticada(Request $request): ?Pessoa
    {
        return $request->attributes->get('pessoa_autenticada');
    }

    /**
     * Aborta com 403 se a pessoa autenticada não for o candidato dono da matrícula.
     */
    protected function garantirCandidatoDono(Request $request, int $matricula): void
    {
        $pessoa = $this->pessoaAutenticada($request);

        if (! $pessoa || ! $pessoa->candidato || (int) $pessoa->candidato->matricula !== $matricula) {
            abort(403, 'Voce nao tem permissao para acessar este recurso.');
        }
    }
}
