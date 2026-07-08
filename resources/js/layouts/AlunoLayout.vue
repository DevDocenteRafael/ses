<template>
    <div class="d-flex">
        <SlideBar :items="menu" @sair="sair" />

        <main class="flex-grow-1 bg-body-tertiary min-vh-100">
            <router-view />
        </main>
    </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '../store/auth';
import SlideBar from '../components/common/slideBar.vue';

const router = useRouter();
const auth = useAuthStore();

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
</script>
