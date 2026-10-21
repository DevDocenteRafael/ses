<template>
    <teleport to="body">
        <Transition name="ses-encerrar" appear>
            <div v-if="aberto" class="ses-encerrar-overlay" data-testid="overlay-encerrar-participacao" @click.self="fechar">
                <!-- Um único overlay: ao trocar de etapa (testid), só o conteúdo do diálogo é substituído. -->
                <Transition name="ses-encerrar-troca" mode="out-in" @after-enter="focarOpcaoSegura">
                <div
                    :key="testid"
                    ref="dialogo"
                    class="ses-encerrar-modal"
                    role="alertdialog"
                    aria-modal="true"
                    :aria-labelledby="`${testid}-titulo`"
                    :aria-describedby="`${testid}-mensagem`"
                    :data-testid="testid"
                    @keydown.esc.prevent.stop="fechar"
                    @keydown.tab="manterFocoNoDialogo"
                >
                    <div class="ses-encerrar-icone" :class="`ses-encerrar-icone--${tom}`" aria-hidden="true">
                        <i class="bi" :class="icone"></i>
                    </div>
                    <h2 :id="`${testid}-titulo`" class="ses-encerrar-titulo">{{ titulo }}</h2>
                    <div :id="`${testid}-mensagem`" class="ses-encerrar-mensagem">
                        <p v-for="(paragrafo, indice) in paragrafos" :key="indice" :class="{ 'mb-0': indice === paragrafos.length - 1 }">{{ paragrafo }}</p>
                    </div>

                    <div v-if="erro" class="alert alert-danger ses-encerrar-erro" role="alert">{{ erro }}</div>

                    <div class="ses-encerrar-acoes">
                        <button ref="botaoVoltar" type="button" class="btn" :class="classeVoltar" :disabled="processando" @click="voltar">
                            {{ textoVoltar }}
                        </button>
                        <button type="button" class="btn" :class="classeConfirmar" :disabled="processando" @click="confirmar">
                            <span v-if="processando" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                            {{ processando ? 'Encerrando...' : textoConfirmar }}
                        </button>
                    </div>
                </div>
                </Transition>
            </div>
        </Transition>
    </teleport>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue';

const props = defineProps({
    aberto: { type: Boolean, default: false },
    processando: { type: Boolean, default: false },
    erro: { type: String, default: '' },
    titulo: { type: String, default: 'Deseja encerrar sua participação?' },
    paragrafos: {
        type: Array,
        default: () => [
            'Entendemos que seus objetivos profissionais podem mudar.',
            'Ao confirmar, seu perfil ficará indisponível para novas oportunidades e você será direcionado à tela de login.',
        ],
    },
    textoVoltar: { type: String, default: 'Voltar ao questionário' },
    textoConfirmar: { type: String, default: 'Confirmar e sair' },
    classeVoltar: { type: String, default: 'btn-outline-secondary' },
    classeConfirmar: { type: String, default: 'btn-danger' },
    icone: { type: String, default: 'bi-door-open' },
    tom: { type: String, default: 'aviso' },
    testid: { type: String, default: 'modal-encerrar-participacao' },
});

// "fechar" = Esc ou clique fora (nunca equivale a escolher uma das opções).
const emit = defineEmits(['voltar', 'confirmar', 'fechar']);
const dialogo = ref(null);
const botaoVoltar = ref(null);

// Foco inicial na opção segura (voltar), para que Enter não encerre a participação por engano.
// Ao abrir usa o watch; ao trocar de etapa, o after-enter da transição interna.
function focarOpcaoSegura() {
    botaoVoltar.value?.focus();
}

watch(() => props.aberto, async (aberto) => {
    if (!aberto) return;
    await nextTick();
    focarOpcaoSegura();
}, { immediate: true });

function voltar() {
    if (props.processando) return;
    emit('voltar');
}

function fechar() {
    if (props.processando) return;
    emit('fechar');
}

function confirmar() {
    if (props.processando) return;
    emit('confirmar');
}

function manterFocoNoDialogo(event) {
    const focaveis = [...(dialogo.value?.querySelectorAll('button:not([disabled])') ?? [])];
    if (!focaveis.length) {
        event.preventDefault();
        return;
    }

    const primeiro = focaveis[0];
    const ultimo = focaveis[focaveis.length - 1];
    if (event.shiftKey && document.activeElement === primeiro) {
        event.preventDefault();
        ultimo.focus();
    } else if (!event.shiftKey && document.activeElement === ultimo) {
        event.preventDefault();
        primeiro.focus();
    }
}
</script>

<style scoped>
.ses-encerrar-overlay {
    position: fixed;
    inset: 0;
    z-index: 1070;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(2px);
}

.ses-encerrar-modal {
    width: min(100%, 30rem);
    max-height: calc(100dvh - 2rem);
    overflow-y: auto;
    padding: 2rem 1.75rem 1.5rem;
    border: 1px solid var(--bs-border-color-translucent);
    border-radius: 1.25rem;
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    box-shadow: 0 1.25rem 3rem rgba(15, 23, 42, 0.28);
    text-align: center;
    outline: none;
}

.ses-encerrar-icone {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 4rem;
    height: 4rem;
    margin: 0 auto 1rem;
    border-radius: 50%;
    font-size: 1.75rem;
}

.ses-encerrar-icone--aviso {
    background: var(--bs-warning-bg-subtle);
    color: var(--bs-warning-text-emphasis);
}

.ses-encerrar-icone--info {
    background: var(--bs-primary-bg-subtle);
    color: var(--bs-primary-text-emphasis);
}

.ses-encerrar-titulo {
    margin-bottom: 0.75rem;
    font-size: 1.25rem;
    font-weight: 600;
    line-height: 1.35;
}

.ses-encerrar-mensagem {
    color: var(--bs-secondary-color);
    line-height: 1.6;
}

.ses-encerrar-mensagem p {
    margin-bottom: 0.5rem;
}

.ses-encerrar-erro {
    margin: 1.25rem 0 0;
    text-align: left;
}

.ses-encerrar-acoes {
    display: flex;
    gap: 0.75rem;
    justify-content: center;
    margin-top: 1.5rem;
}

.ses-encerrar-acoes .btn {
    flex: 1 1 0;
    min-height: 2.75rem;
    border-radius: 0.75rem;
}

:global([data-bs-theme='dark']) .ses-encerrar-overlay {
    background: rgba(2, 6, 23, 0.65);
}

:global([data-bs-theme='dark']) .ses-encerrar-modal {
    box-shadow: 0 1.25rem 3rem rgba(0, 0, 0, 0.55);
}

.ses-encerrar-enter-active,
.ses-encerrar-leave-active {
    transition: opacity 0.2s ease;
}

.ses-encerrar-enter-active .ses-encerrar-modal,
.ses-encerrar-leave-active .ses-encerrar-modal {
    transition: transform 0.2s ease;
}

.ses-encerrar-enter-from,
.ses-encerrar-leave-to {
    opacity: 0;
}

.ses-encerrar-enter-from .ses-encerrar-modal,
.ses-encerrar-leave-to .ses-encerrar-modal {
    transform: translateY(0.5rem) scale(0.97);
}

/* Troca de etapa dentro do mesmo overlay (ex.: autônomo -> confirmação de saída). */
.ses-encerrar-troca-enter-active,
.ses-encerrar-troca-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.ses-encerrar-troca-enter-from,
.ses-encerrar-troca-leave-to {
    opacity: 0;
    transform: scale(0.97);
}

@media (max-width: 576px) {
    .ses-encerrar-modal {
        padding: 1.5rem 1.25rem 1.25rem;
    }

    .ses-encerrar-acoes {
        flex-direction: column;
    }
}

@media (prefers-reduced-motion: reduce) {
    .ses-encerrar-enter-active,
    .ses-encerrar-leave-active,
    .ses-encerrar-enter-active .ses-encerrar-modal,
    .ses-encerrar-leave-active .ses-encerrar-modal,
    .ses-encerrar-troca-enter-active,
    .ses-encerrar-troca-leave-active {
        transition: none;
    }
}
</style>
