<script setup>
import { computed } from 'vue';
import { useToast } from '../../composables/useToast';

const { toasts, removeToast } = useToast();

const configuracoes = {
    success: {
        classe: 'ses-toast--success',
        icone: 'bi bi-check-circle-fill',
        rotulo: 'Sucesso',
        role: 'status',
        ariaLive: 'polite',
    },
    error: {
        classe: 'ses-toast--error',
        icone: 'bi bi-x-circle-fill',
        rotulo: 'Erro',
        role: 'alert',
        ariaLive: 'assertive',
    },
    warning: {
        classe: 'ses-toast--warning',
        icone: 'bi bi-exclamation-triangle-fill',
        rotulo: 'Atenção',
        role: 'alert',
        ariaLive: 'assertive',
    },
    info: {
        classe: 'ses-toast--info',
        icone: 'bi bi-info-circle-fill',
        rotulo: 'Informação',
        role: 'status',
        ariaLive: 'polite',
    },
};

const listaToasts = computed(() => toasts.value.map((toast) => ({
    ...toast,
    config: configuracoes[toast.tipo] || configuracoes.info,
})));
</script>

<template>
    <Teleport to="body">
        <div class="ses-toast-viewport" aria-label="Notificações do sistema">
            <TransitionGroup name="ses-toast" tag="div" class="ses-toast-stack">
                <div
                    v-for="toast in listaToasts"
                    :key="toast.id"
                    class="ses-toast shadow-sm"
                    :class="toast.config.classe"
                    :role="toast.config.role"
                    :aria-live="toast.config.ariaLive"
                    aria-atomic="true"
                >
                    <i class="ses-toast__icon" :class="toast.config.icone" aria-hidden="true"></i>
                    <div class="ses-toast__content">
                        <span class="visually-hidden">{{ toast.config.rotulo }}: </span>
                        {{ toast.mensagem }}
                    </div>
                    <button
                        type="button"
                        class="btn-close ses-toast__close"
                        aria-label="Fechar notificação"
                        @click="removeToast(toast.id)"
                    ></button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.ses-toast-viewport {
    position: fixed;
    top: calc(72px + env(safe-area-inset-top, 0px));
    left: 50%;
    z-index: 2000;
    width: min(560px, calc(100vw - 32px));
    max-width: calc(100vw - 32px);
    pointer-events: none;
    transform: translateX(-50%);
}

.ses-toast-stack {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
}

.ses-toast {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: flex-start;
    gap: 0.75rem;
    width: 100%;
    min-width: 0;
    padding: 0.9rem 1rem;
    border: 1px solid transparent;
    border-radius: 0.9rem;
    pointer-events: auto;
    overflow-wrap: anywhere;
}

.ses-toast--success {
    background: #e8f6ec;
    color: #0f5132;
    border-color: #badbcc;
}

.ses-toast--error {
    background: #fdeaea;
    color: #842029;
    border-color: #f5c2c7;
}

.ses-toast--warning {
    background: #fff3cd;
    color: #664d03;
    border-color: #ffecb5;
}

.ses-toast--info {
    background: #e7f1ff;
    color: #084298;
    border-color: #b6d4fe;
}

.ses-toast__icon {
    margin-top: 0.1rem;
    font-size: 1.1rem;
    line-height: 1;
}

.ses-toast__content {
    min-width: 0;
    line-height: 1.35;
}

.ses-toast__close {
    flex-shrink: 0;
    margin-top: 0.05rem;
}

.ses-toast-enter-active,
.ses-toast-leave-active,
.ses-toast-move {
    transition: opacity 0.35s ease, transform 0.35s ease;
}

.ses-toast-enter-from,
.ses-toast-leave-to {
    opacity: 0;
    transform: translateY(-12px);
}

.ses-toast-leave-active {
    position: absolute;
    width: 100%;
}

@media (max-width: 575.98px) {
    .ses-toast-viewport {
        top: calc(56px + env(safe-area-inset-top, 0px));
        width: calc(100vw - 24px);
        max-width: calc(100vw - 24px);
    }

    .ses-toast {
        padding: 0.8rem 0.85rem;
        gap: 0.6rem;
    }
}

:global([data-bs-theme='dark']) .ses-toast--success,
:global(.dark) .ses-toast--success {
    background: #0f3322;
    color: #d1f7df;
    border-color: #2f8f55;
}

:global([data-bs-theme='dark']) .ses-toast--error,
:global(.dark) .ses-toast--error {
    background: #3a171b;
    color: #ffd7dc;
    border-color: #8f333f;
}

:global([data-bs-theme='dark']) .ses-toast--warning,
:global(.dark) .ses-toast--warning {
    background: #3a2b08;
    color: #ffe9a6;
    border-color: #9a7210;
}

:global([data-bs-theme='dark']) .ses-toast--info,
:global(.dark) .ses-toast--info {
    background: #102b4d;
    color: #d7e9ff;
    border-color: #2f6fb4;
}
</style>
