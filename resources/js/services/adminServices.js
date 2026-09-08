import api from './api';

/**
 * Chamadas de API usadas pelo painel administrativo (SENAC).
 */
export default {
    // Dashboard
    getDashboard() {
        return api.get('/administrativo/dashboard');
    },

    // Gestão de alunos (candidatos)
    listarAlunos(params = {}) {
        return api.get('/candidatos', { params });
    },

    verAluno(matricula) {
        return api.get(`/candidatos/${matricula}`);
    },

    atualizarStatusAluno(matricula, status) {
        return api.put(`/candidatos/${matricula}`, { status });
    },

    cadastrarAluno(dados) {
        return api.post('/candidatos', dados);
    },

    sincronizarAlunos(dados) {
        return api.post('/administrativo/sincronizar-alunos', dados);
    },

    // Gestão de empresas
    listarEmpresas(params = {}) {
        return api.get('/empresas', { params });
    },

    verEmpresa(cnpj) {
        return api.get(`/empresas/${encodeURIComponent(cnpj)}`);
    },

    atualizarStatusEmpresa(cnpj, status) {
        return api.put(`/empresas/${encodeURIComponent(cnpj)}`, { status });
    },

    cadastrarEmpresa(dados) {
        return api.post('/empresas', dados);
    },

    // Vagas
    listarVagas(params = {}) {
        return api.get('/vagas', { params });
    },

    // Convites (usados no dashboard para indicadores de contato/contratação)
    listarConvites(params = {}) {
        return api.get('/convites', { params });
    },

    // Engajamento por unidade
    listarEngajamento() {
        return api.get('/administrativo/engajamento');
    },

    criarEngajamento(dados) {
        return api.post('/administrativo/engajamento', dados);
    },

    atualizarEngajamento(unidade, dados) {
        return api.put(`/administrativo/engajamento/${encodeURIComponent(unidade)}`, dados);
    },

    // Relatórios
    getRelatorioEngajamento(params = {}) {
        return api.get('/administrativo/relatorios/engajamento', { params });
    },
};
