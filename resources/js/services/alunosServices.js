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

    // Cursos realizados no Senac (fallback manual — normalmente vem do SIG)
    adicionarCursoSenac(matricula, dados) {
        return api.post(`/candidatos/${matricula}/perfil/cursos-senac`, dados);
    },

    removerCursoSenac(id) {
        return api.delete(`/cursos-senac/${id}`);
    },

    // Cursos externos
    adicionarCursoExterno(matricula, dados) {
        return api.post(`/candidatos/${matricula}/perfil/cursos-externos`, dados);
    },

    removerCursoExterno(matricula, id) {
        return api.delete(`/candidatos/${matricula}/perfil/cursos-externos/${id}`);
    },

    // Experiências profissionais
    adicionarExperiencia(matricula, dados) {
        return api.post(`/candidatos/${matricula}/perfil/experiencias`, dados);
    },

    atualizarExperiencia(matricula, id, dados) {
        return api.put(`/candidatos/${matricula}/perfil/experiencias/${id}`, dados);
    },

    removerExperiencia(matricula, id) {
        return api.delete(`/candidatos/${matricula}/perfil/experiencias/${id}`);
    },

    // Convites recebidos
    listarConvites(matricula, filtros = {}) {
        return api.get('/convites', { params: { candidatos_matricula: matricula, ...filtros } });
    },

    responderConvite(id, status) {
        return api.put(`/convites/${id}`, { status });
    },
};