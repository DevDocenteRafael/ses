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
    { label: 'Dashboard', icon: 'bi-grid-1x2-fill', to: 'empresa.dashboard' },
    { label: 'Buscar Talentos', icon: 'bi-search', to: 'empresa.buscar-talentos' },
    { label: 'Favoritos', icon: 'bi-star-fill', to: 'empresa.favoritos' },
    { label: 'Convites Enviados', icon: 'bi-envelope-fill', to: 'empresa.convites' },
    { label: 'Minhas Vagas', icon: 'bi-briefcase-fill', to: 'empresa.minhas-vagas' },
];

async function handleSair() {
    await auth.logout();
    router.push('/login');
}
</script>
