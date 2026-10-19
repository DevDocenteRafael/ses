<template>
    <div class="d-flex vh-100 overflow-hidden">
        <SlideBar v-if="!rotaPerfil && !auth.candidatoContratado" :items="menu" @sair="sair" />

        <main class="flex-grow-1 bg-body-tertiary overflow-y-auto">
            <router-view v-if="!auth.candidatoContratado" />
        </main>

        <CandidatoContratadoModal v-if="auth.candidatoContratado" @sair="sair" />
        <CandidatoReativacaoQuestionarioModal v-if="auth.restricoes?.inativo && !auth.candidatoContratado" @sair="sair" @concluido="questionarioConcluido" />
        <CandidatoReativadoSucessoModal v-if="showReativado" @closed="onFechouReativado" />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../store/auth';
import SlideBar from '../components/common/slideBar.vue';
import CandidatoContratadoModal from '../components/aluno/CandidatoContratadoModal.vue';
import CandidatoReativacaoQuestionarioModal from '../components/aluno/CandidatoReativacaoQuestionarioModal.vue';
import CandidatoReativadoSucessoModal from '../components/aluno/CandidatoReativadoSucessoModal.vue';

const router = useRouter();
const route = useRoute();
const auth = useAuthStore();
const rotaPerfil = computed(() => route.name === 'aluno.perfil');

import { ref, onMounted } from 'vue';
const SHOW_KEY = 'ses_reativacao_sucesso';
const showReativado = ref(false);

onMounted(() => {
    try {
        if (sessionStorage.getItem(SHOW_KEY)) {
            showReativado.value = true;
        }
    } catch (e) {}
});

// TODO: substituir pelo total real de convites pendentes vindo da API
// (ex.: auth.convitesPendentes ou uma store dedicada de convites).
const menu = [
    { label: 'Dashboard', icon: 'bi-grid-1x2', to: 'aluno.dashboard' },
    { label: 'Meu Perfil', icon: 'bi-person', to: 'aluno.perfil' },
    { label: 'Convites', icon: 'bi-envelope', to: 'aluno.convites', badge: 1 },
];

async function sair() {
    await auth.logout();
    router.push({ name: 'login' });
}

function questionarioConcluido(data) {
    if (data?.reativado) {
        try { sessionStorage.setItem(SHOW_KEY, '1'); } catch (e) {}
        showReativado.value = true;
        router.push({ name: 'aluno.perfil' });
    }
}

function onFechouReativado() {
    showReativado.value = false;
}
</script>
