<template>
    <div class="container-fluid p-4">
        <div class="d-flex align-items-start justify-content-between mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Meu Perfil Profissional</h1>
                <p class="text-secondary mb-0">Mantenha seus dados atualizados para atrair mais empresas.</p>
            </div>
            <button type="button" class="btn btn-primary" :disabled="salvando || carregando" @click="salvar">
                <span v-if="salvando" class="spinner-border spinner-border-sm me-1"></span>
                Salvar Alterações
            </button>
        </div>

        <div v-if="carregando" class="text-center text-secondary py-5">
            <span class="spinner-border spinner-border-sm me-2"></span> Carregando perfil...
        </div>

        <template v-else>
            <div v-if="mensagem" class="alert" :class="mensagem.tipo === 'erro' ? 'alert-danger' : 'alert-success'">
                {{ mensagem.texto }}
            </div>

            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h2 class="text-uppercase text-secondary small fw-bold mb-3">Dados Acadêmicos (SIG)</h2>

                            <template v-if="dadosAcademicos">
                                <div class="mb-3">
                                    <p class="text-secondary small mb-0">Instituição</p>
                                    <p class="fw-semibold mb-0">{{ dadosAcademicos.instituicao }}</p>
                                </div>
                                <div class="mb-3">
                                    <p class="text-secondary small mb-0">Curso</p>
                                    <p class="fw-semibold mb-0">{{ dadosAcademicos.curso }}</p>
                                </div>
                                <div class="mb-3">
                                    <p class="text-secondary small mb-0">Unidade</p>
                                    <p class="fw-semibold mb-0">{{ dadosAcademicos.unidade }}</p>
                                </div>
                                <div class="mb-3">
                                    <p class="text-secondary small mb-0">Ano de Conclusão</p>
                                    <p class="fw-semibold mb-0">{{ anoConclusao }}</p>
                                </div>
                            </template>
                            <p v-else class="text-secondary small mb-3">
                                Seus dados acadêmicos ainda não foram sincronizados.
                            </p>

                            <div class="alert alert-light border small mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                Dados sincronizados via API SIG (FR4).
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h2 class="text-uppercase text-secondary small fw-bold mb-3">Links Externos</h2>

                            <div class="mb-3">
                                <label class="form-label">LinkedIn</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-linkedin"></i></span>
                                    <input v-model="links.linkedin" type="text" class="form-control" placeholder="linkedin.com/in/seuuser">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Portfólio</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-globe"></i></span>
                                    <input v-model="links.portfolio" type="text" class="form-control" placeholder="https://meuportfolio.com">
                                </div>
                            </div>
                            <div>
                                <label class="form-label">GitHub</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-github"></i></span>
                                    <input v-model="links.github" type="text" class="form-control" placeholder="github.com/seuuser">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h2 class="text-uppercase text-secondary small fw-bold mb-3">Informações Profissionais (FR5)</h2>

                            <div class="mb-3">
                                <label class="form-label">Sobre Mim</label>
                                <textarea
                                    v-model="perfil.sobre_mim"
                                    class="form-control"
                                    rows="4"
                                    maxlength="200"
                                    placeholder="Fale um pouco sobre sua trajetória, objetivos e o que você domina..."
                                ></textarea>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label">Cargo de Interesse</label>
                                    <input
                                        v-model="perfil.cargo_de_interesse"
                                        type="text"
                                        class="form-control"
                                        placeholder="Ex: Desenvolvedor Front-end Junior"
                                    >
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Área de Atuação</label>
                                    <select v-model="perfil.area_de_atuacao" class="form-select">
                                        <option>Tecnologia da Informação</option>
                                        <option>Administração</option>
                                        <option>Marketing</option>
                                        <option>Recursos Humanos</option>
                                        <option>Outra</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="form-label">Habilidades (Tags)</label>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span v-for="(hab, i) in perfil.habilidades" :key="hab" class="badge text-bg-primary-subtle text-primary d-flex align-items-center gap-1 py-2 px-3">
                                        {{ hab }}
                                        <i class="bi bi-x" role="button" @click="removerHabilidade(i)"></i>
                                    </span>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary rounded-circle"
                                        style="width: 28px; height: 28px;"
                                        @click="adicionarHabilidade"
                                    >
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h2 class="text-uppercase text-secondary small fw-bold mb-3">Preferências de Trabalho (FR6)</h2>

                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label d-block">Tipo de Contratação</label>
                                    <div class="form-check form-check-inline">
                                        <input v-model="preferencias.clt" class="form-check-input" type="checkbox" id="tipoClt">
                                        <label class="form-check-label" for="tipoClt">CLT</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input v-model="preferencias.estagio" class="form-check-input" type="checkbox" id="tipoEstagio">
                                        <label class="form-check-label" for="tipoEstagio">Estágio</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input v-model="preferencias.jovemAprendiz" class="form-check-input" type="checkbox" id="tipoJovemAprendiz">
                                        <label class="form-check-label" for="tipoJovemAprendiz">Jovem Aprendiz</label>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Disponibilidade de Horário</label>
                                    <select v-model="preferencias.disponibilidade_de_horario" class="form-select">
                                        <option>Manhã</option>
                                        <option>Tarde/Noite</option>
                                        <option>Integral</option>
                                    </select>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Região Administrativa (RA)</label>
                                    <input v-model="preferencias.regiao_administrativa" type="text" class="form-control" placeholder="Ex: Ceilândia">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Pretensão Salarial (Opcional)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">R$</span>
                                        <input v-model.number="preferencias.pretensao_salarial" type="number" min="0" step="0.01" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, reactive, ref, onMounted } from 'vue';
import { useAuthStore } from '../../../store/auth';
import alunosService from '../../../services/alunosServices';

const auth = useAuthStore();
const matricula = computed(() => auth.pessoa?.id_pessoa);

const carregando = ref(true);
const salvando = ref(false);
const mensagem = ref(null);

const dadosAcademicos = ref(null);

const links = reactive({
    linkedin: '',
    portfolio: '',
    github: '',
});

const perfil = reactive({
    sobre_mim: '',
    cargo_de_interesse: '',
    area_de_atuacao: 'Tecnologia da Informação',
    habilidades: [],
});

// Bitmask: CLT=1, Estagio=2, Jovem Aprendiz=4
const preferencias = reactive({
    clt: false,
    estagio: false,
    jovemAprendiz: false,
    disponibilidade_de_horario: 'Tarde/Noite',
    regiao_administrativa: '',
    pretensao_salarial: 0,
});

const anoConclusao = computed(() => {
    if (!dadosAcademicos.value?.ano_de_conclusao) return '-';
    return new Date(dadosAcademicos.value.ano_de_conclusao).getFullYear();
});

function aplicarTipoContratacao(valor) {
    const bitmask = valor || 0;
    preferencias.clt = Boolean(bitmask & 1);
    preferencias.estagio = Boolean(bitmask & 2);
    preferencias.jovemAprendiz = Boolean(bitmask & 4);
}

function tipoContratacaoBitmask() {
    return (preferencias.clt ? 1 : 0) + (preferencias.estagio ? 2 : 0) + (preferencias.jovemAprendiz ? 4 : 0);
}

async function carregar() {
    carregando.value = true;
    try {
        const { data } = await alunosService.verPerfil(matricula.value);

        dadosAcademicos.value = data.dados_academicos?.[0] || null;

        if (data.link_externo) {
            links.linkedin = data.link_externo.linkedin || '';
            links.portfolio = data.link_externo.portfolio || '';
            links.github = data.link_externo.github || '';
        }

        if (data.informacoes_profissionais) {
            perfil.sobre_mim = data.informacoes_profissionais.sobre_mim || '';
            perfil.cargo_de_interesse = data.informacoes_profissionais.cargo_de_interesse || '';
            perfil.area_de_atuacao = data.informacoes_profissionais.area_de_atuacao || perfil.area_de_atuacao;
            perfil.habilidades = data.informacoes_profissionais.habilidades || [];
        }

        if (data.preferencias_de_trabalho) {
            aplicarTipoContratacao(data.preferencias_de_trabalho.tipo_de_contratacao);
            preferencias.disponibilidade_de_horario = data.preferencias_de_trabalho.disponibilidade_de_horario || preferencias.disponibilidade_de_horario;
            preferencias.regiao_administrativa = data.preferencias_de_trabalho.regiao_administrativa || '';
            preferencias.pretensao_salarial = Number(data.preferencias_de_trabalho.pretensao_salarial || 0);
        }
    } finally {
        carregando.value = false;
    }
}

function adicionarHabilidade() {
    const nova = window.prompt('Nova habilidade:');
    if (nova?.trim()) {
        perfil.habilidades.push(nova.trim());
    }
}

function removerHabilidade(indice) {
    perfil.habilidades.splice(indice, 1);
}

async function salvar() {
    salvando.value = true;
    mensagem.value = null;
    try {
        await Promise.all([
            alunosService.salvarLinks(matricula.value, { ...links }),
            alunosService.salvarInfoProfissional(matricula.value, { ...perfil }),
            alunosService.salvarPreferencias(matricula.value, {
                tipo_de_contratacao: tipoContratacaoBitmask(),
                disponibilidade_de_horario: preferencias.disponibilidade_de_horario,
                regiao_administrativa: preferencias.regiao_administrativa,
                pretensao_salarial: preferencias.pretensao_salarial,
            }),
        ]);
        mensagem.value = { tipo: 'sucesso', texto: 'Perfil atualizado com sucesso.' };
    } catch (e) {
        mensagem.value = { tipo: 'erro', texto: 'Nao foi possivel salvar. Verifique os campos e tente novamente.' };
    } finally {
        salvando.value = false;
    }
}

onMounted(carregar);
</script>
