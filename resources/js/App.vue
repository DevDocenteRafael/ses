<script setup>
import { onMounted } from 'vue';
import { useAuthStore } from './store/auth';

// Ao iniciar a aplicação (ex: usuário deu F5), tenta restaurar a sessão
// a partir do token salvo no localStorage.
const auth = useAuthStore();
onMounted(() => {
    auth.restaurarSessao();
});
</script>

<template>
    <!--
        O <router-view> é onde o Vue Router injeta o componente da
        rota atual. Como definimos Layouts como rotas-pai, o que aparece
        aqui primeiro é sempre um Layout (Auth/Admin/Empresa/Aluno),
        que por sua vez tem seu próprio <router-view> interno para a
        página específica.
    -->
    <router-view />
</template>