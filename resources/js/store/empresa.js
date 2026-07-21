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

    getters: {
        // Vagas, convites e candidatos favoritados da empresa logada
        // vêm todos juntos de um único GET /empresas/{cnpj} (ver
        // EmpresaController@show), que já carrega essas relações.
        vagasDaEmpresa: (state) => state.perfil?.vagas ?? [],

        convitesDaEmpresa: (state) =>
            [...(state.perfil?.convites ?? [])].sort(
                (a, b) => new Date(b.data_envio) - new Date(a.data_envio)
            ),

        favoritosDaEmpresa: (state) => state.perfil?.candidatos ?? [],
    },

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

        async criarVaga(dados) {
            const { data } = await empresaService.criarVaga(dados);
            this.vagas.unshift(data);
            if (this.perfil) {
                this.perfil.vagas = [data, ...(this.perfil.vagas ?? [])];
            }
            return data;
        },

        async atualizarVaga(id, dados) {
            const { data } = await empresaService.atualizarVaga(id, dados);
            const substituir = (lista) => {
                const i = lista?.findIndex((v) => v.id_vaga === id);
                if (lista && i > -1) lista.splice(i, 1, { ...lista[i], ...data });
            };
            substituir(this.vagas);
            substituir(this.perfil?.vagas);
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

        async enviarConvite(dados) {
            const { data } = await empresaService.enviarConvite(dados);
            this.convites.unshift(data);
            if (this.perfil) {
                this.perfil.convites = [data, ...(this.perfil.convites ?? [])];
            }
            return data;
        },

        async excluirConvite(id) {
            await empresaService.excluirConvite(id);
            if (this.perfil?.convites) {
                this.perfil.convites = this.perfil.convites.filter((c) => c.id !== id);
            }
        },

        /**
         * Favorita um candidato. Recebe o objeto do candidato (já
         * carregado em `candidatosEncontrados`) para poder refletir a
         * mudança na tela imediatamente, sem precisar de outra chamada.
         */
        async favoritar(candidato) {
            await empresaService.favoritarCandidato(candidato.matricula);
            if (this.perfil) {
                const jaFavoritado = (this.perfil.candidatos ?? []).some(
                    (c) => c.matricula === candidato.matricula
                );
                if (!jaFavoritado) {
                    this.perfil.candidatos = [...(this.perfil.candidatos ?? []), candidato];
                }
            }
        },

        async desfavoritar(matricula) {
            await empresaService.desfavoritarCandidato(matricula);
            if (this.perfil?.candidatos) {
                this.perfil.candidatos = this.perfil.candidatos.filter(
                    (c) => c.matricula !== matricula
                );
            }
        },
    },
});
