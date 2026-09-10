<template>
    <button
        type="button"
        class="btn ses-theme-toggle rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0"
        :title="theme.toggleLabel"
        :aria-label="theme.toggleLabel"
        @click="theme.alternarTema"
    >
        <i class="bi" :class="theme.toggleIcon" aria-hidden="true"></i>
    </button>
</template>

<script setup>
import { useThemeStore } from '../../store/theme';

const theme = useThemeStore();
</script>

<style scoped>
.ses-theme-toggle {
    --theme-toggle-bg: var(--ses-primary, #004587);
    --theme-toggle-bg-hover: var(--ses-primary-hover, #003366);
    --theme-toggle-bg-active: var(--ses-primary-active, #002850);
    --theme-toggle-color: #ffffff;
    --theme-toggle-border: rgba(255, 255, 255, 0.32);
    --theme-toggle-focus: rgba(var(--ses-primary-rgb, 0, 69, 135), 0.32);

    width: 38px;
    height: 38px;
    padding: 0;
    background-color: var(--theme-toggle-bg);
    border: 1px solid var(--theme-toggle-border);
    color: var(--theme-toggle-color);
    box-shadow: 0 8px 18px rgba(0, 69, 135, 0.28), 0 2px 6px rgba(15, 23, 42, 0.14);
    transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.ses-theme-toggle:hover {
    background-color: var(--theme-toggle-bg-hover);
    border-color: rgba(255, 255, 255, 0.42);
    color: var(--theme-toggle-color);
    box-shadow: 0 10px 22px rgba(0, 51, 102, 0.34), 0 3px 8px rgba(15, 23, 42, 0.16);
    transform: translateY(-1px);
}

.ses-theme-toggle:focus-visible {
    background-color: var(--theme-toggle-bg);
    border-color: #ffffff;
    color: var(--theme-toggle-color);
    box-shadow: 0 0 0 0.25rem var(--theme-toggle-focus), 0 8px 18px rgba(0, 69, 135, 0.28);
    outline: 2px solid #ffffff;
    outline-offset: 2px;
}

.ses-theme-toggle:active {
    background-color: var(--theme-toggle-bg-active) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
    color: var(--theme-toggle-color) !important;
    box-shadow: 0 4px 12px rgba(0, 40, 80, 0.32) !important;
    transform: translateY(0) scale(0.97);
}

:global(html.dark) .ses-theme-toggle {
    --theme-toggle-bg: #0056a8;
    --theme-toggle-bg-hover: #0069cc;
    --theme-toggle-bg-active: #003f7d;
    --theme-toggle-color: #ffd166;
    --theme-toggle-border: rgba(255, 255, 255, 0.28);
    --theme-toggle-focus: rgba(125, 183, 255, 0.38);

    box-shadow: 0 8px 20px rgba(0, 86, 168, 0.38), 0 2px 8px rgba(0, 0, 0, 0.42);
}

:global(html.dark) .ses-theme-toggle:hover {
    box-shadow: 0 10px 24px rgba(0, 105, 204, 0.46), 0 3px 10px rgba(0, 0, 0, 0.48);
}

:global(html.dark) .ses-theme-toggle:focus-visible {
    outline-color: #7db7ff;
}
</style>
