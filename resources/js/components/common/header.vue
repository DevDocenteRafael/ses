<template>
    <header class="ses-topbar d-flex align-items-center justify-content-between px-4 py-3 text-white">
        <div class="ses-topbar__title">
            <h1 class="h4 fw-bold mb-0">{{ titulo }}</h1>
            <p v-if="subtitulo" class="ses-topbar-subtitle small mb-0">{{ subtitulo }}</p>
        </div>

        <div class="ses-topbar__actions d-flex align-items-center gap-3">
            <slot name="acoes" />

            <div v-if="auth.pessoa" class="ses-topbar__user d-flex align-items-center gap-3">
                <AccessibilityMenu />
                <div class="text-end d-none d-sm-block">
                    <p class="fw-semibold mb-0">Administrador SENAC DF</p>
                    <p class="ses-topbar-subtitle small mb-0">{{ cargoLabel }}</p>
                </div>
                <span
                    class="ses-avatar rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                >
                    {{ iniciais }}
                </span>
            </div>
        </div>
    </header>
</template>

<script setup>
import { computed } from 'vue';
import AccessibilityMenu from './AccessibilityMenu.vue';
import { useAuthStore } from '../../store/auth';

defineProps({
    titulo: { type: String, required: true },
    subtitulo: { type: String, default: '' },
});

const auth = useAuthStore();

const cargoPorTipo = {
    empresa: 'Recrutamento',
    administrativo: 'Administrador(a)',
    candidato: 'Candidato(a)',
};

const cargoLabel = computed(() => cargoPorTipo[auth.tipo] || '');

const iniciais = computed(() => {
    if (!auth.pessoa?.nome) return '';
    return auth.pessoa.nome
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0].toUpperCase())
        .join('');
});
</script>

<style scoped>
.ses-topbar {
    background-color: var(--ses-primary);
    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: 0 1px 8px rgba(15, 23, 42, 0.12);
    position: sticky;
    top: 0;
    z-index: 1020;
}

.ses-topbar__title,
.ses-topbar__actions,
.ses-topbar__user {
    min-width: 0;
}

.ses-topbar__title h1,
.ses-topbar__title p {
    overflow-wrap: anywhere;
}

.ses-topbar-subtitle {
    color: rgba(255, 255, 255, 0.75);
}

.ses-avatar {
    width: 44px;
    height: 44px;
    background-color: #ffffff;
    color: var(--ses-primary);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
}

@media (max-width: 575.98px) {
    .ses-topbar {
        align-items: flex-start !important;
        flex-direction: column;
        gap: 0.75rem;
        padding: 0.75rem !important;
    }

    .ses-topbar__title,
    .ses-topbar__actions,
    .ses-topbar__user {
        width: 100%;
    }

    .ses-topbar__title h1 {
        font-size: 1.1rem;
        line-height: 1.25;
    }

    .ses-topbar-subtitle {
        line-height: 1.35;
    }

    .ses-topbar__actions,
    .ses-topbar__user {
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 0.5rem !important;
    }
}
</style>
