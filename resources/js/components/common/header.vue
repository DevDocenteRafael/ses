<template>
    <header class="ses-topbar d-flex align-items-center justify-content-between px-4 py-3 bg-white">
        <div>
            <h1 class="h4 fw-bold mb-0">{{ titulo }}</h1>
            <p v-if="subtitulo" class="text-secondary small mb-0">{{ subtitulo }}</p>
        </div>

        <div class="d-flex align-items-center gap-3">
            <slot name="acoes" />

            <div v-if="auth.pessoa" class="d-flex align-items-center gap-3">
                <div class="text-end d-none d-sm-block">
                    <p class="fw-semibold mb-0">Administrador SENAC DF</p>
                    <p class="text-secondary small mb-0">{{ cargoLabel }}</p>
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
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    box-shadow: 0 1px 8px rgba(15, 23, 42, 0.04);
}

.ses-avatar {
    width: 44px;
    height: 44px;
    background-color: #ffffff;
    color: var(--ses-primary);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
}
</style>
