<?php

namespace App\Services\Candidatos;

use App\Mail\CandidatoBloqueadoMail;
use App\Models\Candidato;
use App\Models\CandidatoBloqueioNotificacao;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class CandidatoBloqueioNotificationService
{
    public function registrarBloqueio(Candidato $candidato, string $tipoBloqueio, ?string $motivoPublico = null, ?int $administradorPessoaId = null): ?CandidatoBloqueioNotificacao
    {
        if ($candidato->estaContratado()) {
            return null;
        }

        $candidato->loadMissing('pessoa', 'bloqueioNotificacoes');

        if (! $candidato->pessoa?->email) {
            return null;
        }

        $bloqueadoEm = $tipoBloqueio === CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE
            ? ($candidato->ultima_atividade_em?->copy()->startOfSecond() ?? $candidato->updated_at?->copy()->startOfSecond() ?? now()->startOfSecond())
            : now()->startOfSecond();

        $dadosCriacao = [
            'status' => CandidatoBloqueioNotificacao::STATUS_PENDENTE,
            'origem' => $tipoBloqueio === CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE
                ? CandidatoBloqueioNotificacao::ORIGEM_AUTOMATICO
                : CandidatoBloqueioNotificacao::ORIGEM_MANUAL_INDIVIDUAL,
            'motivo_publico' => $this->motivoPublicoSeguro($motivoPublico),
        ];

        if ($tipoBloqueio === CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE
            && $candidato->bloqueioNotificacoes()
                ->where('tipo_bloqueio', $tipoBloqueio)
                ->where('bloqueado_em', $bloqueadoEm)
                ->whereIn('status', [CandidatoBloqueioNotificacao::STATUS_PENDENTE, CandidatoBloqueioNotificacao::STATUS_PROCESSANDO, CandidatoBloqueioNotificacao::STATUS_ENVIADO])
                ->exists()) {
            return $candidato->bloqueioNotificacoes()
                ->where('tipo_bloqueio', $tipoBloqueio)
                ->where('bloqueado_em', $bloqueadoEm)
                ->latest('bloqueado_em')
                ->first();
        }

        $notificacao = CandidatoBloqueioNotificacao::query()->firstOrCreate([
            'candidato_matricula' => $candidato->matricula,
            'tipo_bloqueio' => $tipoBloqueio,
            'bloqueado_em' => $bloqueadoEm,
        ], $dadosCriacao);

        if (! $notificacao->wasRecentlyCreated) {
            return $notificacao;
        }

        if ($tipoBloqueio === CandidatoStatusService::BLOQUEADO_MANUALMENTE && Schema::hasColumn('candidato_bloqueio_notificacoes', 'administrador_pessoa_id')) {
            $notificacao->forceFill(['administrador_pessoa_id' => $administradorPessoaId])->save();
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
        $bloqueada = CandidatoBloqueioNotificacao::query()
            ->whereKey($notificacao->getKey())
            ->whereIn('status', [CandidatoBloqueioNotificacao::STATUS_PENDENTE, CandidatoBloqueioNotificacao::STATUS_FALHOU])
            ->update([
                'tentativa_envio_em' => now(),
                'status' => CandidatoBloqueioNotificacao::STATUS_PROCESSANDO,
                'erro' => null,
                'updated_at' => now(),
            ]);

        if ($bloqueada === 0) {
            return;
        }

        $notificacao->refresh();

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
