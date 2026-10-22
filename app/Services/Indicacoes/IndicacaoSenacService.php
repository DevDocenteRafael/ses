<?php

namespace App\Services\Indicacoes;

use App\Events\IndicacaoSenacStatusAlterado;
use App\Events\IndicacoesSenacCriadas;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\IndicacaoSenac;
use App\Models\IndicacaoSenacEvento;
use App\Models\Pessoa;
use App\Services\Candidatos\CandidatoStatusService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IndicacaoSenacService
{
    public const RELACOES_CANDIDATO = [
        'candidato.pessoa:id_pessoa,nome',
        'candidato.contratacao',
        'candidato.dadosAcademicos',
        'candidato.informacoesProfissionais',
        'candidato.preferenciasDeTrabalho',
    ];

    public function __construct(private readonly CandidatoStatusService $statusService) {}

    /**
     * Cria uma indicação para cada par candidato + empresa (produto cartesiano).
     * Tudo ou nada: se algum candidato ou empresa não for elegível, nada é gravado.
     * Pares que já têm indicação vigente são ignorados (sem duplicar e sem apagar histórico).
     *
     * @param  array<string>  $matriculas
     * @param  array<string>  $cnpjs
     */
    public function indicar(Pessoa $administrador, array $matriculas, array $cnpjs, ?string $observacao): array
    {
        $matriculas = array_values(array_unique(array_map('strval', $matriculas)));
        $cnpjs = array_values(array_unique(array_map(fn ($cnpj) => preg_replace('/\D+/', '', (string) $cnpj), $cnpjs)));
        $observacao = trim((string) $observacao) !== '' ? trim((string) $observacao) : null;

        $resultado = DB::transaction(function () use ($administrador, $matriculas, $cnpjs, $observacao): array {
            // O bloqueio das linhas dos candidatos serializa requisições simultâneas (ex.: clique duplo).
            $candidatos = Candidato::query()
                ->with(['pessoa:id_pessoa,nome', 'contratacao'])
                ->whereIn('matricula', $matriculas)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Candidato $candidato) => (string) $candidato->matricula);
            $this->validarCandidatos($matriculas, $candidatos);

            $empresas = Empresa::query()->whereIn('cnpj', $cnpjs)->get()->keyBy(fn (Empresa $empresa) => (string) $empresa->cnpj);
            $this->validarEmpresas($cnpjs, $empresas);

            $vigentes = IndicacaoSenac::query()
                ->whereIn('candidato_matricula', $matriculas)
                ->whereIn('empresa_cnpj', $cnpjs)
                ->where('ativa', 1)
                ->get(['candidato_matricula', 'empresa_cnpj'])
                ->map(fn (IndicacaoSenac $indicacao) => $indicacao->candidato_matricula . '|' . $indicacao->empresa_cnpj)
                ->flip();

            $criadas = [];
            $ignoradas = [];
            foreach ($matriculas as $matricula) {
                foreach ($cnpjs as $cnpj) {
                    if ($vigentes->has($matricula . '|' . $cnpj)) {
                        $ignoradas[] = $this->parIgnorado($candidatos[$matricula], $empresas[$cnpj]);
                        continue;
                    }

                    try {
                        $indicacao = IndicacaoSenac::query()->create([
                            'candidato_matricula' => $matricula,
                            'empresa_cnpj' => $cnpj,
                            'indicado_por_pessoa_id' => $administrador->id_pessoa,
                            'observacao' => $observacao,
                            'status' => IndicacaoSenac::STATUS_NOVA,
                            'ativa' => 1,
                            'indicado_em' => now(),
                        ]);
                    } catch (UniqueConstraintViolationException) {
                        // Corrida improvável não coberta pelo bloqueio: o banco garante a unicidade.
                        $ignoradas[] = $this->parIgnorado($candidatos[$matricula], $empresas[$cnpj]);
                        continue;
                    }

                    IndicacaoSenacEvento::query()->create([
                        'indicacao_id' => $indicacao->id,
                        'tipo' => IndicacaoSenacEvento::TIPO_CRIADA,
                        'status_anterior' => null,
                        'status_novo' => IndicacaoSenac::STATUS_NOVA,
                        'pessoa_id' => $administrador->id_pessoa,
                    ]);
                    $criadas[] = $indicacao->id;
                }
            }

            return ['criadas' => $criadas, 'ignoradas' => $ignoradas];
        });

        if ($resultado['criadas'] !== []) {
            IndicacoesSenacCriadas::dispatch($resultado['criadas']);
        }

        $total = count($matriculas) * count($cnpjs);

        return [
            'message' => $this->mensagemResultado(count($resultado['criadas']), count($resultado['ignoradas'])),
            'total_solicitado' => $total,
            'criadas' => count($resultado['criadas']),
            'ignoradas' => $resultado['ignoradas'],
        ];
    }

    public function atualizarStatus(IndicacaoSenac $indicacao, string $status, Pessoa $responsavel): IndicacaoSenac
    {
        $anterior = DB::transaction(function () use ($indicacao, $status, $responsavel): ?string {
            $atual = IndicacaoSenac::query()->lockForUpdate()->findOrFail($indicacao->id);
            $anterior = $atual->status;

            if ($anterior === $status) {
                return null;
            }

            try {
                $atual->forceFill([
                    'status' => $status,
                    // "Sem interesse" encerra a indicação e libera uma futura reindicação do mesmo par.
                    'ativa' => $status === IndicacaoSenac::STATUS_SEM_INTERESSE ? null : 1,
                ])->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'status' => 'Já existe uma indicação mais recente e vigente deste candidato para a sua empresa.',
                ]);
            }

            IndicacaoSenacEvento::query()->create([
                'indicacao_id' => $atual->id,
                'tipo' => IndicacaoSenacEvento::TIPO_STATUS_ALTERADO,
                'status_anterior' => $anterior,
                'status_novo' => $status,
                'pessoa_id' => $responsavel->id_pessoa,
            ]);

            return $anterior;
        });

        if ($anterior !== null) {
            IndicacaoSenacStatusAlterado::dispatch((int) $indicacao->id, $anterior, $status);
        }

        return $indicacao->fresh(self::RELACOES_CANDIDATO);
    }

    /**
     * Visão da empresa: só expõe os dados profissionais enquanto o candidato estiver disponível.
     * Indisponível (inatividade, bloqueio, contratação): mantém o histórico apenas com nome e aviso.
     */
    public function formatarParaEmpresa(IndicacaoSenac $indicacao): array
    {
        $candidato = $indicacao->candidato;
        $disponivel = $candidato && $this->statusService->estaDisponivel($candidato);

        return [
            'id' => $indicacao->id,
            'status' => $indicacao->status,
            'status_rotulo' => IndicacaoSenac::rotuloStatus($indicacao->status),
            'observacao' => $indicacao->observacao,
            'indicado_em' => $indicacao->indicado_em?->toIso8601String(),
            'atualizado_em' => $indicacao->updated_at?->toIso8601String(),
            'candidato' => $disponivel
                ? ['disponivel' => true, ...$this->resumoProfissional($candidato)]
                : ['disponivel' => false, 'nome' => $candidato?->pessoa?->nome],
        ];
    }

    public function formatarParaAdministrativo(IndicacaoSenac $indicacao, bool $comEventos = false): array
    {
        $candidato = $indicacao->candidato;
        $estado = $candidato ? $this->statusService->estadoEfetivo($candidato) : null;

        $dados = [
            'id' => $indicacao->id,
            'status' => $indicacao->status,
            'status_rotulo' => IndicacaoSenac::rotuloStatus($indicacao->status),
            'ativa' => (bool) $indicacao->ativa,
            'observacao' => $indicacao->observacao,
            'indicado_em' => $indicacao->indicado_em?->toIso8601String(),
            'atualizado_em' => $indicacao->updated_at?->toIso8601String(),
            'indicado_por' => $indicacao->indicadoPor?->nome,
            'empresa' => [
                'cnpj' => $indicacao->empresa?->cnpj,
                'razao_social' => $indicacao->empresa?->razao_social,
                'status' => (bool) $indicacao->empresa?->status,
            ],
            'candidato' => [
                'estado_efetivo' => $estado,
                'estado_efetivo_rotulo' => $estado ? $this->statusService->rotulo($estado) : null,
                ...($candidato ? $this->resumoProfissional($candidato) : []),
            ],
        ];

        if ($comEventos) {
            $dados['eventos'] = $indicacao->eventos->map(fn (IndicacaoSenacEvento $evento) => [
                'tipo' => $evento->tipo,
                'status_anterior' => $evento->status_anterior,
                'status_anterior_rotulo' => IndicacaoSenac::rotuloStatus($evento->status_anterior),
                'status_novo' => $evento->status_novo,
                'status_novo_rotulo' => IndicacaoSenac::rotuloStatus($evento->status_novo),
                'responsavel' => $evento->pessoa?->nome,
                'em' => $evento->created_at?->toIso8601String(),
            ])->all();
        }

        return $dados;
    }

    private function resumoProfissional(Candidato $candidato): array
    {
        $academico = $candidato->dadosAcademicos->first();
        $info = $candidato->informacoesProfissionais;
        $preferencias = $candidato->preferenciasDeTrabalho;
        $mascara = (int) ($preferencias?->tipo_de_contratacao ?? 0);

        return [
            'matricula' => (string) $candidato->matricula,
            'nome' => $candidato->pessoa?->nome,
            'curso' => $academico?->curso,
            'segmento' => $academico?->segmento ?: $info?->area_de_atuacao,
            'habilidades' => array_values(array_filter((array) ($info?->habilidades ?? []))),
            'disponibilidade' => array_values((array) ($preferencias?->disponibilidade_de_horario ?? [])),
            'tipos_contratacao' => array_values(array_filter([($mascara & 1) ? 'CLT' : null, ($mascara & 2) ? 'Estágio' : null])),
        ];
    }

    private function validarCandidatos(array $matriculas, Collection $candidatos): void
    {
        $inexistentes = array_values(array_diff($matriculas, $candidatos->keys()->all()));
        if ($inexistentes !== []) {
            throw ValidationException::withMessages(['candidatos' => 'Candidato não encontrado: ' . implode(', ', $inexistentes) . '.']);
        }

        // Elegibilidade conferida no momento da confirmação: mesma regra que define quem as empresas veem.
        $indisponiveis = $candidatos
            ->reject(fn (Candidato $candidato) => $this->statusService->estaDisponivel($candidato))
            ->map(fn (Candidato $candidato) => ($candidato->pessoa?->nome ?? $candidato->matricula)
                . ' (' . $this->statusService->rotulo($this->statusService->estadoEfetivo($candidato)) . ')')
            ->values();
        if ($indisponiveis->isNotEmpty()) {
            throw ValidationException::withMessages([
                'candidatos' => 'Candidatos indisponíveis para indicação: ' . $indisponiveis->implode(', ') . '. Remova-os da seleção e tente novamente.',
            ]);
        }
    }

    private function validarEmpresas(array $cnpjs, Collection $empresas): void
    {
        $inexistentes = array_values(array_diff($cnpjs, $empresas->keys()->all()));
        if ($inexistentes !== []) {
            throw ValidationException::withMessages(['empresas' => 'Empresa não encontrada: ' . implode(', ', $inexistentes) . '.']);
        }

        $bloqueadas = $empresas->reject(fn (Empresa $empresa) => (bool) $empresa->status)->pluck('razao_social')->values();
        if ($bloqueadas->isNotEmpty()) {
            throw ValidationException::withMessages([
                'empresas' => 'Empresas sem acesso ao portal não podem receber indicações: ' . $bloqueadas->implode(', ') . '.',
            ]);
        }
    }

    private function parIgnorado(Candidato $candidato, Empresa $empresa): array
    {
        return [
            'candidato' => $candidato->pessoa?->nome ?? (string) $candidato->matricula,
            'empresa' => $empresa->razao_social,
            'motivo' => 'Já existe indicação vigente deste candidato para esta empresa.',
        ];
    }

    private function mensagemResultado(int $criadas, int $ignoradas): string
    {
        if ($criadas === 0) {
            return 'Nenhuma nova indicação foi criada: todos os pares selecionados já têm indicação vigente.';
        }

        $texto = $criadas === 1 ? '1 indicação registrada com sucesso.' : "{$criadas} indicações registradas com sucesso.";

        return $ignoradas > 0
            ? $texto . ' ' . ($ignoradas === 1 ? '1 par foi ignorado' : "{$ignoradas} pares foram ignorados") . ' por já ter indicação vigente.'
            : $texto;
    }
}
