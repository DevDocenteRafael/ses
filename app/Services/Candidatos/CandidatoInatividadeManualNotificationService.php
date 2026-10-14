<?php

namespace App\Services\Candidatos;

use App\Jobs\EnviarNotificacaoInatividadeCandidatoJob;
use App\Mail\CandidatoBloqueadoMail;
use App\Models\Candidato;
use App\Models\CandidatoBloqueioNotificacao;
use App\Models\CandidatoInatividadeCampanha;
use App\Models\Pessoa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CandidatoInatividadeManualNotificationService
{
    private const LOCK_KEY = 'notificacao-inativos-global';

    public function __construct(private readonly CandidatoStatusService $statusService) {}

    public function consultaElegiveis(): Builder
    {
        return Candidato::query()
            ->with('pessoa')
            ->where('status', true)
            ->whereDoesntHave('contratacao')
            ->whereHas('pessoa', fn (Builder $query) => $query->whereNotNull('email')->where('email', 'like', '%@%'))
            ->where(function (Builder $query) {
                $query->whereNull('ultima_atividade_em')->orWhere('ultima_atividade_em', '<=', $this->statusService->limiteAtividade());
            });
    }

    public function contarElegiveis(): int
    {
        return $this->statusService->inatividadeHabilitada() ? $this->consultaElegiveis()->count() : 0;
    }

    public function resumo(): array
    {
        $total = $this->contarElegiveis();
        $limite = $this->limiteTeste();
        $campanha = $this->campanhaMaisRecente();

        return [
            'total_elegiveis' => $total,
            'limite_teste' => $limite,
            'destinatarios_execucao' => $limite ? min($total, $limite) : $total,
            'ambiente' => app()->environment(),
            'envio_global_habilitado' => filter_var(config('candidato.notificacao_inativos_em_massa_habilitada', false), FILTER_VALIDATE_BOOL),
            'campanha' => $campanha ? $this->statusCampanha($campanha) : null,
        ];
    }

    public function iniciarEnvioGlobal(Pessoa $administrador): CandidatoInatividadeCampanha
    {
        $this->validarEnvioGlobalHabilitado();
        $this->validarSemCampanhaAtiva();
        $this->liberarLockOrfaoSeSeguro();
        $lock = Cache::lock(self::LOCK_KEY, 600);

        if (! $lock->get()) throw ValidationException::withMessages(['notificacao' => 'Já existe um envio de notificações em andamento.']);

        $campanha = DB::transaction(function () use ($administrador) {
            $total = $this->destinatariosDaExecucao();

            return CandidatoInatividadeCampanha::query()->create([
                'tipo' => CandidatoInatividadeCampanha::TIPO_GLOBAL,
                'status' => CandidatoInatividadeCampanha::STATUS_PROCESSANDO,
                'administrador_pessoa_id' => $administrador->id_pessoa,
                'quantidade_prevista' => $total,
                'pendentes' => $total,
                'iniciado_em' => now(),
            ]);
        });

        if ($campanha->quantidade_prevista === 0) {
            $campanha->forceFill(['status' => CandidatoInatividadeCampanha::STATUS_FINALIZADA, 'finalizado_em' => now()])->save();
            $lock->release();
            return $campanha;
        }

        $matriculas = $this->consultaElegiveis()->select('matricula')->orderBy('matricula')->limit($campanha->quantidade_prevista)->pluck('matricula');
        foreach ($matriculas as $matricula) {
            EnviarNotificacaoInatividadeCandidatoJob::dispatch((string) $matricula, CandidatoBloqueioNotificacao::ORIGEM_MANUAL_GLOBAL, $administrador->id_pessoa, $campanha->id);
        }

        $lock->release();

        return $campanha;
    }

    public function enviarIndividual(Candidato $candidato, Pessoa $administrador): CandidatoBloqueioNotificacao
    {
        $this->validarElegibilidade($candidato);
        $this->validarIntervaloMinimo($candidato);

        $notificacao = $this->criarNotificacao($candidato, CandidatoBloqueioNotificacao::ORIGEM_MANUAL_INDIVIDUAL, $administrador->id_pessoa);
        EnviarNotificacaoInatividadeCandidatoJob::dispatchSync((string) $candidato->matricula, CandidatoBloqueioNotificacao::ORIGEM_MANUAL_INDIVIDUAL, $administrador->id_pessoa, null, $notificacao->id);

        return $notificacao->fresh();
    }

    public function processarEnvio(string $matricula, string $origem, ?int $administradorPessoaId = null, ?int $campanhaId = null, ?int $notificacaoId = null): void
    {
        $candidato = Candidato::query()->with('pessoa', 'contratacao')->find($matricula);
        $campanha = $campanhaId ? CandidatoInatividadeCampanha::query()->find($campanhaId) : null;
        $notificacao = null;

        try {
            if (! $candidato) {
                throw ValidationException::withMessages(['candidato' => 'Candidato não encontrado.']);
            }

            $this->validarElegibilidade($candidato);
            $this->validarIntervaloMinimo($candidato, $notificacaoId);
            $notificacao = $notificacaoId ? CandidatoBloqueioNotificacao::query()->findOrFail($notificacaoId) : $this->criarNotificacao($candidato, $origem, $administradorPessoaId, $campanhaId);

            $bloqueada = CandidatoBloqueioNotificacao::query()
                ->whereKey($notificacao->getKey())
                ->whereIn('status', [CandidatoBloqueioNotificacao::STATUS_PENDENTE, CandidatoBloqueioNotificacao::STATUS_FALHOU])
                ->update(['status' => CandidatoBloqueioNotificacao::STATUS_PROCESSANDO, 'tentativa_envio_em' => now(), 'erro' => null, 'updated_at' => now()]);

            if ($bloqueada === 0) {
                $this->registrarResultadoCampanha($campanha, 'ignorados');
                return;
            }

            $notificacao->refresh();

            Mail::to($candidato->pessoa->email)->send(new CandidatoBloqueadoMail($candidato, CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE, 'Inatividade na plataforma.', $this->urlLogin()));

            $notificacao->forceFill(['status' => CandidatoBloqueioNotificacao::STATUS_ENVIADO, 'tentativa_envio_em' => $notificacao->tentativa_envio_em ?? now(), 'enviado_em' => now(), 'erro' => null])->save();
            $this->registrarResultadoCampanha($campanha, 'enviados');
        } catch (ValidationException $e) {
            $this->registrarResultadoCampanha($campanha, 'ignorados');
        } catch (\Throwable $e) {
            if ($notificacao) {
                $notificacao->forceFill(['status' => CandidatoBloqueioNotificacao::STATUS_FALHOU, 'erro' => substr($e->getMessage(), 0, 1000)])->save();
            }
            $this->registrarResultadoCampanha($campanha, 'falhas');
        }
    }

    public function validarElegibilidade(Candidato $candidato): void
    {
        $candidato->loadMissing('pessoa', 'contratacao');
        $estado = $this->statusService->estadoEfetivo($candidato);

        if ($estado === CandidatoStatusService::CONTRATADO) throw ValidationException::withMessages(['candidato' => 'Este candidato está contratado e não pode receber esta notificação.']);
        if ($estado === CandidatoStatusService::BLOQUEADO_MANUALMENTE) throw ValidationException::withMessages(['candidato' => 'Este candidato possui um bloqueio administrativo e não pode receber esta notificação de reativação.']);
        if ($estado !== CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE) throw ValidationException::withMessages(['candidato' => 'Este candidato não está bloqueado por inatividade.']);
        if (! filter_var($candidato->pessoa?->email, FILTER_VALIDATE_EMAIL)) throw ValidationException::withMessages(['candidato' => 'Este candidato não possui um e-mail válido cadastrado.']);
    }

    private function criarNotificacao(Candidato $candidato, string $origem, ?int $administradorPessoaId = null, ?int $campanhaId = null): CandidatoBloqueioNotificacao
    {
        $bloqueadoEm = $candidato->ultima_atividade_em?->copy()->startOfSecond() ?? now()->startOfSecond();

        return CandidatoBloqueioNotificacao::query()->firstOrCreate([
            'candidato_matricula' => $candidato->matricula,
            'tipo_bloqueio' => CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE,
            'bloqueado_em' => $bloqueadoEm,
        ], ['tentativa_envio_em' => now(), 'status' => CandidatoBloqueioNotificacao::STATUS_PENDENTE, 'origem' => $origem, 'administrador_pessoa_id' => $administradorPessoaId, 'campanha_id' => $campanhaId, 'motivo_publico' => 'Inatividade na plataforma.']);
    }

    private function validarIntervaloMinimo(Candidato $candidato, ?int $notificacaoIgnoradaId = null): void
    {
        $minutos = max(0, (int) config('candidato.notificacao_inativos_intervalo_minimo_minutos', 60));
        if ($minutos === 0) return;

        $recente = $candidato->bloqueioNotificacoes()->where('tipo_bloqueio', CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE)->where('status', CandidatoBloqueioNotificacao::STATUS_ENVIADO)->when($notificacaoIgnoradaId, fn ($query) => $query->whereKeyNot($notificacaoIgnoradaId))->where('enviado_em', '>=', now()->subMinutes($minutos))->exists();
        if ($recente) throw ValidationException::withMessages(['candidato' => 'Este candidato recebeu uma notificação recentemente. Aguarde antes de reenviar.']);
    }

    private function validarEnvioGlobalHabilitado(): void
    {
        $habilitado = filter_var(config('candidato.notificacao_inativos_em_massa_habilitada', false), FILTER_VALIDATE_BOOL);
        if (app()->environment('production') && ! $habilitado) throw ValidationException::withMessages(['ambiente' => 'O envio global de notificações não está habilitado em produção.']);
    }

    private function validarSemCampanhaAtiva(): void
    {
        $campanha = $this->campanhaMaisRecente();
        if (! $campanha) return;

        $status = $this->statusCampanha($campanha);
        if (in_array($status['estado'], ['processando', 'aguardando_worker'], true)) {
            throw ValidationException::withMessages(['notificacao' => $status['estado'] === 'aguardando_worker' ? 'O envio está aguardando processamento.' : 'Já existe um envio de notificações em andamento.']);
        }
    }

    private function liberarLockOrfaoSeSeguro(): void
    {
        $campanha = $this->campanhaMaisRecente();
        $status = $campanha ? $this->statusCampanha($campanha) : null;

        if ($status && in_array($status['estado'], ['processando', 'aguardando_worker'], true)) return;

        if (Schema::hasTable('jobs') && DB::table('jobs')->where('payload', 'like', '%EnviarNotificacaoInatividadeCandidatoJob%')->exists()) return;

        if ($this->lockExisteNoCache()) {
            Cache::lock(self::LOCK_KEY)->forceRelease();
        }
    }

    private function lockExisteNoCache(): bool
    {
        if (! Schema::hasTable('cache')) return false;

        $prefixo = (string) config('cache.prefix', '');
        $chaves = [self::LOCK_KEY, $prefixo . self::LOCK_KEY, Str::slug((string) config('app.name', 'laravel')) . '-cache-' . self::LOCK_KEY];

        return DB::table('cache')->whereIn('key', array_unique($chaves))->exists();
    }

    public function campanhaMaisRecente(): ?CandidatoInatividadeCampanha
    {
        if (! Schema::hasTable('candidato_inatividade_campanhas')) return null;

        return CandidatoInatividadeCampanha::query()->latest()->first();
    }

    public function statusCampanha(CandidatoInatividadeCampanha $campanha): array
    {
        $jobsPendentes = Schema::hasTable('jobs') ? DB::table('jobs')->where('payload', 'like', '%"campanhaId";i:' . $campanha->id . ';%')->count() : 0;
        $processados = (int) $campanha->enviados + (int) $campanha->ignorados + (int) $campanha->falhas;
        $estado = $campanha->status;
        $travada = false;

        if ($campanha->status === CandidatoInatividadeCampanha::STATUS_PROCESSANDO && $processados === 0 && $jobsPendentes === 0) {
            $estado = CandidatoInatividadeCampanha::STATUS_INTERROMPIDA;
            $travada = true;
        } elseif ($campanha->status === CandidatoInatividadeCampanha::STATUS_PROCESSANDO && $jobsPendentes > 0 && $processados === 0) {
            $estado = CandidatoInatividadeCampanha::STATUS_AGUARDANDO_WORKER;
        }

        return [
            'id' => $campanha->id,
            'estado' => $estado,
            'travada' => $travada,
            'total_previsto' => (int) $campanha->quantidade_prevista,
            'processados' => $processados,
            'enviados' => (int) $campanha->enviados,
            'falhas' => (int) $campanha->falhas,
            'ignorados' => (int) $campanha->ignorados,
            'pendentes' => (int) $campanha->pendentes,
            'jobs_pendentes' => $jobsPendentes,
            'criada_em' => $campanha->created_at,
            'iniciada_em' => $campanha->iniciado_em,
            'atualizada_em' => $campanha->updated_at,
            'finalizada_em' => $campanha->finalizado_em,
        ];
    }

    private function limiteTeste(): ?int
    {
        if (app()->environment('production')) return null;
        $valor = config('candidato.notificacao_inativos_limite_teste');
        return is_numeric($valor) && (int) $valor > 0 ? (int) $valor : null;
    }

    private function destinatariosDaExecucao(): int
    {
        $total = $this->contarElegiveis();
        $limite = $this->limiteTeste();

        return $limite ? min($total, $limite) : $total;
    }

    private function tamanhoLote(): int
    {
        return max(1, (int) config('candidato.notificacao_inativos_lote', 100));
    }

    private function registrarResultadoCampanha(?CandidatoInatividadeCampanha $campanha, string $coluna): void
    {
        if (! $campanha) return;
        $campanha->increment($coluna);
        $campanha->decrement('pendentes');
        $campanha->refresh();

        if ($campanha->pendentes <= 0) {
            $campanha->forceFill(['status' => CandidatoInatividadeCampanha::STATUS_FINALIZADA, 'finalizado_em' => now()])->save();
            Cache::lock(self::LOCK_KEY)->forceRelease();
        }
    }

    private function urlLogin(): string
    {
        return rtrim((string) config('portal.frontend_url'), '/') . '/' . ltrim((string) config('portal.login_path', '/login'), '/');
    }
}
