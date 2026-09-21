<template>
    <div class="buscar-talentos-page">
        <header class="buscar-talentos-header bg-primary text-white px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold fs-5">Senac</span>
                <span class="vr d-none d-sm-block opacity-50 mx-1"></span>
                <h1 class="h5 fw-bold mb-0">Portal da Empresa</h1>
            </div>

            <div class="d-flex align-items-center gap-2">
                <div class="text-end d-none d-sm-block">
                    <p class="fw-semibold mb-0">{{ auth.pessoa?.nome || 'Empresa' }}</p>
                    <p class="small mb-0 opacity-75">{{ auth.pessoa?.email }}</p>
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

        <div class="buscar-talentos-content d-flex">
            <aside class="buscar-talentos-filtros bg-white border-end p-4 d-none d-lg-block">
                <h2 class="h6 fw-bold mb-4">Filtros Inteligentes</h2>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-secondary text-uppercase">Filtros Principais</label>
                    <div class="mb-2">
                        <label class="form-label small text-secondary mb-1">Segmento</label>
                        <select v-model="filtros.segmento" class="form-select form-select-sm">
                            <option value="">Todos os Segmentos</option>
                            <option v-for="segmento in segmentos" :key="segmento" :value="segmento">{{ segmento }}</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small text-secondary mb-1">Tipo de Curso</label>
                        <select v-model="filtros.tipo_curso" class="form-select form-select-sm">
                            <option value="">Todos os Tipos</option>
                            <option v-for="t in tiposCurso" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-secondary text-uppercase">Contratação</label>
                    <div class="form-check small mb-1">
                        <input v-model="filtros.clt" class="form-check-input" type="checkbox" id="fCLT">
                        <label class="form-check-label" for="fCLT">CLT / Efetivo</label>
                    </div>
                    <div class="form-check small mb-1">
                        <input v-model="filtros.estagio" class="form-check-input" type="checkbox" id="fEstagio">
                        <label class="form-check-label" for="fEstagio">Estágio</label>
                    </div>
                </div>

                <div class="mb-4 position-relative" ref="regioesDropdownContainer">
                    <label class="form-label small fw-bold text-secondary text-uppercase">Região Administrativa</label>
                    <button
                        type="button"
                        class="form-select form-select-sm filtro-multiselect text-start d-flex align-items-center"
                        :aria-expanded="mostrarDropdownRegioes"
                        @click.stop="alternarDropdownRegioes"
                    >
                        <span class="text-truncate" :class="filtros.regioes_administrativas.length ? 'text-body' : 'text-secondary'">
                            {{ rotuloFiltroRegioes }}
                        </span>
                        <i class="bi bi-chevron-down filtro-multiselect-seta"></i>
                    </button>

                    <div v-if="mostrarDropdownRegioes" class="filtro-multiselect-dropdown border rounded shadow-sm bg-white mt-1">
                        <div class="p-2 border-bottom">
                            <label class="form-label small text-secondary mb-1" for="busca-regiao-filtro">
                                <i class="bi bi-search me-1"></i> Pesquisar região...
                            </label>
                            <input
                                id="busca-regiao-filtro"
                                ref="regiaoBuscaInput"
                                v-model="buscaRegiao"
                                type="text"
                                class="form-control form-control-sm"
                                placeholder="Digite parte do nome..."
                                autocomplete="off"
                            >
                        </div>

                        <div class="filtro-multiselect-lista p-2">
                            <template v-if="regioesFiltradas.length">
                                <label v-for="regiao in regioesFiltradas" :key="regiao.codigo" class="filtro-multiselect-opcao form-check rounded px-2 py-1 mb-1">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" :checked="regiaoSelecionada(regiao.codigo)" @change="alternarRegiao(regiao.codigo)">
                                    <span class="form-check-label text-truncate">{{ regiao.label }}</span>
                                </label>
                            </template>
                            <p v-else class="small text-secondary mb-0 py-2 text-center">Nenhuma região encontrada.</p>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-secondary text-uppercase">Disponibilidade</label>
                    <select v-model="filtros.disponibilidade" class="form-select form-select-sm">
                        <option value="">Qualquer Horário</option>
                        <option value="Manhã">Manhã</option>
                        <option value="Tarde">Tarde</option>
                        <option value="Noite">Noite</option>
                    </select>
                </div>

                <div class="mb-4 position-relative" ref="habilidadesDropdownContainer">
                    <label class="form-label small fw-bold text-secondary text-uppercase">Habilidades Técnicas</label>
                    <button
                        type="button"
                        class="form-select form-select-sm habilidades-filtro-select text-start d-flex align-items-center"
                        :aria-expanded="mostrarDropdownHabilidades"
                        @click.stop="alternarDropdownHabilidades"
                    >
                        <span class="text-truncate" :class="filtros.habilidades.length ? 'text-body' : 'text-secondary'">
                            {{ rotuloFiltroHabilidades }}
                        </span>
                        <i class="bi bi-chevron-down habilidades-filtro-seta"></i>
                    </button>

                    <div v-if="mostrarDropdownHabilidades" class="habilidades-filtro-dropdown border rounded shadow-sm bg-white mt-1">
                        <div class="p-2 border-bottom">
                            <label class="form-label small text-secondary mb-1" for="busca-habilidade-filtro">
                                <i class="bi bi-search me-1"></i> Buscar habilidade...
                            </label>
                            <input
                                id="busca-habilidade-filtro"
                                ref="habilidadeBuscaInput"
                                v-model="buscaHabilidade"
                                type="text"
                                class="form-control form-control-sm"
                                placeholder="Digite parte do nome..."
                                autocomplete="off"
                            >
                        </div>

                        <div class="habilidades-filtro-lista p-2">
                            <div v-if="carregandoHabilidades" class="small text-secondary py-2 text-center">
                                <span class="spinner-border spinner-border-sm me-1"></span> Carregando...
                            </div>
                            <template v-else-if="habilidadesFiltradas.length">
                                <label v-for="habilidade in habilidadesFiltradas" :key="habilidade" class="habilidade-filtro-opcao form-check rounded px-2 py-1 mb-1">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" :checked="habilidadeSelecionada(habilidade)" @change="alternarHabilidade(habilidade)">
                                    <span class="form-check-label text-truncate">{{ habilidade }}</span>
                                </label>
                            </template>
                            <p v-else class="small text-secondary mb-0 py-2 text-center">Nenhuma habilidade encontrada.</p>
                        </div>

                        <div v-if="filtros.habilidades.length" class="border-top p-2">
                            <p class="small text-secondary fw-bold text-uppercase mb-2">Selecionadas</p>
                            <div class="d-flex flex-wrap gap-1">
                                <span v-for="h in filtros.habilidades" :key="h" class="badge bg-primary-subtle text-primary border habilidade-filtro-badge">
                                    {{ h }}
                                    <button type="button" class="btn btn-sm btn-link p-0 ms-1 text-primary" aria-label="Remover habilidade" @click.stop="removerHabilidade(h)">×</button>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn btn-primary w-100" :disabled="buscando" @click="aplicarFiltros">
                    <span v-if="buscando" class="spinner-border spinner-border-sm me-1"></span>
                    Aplicar Filtros
                </button>
                <button type="button" class="btn btn-link btn-sm w-100 mt-2 text-secondary" @click="limparFiltros">
                    Limpar Tudo
                </button>
            </aside>

            <main class="buscar-talentos-resultados flex-grow-1 p-3 p-md-4">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h2 class="h5 mb-0">
                        Resultados da Busca
                        <span class="badge bg-secondary ms-2">{{ paginacao.total }} candidato{{ paginacao.total === 1 ? '' : 's' }}</span>
                    </h2>
                    <small v-if="paginacao.total" class="text-secondary">
                        Página {{ paginacao.current_page }} de {{ paginacao.last_page }} · até {{ paginacao.per_page }} por página
                    </small>
                </div>

                <div v-if="carregando" class="text-center text-secondary py-5">
                    <span class="spinner-border spinner-border-sm me-2"></span> Carregando candidatos...
                </div>

                <p v-else-if="!candidatos.length" class="text-secondary text-center py-5">
                    Nenhum candidato encontrado com os filtros selecionados.
                </p>

                <template v-else>
                    <nav v-if="paginacao.last_page > 1" class="d-flex justify-content-center mb-2" aria-label="Paginação de candidatos superior">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item" :class="{ disabled: paginacao.current_page <= 1 || carregando }">
                                <button class="page-link py-1" type="button" @click="mudarPagina(paginacao.current_page - 1)">Anterior</button>
                            </li>
                            <li v-for="pagina in paginasVisiveis" :key="`topo-${pagina}`" class="page-item" :class="{ active: pagina === paginacao.current_page, disabled: carregando }">
                                <button class="page-link py-1" type="button" @click="mudarPagina(pagina)">{{ pagina }}</button>
                            </li>
                            <li class="page-item" :class="{ disabled: paginacao.current_page >= paginacao.last_page || carregando }">
                                <button class="page-link py-1" type="button" @click="mudarPagina(paginacao.current_page + 1)">Próxima</button>
                            </li>
                        </ul>
                    </nav>

                    <div class="row gy-2 gx-0">
                        <div v-for="c in candidatos" :key="c.matricula" class="col-12">
                            <div class="card border-0 shadow-sm candidato-card-compacto">
                                <div class="card-body py-2 px-3">
                                    <div class="row align-items-center gx-2 gy-1">
                                        <div class="col-auto">
                                            <span class="rounded-3 bg-primary text-white d-flex align-items-center justify-content-center fw-semibold candidato-avatar-compacto">
                                                {{ iniciaisDe(c.pessoa?.nome) }}
                                            </span>
                                        </div>
                                        <div class="col min-w-0">
                                            <h3 class="h6 mb-0 text-truncate">{{ c.pessoa?.nome }}</h3>
                                            <p class="mb-0 text-secondary small text-truncate">
                                                <i class="bi bi-mortarboard me-1"></i>
                                                {{ cursoPrincipal(c)?.curso }} | {{ cursoPrincipal(c)?.unidade }}
                                            </p>
                                            <div class="d-flex flex-wrap gap-1 my-1">
                                                <span v-for="h in habilidadesVisiveis(c)" :key="h" class="badge bg-light text-primary border fw-normal candidato-badge-compacto">
                                                    {{ h }}
                                                </span>
                                                <span v-if="habilidadesExtras(c)" class="badge bg-light text-secondary border fw-normal candidato-badge-compacto">
                                                    +{{ habilidadesExtras(c) }}
                                                </span>
                                            </div>
                                            <div class="d-flex align-items-center flex-wrap gap-2">
                                                <small class="text-secondary"><i class="bi bi-geo-alt me-1"></i>{{ c.preferencias_de_trabalho?.regiao_administrativa }} - DF</small>
                                                <small class="text-secondary"><i class="bi bi-clock me-1"></i>{{ c.preferencias_de_trabalho?.disponibilidade_de_horario || '-' }}</small>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-auto mt-1 mt-md-0">
                                            <router-link :to="{ name: 'empresa.candidato', params: { matricula: c.matricula } }" class="btn btn-sm btn-primary d-block px-2 py-1">
                                                Ver Perfil
                                            </router-link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <nav v-if="paginacao.last_page > 1" class="d-flex justify-content-center mt-2" aria-label="Paginação de candidatos inferior">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item" :class="{ disabled: paginacao.current_page <= 1 || carregando }">
                                <button class="page-link py-1" type="button" @click="mudarPagina(paginacao.current_page - 1)">Anterior</button>
                            </li>
                            <li v-for="pagina in paginasVisiveis" :key="`rodape-${pagina}`" class="page-item" :class="{ active: pagina === paginacao.current_page, disabled: carregando }">
                                <button class="page-link py-1" type="button" @click="mudarPagina(pagina)">{{ pagina }}</button>
                            </li>
                            <li class="page-item" :class="{ disabled: paginacao.current_page >= paginacao.last_page || carregando }">
                                <button class="page-link py-1" type="button" @click="mudarPagina(paginacao.current_page + 1)">Próxima</button>
                            </li>
                        </ul>
                    </nav>
                </template>
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, reactive, ref, onMounted, onBeforeUnmount, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../../store/auth';
import empresaService from '../../../services/empresaServices';
import { regioesAdministrativasDf } from '../../../utils/regioesAdministrativasDf';
import { areasAtuacao, deduplicarHabilidades, habilidadesPadrao } from '../../../utils/habilidadesCatalogo';

const auth = useAuthStore();
const router = useRouter();

const segmentos = areasAtuacao;

const tiposCurso = [
    { value: 'livres', label: 'Cursos Livres' },
    { value: 'extensao', label: 'Certificação em TI' },
    { value: 'tecnico', label: 'Técnico' },
    { value: 'graduacao', label: 'Graduação' },
    { value: 'pos-graduacao', label: 'Pós-graduação' },
];

const filtros = reactive({
    segmento: '',
    tipo_curso: '',
    clt: false,
    estagio: false,
    regioes_administrativas: [],
    disponibilidade: '',
    habilidades: [],
});

const carregando = ref(true);
const buscando = ref(false);
const carregandoHabilidades = ref(false);
const candidatos = ref([]);
const habilidadesDisponiveis = ref([]);
const buscaHabilidade = ref('');
const buscaRegiao = ref('');
const mostrarDropdownHabilidades = ref(false);
const mostrarDropdownRegioes = ref(false);
const habilidadeBuscaInput = ref(null);
const regiaoBuscaInput = ref(null);
const habilidadesDropdownContainer = ref(null);
const regioesDropdownContainer = ref(null);
const paginacao = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
});

const iniciais = computed(() => iniciaisDe(auth.pessoa?.nome || 'Empresa'));
const paginasVisiveis = computed(() => {
    const total = paginacao.last_page || 1;
    const atual = paginacao.current_page || 1;
    const inicio = Math.max(1, atual - 2);
    const fim = Math.min(total, inicio + 4);
    const primeiro = Math.max(1, fim - 4);

    return Array.from({ length: fim - primeiro + 1 }, (_, i) => primeiro + i);
});
const rotuloFiltroHabilidades = computed(() => {
    const total = filtros.habilidades.length;

    if (!total) {
        return 'Selecione habilidades...';
    }

    return total === 1 ? '1 habilidade selecionada' : `${total} habilidades selecionadas`;
});
const rotuloFiltroRegioes = computed(() => {
    const total = filtros.regioes_administrativas.length;

    if (!total) {
        return 'Selecione regiões...';
    }

    if (total === 1) {
        return regioesAdministrativasDf.find((regiao) => regiao.codigo === filtros.regioes_administrativas[0])?.label || '1 região selecionada';
    }

    return `${total} regiões selecionadas`;
});
const habilidadesFiltradas = computed(() => {
    const termo = normalizarTexto(buscaHabilidade.value);

    if (!termo) {
        return habilidadesDisponiveis.value;
    }

    return habilidadesDisponiveis.value.filter((habilidade) => normalizarTexto(habilidade).includes(termo));
});
const regioesFiltradas = computed(() => {
    const termo = normalizarTexto(buscaRegiao.value);
    const termoSemEspacos = termo.replace(/\s+/g, '');

    if (!termo) {
        return regioesAdministrativasDf;
    }

    return regioesAdministrativasDf.filter((regiao) => {
        const texto = normalizarTexto(`${regiao.label} ${regiao.nome}`);
        return texto.includes(termo) || texto.replace(/\s+/g, '').includes(termoSemEspacos);
    });
});

watch(() => [...filtros.habilidades], () => {
    paginacao.current_page = 1;
});

watch(() => [...filtros.regioes_administrativas], () => {
    paginacao.current_page = 1;
});

function iniciaisDe(nome) {
    return (nome || '')
        .split(' ')
        .slice(0, 2)
        .map((p) => p[0])
        .join('')
        .toUpperCase();
}

function cursoPrincipal(candidato) {
    const lista = candidato.dados_academicos || [];
    return lista[0] || null;
}

function habilidadesVisiveis(candidato) {
    return habilidadesPlanas(candidato).slice(0, 3);
}

function habilidadesExtras(candidato) {
    return Math.max(habilidadesPlanas(candidato).length - 3, 0);
}

function habilidadesPlanas(candidato) {
    const info = candidato.informacoes_profissionais || {};
    const porArea = info.habilidades_por_area;
    const areaAtual = info.area_de_atuacao;

    if (porArea && typeof porArea === 'object' && !Array.isArray(porArea)) {
        const entradaAreaAtual = Object.entries(porArea)
            .find(([area]) => normalizarTexto(area) === normalizarTexto(areaAtual));

        return Array.isArray(entradaAreaAtual?.[1]) ? entradaAreaAtual[1].filter(Boolean) : [];
    }

    return info.habilidades || [];
}

function normalizarTexto(valor) {
    return String(valor ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

function regiaoSelecionada(codigo) {
    return filtros.regioes_administrativas.includes(Number(codigo));
}

function alternarRegiao(codigo) {
    const codigoNumerico = Number(codigo);
    const indice = filtros.regioes_administrativas.indexOf(codigoNumerico);

    if (indice >= 0) {
        filtros.regioes_administrativas.splice(indice, 1);
        return;
    }

    filtros.regioes_administrativas.push(codigoNumerico);
    filtros.regioes_administrativas.sort((a, b) => a - b);
}

function habilidadeSelecionada(habilidade) {
    const habilidadeNormalizada = normalizarTexto(habilidade);
    return filtros.habilidades.some((item) => normalizarTexto(item) === habilidadeNormalizada);
}

function alternarHabilidade(habilidade) {
    const habilidadeNormalizada = normalizarTexto(habilidade);
    const indice = filtros.habilidades.findIndex((item) => normalizarTexto(item) === habilidadeNormalizada);

    if (indice >= 0) {
        filtros.habilidades.splice(indice, 1);
        return;
    }

    filtros.habilidades.push(habilidade);
}

function removerHabilidade(habilidade) {
    const habilidadeNormalizada = normalizarTexto(habilidade);
    const indice = filtros.habilidades.findIndex((item) => normalizarTexto(item) === habilidadeNormalizada);

    if (indice >= 0) {
        filtros.habilidades.splice(indice, 1);
    }
}

function alternarDropdownHabilidades() {
    mostrarDropdownHabilidades.value = !mostrarDropdownHabilidades.value;

    if (mostrarDropdownHabilidades.value) {
        nextTick(() => habilidadeBuscaInput.value?.focus());
    }
}

function fecharDropdownHabilidades() {
    mostrarDropdownHabilidades.value = false;
    buscaHabilidade.value = '';
}

function alternarDropdownRegioes() {
    mostrarDropdownRegioes.value = !mostrarDropdownRegioes.value;

    if (mostrarDropdownRegioes.value) {
        nextTick(() => regiaoBuscaInput.value?.focus());
    }
}

function fecharDropdownRegioes() {
    mostrarDropdownRegioes.value = false;
    buscaRegiao.value = '';
}

function aoClicarForaDosDropdowns(evento) {
    if (habilidadesDropdownContainer.value && !habilidadesDropdownContainer.value.contains(evento.target)) {
        fecharDropdownHabilidades();
    }

    if (regioesDropdownContainer.value && !regioesDropdownContainer.value.contains(evento.target)) {
        fecharDropdownRegioes();
    }
}

async function carregarHabilidadesDisponiveis() {
    carregandoHabilidades.value = true;
    try {
        const { data } = await empresaService.listarHabilidadesCandidatos();
        habilidadesDisponiveis.value = deduplicarHabilidades([
            ...habilidadesPadrao,
            ...(Array.isArray(data) ? data : []),
        ]).sort((a, b) => a.localeCompare(b, 'pt-BR', { sensitivity: 'base' }));
    } finally {
        carregandoHabilidades.value = false;
    }
}

function tipoContratacaoBitmask() {
    return (filtros.clt ? 1 : 0) + (filtros.estagio ? 2 : 0);
}

async function buscar(pagina = 1) {
    buscando.value = true;
    carregando.value = true;
    try {
        const params = {
            page: pagina,
            per_page: paginacao.per_page,
        };
        if (filtros.segmento) params.segmento = filtros.segmento;
        if (filtros.tipo_curso) params.tipo_curso = filtros.tipo_curso;
        if (filtros.disponibilidade) params.disponibilidade = filtros.disponibilidade;
        if (filtros.regioes_administrativas.length) params.regioes_administrativas = filtros.regioes_administrativas;
        if (filtros.habilidades.length) params.habilidades = filtros.habilidades;
        const mascara = tipoContratacaoBitmask();
        if (mascara) params.tipo_contratacao = mascara;

        const { data } = await empresaService.buscarTalentos(params);
        candidatos.value = data.data || [];
        paginacao.current_page = data.current_page || 1;
        paginacao.last_page = data.last_page || 1;
        paginacao.per_page = Number(data.per_page || 10);
        paginacao.total = data.total || 0;
    } finally {
        buscando.value = false;
        carregando.value = false;
    }
}

function aplicarFiltros() {
    buscar(1);
}

function mudarPagina(pagina) {
    if (pagina < 1 || pagina > paginacao.last_page || pagina === paginacao.current_page || carregando.value) {
        return;
    }

    buscar(pagina);
}

function limparFiltros() {
    filtros.segmento = '';
    filtros.tipo_curso = '';
    filtros.clt = false;
    filtros.estagio = false;
    filtros.regioes_administrativas = [];
    filtros.disponibilidade = '';
    filtros.habilidades = [];
    buscaHabilidade.value = '';
    buscaRegiao.value = '';
    fecharDropdownHabilidades();
    fecharDropdownRegioes();
    buscar(1);
}

async function sair() {
    await auth.logout();
    router.push({ name: 'login' });
}

onMounted(() => {
    buscar();
    carregarHabilidadesDisponiveis();
    document.addEventListener('click', aoClicarForaDosDropdowns);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', aoClicarForaDosDropdowns);
});
</script>

<style scoped>
.buscar-talentos-page {
    height: 100vh;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.buscar-talentos-header {
    flex-shrink: 0;
}

.buscar-talentos-content {
    flex: 1 1 auto;
    min-height: 0;
    overflow: visible;
}

.buscar-talentos-filtros {
    width: 300px;
    flex: 0 0 300px;
    min-height: 0;
    position: relative;
    z-index: 20;
    overflow: visible;
}

.buscar-talentos-resultados {
    min-width: 0;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
}

.candidato-card-compacto {
    line-height: 1.2;
}

.candidato-avatar-compacto {
    width: 44px;
    height: 44px;
    font-size: 0.95rem;
}

.candidato-badge-compacto {
    padding-top: 0.2rem;
    padding-bottom: 0.2rem;
    line-height: 1;
}

.min-w-0 {
    min-width: 0;
}

.habilidades-filtro-select,
.filtro-multiselect {
    background-image: none;
    min-width: 0;
    padding-right: 2.25rem;
    position: relative;
}

.habilidades-filtro-seta,
.filtro-multiselect-seta {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.875rem;
    line-height: 1;
    pointer-events: none;
}

.habilidades-filtro-dropdown,
.filtro-multiselect-dropdown {
    position: absolute;
    left: 0;
    top: 100%;
    z-index: 1050;
    width: 100%;
    max-width: 100%;
}

.habilidades-filtro-dropdown {
    top: auto;
    bottom: calc(100% + 0.25rem);
    margin-top: 0 !important;
}

.habilidades-filtro-lista,
.filtro-multiselect-lista {
    max-height: 230px;
    overflow-y: auto;
}

.habilidade-filtro-opcao,
.filtro-multiselect-opcao {
    cursor: pointer;
    display: flex;
    align-items: center;
    transition: background-color 0.15s ease;
}

.habilidade-filtro-opcao:hover,
.filtro-multiselect-opcao:hover {
    background-color: var(--bs-primary-bg-subtle);
}

.habilidade-filtro-opcao .form-check-input,
.filtro-multiselect-opcao .form-check-input {
    float: none;
    flex-shrink: 0;
}

.habilidade-filtro-badge {
    max-width: 100%;
    white-space: normal;
    overflow-wrap: anywhere;
}
</style>
