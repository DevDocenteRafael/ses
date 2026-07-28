<template>
    <div class="d-flex">
        <slideBar :items="menuItems" @sair="handleSair" />

        <main class="flex-grow-1 min-vh-100 bg-body-tertiary">
            <router-view />
        </main>
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import slideBar from '../components/common/slideBar.vue';
import { useAuthStore } from '../store/auth';
import { useAdminStore } from '../store/admin';

const router = useRouter();
const auth = useAuthStore();
const admin = useAdminStore();

// Carrega as empresas uma vez para que o badge de "aguardando aprovação"
// já apareça correto assim que o painel abre, em qualquer tela.
onMounted(() => {
    if (!admin.empresas.length) {
        admin.carregarEmpresas();
    }
});

const menuItems = computed(() => [
    { label: 'Relatórios Geral', icon: 'bi-pie-chart-fill', to: 'admin.dashboard' },
    {
        label: 'Gestão de Empresas',
        icon: 'bi-building-fill',
        to: 'admin.empresas',
        badge: admin.empresasPendentes.length || undefined,
    },
    { label: 'Gestão de Alunos', icon: 'bi-people-fill', to: 'admin.alunos' },
    { label: 'Configurações', icon: 'bi-gear-fill', to: 'admin.configuracoes' },
]);

async function handleSair() {
    await auth.logout();
    router.push('/login');
}
</script>
