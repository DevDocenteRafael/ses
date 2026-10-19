<template>
    <div ref="menuRef" class="ses-accessibility" :class="[`ses-accessibility--${variant}`, { 'is-open': aberto }]">
        <button
            ref="triggerRef"
            type="button"
            class="ses-accessibility__trigger"
            :aria-expanded="aberto"
            aria-controls="ses-accessibility-panel"
            aria-label="Abrir opções de acessibilidade"
            title="Acessibilidade"
            @click="alternarMenu"
        >
            <i class="bi bi-universal-access" aria-hidden="true"></i>
        </button>

        <Teleport to="body">
            <Transition name="ses-accessibility-panel">
                <section
                    v-if="aberto"
                    ref="panelRef"
                    id="ses-accessibility-panel"
                    class="ses-accessibility__panel"
                    :style="panelStyle"
                    aria-labelledby="ses-accessibility-title"
                >
                    <h2 id="ses-accessibility-title" class="ses-accessibility__title">Acessibilidade</h2>

                    <div class="ses-accessibility__row">
                        <span class="ses-accessibility__label">Tema</span>
                        <div class="ses-accessibility__actions" role="group" aria-label="Escolha do tema">
                            <button
                                type="button"
                                class="ses-accessibility__option"
                                :class="{ active: !theme.isDark }"
                                aria-label="Ativar modo claro"
                                :aria-pressed="!theme.isDark"
                                @click="theme.definirTema('light')"
                            >
                                <i class="bi bi-sun-fill" aria-hidden="true"></i>
                            </button>
                            <button
                                type="button"
                                class="ses-accessibility__option"
                                :class="{ active: theme.isDark }"
                                aria-label="Ativar modo escuro"
                                :aria-pressed="theme.isDark"
                                @click="theme.definirTema('dark')"
                            >
                                <i class="bi bi-moon-stars-fill" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="ses-accessibility__row ses-accessibility__row--stacked">
                        <span class="ses-accessibility__label">Tamanho</span>
                        <div class="ses-accessibility__scale" role="group" aria-label="Tamanho da fonte">
                            <button
                                type="button"
                                class="ses-accessibility__option ses-accessibility__font-option"
                                aria-label="Diminuir tamanho da fonte"
                                :disabled="estaNoMinimo"
                                @click="diminuirFonte"
                            >
                                A−
                            </button>

                            <output class="ses-accessibility__scale-value" aria-live="polite">
                                {{ fonteAtual }}%
                            </output>

                            <button
                                type="button"
                                class="ses-accessibility__option ses-accessibility__font-option"
                                aria-label="Aumentar tamanho da fonte"
                                :disabled="estaNoMaximo"
                                @click="aumentarFonte"
                            >
                                A+
                            </button>
                        </div>

                        <button
                            type="button"
                            class="ses-accessibility__restore"
                            :disabled="fonteAtual === FONTE_PADRAO"
                            @click="restaurarFontePadrao"
                        >
                            Restaurar 100%
                        </button>
                    </div>
                </section>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useThemeStore } from '../../store/theme';

const props = defineProps({
    variant: {
        type: String,
        default: 'header',
        validator: (value) => ['header', 'floating'].includes(value),
    },
});

const STORAGE_KEY = 'ses_font_scale';
const FONTE_PADRAO = 100;
const NIVEIS_FONTE = [100, 125, 150, 175, 200];

const classesFonte = NIVEIS_FONTE.map((nivel) => `ses-font-scale-${nivel}`);
const theme = useThemeStore();
const aberto = ref(false);
const fonteAtual = ref(FONTE_PADRAO);
const menuRef = ref(null);
const triggerRef = ref(null);
const panelRef = ref(null);
const panelStyle = ref({});

const indiceFonteAtual = computed(() => NIVEIS_FONTE.indexOf(fonteAtual.value));
const estaNoMinimo = computed(() => indiceFonteAtual.value <= 0);
const estaNoMaximo = computed(() => indiceFonteAtual.value >= NIVEIS_FONTE.length - 1);

function aplicarFonte(valor) {
    if (typeof document === 'undefined') return;

    document.documentElement.classList.remove(...classesFonte);
    document.documentElement.classList.add(`ses-font-scale-${valor}`);
}

function definirFonte(valor) {
    if (!NIVEIS_FONTE.includes(valor)) return;

    fonteAtual.value = valor;
    localStorage.setItem(STORAGE_KEY, valor);
    aplicarFonte(valor);
}

function aumentarFonte() {
    if (estaNoMaximo.value) return;

    definirFonte(NIVEIS_FONTE[indiceFonteAtual.value + 1]);
}

function diminuirFonte() {
    if (estaNoMinimo.value) return;

    definirFonte(NIVEIS_FONTE[indiceFonteAtual.value - 1]);
}

function restaurarFontePadrao() {
    definirFonte(FONTE_PADRAO);
}

function restaurarFontePersistida() {
    const fonteSalva = Number(localStorage.getItem(STORAGE_KEY));
    definirFonte(NIVEIS_FONTE.includes(fonteSalva) ? fonteSalva : FONTE_PADRAO);
}

function alternarMenu() {
    aberto.value = !aberto.value;
}

function limparEspacoMenuFlutuante() {
    if (typeof document === 'undefined') return;

    document.documentElement.style.removeProperty('--ses-accessibility-floating-mobile-space');
}

async function atualizarEspacoMenuFlutuante() {
    if (typeof window === 'undefined' || props.variant !== 'floating' || !aberto.value) {
        limparEspacoMenuFlutuante();
        return;
    }

    if (!window.matchMedia('(max-width: 575.98px)').matches) {
        limparEspacoMenuFlutuante();
        return;
    }

    await nextTick();

    const alturaPainel = panelRef.value?.offsetHeight || 0;

    if (!alturaPainel) return;

    document.documentElement.style.setProperty('--ses-accessibility-floating-mobile-space', `${alturaPainel + 28}px`);
}

function calcularPosicaoPainel() {
    if (!triggerRef.value || typeof window === 'undefined') {
        panelStyle.value = {};
        return;
    }

    const triggerRect = triggerRef.value.getBoundingClientRect();
    const gap = 12;
    const painelLargura = panelRef.value?.offsetWidth || 280;
    const painelAltura = panelRef.value?.offsetHeight || 240;
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const padding = 12;

    const alinhamentoVertical = props.variant === 'floating' ? 'above' : 'below';

    let left = triggerRect.right - painelLargura;
    let top = triggerRect.bottom + gap;

    if (alinhamentoVertical === 'above') {
        top = triggerRect.top - painelAltura - gap;
    }

    if (left < padding) {
        left = padding;
    }

    const maxLeft = viewportWidth - painelLargura - padding;
    if (left > maxLeft) {
        left = maxLeft;
    }

    if (alinhamentoVertical === 'below') {
        const maxTop = viewportHeight - painelAltura - padding;
        if (top > maxTop) {
            top = Math.max(padding, triggerRect.top - painelAltura - gap);
        }
    }

    if (alinhamentoVertical === 'above') {
        const minTop = padding;
        const maxTopAbove = triggerRect.bottom - painelAltura - gap;
        if (top < minTop) {
            top = Math.min(Math.max(minTop, maxTopAbove), viewportHeight - painelAltura - padding);
        }
    }

    panelStyle.value = {
        left: `${Math.max(padding, Math.min(left, viewportWidth - painelLargura - padding))}px`,
        top: `${Math.max(padding, Math.min(top, viewportHeight - painelAltura - padding))}px`,
    };
}

async function atualizarPosicaoPainel() {
    await nextTick();
    calcularPosicaoPainel();
    await atualizarEspacoMenuFlutuante();
}

function fecharAoClicarFora(event) {
    if (!aberto.value) return;
    const cliqueNoTrigger = menuRef.value?.contains(event.target);
    const cliqueNoPainel = panelRef.value?.contains(event.target);
    if (cliqueNoTrigger || cliqueNoPainel) return;
    aberto.value = false;
}

function fecharComEsc(event) {
    if (event.key === 'Escape') {
        aberto.value = false;
    }
}

onMounted(() => {
    restaurarFontePersistida();
    document.addEventListener('click', fecharAoClicarFora);
    document.addEventListener('keydown', fecharComEsc);
    window.addEventListener('resize', atualizarPosicaoPainel);
    window.addEventListener('scroll', atualizarPosicaoPainel, true);
    window.addEventListener('resize', atualizarEspacoMenuFlutuante);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', fecharAoClicarFora);
    document.removeEventListener('keydown', fecharComEsc);
    window.removeEventListener('resize', atualizarPosicaoPainel);
    window.removeEventListener('scroll', atualizarPosicaoPainel, true);
    window.removeEventListener('resize', atualizarEspacoMenuFlutuante);
    limparEspacoMenuFlutuante();
});

watch([aberto, props.variant], async () => {
    await nextTick();
    calcularPosicaoPainel();
    await atualizarEspacoMenuFlutuante();
}, { flush: 'post' });

watch(() => fonteAtual.value, async () => {
    await nextTick();
    calcularPosicaoPainel();
});
</script>

<style scoped>
.ses-accessibility {
    position: relative;
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
}

.ses-accessibility--floating {
    position: fixed;
    right: 20px;
    top: 50%;
    z-index: 1040;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
    transform: translateY(-50%);
}

.ses-accessibility__trigger {
    width: 38px;
    height: 38px;
    padding: 0;
    border: 1px solid rgba(255, 255, 255, 0.32);
    border-radius: 999px;
    background: var(--ses-accent, #F78B1F);
    color: #ffffff;
    box-shadow: 0 8px 18px rgba(var(--ses-accent-rgb, 247, 139, 31), 0.3), 0 2px 6px rgba(15, 23, 42, 0.14);
    font-size: 1.15rem;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
    z-index: 1;
}

.ses-accessibility--floating .ses-accessibility__trigger {
    width: 56px;
    height: 56px;
    box-shadow: 0 14px 32px rgba(var(--ses-accent-rgb, 247, 139, 31), 0.34), 0 4px 14px rgba(15, 23, 42, 0.2);
    font-size: 1.65rem;
}

.ses-accessibility__trigger:hover,
.ses-accessibility.is-open .ses-accessibility__trigger {
    background: var(--ses-accent-hover, #d77c0a);
    border-color: rgba(255, 255, 255, 0.48);
    box-shadow: 0 10px 22px rgba(var(--ses-accent-rgb, 247, 139, 31), 0.38), 0 3px 8px rgba(15, 23, 42, 0.16);
    transform: translateY(-1px);
}

.ses-accessibility--floating .ses-accessibility__trigger:hover,
.ses-accessibility--floating.is-open .ses-accessibility__trigger {
    box-shadow: 0 16px 36px rgba(var(--ses-accent-rgb, 247, 139, 31), 0.42), 0 5px 16px rgba(15, 23, 42, 0.24);
    transform: translateY(-2px);
}

.ses-accessibility__trigger:focus-visible,
.ses-accessibility__option:focus-visible {
    outline: 3px solid #ffffff;
    outline-offset: 3px;
    box-shadow: 0 0 0 0.25rem rgba(var(--ses-accent-rgb, 247, 139, 31), 0.45), 0 0 0 0.45rem rgba(var(--ses-primary-rgb, 0, 69, 135), 0.35);
}

.ses-accessibility__panel {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1040;
    width: min(280px, calc(100vw - 24px));
    max-height: min(70vh, calc(100vh - 24px));
    overflow-y: auto;
    overflow-x: visible;
    overscroll-behavior: contain;
    padding: 18px;
    border: 1px solid var(--ses-border, rgba(0, 0, 0, 0.08));
    border-radius: 18px;
    background: var(--ses-surface, #ffffff);
    color: var(--ses-text, #212529);
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.22);
    pointer-events: auto;
}

.ses-accessibility__panel::after {
    content: '';
    position: absolute;
    right: 21px;
    top: -9px;
    width: 18px;
    height: 18px;
    background: var(--ses-surface, #ffffff);
    border-left: 1px solid var(--ses-border, rgba(0, 0, 0, 0.08));
    border-top: 1px solid var(--ses-border, rgba(0, 0, 0, 0.08));
    transform: rotate(45deg);
}

.ses-accessibility--floating .ses-accessibility__panel::after {
    top: auto;
    bottom: -9px;
    border-top: 0;
    border-left: 0;
    border-right: 1px solid var(--ses-border, rgba(0, 0, 0, 0.08));
    border-bottom: 1px solid var(--ses-border, rgba(0, 0, 0, 0.08));
}

.ses-accessibility__title {
    margin: 0 0 16px;
    font-size: 1rem;
    font-weight: 700;
}

.ses-accessibility__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 10px 0;
}

.ses-accessibility__row--stacked {
    align-items: stretch;
    flex-direction: column;
    gap: 12px;
}

.ses-accessibility__row + .ses-accessibility__row {
    border-top: 1px solid var(--ses-border, rgba(0, 0, 0, 0.08));
}

.ses-accessibility__label {
    font-weight: 600;
    color: var(--ses-muted, #6c757d);
}

.ses-accessibility__actions {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.ses-accessibility__scale {
    display: grid;
    grid-template-columns: minmax(40px, 48px) minmax(0, 1fr) minmax(40px, 48px);
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.ses-accessibility__scale-value {
    min-height: 40px;
    padding: 0 12px;
    border: 1px solid var(--ses-border, rgba(0, 0, 0, 0.08));
    border-radius: 999px;
    background: rgba(var(--ses-primary-rgb, 0, 69, 135), 0.08);
    color: var(--ses-text, #212529);
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.ses-accessibility__option {
    min-width: 40px;
    height: 38px;
    border: 1px solid var(--ses-border, rgba(0, 0, 0, 0.08));
    border-radius: 999px;
    background: rgba(var(--ses-primary-rgb, 0, 69, 135), 0.08);
    color: var(--ses-primary, #004587);
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.ses-accessibility__option:hover,
.ses-accessibility__option.active {
    background: var(--ses-primary, #004587);
    border-color: var(--ses-primary, #004587);
    color: #ffffff;
    transform: translateY(-1px);
}

.ses-accessibility__option:disabled,
.ses-accessibility__restore:disabled {
    cursor: not-allowed;
    opacity: 0.48;
    transform: none;
}

.ses-accessibility__option:disabled:hover {
    background: rgba(var(--ses-primary-rgb, 0, 69, 135), 0.08);
    border-color: var(--ses-border, rgba(0, 0, 0, 0.08));
    color: var(--ses-primary, #004587);
    transform: none;
}

.ses-accessibility__font-option {
    min-width: 48px;
    font-size: 1rem;
}

.ses-accessibility__restore {
    width: 100%;
    min-height: 40px;
    border: 1px solid var(--ses-border, rgba(0, 0, 0, 0.08));
    border-radius: 12px;
    background: transparent;
    color: var(--ses-primary, #004587);
    font-weight: 700;
    transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.ses-accessibility__restore:hover:not(:disabled),
.ses-accessibility__restore:focus-visible {
    background: rgba(var(--ses-primary-rgb, 0, 69, 135), 0.08);
    border-color: var(--ses-primary, #004587);
}

.ses-accessibility-panel-enter-active,
.ses-accessibility-panel-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.ses-accessibility-panel-enter-from,
.ses-accessibility-panel-leave-to {
    opacity: 0;
    transform: translateY(12px) scale(0.96);
}

/* Dark theme visuals are driven by CSS variables defined in resources/css/app.css */

@media (max-width: 575.98px) {
    .ses-accessibility {
        position: static;
    }

    .ses-accessibility--floating {
        position: fixed;
        top: calc(env(safe-area-inset-top, 0px) + 18px);
        right: 0;
        left: 0;
        z-index: 1040;
        align-items: center;
        justify-content: center;
        pointer-events: none;
        transform: none;
    }

    .ses-accessibility--floating .ses-accessibility__trigger,
    .ses-accessibility--floating .ses-accessibility__panel {
        pointer-events: auto;
    }

    .ses-accessibility__panel {
        width: min(280px, calc(100vw - 24px));
        max-height: calc(100vh - 24px);
        max-height: calc(100dvh - 24px);
        padding: 14px;
    }

    .ses-accessibility__panel::after {
        display: none;
    }

    .ses-accessibility__row {
        align-items: flex-start;
        flex-direction: column;
        gap: 10px;
    }

    .ses-accessibility__actions {
        flex-wrap: wrap;
    }

    .ses-accessibility__scale {
        width: 100%;
        gap: 8px;
    }

    .ses-accessibility__option,
    .ses-accessibility__font-option {
        min-width: 40px;
        height: 36px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .ses-accessibility__trigger,
    .ses-accessibility__option,
    .ses-accessibility__restore,
    .ses-accessibility-panel-enter-active,
    .ses-accessibility-panel-leave-active {
        transition: none !important;
    }
}
</style>
