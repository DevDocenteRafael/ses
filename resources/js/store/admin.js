import { defineStore } from 'pinia';
import adminService from '../services/adminServices';

export const useAdminStore = defineStore('admin', {
    state: () => ({
        alunos: [],
        empresas: [],
        vagas: [],
        convites: [],
        engajamento: [],
        dashboard: null,
        carregando: false,
        erro: null,
    }),

    actions: {
        async carregarDashboard() {
            this.carregando = true;
            this.erro = null;
            try {
                const { data } = await adminService.getDashboard();
                this.dashboard = data;
            } catch (e) {
                this.erro = e.response?.data?.message || 'Erro ao carregar indicadores.';
            } finally {
                this.carregando = false;
            }
        },

        async carregarConvites(params = {}) {
            this.carregando = true;
            this.erro = null;
            try {
                const { data } = await adminService.listarConvites(params);
                this.convites = data;
            } catch (e) {
                this.erro = e.response?.data?.message || 'Erro ao carregar convites.';
            } finally {
                this.carregando = false;
            }
        },

        async carregarAlunos(params = {}) {
            this.carregando = true;
            this.erro = null;
            try {
                const { data } = await adminService.listarAlunos(params);
                this.alunos = data;
            } catch (e) {
                this.erro = e.response?.data?.message || 'Erro ao carregar alunos.';
            } finally {
                this.carregando = false;
            }
        },

        /**
         * Libera/bloqueia o acesso de um candidato (FR37). Persiste via
         * PUT /candidatos/{matricula} (coluna `candidato.status`).
         */
        async atualizarStatusAluno(matricula, status) {
            await adminService.atualizarStatusAluno(matricula, status);
            const aluno = this.alunos.find((a) => a.matricula === matricula);
            if (aluno) aluno.status = status;
        },

        async cadastrarAluno(dados) {
            const { data } = await adminService.cadastrarAluno(dados);
            this.alunos.unshift(data);
            return data;
        },

        async carregarEmpresas(params = {}) {
            this.carregando = true;
            this.erro = null;
            try {
                const { data } = await adminService.listarEmpresas(params);
                this.empresas = data;
            } catch (e) {
                this.erro = e.response?.data?.message || 'Erro ao carregar empresas.';
            } finally {
                this.carregando = false;
            }
        },

        /**
         * Libera/bloqueia o acesso de uma empresa (FR35). Persiste via
         * PUT /empresas/{cnpj} (coluna `empresa.status`).
         */
        async atualizarStatusEmpresa(cnpj, status) {
            await adminService.atualizarStatusEmpresa(cnpj, status);
            const empresa = this.empresas.find((e) => e.cnpj === cnpj);
            if (empresa) empresa.status = status;
        },

        async cadastrarEmpresa(dados) {
            const { data } = await adminService.cadastrarEmpresa(dados);
            this.empresas.unshift(data);
            return data;
        },

        async carregarVagas(params = {}) {
            this.carregando = true;
            this.erro = null;
            try {
                const { data } = await adminService.listarVagas(params);
                this.vagas = data;
            } catch (e) {
                this.erro = e.response?.data?.message || 'Erro ao carregar vagas.';
            } finally {
                this.carregando = false;
            }
        },

        async carregarEngajamento() {
            this.carregando = true;
            this.erro = null;
            try {
                const { data } = await adminService.listarEngajamento();
                this.engajamento = data;
            } catch (e) {
                this.erro = e.response?.data?.message || 'Erro ao carregar engajamento.';
            } finally {
                this.carregando = false;
            }
        },
    },
});
