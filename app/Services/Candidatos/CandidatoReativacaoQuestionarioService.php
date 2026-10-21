<?php

namespace App\Services\Candidatos;

use App\Models\Candidato;
use App\Models\CandidatoBloqueioNotificacao;
use App\Models\CandidatoReativacaoQuestionario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CandidatoReativacaoQuestionarioService
{
    public const Q1_MANTER = 'manter_ativo';
    public const Q1_ENCERRAR = 'encerrar_cadastro';

    private const JANELA_CLIQUE_DUPLO_SEGUNDOS = 10;

    public const SAIDA_CONFIRMAR = 'confirmar';
    public const SAIDA_PERGUNTAR = 'perguntar';

    /**
     * Classificação única das respostas que levam ao encerramento da participação.
     * CONFIRMAR: a resposta só é aceita com a confirmação de saída do candidato.
     * PERGUNTAR: o candidato decide se continua (resposta normal) ou sai (com confirmação).
     * O frontend recebe essa classificação em cada opção (campo "saida").
     */
    private const RESPOSTAS_DE_SAIDA = [
        'resposta_1' => ['encerrar_cadastro' => self::SAIDA_CONFIRMAR],
        'resposta_2' => ['buscando_qualquer_area' => self::SAIDA_CONFIRMAR],
        'resposta_3' => [
            'trabalhando_nao_busco' => self::SAIDA_CONFIRMAR,
            'autonomo_empreendedor' => self::SAIDA_PERGUNTAR,
        ],
        'resposta_6' => ['sem_disponibilidade' => self::SAIDA_CONFIRMAR],
    ];

    private const PERGUNTAS = [
        1 => [
            'campo' => 'resposta_1',
            'titulo' => 'Você deseja manter seu cadastro ativo no Sistema de Empregabilidade do Senac?',
            'opcoes' => [
                ['valor' => self::Q1_MANTER, 'label' => 'Sim, desejo continuar recebendo oportunidades.'],
                ['valor' => self::Q1_ENCERRAR, 'label' => 'Não, desejo encerrar meu cadastro.'],
            ],
        ],
        2 => [
            'campo' => 'resposta_2',
            'titulo' => 'Atualmente, você está buscando uma oportunidade de emprego ou estágio na área em que se formou no Senac?',
            'opcoes' => [
                ['valor' => 'buscando_area_senac', 'label' => 'Sim, estou procurando emprego ou estágio na minha área de formação no Senac.'],
                ['valor' => 'buscando_qualquer_area', 'label' => 'Não, estou procurando emprego ou estágio em qualquer área.'],
                ['valor' => 'nao_busco_mas_manter', 'label' => 'Não estou procurando emprego ou estágio no momento, mas desejo manter meu cadastro ativo.'],
            ],
        ],
        3 => [
            'campo' => 'resposta_3',
            'titulo' => 'Qual é sua situação profissional atual?',
            'opcoes' => [
                ['valor' => 'nao_trabalhando', 'label' => 'Não estou trabalhando.'],
                ['valor' => 'trabalhando_busco', 'label' => 'Estou trabalhando, mas busco novas oportunidades.'],
                ['valor' => 'trabalhando_nao_busco', 'label' => 'Estou trabalhando e não busco mudança no momento.'],
                ['valor' => 'autonomo_empreendedor', 'label' => 'Estou trabalhando como autônomo(a) ou empreendedor(a).'],
            ],
        ],
        4 => [
            'campo' => 'resposta_4',
            'titulo' => 'Que tipo de oportunidade profissional você procura?',
            'instrucao' => 'Marque todas as alternativas que se aplicam.',
            'multipla' => true,
            'opcoes' => [
                ['valor' => 'jovem_aprendiz', 'label' => 'Jovem Aprendiz.'],
                ['valor' => 'estagio', 'label' => 'Estágio.'],
                ['valor' => 'emprego_efetivo', 'label' => 'Emprego efetivo.'],
                ['valor' => 'temporario', 'label' => 'Trabalho temporário.'],
                ['valor' => 'autonomo_servicos', 'label' => 'Trabalho autônomo ou prestação de serviços.'],
                ['valor' => 'outras', 'label' => 'Outras oportunidades.'],
            ],
        ],
        5 => [
            'campo' => 'resposta_5',
            'titulo' => 'Qual é sua disponibilidade de horário para trabalhar?',
            'opcoes' => [
                ['valor' => 'manha', 'label' => 'Manhã.'],
                ['valor' => 'tarde', 'label' => 'Tarde.'],
                ['valor' => 'noite', 'label' => 'Noite.'],
                ['valor' => 'integral', 'label' => 'Integral.'],
                ['valor' => 'escala_flexivel', 'label' => 'Escala ou horários flexíveis.'],
            ],
        ],
        6 => [
            'campo' => 'resposta_6',
            'titulo' => 'Você tem disponibilidade para participar de processos seletivos e entrevistas?',
            'opcoes' => [
                ['valor' => 'presencial_online', 'label' => 'Sim, presencialmente e/ou on-line.'],
                ['valor' => 'apenas_online', 'label' => 'Sim, apenas on-line.'],
                ['valor' => 'apenas_presencial', 'label' => 'Sim, apenas presencialmente.'],
                ['valor' => 'sem_disponibilidade', 'label' => 'Não tenho disponibilidade no momento.'],
            ],
        ],
        7 => [
            'campo' => 'resposta_7',
            'titulo' => 'Seus dados de contato e suas informações profissionais continuam atualizados?',
            'opcoes' => [
                ['valor' => 'atualizados', 'label' => 'Sim, estão atualizados.'],
                ['valor' => 'atualizar_contato', 'label' => 'Preciso atualizar meus dados de contato.'],
                ['valor' => 'atualizar_profissionais', 'label' => 'Preciso atualizar minhas informações profissionais, como curso, currículo ou experiências profissionais.'],
            ],
        ],
        8 => [
            'campo' => 'resposta_8',
            'titulo' => 'Você deseja informar alguma atualização sobre seus objetivos profissionais ou sua disponibilidade para novas oportunidades?',
            'opcoes' => [
                ['valor' => 'mesmas_informacoes', 'label' => 'Não, minhas informações permanecem as mesmas.'],
                ['valor' => 'desejo_atualizar', 'label' => 'Sim, desejo atualizar minhas informações.'],
            ],
        ],
    ];

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

    public function perguntaAtual(Candidato $candidato): ?array
    {
        $estado = $this->statusService->estadoEfetivo($candidato);
        if ($estado !== CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE) {
            return null;
        }

        $ultimaAtualizacaoReal = $this->ultimaAtualizacaoReal($candidato);
        if (! $ultimaAtualizacaoReal) {
            return null;
        }

        $cicloInicio = $ultimaAtualizacaoReal->copy()->startOfSecond();
        if ($this->encerramentoVigente($candidato, $cicloInicio)) {
            // Encerramento vigente: a única pergunta possível é a P1 de novo, para o candidato reconsiderar.
            return [...$this->perguntaPorNumero(1), 'reconsideracao' => true];
        }

        $intervalosVencidos = $this->intervalosVencidos($ultimaAtualizacaoReal);
        if ($intervalosVencidos < 1) {
            return null;
        }

        $respondidasNoCiclo = CandidatoReativacaoQuestionario::query()
            ->where('candidato_matricula', $candidato->matricula)
            ->where('ciclo_inicio_em', $cicloInicio)
            ->pluck('pergunta_numero')
            ->map(fn ($valor) => (int) $valor)
            ->unique()
            ->all();

        // Sequência individual: menor pergunta ainda não respondida neste ciclo, liberada
        // somente quando o número de intervalos vencidos comporta mais uma resposta.
        $pendentes = array_values(array_diff(range(1, 8), $respondidasNoCiclo));
        if ($pendentes === [] || count($respondidasNoCiclo) >= $intervalosVencidos) {
            return null;
        }

        return $this->perguntaPorNumero($pendentes[0]);
    }

    public function concluir(Candidato $candidato, array $respostas): array
    {
        $dados = $this->normalizarRespostas($respostas);
        $this->validarRespostas($dados);
        $encerra = $this->validarDecisaoDeSaida($dados);

        return DB::transaction(function () use ($candidato, $dados, $encerra): array {
            $bloqueado = Candidato::with(['contratacao', 'bloqueioNotificacoes'])->lockForUpdate()->findOrFail($candidato->matricula);
            $estado = $this->statusService->estadoEfetivo($bloqueado);

            if ($estado === CandidatoStatusService::CONTRATADO) {
                throw ValidationException::withMessages(['candidato' => 'Candidato contratado não pode ser reativado por questionário.']);
            }

            if ($estado === CandidatoStatusService::BLOQUEADO_MANUALMENTE) {
                throw ValidationException::withMessages(['candidato' => 'Bloqueio administrativo não pode ser removido por questionário.']);
            }

            $perguntaNumero = (int) ($dados['pergunta_numero'] ?? $this->determinarPerguntaNumeroLegacy($dados));
            $perguntaEsperada = $this->perguntaAtual($bloqueado);
            if ($perguntaEsperada !== null && $perguntaEsperada['numero'] !== $perguntaNumero) {
                throw ValidationException::withMessages(['pergunta_numero' => 'A pergunta pendente atual é a número ' . $perguntaEsperada['numero'] . '.']);
            }

            $cicloInicio = $bloqueado->ultima_atualizacao_real_em?->copy()->startOfSecond()
                ?? $bloqueado->ultima_atividade_em?->copy()->startOfSecond()
                ?? now()->startOfSecond();
            $ultimaDecisao = CandidatoReativacaoQuestionario::query()
                ->where('candidato_matricula', $bloqueado->matricula)
                ->where('ciclo_inicio_em', $cicloInicio)
                ->where('pergunta_numero', $perguntaNumero)
                ->orderByDesc('sequencia')
                ->lockForUpdate()
                ->first();

            // Só a P1 com encerramento vigente (vindo da P1, P2, P3 ou P6) aceita nova decisão;
            // nos demais casos a pergunta já foi respondida e o registro existente é devolvido.
            $reconsideracao = $perguntaNumero === 1 && $this->encerramentoVigente($bloqueado, $cicloInicio);
            if ($ultimaDecisao && ! $reconsideracao) {
                return $this->respostaConclusao($ultimaDecisao, $bloqueado);
            }

            // Clique duplo: a mesma P1 de encerramento repetida em poucos segundos devolve o registro já gravado.
            if ($reconsideracao
                && $ultimaDecisao?->resultado === CandidatoReativacaoQuestionario::RESULTADO_ENCERRAMENTO_SOLICITADO
                && $ultimaDecisao->resposta_1 === $dados['resposta_1']
                && abs($ultimaDecisao->concluido_em->diffInSeconds(now())) < self::JANELA_CLIQUE_DUPLO_SEGUNDOS) {
                return $this->respostaConclusao($ultimaDecisao, $bloqueado);
            }

            // Encerramento só existe dentro da atualização periódica: nunca "encerrado" com perfil disponível.
            if ($encerra && $estado !== CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE) {
                throw ValidationException::withMessages(['confirmar_encerramento' => 'O encerramento só pode ser registrado durante a atualização periódica do cadastro.']);
            }

            $campoResposta = 'resposta_' . $perguntaNumero;
            $payload = [
                'candidato_matricula' => $bloqueado->matricula,
                'bloqueado_em' => $cicloInicio,
                'ciclo_inicio_em' => $cicloInicio,
                'pergunta_numero' => $perguntaNumero,
                'sequencia' => ($ultimaDecisao?->sequencia ?? 0) + 1,
                'bloqueio_notificacao_id' => $this->notificacaoEvento($bloqueado, $cicloInicio)?->id,
                'resultado' => 'RESPONDIDA',
                'concluido_em' => now(),
                // Cada registro guarda somente a pergunta respondida; as demais colunas ficam NULL.
                $campoResposta => $dados[$campoResposta],
            ];

            $questionario = CandidatoReativacaoQuestionario::query()->create($payload);

            // Reativa somente o bloqueio por inatividade e somente se o candidato confirmou,
            // na Pergunta 1 deste ciclo, que deseja manter o cadastro ativo.
            $respostaPergunta1 = $perguntaNumero === 1
                ? $dados['resposta_1']
                : $this->respostaPergunta1NoCiclo($bloqueado, $cicloInicio);

            if ($encerra) {
                // A resposta original fica em resposta_N; a decisão de saída fica no resultado.
                $questionario->forceFill(['resultado' => CandidatoReativacaoQuestionario::RESULTADO_ENCERRAMENTO_SOLICITADO])->save();
            } elseif ($respostaPergunta1 === self::Q1_MANTER && $estado === CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE) {
                $questionario->forceFill(['resultado' => CandidatoReativacaoQuestionario::RESULTADO_REATIVADO])->save();
                $bloqueado->forceFill([
                    'status' => true,
                    'ultima_atividade_em' => now(),
                    // Sem marco gravado, o ciclo usaria ultima_atividade_em e reiniciaria ao reativar.
                    'ultima_atualizacao_real_em' => $bloqueado->ultima_atualizacao_real_em ?? $cicloInicio,
                ])->save();
            }

            return $this->respostaConclusao($questionario->fresh(), $bloqueado->fresh());
        });
    }

    public function perguntaPorNumero(int $numero): array
    {
        $numero = min(8, max(1, $numero));
        $pergunta = self::PERGUNTAS[$numero];

        return [
            'numero' => $numero,
            'campo' => $pergunta['campo'],
            'titulo' => $pergunta['titulo'],
            'instrucao' => $pergunta['instrucao'] ?? null,
            'multipla' => $pergunta['multipla'] ?? false,
            'opcoes' => array_map(fn (array $opcao) => [
                ...$opcao,
                'saida' => self::RESPOSTAS_DE_SAIDA[$pergunta['campo']][$opcao['valor']] ?? null,
            ], $pergunta['opcoes']),
            'reconsideracao' => false,
        ];
    }

    private function normalizarRespostas(array $respostas): array
    {
        $validacao = $respostas;

        if (isset($respostas['pergunta_numero'])) {
            $validacao['pergunta_numero'] = (int) $respostas['pergunta_numero'];
        }

        if (isset($respostas['resposta']) && ! isset($validacao['resposta_1']) && ! isset($validacao['resposta_2']) && ! isset($validacao['resposta_3']) && ! isset($validacao['resposta_4']) && ! isset($validacao['resposta_5']) && ! isset($validacao['resposta_6']) && ! isset($validacao['resposta_7']) && ! isset($validacao['resposta_8'])) {
            $numero = (int) ($validacao['pergunta_numero'] ?? 1);
            $validacao['resposta_' . $numero] = $respostas['resposta'];
            unset($validacao['resposta']);
        }

        if (isset($respostas['resposta_4']) && is_array($respostas['resposta_4'])) {
            $validacao['resposta_4'] = array_values(array_unique($respostas['resposta_4']));
        }

        return $validacao;
    }

    private function determinarPerguntaNumeroLegacy(array $respostas): int
    {
        foreach (range(1, 8) as $numero) {
            if (array_key_exists('resposta_' . $numero, $respostas) && $respostas['resposta_' . $numero] !== null) {
                return $numero;
            }
        }

        return 1;
    }

    private function validarRespostas(array $respostas): void
    {
        $numero = (int) ($respostas['pergunta_numero'] ?? $this->determinarPerguntaNumeroLegacy($respostas));
        if ($numero < 1 || $numero > 8) {
            throw ValidationException::withMessages(['pergunta_numero' => 'Pergunta inválida.']);
        }

        foreach (range(1, 8) as $indice) {
            $campo = 'resposta_' . $indice;
            if (! array_key_exists($campo, $respostas) || $respostas[$campo] === null) {
                continue;
            }

            $valor = $respostas[$campo];
            if ($indice === 4) {
                if (! is_array($valor) || array_diff($valor, self::OPCOES['resposta_4'])) {
                    throw ValidationException::withMessages([$campo => 'Selecione alternativas válidas.']);
                }
                continue;
            }

            if (! in_array($valor, self::OPCOES[$campo] ?? [], true)) {
                throw ValidationException::withMessages([$campo => 'Resposta inválida.']);
            }
        }

        $campo = 'resposta_' . $numero;
        $valor = $respostas[$campo] ?? null;
        if ($valor === null) {
            throw ValidationException::withMessages([$campo => 'Resposta inválida.']);
        }
    }

    private function notificacaoEvento(Candidato $candidato, Carbon $bloqueadoEm): ?CandidatoBloqueioNotificacao
    {
        return $candidato->bloqueioNotificacoes()
            ->where('tipo_bloqueio', CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE)
            ->where('bloqueado_em', $bloqueadoEm)
            ->latest('id')
            ->first();
    }

    private function respostaConclusao(CandidatoReativacaoQuestionario $questionario, Candidato $candidato): array
    {
        $status = $this->statusService->estadoEfetivo($candidato);
        $reativado = $questionario->resultado === CandidatoReativacaoQuestionario::RESULTADO_REATIVADO;

        return [
            'message' => $reativado ? 'Cadastro reativado com sucesso.' : 'Resposta registrada. A pergunta será apresentada apenas uma vez por ciclo de 3 meses.',
            'resultado' => $questionario->resultado,
            'reativado' => $reativado,
            'pergunta' => $this->perguntaPorNumero((int) $questionario->pergunta_numero),
            'orientacoes' => $questionario->orientacoes ?? [],
            'restricoes' => [
                'contratado' => false,
                'inativo' => $status === CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE,
                'estado' => $status,
                'estado_rotulo' => $this->statusService->rotulo($status),
                'pode_editar_perfil' => $status === CandidatoStatusService::ATIVO,
                'code' => $status === CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE ? CandidatoStatusService::INATIVIDADE_CODE : null,
            ],
        ];
    }

    private function ultimaAtualizacaoReal(Candidato $candidato): ?Carbon
    {
        $valor = $candidato->ultima_atualizacao_real_em ?? $candidato->ultima_atividade_em;

        if ($valor === null) {
            return null;
        }

        if ($valor instanceof Carbon) {
            return $valor->copy();
        }

        return Carbon::parse($valor);
    }

    /**
     * Intervalos vencidos desde o marco do próprio candidato (máx. 8) = quantas perguntas
     * ele já pode ter respondido no ciclo. Produção: 1 a cada 3 meses. Modo teste: 1 a cada N segundos.
     */
    /**
     * Retorna true quando a resposta encerra a participação. Exige a confirmação explícita
     * para respostas de saída e recusa confirmação em respostas que não encerram.
     */
    private function validarDecisaoDeSaida(array $dados): bool
    {
        $numero = (int) ($dados['pergunta_numero'] ?? $this->determinarPerguntaNumeroLegacy($dados));
        $campo = 'resposta_' . $numero;
        $valor = $dados[$campo] ?? null;
        $tipo = is_string($valor) ? (self::RESPOSTAS_DE_SAIDA[$campo][$valor] ?? null) : null;
        $confirmou = filter_var($dados['confirmar_encerramento'] ?? false, FILTER_VALIDATE_BOOL);

        if ($tipo === self::SAIDA_CONFIRMAR && ! $confirmou) {
            throw ValidationException::withMessages(['confirmar_encerramento' => 'Confirme o encerramento da sua participação para registrar esta resposta.']);
        }

        if ($tipo === null && $confirmou) {
            throw ValidationException::withMessages(['confirmar_encerramento' => 'Esta resposta não encerra a participação.']);
        }

        return $confirmou;
    }

    /**
     * Há encerramento vigente quando a decisão de saída mais recente do ciclo (P1, P2, P3 ou P6)
     * é posterior à última confirmação de permanência na P1.
     */
    private function encerramentoVigente(Candidato $candidato, Carbon $cicloInicio): bool
    {
        $doCiclo = fn () => CandidatoReativacaoQuestionario::query()
            ->where('candidato_matricula', $candidato->matricula)
            ->where('ciclo_inicio_em', $cicloInicio);

        $ultimoEncerramento = $doCiclo()
            ->where('resultado', CandidatoReativacaoQuestionario::RESULTADO_ENCERRAMENTO_SOLICITADO)
            ->max('id');
        if ($ultimoEncerramento === null) {
            return false;
        }

        $ultimaPermanencia = $doCiclo()
            ->where('pergunta_numero', 1)
            ->where('resposta_1', self::Q1_MANTER)
            ->max('id');

        return $ultimaPermanencia === null || $ultimaPermanencia < $ultimoEncerramento;
    }

    private function respostaPergunta1NoCiclo(Candidato $candidato, Carbon $cicloInicio): ?string
    {
        return CandidatoReativacaoQuestionario::query()
            ->where('candidato_matricula', $candidato->matricula)
            ->where('ciclo_inicio_em', $cicloInicio)
            ->where('pergunta_numero', 1)
            ->orderByDesc('sequencia')
            ->value('resposta_1');
    }

    private function intervalosVencidos(Carbon $ultimaAtualizacaoReal): int
    {
        if ($this->modoTesteAtivo()) {
            $intervalos = (int) floor($ultimaAtualizacaoReal->diffInSeconds(now()) / $this->intervaloTesteSegundos());

            return min(8, max(0, $intervalos));
        }

        $meses = (int) floor($ultimaAtualizacaoReal->diffInMonths(now()));

        return min(8, max(0, intdiv($meses, 3)));
    }

    private function modoTesteAtivo(): bool
    {
        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            return false;
        }

        return (bool) config('candidato.perguntas_modo_teste', false);
    }

    private function intervaloTesteSegundos(): int
    {
        return max(1, (int) config('candidato.perguntas_intervalo_teste_segundos', 30));
    }
}
