<?php

namespace App\Services\Candidatos;

use App\Models\Candidato;
use App\Models\CandidatoReativacaoQuestionario;
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

    public function prazoMeses(): int
    {
        return max(1, (int) config('candidato.inatividade_meses', 3));
    }

    /**
     * Prazo curto em segundos só existe com o modo de teste explicitamente habilitado
     * e apenas em local/testing; em qualquer outro caso vale a regra de 3 meses.
     */
    public function modoTesteInatividade(): bool
    {
        return in_array(app()->environment(), ['local', 'testing'], true)
            && filter_var(config('candidato.inatividade_modo_teste', false), FILTER_VALIDATE_BOOL);
    }

    public function prazoTesteSegundos(): int
    {
        return max(1, (int) config('candidato.inatividade_segundos', 30));
    }

    public function inatividadeHabilitada(): bool
    {
        return filter_var(config('candidato.inatividade_habilitada', true), FILTER_VALIDATE_BOOL);
    }

    public function limiteAtividade(): Carbon
    {
        return $this->modoTesteInatividade()
            ? now()->subSeconds($this->prazoTesteSegundos())
            : now()->subMonthsNoOverflow($this->prazoMeses());
    }

    public function estadoEfetivo(Candidato $candidato): string
    {
        if ($candidato->estaContratado()) {
            return self::CONTRATADO;
        }

        if (! $candidato->status) {
            return self::BLOQUEADO_MANUALMENTE;
        }

        // O encerramento voluntário mantém o candidato fora da plataforma mesmo com atividade
        // recente; continua no mesmo estado para que a P1 de reconsideração siga disponível.
        if ($this->estaInativoPorTempo($candidato) || $this->possuiEncerramentoVigente($candidato)) {
            return self::BLOQUEADO_POR_INATIVIDADE;
        }

        return self::ATIVO;
    }

    public function possuiEncerramentoVigente(Candidato $candidato): bool
    {
        if (! $candidato->exists) {
            return false;
        }

        return Candidato::query()
            ->whereKey($candidato->getKey())
            ->whereExists($this->subconsultaEncerramentoVigente($candidato->getTable()))
            ->exists();
    }

    /**
     * Encerramento vigente: decisão de saída (P1, P2, P3 ou P6) no ciclo atual do candidato
     * sem confirmação posterior de permanência na P1 do mesmo ciclo.
     */
    private function subconsultaEncerramentoVigente(string $tabelaCandidato): \Closure
    {
        return function ($sub) use ($tabelaCandidato) {
            $sub->selectRaw('1')
                ->from('candidato_reativacao_questionarios as enc')
                ->whereColumn('enc.candidato_matricula', $tabelaCandidato . '.matricula')
                ->whereRaw("enc.ciclo_inicio_em = COALESCE({$tabelaCandidato}.ultima_atualizacao_real_em, {$tabelaCandidato}.ultima_atividade_em)")
                ->where('enc.resultado', CandidatoReativacaoQuestionario::RESULTADO_ENCERRAMENTO_SOLICITADO)
                ->whereNotExists(function ($permanencia) {
                    $permanencia->selectRaw('1')
                        ->from('candidato_reativacao_questionarios as perm')
                        ->whereColumn('perm.candidato_matricula', 'enc.candidato_matricula')
                        ->whereColumn('perm.ciclo_inicio_em', 'enc.ciclo_inicio_em')
                        ->where('perm.pergunta_numero', 1)
                        ->where('perm.resposta_1', CandidatoReativacaoQuestionarioService::Q1_MANTER)
                        ->whereColumn('perm.id', '>', 'enc.id');
                });
        };
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
        $candidato->forceFill([
            'ultima_atividade_em' => now(),
            'ultima_atualizacao_real_em' => now(),
        ])->save();
    }

    public function bloquearManualmente(Candidato $candidato, ?string $motivoPublico = null, ?int $administradorPessoaId = null): bool
    {
        return DB::transaction(function () use ($candidato, $motivoPublico, $administradorPessoaId): bool {
            $bloqueado = Candidato::query()->lockForUpdate()->findOrFail($candidato->matricula);

            if ($bloqueado->estaContratado()) {
                return false;
            }

            $estadoAtual = $this->estadoEfetivo($bloqueado);

            if ($estadoAtual === self::BLOQUEADO_MANUALMENTE) {
                return false;
            }

            if ($bloqueado->status) {
                $bloqueado->forceFill(['status' => false])->save();
            }

            $this->bloqueioNotificationService->registrarBloqueio(
                $bloqueado,
                self::BLOQUEADO_MANUALMENTE,
                $motivoPublico,
                $administradorPessoaId
            );

            return true;
        });
    }

    public function registrarBloqueioPorInatividade(Candidato $candidato): void
    {
        if (! $candidato->status || $candidato->estaContratado()) {
            return;
        }

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
        $tabela = $query->getModel()->getTable();

        $query
            ->where('status', true)
            ->whereDoesntHave('contratacao')
            ->whereNotExists($this->subconsultaEncerramentoVigente($tabela));

        if ($this->inatividadeHabilitada()) {
            $query
                ->whereNotNull('ultima_atividade_em')
                ->where('ultima_atividade_em', '>', $this->limiteAtividade());
        }

        return $query;
    }

    /** Mesmo critério de estadoEfetivo() para BLOQUEADO_POR_INATIVIDADE, em SQL. */
    public function aplicarEscopoBloqueadosPorInatividade(Builder $query): Builder
    {
        $tabela = $query->getModel()->getTable();

        return $query
            ->where('status', true)
            ->whereDoesntHave('contratacao')
            ->where(function (Builder $bloqueado) use ($tabela) {
                if ($this->inatividadeHabilitada()) {
                    $bloqueado->whereNull('ultima_atividade_em')
                        ->orWhere('ultima_atividade_em', '<=', $this->limiteAtividade());
                }

                $bloqueado->orWhereExists($this->subconsultaEncerramentoVigente($tabela));
            });
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
