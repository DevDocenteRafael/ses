import { defineStore } from 'pinia';

const STORAGE_KEY = 'ses_theme';
const DARK_CLASS = 'dark';

function detectarPreferenciaInicial() {
    if (typeof window === 'undefined') return 'light';

    const temaSalvo = localStorage.getItem(STORAGE_KEY);
    if (temaSalvo === 'light' || temaSalvo === 'dark') {
        return temaSalvo;
    }

    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function aplicarTemaNoDocumento(theme) {
    if (typeof document === 'undefined') return;

    document.documentElement.classList.toggle(DARK_CLASS, theme === 'dark');
    document.documentElement.setAttribute('data-bs-theme', theme);
    document.documentElement.style.colorScheme = theme;
}

export const useThemeStore = defineStore('theme', {
    state: () => ({
        theme: detectarPreferenciaInicial(),
    }),

    getters: {
        isDark: (state) => state.theme === 'dark',
        proximoTema: (state) => (state.theme === 'dark' ? 'light' : 'dark'),
        toggleLabel: (state) => (state.theme === 'dark' ? 'Ativar modo claro' : 'Ativar modo escuro'),
        toggleIcon: (state) => (state.theme === 'dark' ? 'bi-sun-fill' : 'bi-moon-stars-fill'),
    },

    actions: {
        inicializar() {
            this.theme = detectarPreferenciaInicial();
            aplicarTemaNoDocumento(this.theme);
        },

        definirTema(theme) {
            if (theme !== 'light' && theme !== 'dark') return;

            this.theme = theme;
            localStorage.setItem(STORAGE_KEY, theme);
            aplicarTemaNoDocumento(theme);
        },

        alternarTema() {
            this.definirTema(this.proximoTema);
        },
    },
});
