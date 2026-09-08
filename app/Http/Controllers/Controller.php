<?php

namespace App\Http\Controllers;

use App\Models\Pessoa;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
    protected function garantirCandidatoDono(Request $request, string $matricula): void
    {
        $pessoa = $this->pessoaAutenticada($request);

        if (! $pessoa || ! $pessoa->candidato || (string) $pessoa->candidato->matricula !== $matricula) {
            abort(403, 'Voce nao tem permissao para acessar este recurso.');
        }
    }

    /**
     * Retorna a Empresa da pessoa autenticada, ou aborta com 403 se
     * quem está logado não for uma empresa.
     */
    protected function empresaAutenticada(Request $request): \App\Models\Empresa
    {
        $pessoa = $this->pessoaAutenticada($request);

        if (! $pessoa || ! $pessoa->empresa) {
            abort(403, 'Apenas empresas podem acessar este recurso.');
        }

        return $pessoa->empresa;
    }

    /**
     * Aborta com 403 se a pessoa autenticada não for administrativa.
     */
    protected function garantirAdministrativo(Request $request): \App\Models\Pessoa
    {
        $pessoa = $this->pessoaAutenticada($request);

        if (! $pessoa || ! $pessoa->administrativo) {
            abort(403, 'Apenas o administrativo pode acessar este recurso.');
        }

        return $pessoa;
    }

    /**
     * Aborta com 403 se a vaga não pertencer à empresa autenticada.
     */
    protected function garantirVagaDaEmpresa(Request $request, \App\Models\Vaga $vaga): void
    {
        $empresa = $this->empresaAutenticada($request);

        if ((string) $vaga->empresa_cnpj !== (string) $empresa->cnpj) {
            abort(403, 'Voce nao tem permissao para gerenciar esta vaga.');
        }
    }

    /**
     * Aborta com 403 se o convite não pertencer à empresa autenticada.
     */
    protected function garantirConviteDaEmpresa(Request $request, \App\Models\Convite $convite): void
    {
        $empresa = $this->empresaAutenticada($request);

        if ((string) $convite->empresa_cnpj !== (string) $empresa->cnpj) {
            abort(403, 'Voce nao tem permissao para gerenciar este convite.');
        }
    }

    /**
     * Aborta com 403 se o recurso do candidato não pertencer à matrícula informada.
     */
    protected function garantirRecursoDoCandidato(string $matricula, string $recursoMatricula, string $mensagem = 'Voce nao tem permissao para acessar este recurso.'): void
    {
        if ($recursoMatricula !== $matricula) {
            throw new HttpException(403, $mensagem);
        }
    }
}
