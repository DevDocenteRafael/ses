<template>
    <div class="modal-backdrop fade show"></div>
    <div
        ref="modalRef"
        class="modal fade show d-block candidato-contratado-modal"
        tabindex="-1"
        role="dialog"
        aria-modal="true"
        aria-labelledby="candidato-contratado-title"
        aria-describedby="candidato-contratado-message"
        @keydown.esc.prevent.stop
        @keydown.tab="manterFocoNoModal"
    >
        <div class="modal-dialog modal-dialog-centered candidato-contratado-modal__dialog">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-body text-center p-4 p-sm-5">
                    <div class="candidato-contratado-modal__icon mx-auto mb-3" aria-hidden="true">
                        <i class="bi bi-lock-fill"></i>
                    </div>
                    <h1 id="candidato-contratado-title" class="h4 fw-bold mb-3">Perfil indisponível</h1>
                    <p id="candidato-contratado-message" class="text-body-secondary mb-3">
                        Seu perfil foi registrado como contratado. Por esse motivo, não é mais possível realizar alterações ou utilizar as funcionalidades do portal do candidato.
                    </p>
                    <p class="text-body-secondary mb-4">Para acessar outra conta, encerre esta sessão.</p>
                    <button ref="sairButtonRef" type="button" class="btn btn-primary px-4" @click="$emit('sair')">
                        <i class="bi bi-box-arrow-left me-2"></i>Sair
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

defineEmits(['sair']);

const modalRef = ref(null);
const sairButtonRef = ref(null);

function focaveis() {
    return Array.from(modalRef.value?.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])') || [])
        .filter((elemento) => !elemento.disabled && elemento.offsetParent !== null);
}

function manterFocoNoModal(event) {
    const elementos = focaveis();
    if (!elementos.length) return;

    const primeiro = elementos[0];
    const ultimo = elementos[elementos.length - 1];

    if (event.shiftKey && document.activeElement === primeiro) {
        event.preventDefault();
        ultimo.focus();
    } else if (!event.shiftKey && document.activeElement === ultimo) {
        event.preventDefault();
        primeiro.focus();
    }
}

function impedirFocoFora(event) {
    if (!modalRef.value?.contains(event.target)) {
        event.stopPropagation();
        sairButtonRef.value?.focus();
    }
}

onMounted(() => {
    document.body.classList.add('modal-open');
    document.addEventListener('focusin', impedirFocoFora, true);
    nextTick(() => sairButtonRef.value?.focus());
});

onBeforeUnmount(() => {
    document.body.classList.remove('modal-open');
    document.removeEventListener('focusin', impedirFocoFora, true);
});
</script>

<style scoped>
.candidato-contratado-modal {
    z-index: 1060;
}

.candidato-contratado-modal__dialog {
    width: min(92vw, 30rem);
}

.candidato-contratado-modal__icon {
    align-items: center;
    background: var(--bs-primary-bg-subtle);
    border-radius: 999px;
    color: var(--bs-primary);
    display: flex;
    font-size: 1.5rem;
    height: 3.5rem;
    justify-content: center;
    width: 3.5rem;
}

@media (max-width: 360px) {
    .candidato-contratado-modal__dialog {
        width: calc(100vw - 1rem);
    }
}
</style>
