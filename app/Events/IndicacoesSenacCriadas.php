<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Disparado depois que um lote de indicações é gravado.
 * Nesta versão não há listeners (sem e-mails ou notificações); é o ponto de extensão
 * para notificar empresas ou candidatos no futuro.
 */
class IndicacoesSenacCriadas implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  array<int>  $indicacaoIds
     */
    public function __construct(public readonly array $indicacaoIds) {}
}
