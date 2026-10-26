<template>
    <teleport to="body">
        <Transition name="ses-bloqueio">
            <div v-if="aberto" class="ses-bloqueio-overlay" data-testid="overlay-bloqueio-candidato" @click.self="fechar">
                <div
                    ref="dialogo"
                    class="ses-bloqueio-modal"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="bloqueio-candidato-titulo"
                    aria-describedby="bloqueio-candidato-mensagem"
                    :aria-busy="processando"
                    data-testid="modal-bloqueio-candidato"
                    @keydown.esc.prevent.stop="fechar"
                    @keydown.tab="manterFocoNoDialogo"
                >
                    <div class="ses-bloqueio-cabecalho">
                        <div class="ses-bloqueio-icone" aria-hidden="true">
                            <i class="bi bi-person-lock"></i>
                        </div>
                        <h2 id="bloqueio-candidato-titulo" class="ses-bloqueio-titulo">Bloquear acesso do candidato</h2>
                    </div>

                    <p id="bloqueio-candidato-mensagem" class="ses-bloqueio-mensagem">
                        Você está prestes a bloquear o acesso de
                        <strong class="ses-bloqueio-nome">{{ nomeCandidato }}</strong>
                        ao Portal de Empregabilidade Senac DF.
                    </p>

                    <label for="bloqueio-candidato-motivo" class="form-label fw-semibold mb-1">Motivo do bloqueio (opcional)</label>
                    <textarea
                        id="bloqueio-candidato-motivo"
                        ref="campoMotivo"
                        :value="motivo"
                        class="form-control ses-bloqueio-textarea"
                        :class="{ 'is-invalid': erroValidacao }"
                        rows="4"
                        :maxlength="LIMITE_MOTIVO"
                        placeholder="Ex.: Informações de cadastro em análise pela equipe Senac DF."
                        :readonly="processando"
                        :aria-invalid="erroValidacao ? 'true' : 'false'"
                        aria-describedby="bloqueio-candidato-ajuda bloqueio-candidato-contador bloqueio-candidato-erro-motivo"
                        data-testid="motivo-bloqueio"
                        @input="emit('update:motivo', $event.target.value)"
                    ></textarea>
                    <div class="ses-bloqueio-rodape-campo">
                        <small id="bloqueio-candidato-ajuda" class="ses-bloqueio-ajuda">Esse motivo poderá ser informado ao candidato.</small>
                        <small
                            id="bloqueio-candidato-contador"
                            class="ses-bloqueio-contador"
                            :class="{ 'ses-bloqueio-contador--limite': restantes <= 50 }"
                            data-testid="contador-motivo"
                        >
                            {{ caracteres }}/{{ LIMITE_MOTIVO }}<span class="visually-hidden"> caracteres</span>
                        </small>
                    </div>
                    <div id="bloqueio-candidato-erro-motivo" class="invalid-feedback" :class="{ 'd-block': erroValidacao }" data-testid="erro-motivo">
                        {{ erroValidacao }}
                    </div>

                    <div v-if="erro" class="alert alert-danger ses-bloqueio-erro" role="alert" data-testid="erro-bloqueio">
                        <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>{{ erro }}
                    </div>

                    <div class="ses-bloqueio-acoes">
                        <button type="button" class="btn btn-outline-secondary ses-bloqueio-cancelar" :disabled="processando" data-testid="cancelar-bloqueio" @click="fechar">
                            Cancelar
                        </button>
                        <button type="button" class="btn btn-danger" :disabled="processando" data-testid="confirmar-bloqueio" @click="confirmar">
                            <span v-if="processando" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                            {{ processando ? 'Bloqueando...' : 'Confirmar bloqueio' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </teleport>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';

// Mesmo limite da API: motivo_bloqueio_publico => nullable|string|max:500 (CandidatoController::update).
const LIMITE_MOTIVO = 500;

const props = defineProps({
    aberto: { type: Boolean, default: false },
    nomeCandidato: { type: String, default: '' },
    motivo: { type: String, default: '' },
    processando: { type: Boolean, default: false },
    // Erro geral da requisição (403, 500, rede).
    erro: { type: String, default: '' },
    // Erro de validação do motivo devolvido pela API (422).
    erroMotivo: { type: String, default: '' },
});

// "fechar" = Cancelar, Esc ou clique fora; nunca durante o envio.
const emit = defineEmits(['update:motivo', 'fechar', 'confirmar']);
const dialogo = ref(null);
const campoMotivo = ref(null);
const erroLocal = ref('');

const caracteres = computed(() => props.motivo.length);
const restantes = computed(() => LIMITE_MOTIVO - caracteres.value);
const erroValidacao = computed(() => erroLocal.value || props.erroMotivo);

watch(() => props.aberto, async (aberto) => {
    if (!aberto) return;
    erroLocal.value = '';
    await nextTick();
    campoMotivo.value?.focus();
}, { immediate: true });

watch(() => props.motivo, () => {
    erroLocal.value = '';
});

function fechar() {
    if (props.processando) return;
    emit('fechar');
}

function confirmar() {
    if (props.processando) return;

    if (props.motivo.trim().length > LIMITE_MOTIVO) {
        erroLocal.value = `O motivo deve ter no máximo ${LIMITE_MOTIVO} caracteres.`;
        campoMotivo.value?.focus();
        return;
    }

    emit('confirmar');
}

function manterFocoNoDialogo(event) {
    const focaveis = [...(dialogo.value?.querySelectorAll('textarea, button:not([disabled])') ?? [])];
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
.ses-bloqueio-overlay {
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

.ses-bloqueio-modal {
    width: min(100%, 32rem);
    max-height: calc(100dvh - 2rem);
    overflow-y: auto;
    padding: 1.75rem 1.75rem 1.5rem;
    border: 1px solid var(--bs-border-color-translucent);
    border-radius: 1.25rem;
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    box-shadow: 0 1.25rem 3rem rgba(15, 23, 42, 0.28);
    outline: none;
}

.ses-bloqueio-cabecalho {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    margin-bottom: 1rem;
}

.ses-bloqueio-icone {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    background: var(--bs-danger-bg-subtle);
    color: var(--bs-danger-text-emphasis);
    font-size: 1.4rem;
}

.ses-bloqueio-titulo {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    line-height: 1.35;
}

.ses-bloqueio-mensagem {
    margin-bottom: 1.25rem;
    color: var(--bs-secondary-color);
    line-height: 1.6;
}

.ses-bloqueio-nome {
    color: var(--bs-emphasis-color);
    overflow-wrap: anywhere;
}

.ses-bloqueio-textarea {
    min-height: 7.5rem;
    resize: vertical;
}

.ses-bloqueio-rodape-campo {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 0.25rem 1rem;
    margin-top: 0.375rem;
}

.ses-bloqueio-ajuda,
.ses-bloqueio-contador {
    color: var(--bs-secondary-color);
}

.ses-bloqueio-contador {
    margin-left: auto;
    font-variant-numeric: tabular-nums;
}

.ses-bloqueio-contador--limite {
    color: var(--bs-danger-text-emphasis);
    font-weight: 600;
}

.ses-bloqueio-erro {
    margin: 1rem 0 0;
}

.ses-bloqueio-acoes {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
}

.ses-bloqueio-acoes .btn {
    min-height: 2.75rem;
    min-width: 9rem;
    border-radius: 0.75rem;
}

/* O cinza #6c757d do btn-outline-secondary fica com 3,8:1 sobre o fundo escuro do modal. */
html.dark .ses-bloqueio-cancelar {
    --bs-btn-color: #cbd5e1;
    --bs-btn-border-color: #64748b;
    --bs-btn-hover-color: #0f172a;
    --bs-btn-hover-bg: #cbd5e1;
    --bs-btn-hover-border-color: #cbd5e1;
    --bs-btn-active-color: #0f172a;
    --bs-btn-active-bg: #e2e8f0;
    --bs-btn-active-border-color: #e2e8f0;
    --bs-btn-disabled-color: #cbd5e1;
    --bs-btn-disabled-border-color: #64748b;
}

:global([data-bs-theme='dark']) .ses-bloqueio-overlay {
    background: rgba(2, 6, 23, 0.65);
}

:global([data-bs-theme='dark']) .ses-bloqueio-modal {
    box-shadow: 0 1.25rem 3rem rgba(0, 0, 0, 0.55);
}

.ses-bloqueio-enter-active,
.ses-bloqueio-leave-active {
    transition: opacity 0.18s ease;
}

.ses-bloqueio-enter-active .ses-bloqueio-modal,
.ses-bloqueio-leave-active .ses-bloqueio-modal {
    transition: transform 0.18s ease;
}

.ses-bloqueio-enter-from,
.ses-bloqueio-leave-to {
    opacity: 0;
}

.ses-bloqueio-enter-from .ses-bloqueio-modal,
.ses-bloqueio-leave-to .ses-bloqueio-modal {
    transform: translateY(0.5rem) scale(0.97);
}

@media (max-width: 576px) {
    .ses-bloqueio-modal {
        padding: 1.5rem 1.25rem 1.25rem;
    }

    .ses-bloqueio-acoes {
        flex-direction: column-reverse;
    }

    .ses-bloqueio-acoes .btn {
        width: 100%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .ses-bloqueio-enter-active,
    .ses-bloqueio-leave-active,
    .ses-bloqueio-enter-active .ses-bloqueio-modal,
    .ses-bloqueio-leave-active .ses-bloqueio-modal {
        transition: none;
    }
}
</style>
