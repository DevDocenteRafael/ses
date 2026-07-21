<template>
    <header class="ses-topbar d-flex align-items-center justify-content-between px-4 py-3 bg-white border-bottom">
        <div>
            <h1 class="h4 fw-bold mb-0">{{ titulo }}</h1>
            <p v-if="subtitulo" class="text-secondary small mb-0">{{ subtitulo }}</p>
        </div>

        <div v-if="auth.pessoa" class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-sm-block">
                <p class="fw-semibold mb-0">{{ auth.pessoa.nome }}</p>
                <p class="text-secondary small mb-0">{{ cargoLabel }}</p>
            </div>
            <span
                class="ses-avatar rounded-circle d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0"
            >
                {{ iniciais }}
            </span>
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
.ses-avatar {
    width: 44px;
    height: 44px;
    background-color: #f5a623;
    color: #142a4d;
}
</style>
