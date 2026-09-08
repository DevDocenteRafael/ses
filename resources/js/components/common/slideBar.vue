<template>
    <aside :class="['ses-sidebar', { 'ses-sidebar-collapsed': isCollapsed }]" class="d-flex flex-column flex-shrink-0 p-3 text-white">
        <div class="ses-header d-flex align-items-center justify-content-between mb-4">
            <span v-if="!isCollapsed" class="ses-brand fs-4 fw-bold">Senac</span>
            <button
                type="button"
                class="btn btn-sm ses-toggle rounded-circle flex-shrink-0"
                aria-label="Recolher menu"
                @click="toggleCollapse"
            >
                <i :class="['bi', isCollapsed ? 'bi-chevron-right' : 'bi-chevron-left']"></i>
            </button>
        </div>

        <ul class="nav nav-pills flex-column mb-auto gap-1">
            <li v-for="item in items" :key="item.to">
                <router-link
                    :to="{ name: item.to }"
                    class="nav-link ses-nav-link d-flex align-items-center justify-content-between text-white"
                    active-class="ses-nav-link-active"
                    exact-active-class="ses-nav-link-active"
                >
                    <span>
                        <i class="bi" :class="item.icon"></i>
                        <span class="ms-2 sidebar-label">{{ item.label }}</span>
                    </span>
                    <span v-if="item.badge" class="badge rounded-pill text-bg-danger sidebar-badge">
                        {{ item.badge }}
                    </span>
                </router-link>
            </li>
        </ul>

        <hr class="ses-divider">

        <button type="button" class="btn ses-nav-link text-white d-flex align-items-center" @click="$emit('sair')">
            <i class="bi bi-box-arrow-left"></i>
            <span class="ms-2 sidebar-label">Sair</span>
        </button>
    </aside>
</template>

<script setup>
import { ref } from 'vue';

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

const isCollapsed = ref(false);

const toggleCollapse = () => {
    isCollapsed.value = !isCollapsed.value;
};
</script>

<style scoped>
.ses-sidebar {
    width: 260px;
    min-height: 100vh;
    background-color: var(--ses-primary);
    transition: width 0.3s ease-in-out;
    box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.08);
}

.ses-sidebar-collapsed {
    width: 80px;
}

.ses-sidebar-collapsed .sidebar-label,
.ses-sidebar-collapsed .sidebar-badge {
    display: none;
}

.ses-sidebar-collapsed .ses-nav-link {
    justify-content: center;
    padding: 0.6rem;
}

.ses-header {
    min-height: 44px;
    align-items: center;
}

.ses-brand {
    letter-spacing: 0.5px;
    flex-grow: 1;
}

.ses-toggle {
    background-color: #ffffff;
    border: 1px solid rgba(0, 0, 0, 0.05);
    width: 28px;
    height: 28px;
    color: var(--ses-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.ses-toggle:hover {
    transform: scale(1.1);
    background-color: #f8f9fa;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.14);
}

.ses-nav-link {
    color: rgba(255, 255, 255, 0.85);
    border-radius: 0.5rem;
    padding: 0.6rem 0.9rem;
    border-left: 3px solid transparent;
    transition: all 0.2s ease;
}

.ses-nav-link:hover {
    background-color: rgba(255, 255, 255, 0.08);
    color: #fff;
}

.ses-nav-link-active {
    background-color: rgba(255, 255, 255, 0.14) !important;
    border-left-color: #f5a623 !important;
    color: #fff !important;
    font-weight: 600 !important;
}

.ses-divider {
    border-color: rgba(255, 255, 255, 0.15);
}
</style>
