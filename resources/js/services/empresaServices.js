import api from './api';

/**
 * Chamadas de API usadas pelo painel da empresa.
 */
export default {
    // Perfil da empresa
    verPerfil(cnpj) {
        return api.get(`/empresas/${encodeURIComponent(cnpj)}`);
    },

    atualizarPerfil(cnpj, dados) {
        return api.put(`/empresas/${encodeURIComponent(cnpj)}`, dados);
    },

    // Vagas da empresa
    listarMinhasVagas(params = {}) {
        return api.get('/vagas', { params });
    },

    criarVaga(dados) {
        return api.post('/vagas', dados);
    },

    atualizarVaga(id, dados) {
        return api.put(`/vagas/${id}`, dados);
    },

    excluirVaga(id) {
        return api.delete(`/vagas/${id}`);
    },

    // Buscar talentos (candidatos)
    buscarTalentos(params = {}) {
        return api.get('/candidatos', { params });
    },

    verTalento(matricula) {
        return api.get(`/candidatos/${matricula}`);
    },

    // Convites enviados
    listarConvites(params = {}) {
        return api.get('/convites', { params });
    },

    enviarConvite(dados) {
        return api.post('/convites', dados);
    },

    atualizarConvite(id, dados) {
        return api.put(`/convites/${id}`, dados);
    },

    // Favoritos (candidatos salvos pela empresa)
    listarFavoritos() {
        return api.get('/empresas/favoritos');
    },

    favoritarCandidato(matricula) {
        return api.post(`/empresas/favoritos/${matricula}`);
    },

    desfavoritarCandidato(matricula) {
        return api.delete(`/empresas/favoritos/${matricula}`);
    },
};