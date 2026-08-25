<template>
    <div>
        <header class="bg-primary text-white px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold fs-5">Senac</span>
                <span class="vr d-none d-sm-block opacity-50 mx-1"></span>
                <div>
                    <h1 class="h5 fw-bold mb-0">Meu Perfil Profissional</h1>
                    <p class="small mb-0 opacity-75">Mantenha seus dados atualizados para atrair mais empresas.</p>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <div class="text-end d-none d-sm-block">
                    <p class="fw-semibold mb-0">{{ auth.pessoa?.nome || 'Aluno' }}</p>
                </div>
                <span class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center fw-semibold flex-shrink-0"
                      style="width: 38px; height: 38px;">
                    {{ iniciais }}
                </span>
                <button type="button" class="btn btn-sm btn-outline-light ms-2" @click="sair">
                    <i class="bi bi-box-arrow-left me-1"></i> Sair
                </button>
            </div>
        </header>

        <div class="container-fluid p-4">
        <div v-if="mensagem" class="alert" :class="mensagem.tipo === 'erro' ? 'alert-danger' : 'alert-success'">
            {{ mensagem.texto }}
        </div>


        <div v-if="carregando" class="text-center text-secondary py-5">
            <span class="spinner-border spinner-border-sm me-2"></span> Carregando perfil...
        </div>

        <template v-else>
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h2 class="text-uppercase text-secondary small fw-bold mb-0">Cursos Externos</h2>
                                <button type="button" class="btn btn-sm btn-primary" @click="mostrarFormCursoExterno = !mostrarFormCursoExterno">
                                    <i class="bi bi-plus-lg me-1"></i> Adicionar
                                </button>
                            </div>

                            <p v-if="!cursosExternos.length && !mostrarFormCursoExterno" class="text-secondary small mb-0">
                                Nenhum curso externo cadastrado ainda.
                            </p>

                            <div v-for="curso in cursosExternos" :key="curso.id" class="d-flex align-items-start justify-content-between mb-3">
                                <div>
                                    <p class="fw-semibold mb-0">{{ curso.nome_curso }}</p>
                                    <p class="text-secondary small mb-0">
                                        {{ curso.instituicao }} | Concluído em {{ anoDe(curso.concluido_em) }}<template v-if="curso.carga_horaria"> | {{ curso.carga_horaria }}h</template>
                                    </p>
                                </div>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0" @click="removerCursoExterno(curso.id)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>

                            <div v-if="mostrarFormCursoExterno" class="border rounded p-3 mt-2">
                                <div class="mb-2">
                                    <label class="form-label small mb-1">Nome do Curso</label>
                                    <input v-model="novoCursoExterno.nome_curso" type="text" class="form-control form-control-sm" placeholder="Ex: Inglês Intermediário">
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-sm-6">
                                        <label class="form-label small mb-1">Instituição</label>
                                        <input v-model="novoCursoExterno.instituicao" type="text" class="form-control form-control-sm" placeholder="Ex: CNA">
                                    </div>
                                    <div class="col-sm-3">
                                        <label class="form-label small mb-1">Carga Horária</label>
                                        <input
                                            :value="novoCursoExterno.carga_horaria ?? ''"
                                            type="text"
                                            inputmode="numeric"
                                            class="form-control form-control-sm sem-setas"
                                            placeholder="120h"
                                            @keydown="bloquearSinalNegativo"
                                            @input="normalizarCargaHorariaCursoExterno"
                                        >
                                    </div>
                                    <div class="col-sm-3">
                                        <label class="form-label small mb-1">Concluído em</label>
                                        <input v-model="novoCursoExterno.concluido_em" type="date" class="form-control form-control-sm">
                                    </div>
                                </div>
                                <div class="d-flex gap-2 justify-content-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="cancelarCursoExterno">Cancelar</button>
                                    <button type="button" class="btn btn-sm btn-primary" @click="adicionarCursoExterno">Salvar</button>
                                </div>
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

                    <div class="card border-0 shadow-sm mb-3">
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
                                        <option>Tarde</option>
                                        <option>Noite</option>
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
                                        <input
                                            :value="preferencias.pretensao_salarial"
                                            type="text"
                                            inputmode="numeric"
                                            class="form-control sem-setas"
                                            @keydown="bloquearSinalNegativo"
                                            @input="aplicarMascaraPretensaoSalarial"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-0">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h2 class="text-uppercase text-secondary small fw-bold mb-0">Experiências Profissionais</h2>
                                <button type="button" class="btn btn-sm btn-primary" @click="mostrarFormExperiencia = !mostrarFormExperiencia">
                                    <i class="bi bi-plus-lg me-1"></i> Adicionar
                                </button>
                            </div>

                            <p v-if="!experiencias.length && !mostrarFormExperiencia" class="text-secondary small mb-0">
                                Nenhuma experiência profissional cadastrada ainda.
                            </p>

                            <div v-for="exp in experiencias" :key="exp.id" class="d-flex align-items-start justify-content-between border-bottom pb-3 mb-3">
                                <div>
                                    <span class="badge text-bg-primary-subtle text-primary mb-1">{{ exp.tipo }}</span>
                                    <p class="fw-semibold mb-0">{{ exp.cargo }}</p>
                                    <p class="text-secondary small mb-1">{{ exp.empresa }}</p>
                                    <p class="text-secondary small mb-1">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        {{ mesAno(exp.data_inicio) }} - {{ exp.data_fim ? mesAno(exp.data_fim) : 'Atual' }}
                                        · {{ duracao(exp.data_inicio, exp.data_fim) }}
                                        <template v-if="exp.local"> · {{ exp.local }}</template>
                                    </p>
                                    <p v-if="exp.descricao" class="small mb-0">{{ exp.descricao }}</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0" @click="removerExperiencia(exp.id)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>

                            <div v-if="mostrarFormExperiencia" class="border rounded p-3">
                                <div class="row g-2 mb-2">
                                    <div class="col-sm-4">
                                        <label class="form-label small mb-1">Tipo</label>
                                        <select v-model="novaExperiencia.tipo" class="form-select form-select-sm">
                                            <option>Estágio</option>
                                            <option>CLT</option>
                                            <option>PJ / Freelancer</option>
                                            <option>Jovem Aprendiz</option>
                                            <option>Voluntariado</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label small mb-1">Cargo</label>
                                        <input v-model="novaExperiencia.cargo" type="text" class="form-control form-control-sm" placeholder="Ex: Desenvolvedor Web Estagiário">
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label small mb-1">Empresa</label>
                                        <input v-model="novaExperiencia.empresa" type="text" class="form-control form-control-sm" placeholder="Ex: TechSolutions LTDA">
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-sm-3">
                                        <label class="form-label small mb-1">Início</label>
                                        <input v-model="novaExperiencia.data_inicio" type="date" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-sm-3">
                                        <label class="form-label small mb-1">Fim</label>
                                        <input v-model="novaExperiencia.data_fim" type="date" class="form-control form-control-sm" :disabled="novaExperiencia.atual">
                                    </div>
                                    <div class="col-sm-2 d-flex align-items-end">
                                        <div class="form-check">
                                            <input v-model="novaExperiencia.atual" class="form-check-input" type="checkbox" id="expAtual">
                                            <label class="form-check-label small" for="expAtual">Atual</label>
                                        </div>
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label small mb-1">Local</label>
                                        <input v-model="novaExperiencia.local" type="text" class="form-control form-control-sm" placeholder="Ex: Brasília, DF">
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small mb-1">Descrição</label>
                                    <textarea v-model="novaExperiencia.descricao" class="form-control form-control-sm" rows="2" placeholder="Principais atividades e responsabilidades..."></textarea>
                                </div>
                                <div class="d-flex gap-2 justify-content-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="cancelarExperiencia">Cancelar</button>
                                    <button type="button" class="btn btn-sm btn-primary" @click="adicionarExperiencia">Salvar</button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="button" class="btn btn-primary" :disabled="salvando" @click="salvar">
                    <span v-if="salvando" class="spinner-border spinner-border-sm me-1"></span>
                    Salvar Alterações
                </button>
            </div>
        </template>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../../store/auth';
import alunosService from '../../../services/alunosServices';

const auth = useAuthStore();
const router = useRouter();

// Esta página não usa o AlunoLayout (sem sidebar, cabeçalho próprio),
// então precisa resolver iniciais/logout localmente.
const iniciais = computed(() => {
    const nome = auth.pessoa?.nome || 'Aluno';
    return nome
        .split(' ')
        .slice(0, 2)
        .map((parte) => parte[0])
        .join('')
        .toUpperCase();
});

async function sair() {
    await auth.logout();
    router.push({ name: 'login' });
}
const matricula = computed(() => auth.pessoa?.id_pessoa);

const carregando = ref(true);
const salvando = ref(false);
const mensagem = ref(null);

const dadosAcademicos = ref(null);
const cursosSenac = ref([]);
const cursosExternos = ref([]);
const experiencias = ref([]);

const mostrarFormCursoExterno = ref(false);
const mostrarFormExperiencia = ref(false);

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
    disponibilidade_de_horario: 'Manhã',
    regiao_administrativa: '',
    pretensao_salarial: '',
});

function formatarPretensaoSalarial(valor) {
    const apenasDigitos = String(valor ?? '').replace(/\D/g, '');

    if (!apenasDigitos) {
        return '';
    }

    const valorEmCentavos = Number(apenasDigitos) / 100;

    return valorEmCentavos.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function converterPretensaoSalarialParaNumero(valor) {
    const apenasDigitos = String(valor ?? '').replace(/\D/g, '');

    if (!apenasDigitos) {
        return null;
    }

    return Number(apenasDigitos) / 100;
}

function aplicarMascaraPretensaoSalarial(evento) {
    preferencias.pretensao_salarial = formatarPretensaoSalarial(evento.target.value);
}

function bloquearSinalNegativo(evento) {
    if (evento.key === '-') {
        evento.preventDefault();
    }
}

function normalizarCargaHorariaCursoExterno(evento) {
    const apenasDigitos = String(evento.target.value ?? '').replace(/\D/g, '');

    novoCursoExterno.carga_horaria = apenasDigitos === '' ? null : Number(apenasDigitos);
}

function cursoExternoVazio() {
    return { nome_curso: '', instituicao: '', carga_horaria: null, concluido_em: '' };
}

function experienciaVazia() {
    return { tipo: 'Estágio', cargo: '', empresa: '', local: '', data_inicio: '', data_fim: '', atual: false, descricao: '' };
}

const novoCursoExterno = reactive(cursoExternoVazio());
const novaExperiencia = reactive(experienciaVazia());

function anoDe(data) {
    if (!data) return '-';
    return new Date(data).getFullYear();
}

const MESES = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

function mesAno(data) {
    if (!data) return '';
    const d = new Date(data);
    return `${MESES[d.getMonth()]} ${d.getFullYear()}`;
}

function duracao(inicio, fim) {
    if (!inicio) return '';
    const dataInicio = new Date(inicio);
    const dataFim = fim ? new Date(fim) : new Date();
    const meses = Math.max(
        1,
        (dataFim.getFullYear() - dataInicio.getFullYear()) * 12 + (dataFim.getMonth() - dataInicio.getMonth()) + 1
    );
    return meses === 1 ? '1 mês' : `${meses} meses`;
}

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
        cursosSenac.value = data.cursos_senac || [];
        cursosExternos.value = data.cursos_externos || [];
        experiencias.value = (data.experiencias_profissionais || []).slice().sort((a, b) => new Date(b.data_inicio) - new Date(a.data_inicio));

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
            preferencias.pretensao_salarial = formatarPretensaoSalarial(data.preferencias_de_trabalho.pretensao_salarial);
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

function cancelarCursoExterno() {
    Object.assign(novoCursoExterno, cursoExternoVazio());
    mostrarFormCursoExterno.value = false;
}

async function adicionarCursoExterno() {
    if (!novoCursoExterno.nome_curso.trim() || !novoCursoExterno.instituicao.trim() || !novoCursoExterno.concluido_em) {
        mensagem.value = { tipo: 'erro', texto: 'Preencha nome, instituição e data de conclusão do curso externo.' };
        return;
    }
    try {
        await alunosService.adicionarCursoExterno(matricula.value, { ...novoCursoExterno });
        cancelarCursoExterno();
        await carregar();
    } catch (e) {
        mensagem.value = { tipo: 'erro', texto: 'Não foi possível adicionar o curso externo.' };
    }
}

async function removerCursoExterno(id) {
    try {
        await alunosService.removerCursoExterno(matricula.value, id);
        await carregar();
    } catch (e) {
        mensagem.value = { tipo: 'erro', texto: 'Não foi possível remover o curso externo.' };
    }
}

function cancelarExperiencia() {
    Object.assign(novaExperiencia, experienciaVazia());
    mostrarFormExperiencia.value = false;
}

async function adicionarExperiencia() {
    if (!novaExperiencia.cargo.trim() || !novaExperiencia.empresa.trim() || !novaExperiencia.data_inicio) {
        mensagem.value = { tipo: 'erro', texto: 'Preencha cargo, empresa e data de início da experiência.' };
        return;
    }
    try {
        await alunosService.adicionarExperiencia(matricula.value, {
            tipo: novaExperiencia.tipo,
            cargo: novaExperiencia.cargo,
            empresa: novaExperiencia.empresa,
            local: novaExperiencia.local,
            data_inicio: novaExperiencia.data_inicio,
            data_fim: novaExperiencia.atual ? null : (novaExperiencia.data_fim || null),
            descricao: novaExperiencia.descricao,
        });
        cancelarExperiencia();
        await carregar();
    } catch (e) {
        mensagem.value = { tipo: 'erro', texto: 'Não foi possível adicionar a experiência profissional.' };
    }
}

async function removerExperiencia(id) {
    try {
        await alunosService.removerExperiencia(matricula.value, id);
        await carregar();
    } catch (e) {
        mensagem.value = { tipo: 'erro', texto: 'Não foi possível remover a experiência profissional.' };
    }
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
                pretensao_salarial: converterPretensaoSalarialParaNumero(preferencias.pretensao_salarial),
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

<style scoped>
.border-dashed {
    border-style: dashed !important;
}

.sem-setas::-webkit-outer-spin-button,
.sem-setas::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.sem-setas[type='number'] {
    -moz-appearance: textfield;
    appearance: textfield;
}
</style>
