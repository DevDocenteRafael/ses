import api from './api';

/**
 * Chamadas de API usadas pelo painel administrativo (SENAC).
 */
export default {
    // Dashboard
    getDashboard() {
        return api.get('/administrativo/dashboard');
    },

    gerarRelatorioDashboard(payload) {
        return api.post('/administrativo/relatorios/dashboard', payload, {
            responseType: 'blob',
        });
    },

    // Gestão de alunos (candidatos)
    listarAlunos(params = {}) {
        return api.get('/candidatos', { params });
    },

    listarUnidadesAlunos() {
        return api.get('/candidatos/unidades');
    },

    listarCursosAlunos(busca = '') {
        return api.get('/candidatos/cursos', { params: busca ? { busca } : {} });
    },

    verAluno(matricula) {
        return api.get(`/candidatos/${matricula}`);
    },

    atualizarStatusAluno(matricula, status) {
        return api.put(`/candidatos/${matricula}`, { status });
    },

    registrarContratacao(matricula, dados) {
        return api.post(`/candidatos/${encodeURIComponent(matricula)}/contratacao`, dados);
    },

    listarContratacoes(params = {}) {
        return api.get('/contratacoes', { params });
    },

    cadastrarAluno(dados) {
        return api.post('/candidatos', dados);
    },

    baixarCurriculoAluno(matricula) {
        return api.get(`/administrativo/candidatos/${encodeURIComponent(matricula)}/curriculo`, {
            responseType: 'blob',
        });
    },

    baixarCurriculosAlunos(params = {}) {
        return api.post('/administrativo/candidatos/curriculos/zip', params, {
            responseType: 'blob',
        });
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
