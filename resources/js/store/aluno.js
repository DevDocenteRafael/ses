import { defineStore } from 'pinia';
import alunoService from '../services/alunosServices';

export const useAlunoStore = defineStore('aluno', {
    state: () => ({
        perfil: null,
        convites: [],
        dashboard: null,
        carregando: false,
        erro: null,
    }),

    actions: {
        async carregarDashboard(matricula) {
            this.carregando = true;
            this.erro = null;
            try {
                const { data } = await alunoService.dashboard(matricula);
                this.dashboard = data;
                return data;
            } catch (e) {
                this.erro = e.response?.data?.message || 'Erro ao carregar indicadores do painel.';
            } finally {
                this.carregando = false;
            }
        },

        async carregarPerfil(matricula) {
            this.carregando = true;
            this.erro = null;
            try {
                const { data } = await alunoService.verPerfil(matricula);
                this.perfil = data;
            } catch (e) {
                this.erro = e.response?.data?.message || 'Erro ao carregar perfil.';
            } finally {
                this.carregando = false;
            }
        },

        async salvarLinks(matricula, dados) {
            await alunoService.salvarLinks(matricula, dados);
            await this.carregarPerfil(matricula);
        },

        async salvarInfoProfissional(matricula, dados) {
            await alunoService.salvarInfoProfissional(matricula, dados);
            await this.carregarPerfil(matricula);
        },

        async salvarPreferencias(matricula, dados) {
            await alunoService.salvarPreferencias(matricula, dados);
            await this.carregarPerfil(matricula);
        },

        async adicionarFormacaoAcademica(matricula, dados) {
            await alunoService.adicionarFormacaoAcademica(matricula, dados);
            await this.carregarPerfil(matricula);
        },

        async carregarConvites(matricula) {
            const { data } = await alunoService.listarConvites(matricula);
            this.convites = data;
        },

        async responderConvite(id, status) {
            await alunoService.responderConvite(id, status);
            const convite = this.convites.find((c) => c.id === id);
            if (convite) convite.status = status;
        },
    },
});