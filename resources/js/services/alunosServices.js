import api from './api';

/**
 * Chamadas de API usadas pelo painel do aluno/candidato.
 */
export default {
    // Painel (indicadores, convites pendentes, últimas visualizações)
    dashboard(matricula) {
        return api.get(`/candidatos/${matricula}/dashboard`);
    },

    // Perfil básico
    verPerfil(matricula) {
        return api.get(`/candidatos/${matricula}`);
    },

    atualizarPerfil(matricula, dados) {
        return api.put(`/candidatos/${matricula}`, dados);
    },

    // Currículo (links, info profissional, preferências, dados acadêmicos)
    salvarLinks(matricula, dados) {
        return api.post(`/candidatos/${matricula}/perfil/links`, dados);
    },

    salvarInfoProfissional(matricula, dados) {
        return api.post(`/candidatos/${matricula}/perfil/profissional`, dados);
    },

    salvarPreferencias(matricula, dados) {
        return api.post(`/candidatos/${matricula}/perfil/preferencias`, dados);
    },

    adicionarFormacaoAcademica(matricula, dados) {
        return api.post(`/candidatos/${matricula}/perfil/academico`, dados);
    },

    removerFormacaoAcademica(id) {
        return api.delete(`/academico/${id}`);
    },

    // Convites recebidos
    listarConvites(matricula, filtros = {}) {
        return api.get('/convites', { params: { candidatos_matricula: matricula, ...filtros } });
    },

    responderConvite(id, status) {
        return api.put(`/convites/${id}`, { status });
    },
};