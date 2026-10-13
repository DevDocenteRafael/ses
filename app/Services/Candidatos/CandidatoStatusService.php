<?php

namespace App\Services\Candidatos;

use App\Models\Candidato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class CandidatoStatusService
{
    public const CONTRATADO = 'CONTRATADO';
    public const BLOQUEADO_MANUALMENTE = 'BLOQUEADO_MANUALMENTE';
    public const BLOQUEADO_POR_INATIVIDADE = 'BLOQUEADO_POR_INATIVIDADE';
    public const ATIVO = 'ATIVO';

    public const INATIVIDADE_CODE = 'CANDIDATO_INATIVO';

    public function __construct(private readonly CandidatoBloqueioNotificationService $bloqueioNotificationService) {}

    public function prazoSegundos(): int
    {
        return max(1, (int) config('candidato.inatividade_segundos', 30));
    }

    public function inatividadeHabilitada(): bool
    {
        return filter_var(config('candidato.inatividade_habilitada', true), FILTER_VALIDATE_BOOL);
    }

    public function limiteAtividade(): Carbon
    {
        return now()->subSeconds($this->prazoSegundos());
    }

    public function estadoEfetivo(Candidato $candidato): string
    {
        if ($candidato->estaContratado()) {
            return self::CONTRATADO;
        }

        if (! $candidato->status) {
            return self::BLOQUEADO_MANUALMENTE;
        }

        if ($this->estaInativoPorTempo($candidato)) {
            return self::BLOQUEADO_POR_INATIVIDADE;
        }

        return self::ATIVO;
    }

    public function estaDisponivel(Candidato $candidato): bool
    {
        return $this->estadoEfetivo($candidato) === self::ATIVO;
    }

    public function estaInativoPorTempo(Candidato $candidato): bool
    {
        if (! $this->inatividadeHabilitada()) {
            return false;
        }

        if (! $candidato->ultima_atividade_em) {
            return true;
        }

        return $candidato->ultima_atividade_em->lessThanOrEqualTo($this->limiteAtividade());
    }

    public function registrarAtividade(Candidato $candidato): void
    {
        $candidato->forceFill(['ultima_atividade_em' => now()])->save();
    }

    public function bloquearManualmente(Candidato $candidato, ?string $motivoPublico = null): bool
    {
        return DB::transaction(function () use ($candidato, $motivoPublico): bool {
            $bloqueado = Candidato::query()->lockForUpdate()->findOrFail($candidato->matricula);

            if (! $bloqueado->status) {
                return false;
            }

            if ($bloqueado->estaContratado()) {
                return false;
            }

            $bloqueado->forceFill(['status' => false])->save();

            $this->bloqueioNotificationService->registrarBloqueio(
                $bloqueado,
                self::BLOQUEADO_MANUALMENTE,
                $motivoPublico
            );

            return true;
        });
    }

    public function registrarBloqueioPorInatividade(Candidato $candidato): void
    {
        if ($this->estadoEfetivo($candidato) !== self::BLOQUEADO_POR_INATIVIDADE) {
            return;
        }

        $this->bloqueioNotificationService->registrarBloqueio(
            $candidato,
            self::BLOQUEADO_POR_INATIVIDADE,
            'Inatividade na plataforma.'
        );
    }

    public function aplicarEscopoDisponiveis(Builder $query): Builder
    {
        $query
            ->where('status', true)
            ->whereDoesntHave('contratacao');

        if ($this->inatividadeHabilitada()) {
            $query
                ->whereNotNull('ultima_atividade_em')
                ->where('ultima_atividade_em', '>', $this->limiteAtividade());
        }

        return $query;
    }

    public function rotulo(string $estado): string
    {
        return match ($estado) {
            self::CONTRATADO => 'Contratado',
            self::BLOQUEADO_MANUALMENTE => 'Bloqueado manualmente',
            self::BLOQUEADO_POR_INATIVIDADE => 'Bloqueado por inatividade',
            default => 'Ativo',
        };
    }
}
