import { defineStore } from 'pinia';
import empresaService from '../services/empresaServices';

export const useEmpresaStore = defineStore('empresa', {
    state: () => ({
        perfil: null,
        vagas: [],
        candidatosEncontrados: [],
        convites: [],
        carregando: false,
        erro: null,
    }),

    actions: {
        async carregarPerfil(cnpj) {
            this.carregando = true;
            this.erro = null;
            try {
                const { data } = await empresaService.verPerfil(cnpj);
                this.perfil = data;
            } catch (e) {
                this.erro = e.response?.data?.message || 'Erro ao carregar perfil.';
            } finally {
                this.carregando = false;
            }
        },

        async carregarMinhasVagas(params = {}) {
            this.carregando = true;
            this.erro = null;
            try {
                const { data } = await empresaService.listarMinhasVagas(params);
                this.vagas = data;
            } catch (e) {
                this.erro = e.response?.data?.message || 'Erro ao carregar vagas.';
            } finally {
                this.carregando = false;
            }
        },

        async criarVaga(dados) {
            const { data } = await empresaService.criarVaga(dados);
            this.vagas.unshift(data);
            return data;
        },

        async buscarTalentos(params = {}) {
            this.carregando = true;
            this.erro = null;
            try {
                const { data } = await empresaService.buscarTalentos(params);
                this.candidatosEncontrados = data;
            } catch (e) {
                this.erro = e.response?.data?.message || 'Erro ao buscar talentos.';
            } finally {
                this.carregando = false;
            }
        },

        async carregarConvites(params = {}) {
            const { data } = await empresaService.listarConvites(params);
            this.convites = data;
        },

        async enviarConvite(dados) {
            const { data } = await empresaService.enviarConvite(dados);
            this.convites.unshift(data);
            return data;
        },
    },
});