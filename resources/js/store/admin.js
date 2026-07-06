import { defineStore } from 'pinia';
import adminService from '../services/adminServices';

export const useAdminStore = defineStore('admin', {
    state: () => ({
        alunos: [],
        empresas: [],
        vagas: [],
        engajamento: [],
        carregando: false,
        erro: null,
    }),

    actions: {
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