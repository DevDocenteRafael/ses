<template>
    <aside class="ses-sidebar d-flex flex-column flex-shrink-0 p-3 text-white">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <span class="ses-brand fs-4 fw-bold">Senac</span>
            <button
                type="button"
                class="btn btn-sm ses-toggle rounded-circle"
                aria-label="Recolher menu"
            >
                <i class="bi bi-chevron-left"></i>
            </button>
        </div>

        <ul class="nav nav-pills flex-column mb-auto gap-1">
            <li v-for="item in items" :key="item.to">
                <router-link
                    :to="{ name: item.to }"
                    class="nav-link ses-nav-link d-flex align-items-center justify-content-between text-white"
                    active-class="ses-nav-link-active"
                >
                    <span>
                        <i class="bi" :class="item.icon"></i>
                        <span class="ms-2">{{ item.label }}</span>
                    </span>
                    <span v-if="item.badge" class="badge rounded-pill text-bg-danger">
                        {{ item.badge }}
                    </span>
                </router-link>
            </li>
        </ul>

        <hr class="ses-divider">

        <button type="button" class="btn ses-nav-link text-white d-flex align-items-center" @click="$emit('sair')">
            <i class="bi bi-box-arrow-left"></i>
            <span class="ms-2">Sair</span>
        </button>
    </aside>
</template>

<script setup>
defineProps({
    /**
     * Itens do menu: [{ label, icon (classe bootstrap-icons), to (nome da rota), badge (opcional) }]
     */
    items: {
        type: Array,
        required: true,
    },
});

defineEmits(['sair']);
</script>

<style scoped>
.ses-sidebar {
    width: 260px;
    min-height: 100vh;
    background-color: #142a4d;
}

.ses-brand {
    letter-spacing: 0.5px;
}

.ses-toggle {
    background-color: #f5a623;
    border: none;
    width: 28px;
    height: 28px;
    color: #142a4d;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ses-nav-link {
    color: rgba(255, 255, 255, 0.85);
    border-radius: 0.375rem;
    padding: 0.6rem 0.9rem;
    border-left: 3px solid transparent;
}

.ses-nav-link:hover {
    background-color: rgba(255, 255, 255, 0.08);
    color: #fff;
}

.ses-nav-link-active {
    background-color: rgba(245, 166, 35, 0.15);
    border-left-color: #f5a623;
    color: #fff;
    font-weight: 600;
}

.ses-divider {
    border-color: rgba(255, 255, 255, 0.15);
}
</style>
