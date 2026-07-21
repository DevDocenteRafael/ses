<template>
    <teleport to="body">
        <div v-if="show" class="ses-modal-backdrop" @click.self="$emit('fechar')">
            <div class="ses-modal-dialog card border-0 shadow-lg">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h2 class="h5 fw-bold mb-0">
                            <slot name="titulo">{{ titulo }}</slot>
                        </h2>
                        <button type="button" class="btn-close" aria-label="Fechar" @click="$emit('fechar')"></button>
                    </div>

                    <slot />
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
defineProps({
    show: { type: Boolean, default: false },
    titulo: { type: String, default: '' },
});
defineEmits(['fechar']);
</script>

<style scoped>
.ses-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(20, 42, 77, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1055;
    padding: 1rem;
}

.ses-modal-dialog {
    width: 100%;
    max-width: 560px;
    max-height: 90vh;
    overflow-y: auto;
}
</style>
