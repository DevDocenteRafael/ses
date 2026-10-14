<?php

namespace App\Services\Candidatos;

use App\Models\Candidato;
use App\Models\CandidatoBloqueioNotificacao;
use App\Models\CandidatoReativacaoQuestionario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CandidatoReativacaoQuestionarioService
{
    public const Q1_MANTER = 'manter_ativo';
    public const Q1_ENCERRAR = 'encerrar_cadastro';

    private const OPCOES = [
        'resposta_1' => [self::Q1_MANTER, self::Q1_ENCERRAR],
        'resposta_2' => ['buscando_area_senac', 'buscando_qualquer_area', 'nao_busco_mas_manter'],
        'resposta_3' => ['nao_trabalhando', 'trabalhando_busco', 'trabalhando_nao_busco', 'autonomo_empreendedor'],
        'resposta_4' => ['jovem_aprendiz', 'estagio', 'emprego_efetivo', 'temporario', 'autonomo_servicos', 'outras'],
        'resposta_5' => ['manha', 'tarde', 'noite', 'integral', 'escala_flexivel'],
        'resposta_6' => ['presencial_online', 'apenas_online', 'apenas_presencial', 'sem_disponibilidade'],
        'resposta_7' => ['atualizados', 'atualizar_contato', 'atualizar_profissionais'],
        'resposta_8' => ['mesmas_informacoes', 'desejo_atualizar'],
    ];

    public function __construct(private readonly CandidatoStatusService $statusService) {}

    public function concluir(Candidato $candidato, array $respostas): array
    {
        $this->validarRespostas($respostas);

        return DB::transaction(function () use ($candidato, $respostas): array {
            $bloqueado = Candidato::with(['contratacao', 'bloqueioNotificacoes'])->lockForUpdate()->findOrFail($candidato->matricula);
            $estado = $this->statusService->estadoEfetivo($bloqueado);

            if ($estado === CandidatoStatusService::CONTRATADO) {
                throw ValidationException::withMessages(['candidato' => 'Candidato contratado não pode ser reativado por questionário.']);
            }

            $conclusaoRecente = CandidatoReativacaoQuestionario::query()
                ->where('candidato_matricula', $bloqueado->matricula)
                ->latest('concluido_em')
                ->lockForUpdate()
                ->first();

            if ($estado === CandidatoStatusService::ATIVO && $conclusaoRecente?->resultado === CandidatoReativacaoQuestionario::RESULTADO_REATIVADO) {
                return $this->respostaConclusao($conclusaoRecente, $bloqueado);
            }

            if ($estado === CandidatoStatusService::BLOQUEADO_MANUALMENTE) {
                throw ValidationException::withMessages(['candidato' => 'Bloqueio administrativo não pode ser removido por questionário.']);
            }

            if ($estado !== CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE) {
                throw ValidationException::withMessages(['candidato' => 'Não há reativação por inatividade pendente.']);
            }

            $bloqueadoEm = $this->bloqueadoEm($bloqueado);
            $existente = CandidatoReativacaoQuestionario::query()
                ->where('candidato_matricula', $bloqueado->matricula)
                ->where('bloqueado_em', $bloqueadoEm)
                ->lockForUpdate()
                ->first();

            if ($existente) {
                return $this->respostaConclusao($existente, $bloqueado);
            }

            $querManter = $respostas['resposta_1'] === self::Q1_MANTER;
            if ($querManter) {
                $this->validarQuestionarioCompleto($respostas);
            }

            $questionario = CandidatoReativacaoQuestionario::query()->create([
                'candidato_matricula' => $bloqueado->matricula,
                'bloqueado_em' => $bloqueadoEm,
                'bloqueio_notificacao_id' => $this->notificacaoEvento($bloqueado, $bloqueadoEm)?->id,
                'resposta_1' => $respostas['resposta_1'],
                'resposta_2' => $respostas['resposta_2'] ?? null,
                'resposta_3' => $respostas['resposta_3'] ?? null,
                'resposta_4' => $respostas['resposta_4'] ?? null,
                'resposta_5' => $respostas['resposta_5'] ?? null,
                'resposta_6' => $respostas['resposta_6'] ?? null,
                'resposta_7' => $respostas['resposta_7'] ?? null,
                'resposta_8' => $respostas['resposta_8'] ?? null,
                'resultado' => $querManter ? CandidatoReativacaoQuestionario::RESULTADO_REATIVADO : CandidatoReativacaoQuestionario::RESULTADO_ENCERRAMENTO_SOLICITADO,
                'orientacoes' => $this->orientacoes($respostas),
                'concluido_em' => now(),
            ]);

            if ($querManter) {
                $this->statusService->registrarAtividade($bloqueado);
                $bloqueado->refresh()->load('contratacao');
            }

            return $this->respostaConclusao($questionario, $bloqueado);
        });
    }

    private function validarRespostas(array $respostas): void
    {
        if (! in_array($respostas['resposta_1'] ?? null, self::OPCOES['resposta_1'], true)) {
            throw ValidationException::withMessages(['resposta_1' => 'Informe se deseja manter o cadastro ativo.']);
        }

        foreach ($respostas as $campo => $valor) {
            if ($campo === 'resposta_4') {
                if (! is_array($valor) || array_diff($valor, self::OPCOES['resposta_4'])) {
                    throw ValidationException::withMessages([$campo => 'Selecione oportunidades válidas.']);
                }
                continue;
            }

            if (isset(self::OPCOES[$campo]) && ! in_array($valor, self::OPCOES[$campo], true)) {
                throw ValidationException::withMessages([$campo => 'Resposta inválida.']);
            }
        }
    }

    private function validarQuestionarioCompleto(array $respostas): void
    {
        foreach (array_keys(self::OPCOES) as $campo) {
            if ($campo === 'resposta_4') {
                if (empty($respostas[$campo]) || ! is_array($respostas[$campo])) {
                    throw ValidationException::withMessages([$campo => 'Selecione pelo menos uma alternativa.']);
                }
                continue;
            }

            if (empty($respostas[$campo])) {
                throw ValidationException::withMessages([$campo => 'Esta pergunta é obrigatória.']);
            }
        }
    }

    private function bloqueadoEm(Candidato $candidato): \Illuminate\Support\Carbon
    {
        return ($candidato->ultima_atividade_em?->copy() ?? now())->startOfSecond();
    }

    private function notificacaoEvento(Candidato $candidato, $bloqueadoEm): ?CandidatoBloqueioNotificacao
    {
        return $candidato->bloqueioNotificacoes()
            ->where('tipo_bloqueio', CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE)
            ->where('bloqueado_em', $bloqueadoEm)
            ->latest('id')
            ->first();
    }

    private function orientacoes(array $respostas): array
    {
        $orientacoes = [];
        if (in_array($respostas['resposta_7'] ?? null, ['atualizar_contato', 'atualizar_profissionais'], true)) {
            $orientacoes[] = 'Acesse Meu Perfil para atualizar seus dados de contato ou informações profissionais.';
        }
        if (($respostas['resposta_8'] ?? null) === 'desejo_atualizar') {
            $orientacoes[] = 'Atualize seus objetivos profissionais e disponibilidade nos formulários do perfil.';
        }

        return $orientacoes;
    }

    private function respostaConclusao(CandidatoReativacaoQuestionario $questionario, Candidato $candidato): array
    {
        return [
            'message' => $questionario->resultado === CandidatoReativacaoQuestionario::RESULTADO_REATIVADO
                ? 'Cadastro reativado com sucesso.'
                : 'Sua decisão foi registrada. O perfil continuará indisponível para novas oportunidades.',
            'resultado' => $questionario->resultado,
            'reativado' => $questionario->resultado === CandidatoReativacaoQuestionario::RESULTADO_REATIVADO,
            'orientacoes' => $questionario->orientacoes ?? [],
            'restricoes' => [
                'contratado' => false,
                'inativo' => $this->statusService->estadoEfetivo($candidato) === CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE,
                'estado' => $this->statusService->estadoEfetivo($candidato),
                'estado_rotulo' => $this->statusService->rotulo($this->statusService->estadoEfetivo($candidato)),
                'pode_editar_perfil' => $this->statusService->estadoEfetivo($candidato) === CandidatoStatusService::ATIVO,
                'code' => $this->statusService->estadoEfetivo($candidato) === CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE ? CandidatoStatusService::INATIVIDADE_CODE : null,
            ],
        ];
    }
}
