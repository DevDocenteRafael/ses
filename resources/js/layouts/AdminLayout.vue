<template>
    <div class="d-flex">
        <slideBar :items="menuItems" @sair="handleSair" />

        <main class="flex-grow-1 min-vh-100 bg-body-tertiary">
            <router-view />
        </main>
    </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import slideBar from '../components/common/slideBar.vue';
import { useAuthStore } from '../store/auth';

const router = useRouter();
const auth = useAuthStore();

const menuItems = [
    { label: 'Relatórios Geral', icon: 'bi-pie-chart-fill', to: 'admin.dashboard' },
    { label: 'Gestão de Empresas', icon: 'bi-building-fill', to: 'admin.empresas' },
    { label: 'Gestão dos Candidatos', icon: 'bi-people-fill', to: 'admin.alunos' },
];

async function handleSair() {
    await auth.logout();
    router.push('/login');
}
</script>
