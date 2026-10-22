<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Disparado quando a empresa altera o status de uma indicação.
 * Sem listeners nesta versão; ponto de extensão para notificar o administrativo no futuro.
 */
class IndicacaoSenacStatusAlterado implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $indicacaoId,
        public readonly string $statusAnterior,
        public readonly string $statusNovo,
    ) {}
}
