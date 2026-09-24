<template>
    <div ref="menuRef" class="ses-accessibility" :class="{ 'is-open': aberto }">
        <Transition name="ses-accessibility-panel">
            <section
                v-if="aberto"
                id="ses-accessibility-panel"
                class="ses-accessibility__panel"
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

        <button
            type="button"
            class="ses-accessibility__trigger"
            :aria-expanded="aberto"
            aria-controls="ses-accessibility-panel"
            aria-label="Abrir menu de acessibilidade"
            @click="alternarMenu"
        >
            <i class="bi bi-universal-access" aria-hidden="true"></i>
        </button>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useThemeStore } from '../../store/theme';

const STORAGE_KEY = 'ses_font_scale';
const FONTE_PADRAO = 100;
const NIVEIS_FONTE = [100, 125, 150, 175, 200];

const classesFonte = NIVEIS_FONTE.map((nivel) => `ses-font-scale-${nivel}`);
const theme = useThemeStore();
const aberto = ref(false);
const fonteAtual = ref(FONTE_PADRAO);
const menuRef = ref(null);

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

function fecharAoClicarFora(event) {
    if (!aberto.value || menuRef.value?.contains(event.target)) return;
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
});

onBeforeUnmount(() => {
    document.removeEventListener('click', fecharAoClicarFora);
    document.removeEventListener('keydown', fecharComEsc);
});
</script>

<style scoped>
.ses-accessibility {
    position: fixed;
    right: 20px;
    top: 50%;
    z-index: 1080;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
    transform: translateY(-50%);
}

.ses-accessibility__trigger {
    width: 56px;
    height: 56px;
    padding: 0;
    border: 1px solid rgba(255, 255, 255, 0.32);
    border-radius: 999px;
    background: var(--ses-primary, #004587);
    color: #ffffff;
    box-shadow: 0 14px 32px rgba(0, 69, 135, 0.32), 0 4px 14px rgba(15, 23, 42, 0.2);
    font-size: 1.65rem;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.ses-accessibility__trigger:hover,
.ses-accessibility.is-open .ses-accessibility__trigger {
    background: var(--ses-primary-hover, #003366);
    border-color: rgba(255, 255, 255, 0.48);
    box-shadow: 0 16px 36px rgba(0, 51, 102, 0.38), 0 5px 16px rgba(15, 23, 42, 0.24);
    transform: translateY(-2px);
}

.ses-accessibility__trigger:focus-visible,
.ses-accessibility__option:focus-visible {
    outline: 3px solid #ffffff;
    outline-offset: 3px;
    box-shadow: 0 0 0 0.25rem rgba(var(--ses-primary-rgb, 0, 69, 135), 0.35);
}

.ses-accessibility__panel {
    width: min(280px, calc(100vw - 40px));
    padding: 18px;
    border: 1px solid var(--ses-border, rgba(0, 0, 0, 0.08));
    border-radius: 18px;
    background: var(--ses-surface, #ffffff);
    color: var(--ses-text, #212529);
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.22);
}

.ses-accessibility__panel::after {
    content: '';
    position: absolute;
    right: 21px;
    bottom: 68px;
    width: 18px;
    height: 18px;
    background: var(--ses-surface, #ffffff);
    border-right: 1px solid var(--ses-border, rgba(0, 0, 0, 0.08));
    border-bottom: 1px solid var(--ses-border, rgba(0, 0, 0, 0.08));
    transform: rotate(45deg);
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
}

.ses-accessibility__scale {
    display: grid;
    grid-template-columns: 48px minmax(84px, 1fr) 48px;
    align-items: center;
    gap: 10px;
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

:global(html.dark) .ses-accessibility__trigger {
    background: #0056a8;
    box-shadow: 0 14px 34px rgba(0, 86, 168, 0.42), 0 4px 16px rgba(0, 0, 0, 0.44);
}

:global(html.dark) .ses-accessibility__trigger:hover,
:global(html.dark) .ses-accessibility.is-open .ses-accessibility__trigger {
    background: #0069cc;
}

:global(html.dark) .ses-accessibility__panel {
    box-shadow: 0 18px 45px rgba(0, 0, 0, 0.48);
}

@media (max-width: 575.98px) {
    .ses-accessibility {
        right: 16px;
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
