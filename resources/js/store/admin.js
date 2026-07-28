import { defineStore } from 'pinia';
import adminService from '../services/adminServices';

export const useAdminStore = defineStore('admin', {
    state: () => ({
        alunos: [],
        empresas: [],
        vagas: [],
        convites: [],
        engajamento: [],
        carregando: false,
        erro: null,

        // TODO(back-end): a tabela `empresa` ainda não tem uma coluna de
        // aprovação de cadastro. Enquanto essa migração não existe, guardamos
        // aqui (em memória, não persiste) quais CNPJs foram marcados como
        // "pendente" pela equipe do SENAC, para a tela de Gestão de Empresas
        // funcionar como no protótipo.
        statusEmpresas: {},
    }),

    getters: {
        empresasPendentes: (state) => state.empresas.filter(
            (e) => state.statusEmpresas[e.cnpj] === 'pendente',
        ),
        empresasAprovadas: (state) => state.empresas.filter(
            (e) => state.statusEmpresas[e.cnpj] !== 'pendente',
        ),
    },

    actions: {
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

        marcarEmpresaPendente(cnpj) {
            this.statusEmpresas = { ...this.statusEmpresas, [cnpj]: 'pendente' };
        },

        marcarEmpresaAprovada(cnpj) {
            this.statusEmpresas = { ...this.statusEmpresas, [cnpj]: 'aprovada' };
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