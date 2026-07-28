<template>
    <div>
        <topbar titulo="Configurações" subtitulo="Preferências do painel administrativo" />

        <div class="container-fluid p-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-gear text-secondary" style="font-size: 2rem;"></i>
                    <p class="text-secondary mt-3 mb-0">
                        Nenhuma configuração disponível ainda. Esta tela está reservada para
                        parâmetros do portal (ex: unidades do Senac DF, regras de aprovação de empresas).
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import topbar from '../../../components/common/header.vue';
</script>
