<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Candidato;
use App\Services\Candidatos\CandidatoStatusService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('candidatos:notificar-inativos', function (CandidatoStatusService $statusService) {
    Candidato::query()
        ->with(['pessoa', 'contratacao'])
        ->where('status', true)
        ->whereDoesntHave('contratacao')
        ->where(function ($query) use ($statusService) {
            $query->whereNull('ultima_atividade_em')
                ->orWhere('ultima_atividade_em', '<=', $statusService->limiteAtividade());
        })
        ->orderBy('matricula')
        ->chunkById(100, function ($candidatos) use ($statusService) {
            $candidatos->each(fn (Candidato $candidato) => $statusService->registrarBloqueioPorInatividade($candidato));
        }, 'matricula');

    $this->info('Verificação de candidatos inativos concluída.');
})->purpose('Registra notificações de novos bloqueios por inatividade sem alterar o prazo configurado');
