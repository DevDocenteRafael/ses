<template>
    <div class="modal-backdrop fade show"></div>
    <div class="modal fade show d-block candidato-reativacao-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="reativacao-titulo" @keydown.esc.prevent.stop>
        <div class="modal-dialog modal-dialog-centered candidato-reativacao-modal__dialog">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h2 id="reativacao-titulo" class="modal-title h5 mb-0">ATUALIZAÇÃO DO CADASTRO</h2>
                </div>
                <form @submit.prevent="confirmar">
                    <div class="modal-body candidato-reativacao-modal__body">
                        <p class="text-secondary">Para manter seu cadastro ativo no Sistema de Empregabilidade do Senac DF, precisamos confirmar algumas informações sobre sua situação profissional e seu interesse em novas oportunidades.</p>
                        <p class="text-secondary">Responda às perguntas abaixo para continuar.</p>
                        <div v-if="mensagem" class="alert" :class="mensagemClasse" role="alert">{{ mensagem }}</div>

                        <fieldset v-for="pergunta in perguntas" :key="pergunta.campo" class="mb-4">
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
                    <div class="modal-footer flex-column flex-sm-row align-items-stretch align-items-sm-center">
                        <button type="button" class="btn btn-outline-secondary" :disabled="salvando" @click="$emit('sair')">Sair</button>
                        <button type="submit" class="btn btn-primary" :disabled="salvando">
                            <span v-if="salvando" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                            {{ salvando ? 'Confirmando...' : 'Confirmar respostas' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import alunosService from '../../services/alunosServices';
import { useAuthStore } from '../../store/auth';

const emit = defineEmits(['concluido', 'sair']);
const auth = useAuthStore();
const salvando = ref(false);
const mensagem = ref('');
const mensagemTipo = ref('erro');
const erros = reactive({});
const form = reactive({ resposta_1: '', resposta_2: '', resposta_3: '', resposta_4: [], resposta_5: '', resposta_6: '', resposta_7: '', resposta_8: '' });

const perguntas = [
    { campo: 'resposta_1', titulo: 'Você deseja manter seu cadastro ativo no Sistema de Empregabilidade do Senac?', opcoes: [{ valor: 'manter_ativo', label: 'Sim, desejo continuar recebendo oportunidades.' }, { valor: 'encerrar_cadastro', label: 'Não, desejo encerrar meu cadastro.' }] },
    { campo: 'resposta_2', titulo: 'Atualmente, você está buscando uma oportunidade de emprego ou estágio na área em que se formou no Senac?', opcoes: [{ valor: 'buscando_area_senac', label: 'Sim, estou procurando emprego ou estágio na minha área de formação no Senac.' }, { valor: 'buscando_qualquer_area', label: 'Não, estou procurando emprego ou estágio em qualquer área.' }, { valor: 'nao_busco_mas_manter', label: 'Não estou procurando emprego ou estágio no momento, mas desejo manter meu cadastro ativo.' }] },
    { campo: 'resposta_3', titulo: 'Qual é sua situação profissional atual?', opcoes: [{ valor: 'nao_trabalhando', label: 'Não estou trabalhando.' }, { valor: 'trabalhando_busco', label: 'Estou trabalhando, mas busco novas oportunidades.' }, { valor: 'trabalhando_nao_busco', label: 'Estou trabalhando e não busco mudança no momento.' }, { valor: 'autonomo_empreendedor', label: 'Estou trabalhando como autônomo(a) ou empreendedor(a).' }] },
    { campo: 'resposta_4', titulo: 'Que tipo de oportunidade profissional você procura?', instrucao: 'Marque todas as alternativas que se aplicam.', multipla: true, opcoes: [{ valor: 'jovem_aprendiz', label: 'Jovem Aprendiz.' }, { valor: 'estagio', label: 'Estágio.' }, { valor: 'emprego_efetivo', label: 'Emprego efetivo.' }, { valor: 'temporario', label: 'Trabalho temporário.' }, { valor: 'autonomo_servicos', label: 'Trabalho autônomo ou prestação de serviços.' }, { valor: 'outras', label: 'Outras oportunidades.' }] },
    { campo: 'resposta_5', titulo: 'Qual é sua disponibilidade de horário para trabalhar?', opcoes: [{ valor: 'manha', label: 'Manhã.' }, { valor: 'tarde', label: 'Tarde.' }, { valor: 'noite', label: 'Noite.' }, { valor: 'integral', label: 'Integral.' }, { valor: 'escala_flexivel', label: 'Escala ou horários flexíveis.' }] },
    { campo: 'resposta_6', titulo: 'Você tem disponibilidade para participar de processos seletivos e entrevistas?', opcoes: [{ valor: 'presencial_online', label: 'Sim, presencialmente e/ou on-line.' }, { valor: 'apenas_online', label: 'Sim, apenas on-line.' }, { valor: 'apenas_presencial', label: 'Sim, apenas presencialmente.' }, { valor: 'sem_disponibilidade', label: 'Não tenho disponibilidade no momento.' }] },
    { campo: 'resposta_7', titulo: 'Seus dados de contato e suas informações profissionais continuam atualizados?', opcoes: [{ valor: 'atualizados', label: 'Sim, estão atualizados.' }, { valor: 'atualizar_contato', label: 'Preciso atualizar meus dados de contato.' }, { valor: 'atualizar_profissionais', label: 'Preciso atualizar minhas informações profissionais, como curso, currículo ou experiências profissionais.' }] },
    { campo: 'resposta_8', titulo: 'Você deseja informar alguma atualização sobre seus objetivos profissionais ou sua disponibilidade para novas oportunidades?', opcoes: [{ valor: 'mesmas_informacoes', label: 'Não, minhas informações permanecem as mesmas.' }, { valor: 'desejo_atualizar', label: 'Sim, desejo atualizar minhas informações.' }] },
];

const mensagemClasse = computed(() => mensagemTipo.value === 'sucesso' ? 'alert-success' : 'alert-danger');

function validar() {
    Object.keys(erros).forEach((campo) => { erros[campo] = ''; });
    if (!form.resposta_1) erros.resposta_1 = 'Escolha uma opção.';
    if (form.resposta_1 === 'encerrar_cadastro') return !Object.values(erros).some(Boolean);
    perguntas.forEach((pergunta) => {
        if (pergunta.campo === 'resposta_4' && !form.resposta_4.length) erros.resposta_4 = 'Selecione pelo menos uma alternativa.';
        else if (!pergunta.multipla && !form[pergunta.campo]) erros[pergunta.campo] = 'Esta pergunta é obrigatória.';
    });
    return !Object.values(erros).some(Boolean);
}

async function confirmar() {
    mensagem.value = '';
    if (!validar()) {
        mensagem.value = 'Responda as perguntas obrigatórias para continuar.';
        return;
    }
    if (form.resposta_1 === 'encerrar_cadastro' && !window.confirm('Você informou que não deseja manter seu cadastro ativo.\n\nSeu perfil continuará indisponível para novas oportunidades.\n\nDeseja confirmar essa decisão?')) return;
    salvando.value = true;
    try {
        const { data } = await alunosService.concluirQuestionarioReativacao({ ...form, resposta_4: [...form.resposta_4] });
        auth.restricoes = data.restricoes || auth.restricoes;
        localStorage.setItem('ses_restricoes', JSON.stringify(auth.restricoes));
        mensagemTipo.value = 'sucesso';
        mensagem.value = [data.message, ...(data.orientacoes || [])].join(' ');
        emit('concluido', data);
    } catch (error) {
        mensagemTipo.value = 'erro';
        mensagem.value = error?.response?.data?.message || 'Não foi possível concluir o questionário.';
        const backend = error?.response?.data?.errors || {};
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
