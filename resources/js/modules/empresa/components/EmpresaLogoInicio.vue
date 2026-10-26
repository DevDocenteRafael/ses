<template>
    <router-link
        :to="ROTA_INICIO_EMPRESA"
        class="empresa-logo-inicio"
        aria-label="Ir para o início do Portal da Empresa"
        title="Início do Portal da Empresa"
        data-testid="logo-inicio-empresa"
    >
        <img :src="logoSenacSrc" alt="" class="empresa-logo-inicio__img">
    </router-link>
</template>

<script setup>
// Início do Portal da Empresa: a mesma página para onde o login leva a empresa
// (painelPorTipo em LoginView.vue e router/index.js). A rota /empresa (DashboardView)
// é um painel antigo sem o cabeçalho do portal e não é usada como início.
const ROTA_INICIO_EMPRESA = { name: 'empresa.buscar-talentos' };
// Ligado com :src (arquivo de public/), como nas demais telas, para o Vite não tentar importá-lo.
const logoSenacSrc = '/img/senac-logo.png';
</script>

<style scoped>
/* Padding com margem negativa: área de clique maior sem mudar a altura do cabeçalho. */
.empresa-logo-inicio {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    padding: 0.25rem;
    margin: -0.25rem;
    border-radius: 0.5rem;
    transition: background-color 0.15s ease;
}

.empresa-logo-inicio:hover {
    background-color: rgba(255, 255, 255, 0.12);
}

/* Mesmo contorno de foco das Indicações Senac (laranja institucional, 3,8:1 sobre o azul do cabeçalho). */
.empresa-logo-inicio:focus-visible {
    outline: 3px solid var(--ses-accent, #f7941d);
    outline-offset: 2px;
}

.empresa-logo-inicio__img {
    display: block;
    width: auto;
    height: clamp(28px, 4vw, 36px);
    max-width: 120px;
    object-fit: contain;
}

@media (prefers-reduced-motion: reduce) {
    .empresa-logo-inicio {
        transition: none;
    }
}
</style>
