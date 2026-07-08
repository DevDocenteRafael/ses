<template>
    <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-start justify-content-between">
            <div>
                <p class="text-uppercase text-secondary small fw-semibold mb-1">{{ titulo }}</p>
                <p class="fs-3 fw-bold mb-1">{{ valor }}</p>
                <p v-if="subtitulo" class="small mb-0" :class="subtituloClass">
                    {{ subtitulo }}
                </p>
            </div>
            <span
                class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                :class="`bg-${variante}-subtle text-${variante}`"
                style="width: 44px; height: 44px;"
            >
                <i class="bi" :class="icone"></i>
            </span>
        </div>
    </div>
</template>

<script setup>
defineProps({
    titulo: { type: String, required: true },
    valor: { type: [String, Number], required: true },
    subtitulo: { type: String, default: '' },
    subtituloClass: { type: String, default: 'text-success' },
    icone: { type: String, default: 'bi-graph-up' },
    variante: { type: String, default: 'primary' },
});
</script>
