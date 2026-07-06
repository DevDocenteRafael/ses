import { defineStore } from 'pinia';
import authService from '../services/authServices';

/**
 * Store central de autenticação.
 * Guarda quem está logado, o token, e o tipo de pessoa
 * ('administrativo' | 'empresa' | 'candidato'), que é o que o
 * router usa para decidir qual layout/painel mostrar.
 */
export const useAuthStore = defineStore('auth', {
    state: () => ({
        pessoa: JSON.parse(localStorage.getItem('ses_pessoa')) || null,
        token: localStorage.getItem('ses_token') || null,
        tipo: localStorage.getItem('ses_tipo') || null, // administrativo | empresa | candidato
    }),

    getters: {
        estaAutenticado: (state) => !!state.token,
        isAdmin: (state) => state.tipo === 'administrativo',
        isEmpresa: (state) => state.tipo === 'empresa',
        isAluno: (state) => state.tipo === 'candidato',
    },

    actions: {
        async login(credenciais) {
            const { data } = await authService.login(credenciais);

            this.token = data.token;
            this.pessoa = data.pessoa;
            this.tipo = data.tipo;

            localStorage.setItem('ses_token', data.token);
            localStorage.setItem('ses_pessoa', JSON.stringify(data.pessoa));
            localStorage.setItem('ses_tipo', data.tipo);

            return data;
        },

        async logout() {
            try {
                await authService.logout();
            } finally {
                this.limparSessao();
            }
        },

        /**
         * Restaura a sessão ao recarregar a página, validando o token
         * atual contra o backend.
         */
        async restaurarSessao() {
            if (!this.token) return;

            try {
                const { data } = await authService.me();
                this.pessoa = data.pessoa;
                this.tipo = data.tipo;
            } catch {
                this.limparSessao();
            }
        },

        limparSessao() {
            this.pessoa = null;
            this.token = null;
            this.tipo = null;
            localStorage.removeItem('ses_token');
            localStorage.removeItem('ses_pessoa');
            localStorage.removeItem('ses_tipo');
        },
    },
});