<?php

namespace App\Services\Candidatos;

use App\Mail\CandidatoBloqueadoMail;
use App\Models\Candidato;
use App\Models\CandidatoBloqueioNotificacao;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class CandidatoBloqueioNotificationService
{
    public function registrarBloqueio(Candidato $candidato, string $tipoBloqueio, ?string $motivoPublico = null): ?CandidatoBloqueioNotificacao
    {
        if ($candidato->estaContratado()) {
            return null;
        }

        $candidato->loadMissing('pessoa');

        if (! $candidato->pessoa?->email) {
            return null;
        }

        $bloqueadoEm = now()->startOfSecond();

        $notificacao = CandidatoBloqueioNotificacao::query()->firstOrCreate([
            'candidato_matricula' => $candidato->matricula,
            'tipo_bloqueio' => $tipoBloqueio,
            'bloqueado_em' => $bloqueadoEm,
        ], [
            'status' => CandidatoBloqueioNotificacao::STATUS_PENDENTE,
            'motivo_publico' => $this->motivoPublicoSeguro($motivoPublico),
        ]);

        if (! $notificacao->wasRecentlyCreated) {
            return $notificacao;
        }

        $callback = fn () => $this->enviar($notificacao->fresh(), $candidato->fresh(['pessoa']), $tipoBloqueio);

        if (method_exists(app('db'), 'afterCommit')) {
            app('db')->afterCommit($callback);
        } else {
            $callback();
        }

        return $notificacao;
    }

    public function enviar(CandidatoBloqueioNotificacao $notificacao, Candidato $candidato, string $tipoBloqueio): void
    {
        if ($notificacao->status === CandidatoBloqueioNotificacao::STATUS_ENVIADO) {
            return;
        }

        $notificacao->forceFill([
            'tentativa_envio_em' => now(),
            'status' => CandidatoBloqueioNotificacao::STATUS_PENDENTE,
            'erro' => null,
        ])->save();

        try {
            Mail::to($candidato->pessoa->email)->send(new CandidatoBloqueadoMail(
                $candidato,
                $tipoBloqueio,
                $notificacao->motivo_publico,
                $this->urlLogin()
            ));

            $notificacao->forceFill([
                'status' => CandidatoBloqueioNotificacao::STATUS_ENVIADO,
                'enviado_em' => now(),
                'erro' => null,
            ])->save();
        } catch (Throwable $e) {
            $notificacao->forceFill([
                'status' => CandidatoBloqueioNotificacao::STATUS_FALHOU,
                'erro' => Str::limit($e->getMessage(), 1000, ''),
            ])->save();

            Log::error('Falha ao enviar e-mail de bloqueio do candidato.', [
                'exception' => $e,
                'notificacao_id' => $notificacao->id,
                'candidato_matricula' => $candidato->matricula,
                'tipo_bloqueio' => $tipoBloqueio,
            ]);
        }
    }

    public function motivoPublicoSeguro(?string $motivo): ?string
    {
        $motivo = trim((string) $motivo);

        if ($motivo === '') {
            return null;
        }

        return Str::limit(strip_tags($motivo), 500, '');
    }

    private function urlLogin(): string
    {
        return rtrim((string) config('portal.frontend_url'), '/') . '/' . ltrim((string) config('portal.login_path', '/login'), '/');
    }
}
