import api from './api';

/**
 * Centraliza todas as chamadas de API relacionadas à autenticação.
 * As views (LoginView.vue, etc.) e a store (store/auth.js) chamam
 * estas funções em vez de usar `api` diretamente.
 */
export default {
    /**
     * Autentica a pessoa e retorna { token, pessoa, tipo }.
     * `tipo` é usado pelo router para decidir qual painel mostrar
     * ('administrativo' | 'empresa' | 'candidato').
     */
    login(credenciais) {
        return api.post('/auth/login', credenciais);
    },

    logout() {
        return api.post('/auth/logout');
    },

    /**
     * Retorna os dados da pessoa autenticada com base no token atual.
     * Usado ao recarregar a página, para restaurar a sessão.
     */
    me() {
        return api.get('/auth/me');
    },

    solicitarRecuperacaoSenha(email) {
        return api.post('/auth/forgot-password', { email });
    },

    redefinirSenha(dados) {
        return api.post('/auth/reset-password', dados);
    },
};