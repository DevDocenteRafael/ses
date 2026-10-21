<template>
    <div v-if="deveExibirModal" class="modal-backdrop fade show"></div>
    <div v-if="deveExibirModal" class="modal fade show d-block candidato-reativacao-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="reativacao-titulo" @keydown.esc.prevent.stop>
        <div class="modal-dialog modal-dialog-centered candidato-reativacao-modal__dialog">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h2 id="reativacao-titulo" class="modal-title h5 mb-0">Atualização periódica do cadastro</h2>
                </div>
                <form @submit.prevent="confirmar">
                    <div class="modal-body candidato-reativacao-modal__body">
                        <p class="text-secondary">Identificamos que seu perfil está há algum tempo sem atualizações. Responda à pergunta abaixo para nos ajudar a acompanhar sua situação.</p>
                        <div v-if="mensagem" class="alert" :class="mensagemClasse" role="alert">{{ mensagem }}</div>

                        <div v-if="carregando" class="d-flex align-items-center justify-content-center py-4 text-secondary" aria-live="polite">
                            <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                            Carregando pergunta...
                        </div>

                        <div v-else-if="erro" class="alert alert-danger" role="alert">
                            {{ erro }}
                            <div class="mt-3">
                                <button type="button" class="btn btn-outline-primary btn-sm" @click="carregarPergunta">Tentar novamente</button>
                            </div>
                        </div>

                        <div v-else-if="pergunta" class="mb-4">
                            <div v-if="pergunta.reconsideracao" class="alert alert-info small d-flex gap-2 align-items-start" role="status" data-testid="aviso-reconsideracao">
                                <i class="bi bi-info-circle mt-1" aria-hidden="true"></i>
                                <span>Você solicitou anteriormente o encerramento do seu cadastro, e seu perfil continua indisponível para as empresas. Se mudou de ideia, você pode reconsiderar sua decisão abaixo.</span>
                            </div>
                            <div class="text-muted small mb-2">Pergunta {{ pergunta.numero }} de 8</div>
                            <fieldset>
                                <legend class="h6 fw-semibold">{{ pergunta.titulo }}</legend>
                                <p v-if="pergunta.instrucao" class="small text-secondary mb-2">{{ pergunta.instrucao }}</p>
                                <div v-for="opcao in pergunta.opcoes" :key="opcao.valor" class="form-check mb-2">
                                    <input
                                        :id="`${pergunta.campo}-${opcao.valor}`"
                                        v-model="form[pergunta.campo]"
                                        class="form-check-input"
                                        :type="pergunta.multipla ? 'checkbox' : 'radio'"
                                        :value="opcao.valor"
                                        :name="pergunta.campo"
                                        :aria-invalid="Boolean(erros[pergunta.campo])"
                                    >
                                    <label class="form-check-label" :for="`${pergunta.campo}-${opcao.valor}`">{{ opcao.label }}</label>
                                </div>
                                <p v-if="erros[pergunta.campo]" class="text-danger small mb-0">{{ erros[pergunta.campo] }}</p>
                            </fieldset>
                        </div>

                        <div v-else class="alert alert-warning" role="alert">
                            Não há pergunta trimestral pendente no momento.
                        </div>
                    </div>
                    <div v-if="deveExibirAcoes" class="modal-footer flex-column flex-sm-row align-items-stretch align-items-sm-center">
                        <button type="button" class="btn btn-outline-secondary" :disabled="salvando" @click="$emit('sair')">Sair</button>
                        <button type="submit" class="btn btn-primary" :disabled="salvando || carregando || !pergunta || !podeConfirmar">
                            <span v-if="salvando" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                            {{ salvando ? 'Confirmando...' : 'Confirmar resposta' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Uma única instância (um único overlay); a etapa define o conteúdo exibido. -->
    <CandidatoEncerrarParticipacaoModal
        :aberto="etapaSaida !== null"
        v-bind="etapaSaida === ETAPA_CONTINUIDADE ? MODAL_CONTINUIDADE_AUTONOMO : {}"
        :processando="salvando"
        :erro="etapaSaida === ETAPA_CONFIRMACAO ? erroSaida : ''"
        @voltar="etapaSaida === ETAPA_CONTINUIDADE ? continuarParticipando() : voltarAoQuestionario()"
        @confirmar="etapaSaida === ETAPA_CONTINUIDADE ? pedirConfirmacaoDeSaida() : confirmarSaida()"
        @fechar="voltarAoQuestionario"
    />
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import alunosService from '../../services/alunosServices';
import { useAuthStore } from '../../store/auth';
import CandidatoEncerrarParticipacaoModal from './CandidatoEncerrarParticipacaoModal.vue';

// Classificação de saída definida no backend (campo "saida" de cada opção).
const SAIDA_CONFIRMAR = 'confirmar';
const SAIDA_PERGUNTAR = 'perguntar';

const emit = defineEmits(['concluido', 'encerrado', 'sair']);
const auth = useAuthStore();
const salvando = ref(false);
const carregando = ref(true);
const erro = ref('');
const mensagem = ref('');
const mensagemTipo = ref('erro');
const erros = reactive({});
const pergunta = ref(null);
const ETAPA_CONTINUIDADE = 'continuidade';
const ETAPA_CONFIRMACAO = 'confirmacao';
const MODAL_CONTINUIDADE_AUTONOMO = {
    titulo: 'Deseja continuar participando?',
    paragrafos: [
        'Você informou que trabalha como autônomo(a) ou empreendedor(a).',
        'Deseja continuar recebendo oportunidades de emprego e estágio pelo Portal de Empregabilidade do Senac?',
    ],
    textoVoltar: 'Sim, manter meu cadastro ativo',
    textoConfirmar: 'Não, desejo encerrar meu cadastro',
    classeVoltar: 'btn-primary',
    classeConfirmar: 'btn-outline-danger',
    icone: 'bi-briefcase',
    tom: 'info',
    testid: 'modal-continuidade-autonomo',
};
// null (fechado) | ETAPA_CONTINUIDADE (pergunta do autônomo) | ETAPA_CONFIRMACAO (confirmar saída)
const etapaSaida = ref(null);
const erroSaida = ref('');
const form = reactive({ resposta_1: '', resposta_2: '', resposta_3: '', resposta_4: [], resposta_5: '', resposta_6: '', resposta_7: '', resposta_8: '' });
let pollTimer = null;

const mensagemClasse = computed(() => mensagemTipo.value === 'sucesso' ? 'alert-success' : 'alert-danger');
// Também exibe o modal quando o carregamento falha, para o candidato ver o motivo e poder tentar de novo.
const deveExibirModal = computed(() => !carregando.value && (!!erro.value || !!pergunta.value));
const deveExibirAcoes = computed(() => !!pergunta.value && !carregando.value && !erro.value);
const podeConfirmar = computed(() => {
    if (!pergunta.value || carregando.value || salvando.value) {
        return false;
    }

    const campo = pergunta.value.campo;
    if (pergunta.value.multipla) {
        return Array.isArray(form[campo]) && form[campo].length > 0;
    }

    return Boolean(form[campo]);
});

onMounted(async () => {
    await carregarPergunta();
    iniciarVerificacaoLocal();
});

onBeforeUnmount(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
});

function iniciarVerificacaoLocal() {
    if (!import.meta.env.DEV || import.meta.env.PROD || !auth.restricoes?.inativo) {
        return;
    }

    if (pollTimer) {
        return;
    }

    pollTimer = window.setInterval(async () => {
        if (salvando.value || carregando.value || pergunta.value) {
            return;
        }

        if (!auth.restricoes?.inativo) {
            return;
        }

        await carregarPergunta();
    }, 5000);
}

async function carregarPergunta() {
    carregando.value = true;
    erro.value = '';
    mensagem.value = '';
    pergunta.value = null;

    try {
        const { data } = await alunosService.obterPerguntaInatividade();
        const proximaPergunta = data?.pergunta ?? null;

        if (data?.tem_pergunta === false || !proximaPergunta) {
            pergunta.value = null;
            return;
        }

        if (typeof proximaPergunta.titulo !== 'string' || !Array.isArray(proximaPergunta.opcoes)) {
            erro.value = 'Não foi possível carregar a pergunta trimestral no momento.';
            return;
        }

        pergunta.value = proximaPergunta;
        const campo = pergunta.value.campo;

        Object.keys(form).forEach((chave) => {
            if (chave === campo) {
                form[chave] = pergunta.value.multipla ? (Array.isArray(form[chave]) ? form[chave] : []) : '';
                return;
            }

            form[chave] = chave === 'resposta_4' ? [] : '';
        });
    } catch (error) {
        erro.value = mensagemErroSegura(error, 'carregar a pergunta', { manterSelecao: false });
    } finally {
        carregando.value = false;
    }
}

/**
 * Mensagem específica por tipo de falha, sem expor SQL, stack trace ou nomes internos
 * (esses detalhes ficam no log do Laravel). A seleção do candidato nunca é descartada.
 * 401 não chega aqui: o interceptor de api.js já redireciona para o login.
 */
function mensagemErroSegura(error, acao, { manterSelecao = true } = {}) {
    const status = error?.response?.status;
    const mensagemBackend = error?.response?.data?.message;
    const selecao = manterSelecao ? ' Sua seleção foi mantida.' : '';

    if (!error?.response) {
        return `Não foi possível ${acao}: sem conexão com o servidor. Verifique sua internet e tente novamente.${selecao}`;
    }

    if (status === 422 && typeof mensagemBackend === 'string' && mensagemBackend) {
        return mensagemBackend;
    }

    if (status === 419) {
        return `Sua sessão expirou. Atualize a página (tecla F5) e entre novamente para ${acao}.`;
    }

    if (status === 403) {
        return `Não foi possível ${acao}: seu acesso não está liberado no momento. Saia e entre novamente; se continuar, procure o suporte.`;
    }

    if (status === 404 || status === 405) {
        return `O portal foi atualizado e esta página está desatualizada. Atualize a página (tecla F5) para ${acao}.`;
    }

    if (status >= 500) {
        return `Não foi possível ${acao} por uma falha no servidor (código ${status}).${selecao} Tente novamente em instantes; se o erro continuar, informe o suporte.`;
    }

    return `Não foi possível ${acao}. Tente novamente.${selecao}`;
}

function validar() {
    Object.keys(erros).forEach((campo) => { erros[campo] = ''; });
    if (!pergunta.value) return false;

    const campo = pergunta.value.campo;
    if (pergunta.value.multipla) {
        if (!Array.isArray(form[campo]) || !form[campo].length) {
            erros[campo] = 'Selecione pelo menos uma alternativa.';
        }
        return !Object.values(erros).some(Boolean);
    }

    if (!form[campo]) {
        erros[campo] = 'Escolha uma opção.';
    }
    return !Object.values(erros).some(Boolean);
}

function tipoSaidaSelecionada() {
    if (!pergunta.value || pergunta.value.multipla) return null;
    const valor = form[pergunta.value.campo];
    return pergunta.value.opcoes.find((opcao) => opcao.valor === valor)?.saida ?? null;
}

function montarPayload(confirmarEncerramento = false) {
    const campo = pergunta.value.campo;

    return {
        pergunta_numero: pergunta.value.numero,
        [campo]: pergunta.value.multipla ? [...form[campo]] : form[campo],
        ...(confirmarEncerramento ? { confirmar_encerramento: true } : {}),
    };
}

async function confirmar() {
    if (salvando.value) return;
    mensagem.value = '';

    if (!pergunta.value) {
        mensagemTipo.value = 'erro';
        mensagem.value = 'A pergunta ainda não foi carregada. Tente novamente.';
        return;
    }

    if (!validar()) {
        mensagemTipo.value = 'erro';
        mensagem.value = 'Responda a pergunta obrigatória para continuar.';
        return;
    }

    const tipoSaida = tipoSaidaSelecionada();
    if (tipoSaida === SAIDA_CONFIRMAR) {
        pedirConfirmacaoDeSaida();
        return;
    }

    if (tipoSaida === SAIDA_PERGUNTAR) {
        etapaSaida.value = ETAPA_CONTINUIDADE;
        return;
    }

    await enviarResposta();
}

function pedirConfirmacaoDeSaida() {
    // Troca de etapa no mesmo overlay: nunca existem dois backdrops ao mesmo tempo.
    erroSaida.value = '';
    etapaSaida.value = ETAPA_CONFIRMACAO;
}

async function continuarParticipando() {
    // Autônomo(a) que quer continuar: grava a resposta original como resposta normal, sem encerramento.
    etapaSaida.value = null;
    await enviarResposta();
}

function voltarAoQuestionario() {
    // Fecha o modal (qualquer etapa); a alternativa marcada continua selecionada e nada é enviado.
    if (salvando.value) return;
    etapaSaida.value = null;
    erroSaida.value = '';
}

async function confirmarSaida() {
    if (salvando.value || !pergunta.value) return;

    salvando.value = true;
    erroSaida.value = '';
    try {
        const { data } = await alunosService.concluirQuestionarioReativacao(montarPayload(true));

        if (data?.resultado !== 'ENCERRAMENTO_SOLICITADO') {
            erroSaida.value = 'Não foi possível registrar sua decisão. Tente novamente.';
            salvando.value = false;
            return;
        }

        // Decisão salva: o layout encerra a sessão e leva ao login. "salvando" segue true para bloquear novos cliques.
        emit('encerrado', data);
    } catch (error) {
        erroSaida.value = mensagemErroSegura(error, 'registrar sua decisão');
        salvando.value = false;
    }
}

async function enviarResposta() {
    if (salvando.value) return;
    salvando.value = true;
    try {
        const { data } = await alunosService.concluirQuestionarioReativacao(montarPayload());
        auth.restricoes = data.restricoes || auth.restricoes;
        localStorage.setItem('ses_restricoes', JSON.stringify(auth.restricoes));
        emit('concluido', data);

        // Sem reativação, a pergunta respondida não pode continuar na tela: busca a próxima pendente.
        if (!data.reativado && auth.restricoes?.inativo) {
            await carregarPergunta();
        }

        mensagemTipo.value = 'sucesso';
        mensagem.value = data.message || 'Resposta registrada com sucesso.';
    } catch (error) {
        // Detalhes técnicos (SQL, stack trace) ficam no log do Laravel; só erros de validação chegam ao candidato.
        mensagemTipo.value = 'erro';
        mensagem.value = mensagemErroSegura(error, 'salvar sua resposta');
        const backend = error?.response?.status === 422 ? (error.response.data?.errors || {}) : {};
        Object.keys(backend).forEach((campo) => { erros[campo] = backend[campo][0]; });
    } finally {
        salvando.value = false;
    }
}
</script>

<style scoped>
.candidato-reativacao-modal { z-index: 1060; }
.candidato-reativacao-modal__dialog { width: min(96vw, 54rem); max-width: 54rem; }
.candidato-reativacao-modal__body { max-height: min(68vh, 42rem); overflow-y: auto; }
@media (max-width: 576px) { .candidato-reativacao-modal__dialog { width: calc(100vw - 1rem); margin: 0.5rem auto; } }
</style>
