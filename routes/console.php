<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Candidato;
use App\Services\Candidatos\CandidatoStatusService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('candidatos:notificar-inativos {--matricula=} {--limit=}', function (CandidatoStatusService $statusService) {
    $query = Candidato::query()
        ->with(['pessoa', 'contratacao'])
        ->where('status', true)
        ->whereDoesntHave('contratacao')
        ->where(function ($query) use ($statusService) {
            $query->whereNull('ultima_atividade_em')
                ->orWhere('ultima_atividade_em', '<=', $statusService->limiteAtividade());
        });

    if ($matricula = $this->option('matricula')) {
        $query->where('matricula', (string) $matricula);
    }

    if ($limit = (int) $this->option('limit')) {
        $query->limit(max(1, $limit));
    }

    $query->orderBy('matricula')
        ->get()
        ->each(fn (Candidato $candidato) => $statusService->registrarBloqueioPorInatividade($candidato));

    $this->info('Verificação de candidatos inativos concluída.');
})->purpose('Registra notificações de novos bloqueios por inatividade sem alterar o prazo configurado');
