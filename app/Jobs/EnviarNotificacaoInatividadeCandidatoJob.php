<?php

namespace App\Jobs;

use App\Services\Candidatos\CandidatoInatividadeManualNotificationService;
use App\Models\CandidatoBloqueioNotificacao;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnviarNotificacaoInatividadeCandidatoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public string $matricula,
        public string $origem,
        public ?int $administradorPessoaId = null,
        public ?int $campanhaId = null,
        public ?int $notificacaoId = null,
    ) {}

    public function handle(CandidatoInatividadeManualNotificationService $service): void
    {
        if (in_array($this->origem, [CandidatoBloqueioNotificacao::ORIGEM_MANUAL_GLOBAL, CandidatoBloqueioNotificacao::ORIGEM_MANUAL_INDIVIDUAL], true)
            && ! filter_var(config('candidato.notificacoes_manuais_habilitadas', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $service->processarEnvio($this->matricula, $this->origem, $this->administradorPessoaId, $this->campanhaId, $this->notificacaoId);
    }
}
