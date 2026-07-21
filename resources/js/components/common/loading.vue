<template>
    <div class="d-flex flex-column align-items-center justify-content-center py-5 text-secondary">
        <div class="spinner-border text-primary mb-2" role="status">
            <span class="visually-hidden">Carregando...</span>
        </div>
        <p v-if="mensagem" class="small mb-0">{{ mensagem }}</p>
    </div>
</template>

<script setup>
defineProps({
    mensagem: { type: String, default: '' },
});
</script>
