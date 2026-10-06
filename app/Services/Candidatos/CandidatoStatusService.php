<?php

namespace App\Services\Candidatos;

use App\Models\Candidato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CandidatoStatusService
{
    public const CONTRATADO = 'CONTRATADO';
    public const BLOQUEADO_MANUALMENTE = 'BLOQUEADO_MANUALMENTE';
    public const BLOQUEADO_POR_INATIVIDADE = 'BLOQUEADO_POR_INATIVIDADE';
    public const ATIVO = 'ATIVO';

    public const INATIVIDADE_CODE = 'CANDIDATO_INATIVO';

    public function prazoSegundos(): int
    {
        return max(1, (int) config('candidato.inatividade_segundos', 30));
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
        if (! $candidato->ultima_atividade_em) {
            return true;
        }

        return $candidato->ultima_atividade_em->lessThanOrEqualTo($this->limiteAtividade());
    }

    public function registrarAtividade(Candidato $candidato): void
    {
        $candidato->forceFill(['ultima_atividade_em' => now()])->save();
    }

    public function aplicarEscopoDisponiveis(Builder $query): Builder
    {
        return $query
            ->where('status', true)
            ->whereDoesntHave('contratacao')
            ->whereNotNull('ultima_atividade_em')
            ->where('ultima_atividade_em', '>', $this->limiteAtividade());
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
