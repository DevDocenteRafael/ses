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
                        <label class="form-label small text-secondary mb-1">Tipo de Curso</label>
                        <div class="position-relative" ref="tiposCursoDropdownContainer">
                            <input
                                v-model="buscaTipoCurso"
                                class="form-control form-control-sm"
                                type="search"
                                placeholder="Pesquisar tipo de curso"
                                aria-label="Pesquisar tipo de curso"
                                autocomplete="off"
                                :disabled="carregandoTiposCurso"
                                @focus="mostrarDropdownTiposCurso = true"
                                @input="aoDigitarTipoCurso"
                            >
                            <div v-if="mostrarDropdownTiposCurso" class="list-group position-absolute w-100 shadow mt-1" style="z-index: 1060; max-height: 220px; overflow-y: auto;">
                                <button type="button" class="list-group-item list-group-item-action text-start" @mousedown.prevent="selecionarTipoCurso(null)">
                                    Todos os tipos
                                </button>
                                <button
                                    v-for="tipo in tiposCursoFiltrados"
                                    :key="tipo.id"
                                    type="button"
                                    class="list-group-item list-group-item-action text-start"
                                    @mousedown.prevent="selecionarTipoCurso(tipo)"
                                >
                                    {{ tipo.nome }}
                                </button>
                                <p v-if="!tiposCursoFiltrados.length" class="list-group-item small text-secondary mb-0">Nenhum tipo de curso encontrado.</p>
                            </div>
                        </div>
                        <div v-if="erroTiposCurso" class="form-text text-danger">{{ erroTiposCurso }}</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small text-secondary mb-1">Segmento</label>
                        <div class="position-relative" ref="segmentosDropdownContainer">
                            <input
                                v-model="buscaSegmento"
                                class="form-control form-control-sm"
                                type="search"
                                :placeholder="rotuloOpcaoInicialSegmento"
                                aria-label="Pesquisar segmento"
                                autocomplete="off"
                                :disabled="segmentoDesabilitado"
                                @focus="mostrarDropdownSegmentos = true"
                                @input="aoDigitarSegmento"
                            >
                            <div v-if="mostrarDropdownSegmentos && !segmentoDesabilitado" class="list-group position-absolute w-100 shadow mt-1" style="z-index: 1060; max-height: 220px; overflow-y: auto;">
                                <button type="button" class="list-group-item list-group-item-action text-start" @mousedown.prevent="selecionarSegmento(null)">
                                    Todos os segmentos
                                </button>
                                <button
                                    v-for="segmento in segmentosFiltrados"
                                    :key="segmento.id"
                                    type="button"
                                    class="list-group-item list-group-item-action text-start"
                                    @mousedown.prevent="selecionarSegmento(segmento)"
                                >
                                    {{ segmento.nome }}
                                </button>
                                <p v-if="!segmentosFiltrados.length" class="list-group-item small text-secondary mb-0">Nenhum segmento encontrado.</p>
                            </div>
                        </div>
                        <div v-if="carregandoSegmentos" class="form-text text-secondary">Carregando segmentos...</div>
                        <div v-else-if="erroSegmentos" class="form-text text-danger">{{ erroSegmentos }}</div>
                        <div v-else-if="filtros.tipo_curso && !segmentosAcademicos.length" class="form-text text-secondary">Nenhum segmento disponível para este tipo de curso.</div>
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
                    <div class="d-flex align-items-center flex-wrap gap-2 ms-auto">
                        <small v-if="paginacao.total" class="text-secondary">
                            Página {{ paginacao.current_page }} de {{ paginacao.last_page }} · até {{ paginacao.per_page }} por página
                        </small>
                        <button type="button" class="btn btn-outline-secondary text-nowrap" :disabled="carregando || gerandoPdf || !paginacao.total" @click="baixarListaFiltrada">
                            <span v-if="gerandoPdf" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                            <i v-else class="bi bi-file-earmark-pdf me-1"></i>
                            {{ gerandoPdf ? 'Preparando PDF...' : 'Baixar' }}
                        </button>
                        <button type="button" class="btn btn-outline-primary text-nowrap" :disabled="carregando || baixandoCurriculos || !candidatos.length" @click="abrirModalCurriculos">
                            <i class="bi bi-download me-1"></i>
                            Baixar currículos
                        </button>
                    </div>
                </div>

                <download-curriculos-modal
                    v-model:pagina-inicial-model="formularioCurriculos.paginaInicial"
                    v-model:pagina-final-model="formularioCurriculos.paginaFinal"
                    :show="modalCurriculosAberto"
                    :loading="baixandoCurriculos"
                    :erro="erroCurriculos"
                    :per-page="Number(paginacao.per_page || 10)"
                    :last-page="Number(paginacao.last_page || 1)"
                    :limite-paginas="limitePaginasZip"
                    @fechar="fecharModalCurriculos"
                    @gerar="gerarZipCurriculos"
                />

                <div v-if="carregando" class="text-center text-secondary py-5">
                    <span class="spinner-border spinner-border-sm me-2"></span> Carregando candidatos...
                </div>

                <p v-else-if="!candidatos.length" class="text-secondary text-center py-5">
                    Nenhum candidato encontrado com os filtros selecionados.
                </p>

                <template v-else>
                    <base-pagination
                        class="mb-2"
                        :current-page="paginacao.current_page"
                        :last-page="paginacao.last_page"
                        :per-page="paginacao.per_page"
                        :total="paginacao.total"
                        :from="paginacao.from"
                        :to="paginacao.to"
                        :loading="carregando"
                        item-label="candidatos"
                        aria-label="Paginação de candidatos superior"
                        @change="mudarPagina"
                    />

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
                                             <div class="d-flex align-items-center flex-wrap gap-2 candidato-metadados-compacto">
                                                  <small v-if="regioesCard(c).texto" class="text-secondary candidato-regioes-card">
                                                      <i class="bi bi-geo-alt me-1"></i>{{ regioesCard(c).texto }}
                                                      <span
                                                          v-if="regioesCard(c).quantidadeRestante"
                                                          class="badge bg-light text-secondary border fw-normal ms-1 candidato-regioes-restantes"
                                                          tabindex="0"
                                                          :title="regioesCard(c).restantes.map((regiao) => regiao.nome).join('\n')"
                                                          :aria-label="`${regioesCard(c).quantidadeRestante} outras regiões de interesse: ${regioesCard(c).restantes.map((regiao) => regiao.nome).join(', ')}`"
                                                      >
                                                          +{{ regioesCard(c).quantidadeRestante }}
                                                      </span>
                                                  </small>
                                                  <a
                                                      v-if="telefoneFormatado(c)"
                                                      class="text-secondary text-decoration-none candidato-contato-link"
                                                     :href="`tel:${telefoneNormalizado(c)}`"
                                                     :aria-label="`Telefone: ${telefoneFormatado(c)}`"
                                                 >
                                                     <small><i class="bi bi-telephone me-1"></i>{{ telefoneFormatado(c) }}</small>
                                                 </a>
                                                 <small class="text-secondary"><i class="bi bi-clock me-1"></i>{{ formatarDisponibilidade(c.preferencias_de_trabalho?.disponibilidade_de_horario) }}</small>
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

                    <base-pagination
                        class="mt-2"
                        :current-page="paginacao.current_page"
                        :last-page="paginacao.last_page"
                        :per-page="paginacao.per_page"
                        :total="paginacao.total"
                        :from="paginacao.from"
                        :to="paginacao.to"
                        :loading="carregando"
                        item-label="candidatos"
                        aria-label="Paginação de candidatos inferior"
                        @change="mudarPagina"
                    />
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
import BasePagination from '../../../components/common/BasePagination.vue';
import DownloadCurriculosModal from '../../../components/common/DownloadCurriculosModal.vue';
import { regioesAdministrativasDf } from '../../../utils/regioesAdministrativasDf';
import { formatarRegioesPreferidasCard } from '../../../utils/regioesPreferidasTrabalho';
import { deduplicarHabilidades, habilidadesPadrao } from '../../../utils/habilidadesCatalogo';
import { formatarTelefone, somenteNumeros } from '../../../utils/telefone';
import { formatarDisponibilidadeHorario } from '../../../utils/listasPtBr';
import { useToast } from '../../../composables/useToast';
import {
    LIMITE_PAGINAS_CURRICULOS_ZIP,
    baixarBlobZipCurriculos,
    obterMensagemErroDownloadCurriculos,
    validarIntervaloCurriculos,
} from '../../../utils/downloadCurriculosZip';

const auth = useAuthStore();
const router = useRouter();
const toast = useToast();

const tiposCurso = ref([]);
const segmentosAcademicos = ref([]);
const buscaTipoCurso = ref('');
const buscaSegmento = ref('');
const mostrarDropdownTiposCurso = ref(false);
const mostrarDropdownSegmentos = ref(false);
const tiposCursoDropdownContainer = ref(null);
const segmentosDropdownContainer = ref(null);

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
const gerandoPdf = ref(false);
const baixandoCurriculos = ref(false);
const modalCurriculosAberto = ref(false);
const erroCurriculos = ref('');
const limitePaginasZip = LIMITE_PAGINAS_CURRICULOS_ZIP;
const carregandoHabilidades = ref(false);
const carregandoTiposCurso = ref(false);
const carregandoSegmentos = ref(false);
const erroTiposCurso = ref('');
const erroSegmentos = ref('');
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
    from: null,
    to: null,
});
const formularioCurriculos = reactive({
    paginaInicial: 1,
    paginaFinal: 1,
});

const iniciais = computed(() => iniciaisDe(auth.pessoa?.nome || 'Empresa'));
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
const segmentoDesabilitado = computed(() => !filtros.tipo_curso || carregandoSegmentos.value || Boolean(erroSegmentos.value) || !segmentosAcademicos.value.length);
const rotuloOpcaoInicialSegmento = computed(() => {
    if (!filtros.tipo_curso) {
        return 'Selecione primeiro o Tipo de Curso';
    }

    if (carregandoSegmentos.value) {
        return 'Carregando segmentos...';
    }

    if (erroSegmentos.value) {
        return 'Não foi possível carregar os segmentos';
    }

    if (!segmentosAcademicos.value.length) {
        return 'Nenhum segmento disponível para este tipo de curso';
    }

    return 'Todos os segmentos';
});
const tiposCursoFiltrados = computed(() => {
    const termo = normalizarTexto(buscaTipoCurso.value);
    return tiposCurso.value.filter((tipo) => normalizarTexto(tipo.nome).includes(termo));
});
const segmentosFiltrados = computed(() => {
    const termo = normalizarTexto(buscaSegmento.value);
    return segmentosAcademicos.value.filter((segmento) => normalizarTexto(segmento.nome).includes(termo));
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

watch(() => filtros.tipo_curso, async (novoTipo, tipoAnterior) => {
    paginacao.current_page = 1;

    if (!novoTipo) {
        filtros.segmento = '';
        buscaSegmento.value = '';
        mostrarDropdownSegmentos.value = false;
        segmentosAcademicos.value = [];
        erroSegmentos.value = '';
        return;
    }

    const segmentoAnterior = filtros.segmento;
    await carregarSegmentosAcademicos(novoTipo);

    if (segmentoAnterior && segmentosAcademicos.value.some((segmento) => segmento.id === segmentoAnterior)) {
        buscaSegmento.value = segmentosAcademicos.value.find((segmento) => segmento.id === segmentoAnterior)?.nome || '';
        filtros.segmento = segmentoAnterior;
        return;
    }

    if (tipoAnterior !== undefined) {
        filtros.segmento = '';
        buscaSegmento.value = '';
    }
});

watch(() => filtros.segmento, () => {
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

function formatarDisponibilidade(valor) {
    return formatarDisponibilidadeHorario(valor, '-');
}

function telefoneNormalizado(candidato) {
    return somenteNumeros(candidato?.pessoa?.telefone);
}

function telefoneFormatado(candidato) {
    const telefone = telefoneNormalizado(candidato);
    return telefone ? formatarTelefone(telefone) : '';
}

function regioesCard(candidato) {
    return formatarRegioesPreferidasCard(candidato);
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
    if (tiposCursoDropdownContainer.value && !tiposCursoDropdownContainer.value.contains(evento.target)) {
        mostrarDropdownTiposCurso.value = false;
    }

    if (segmentosDropdownContainer.value && !segmentosDropdownContainer.value.contains(evento.target)) {
        mostrarDropdownSegmentos.value = false;
    }

    if (habilidadesDropdownContainer.value && !habilidadesDropdownContainer.value.contains(evento.target)) {
        fecharDropdownHabilidades();
    }

    if (regioesDropdownContainer.value && !regioesDropdownContainer.value.contains(evento.target)) {
        fecharDropdownRegioes();
    }
}

function aoDigitarTipoCurso() {
    mostrarDropdownTiposCurso.value = true;
    filtros.tipo_curso = '';
}

function selecionarTipoCurso(tipo) {
    filtros.tipo_curso = tipo?.id || '';
    buscaTipoCurso.value = tipo?.nome || '';
    mostrarDropdownTiposCurso.value = false;
}

function aoDigitarSegmento() {
    mostrarDropdownSegmentos.value = true;
    filtros.segmento = '';
}

function selecionarSegmento(segmento) {
    filtros.segmento = segmento?.id || '';
    buscaSegmento.value = segmento?.nome || '';
    mostrarDropdownSegmentos.value = false;
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

async function carregarTiposCurso() {
    carregandoTiposCurso.value = true;
    erroTiposCurso.value = '';

    try {
        const { data } = await empresaService.listarTiposCurso();
        tiposCurso.value = Array.isArray(data) ? data : [];
    } catch (e) {
        erroTiposCurso.value = 'Não foi possível carregar os tipos de curso.';
        tiposCurso.value = [];
    } finally {
        carregandoTiposCurso.value = false;
    }
}

async function carregarSegmentosAcademicos(tipoCurso) {
    carregandoSegmentos.value = true;
    erroSegmentos.value = '';
    segmentosAcademicos.value = [];

    try {
        const { data } = await empresaService.listarSegmentosAcademicos(tipoCurso);
        segmentosAcademicos.value = Array.isArray(data) ? data : [];
    } catch (e) {
        erroSegmentos.value = 'Não foi possível carregar os segmentos.';
        segmentosAcademicos.value = [];
    } finally {
        carregandoSegmentos.value = false;
    }
}

function tipoContratacaoBitmask() {
    return (filtros.clt ? 1 : 0) + (filtros.estagio ? 2 : 0);
}

function parametrosBusca(pagina = 1) {
    const params = { page: pagina, per_page: paginacao.per_page };
    if (filtros.segmento) params.segmento = filtros.segmento;
    if (filtros.tipo_curso) params.tipo_curso = filtros.tipo_curso;
    if (filtros.disponibilidade) params.disponibilidade = filtros.disponibilidade;
    if (filtros.regioes_administrativas.length) params.regioes_administrativas = filtros.regioes_administrativas;
    if (filtros.habilidades.length) params.habilidades = filtros.habilidades;
    const mascara = tipoContratacaoBitmask();
    if (mascara) params.tipo_contratacao = mascara;
    return params;
}

function parametrosCurriculos(paginaInicial, paginaFinal) {
    const { page, per_page, ...filtrosAtuais } = parametrosBusca(paginacao.current_page || 1);

    return {
        ...filtrosAtuais,
        pagina_inicial: paginaInicial,
        pagina_final: paginaFinal,
    };
}

async function buscar(pagina = 1) {
    buscando.value = true;
    carregando.value = true;
    try {
        const { data } = await empresaService.buscarTalentos(parametrosBusca(pagina));
        candidatos.value = data.data || [];
        paginacao.current_page = data.current_page || 1;
        paginacao.last_page = data.last_page || 1;
        paginacao.per_page = Number(data.per_page || 10);
        paginacao.total = data.total || 0;
        paginacao.from = data.from || null;
        paginacao.to = data.to || null;
    } finally {
        buscando.value = false;
        carregando.value = false;
    }
}

async function baixarListaFiltrada() {
    const janela = window.open('', '_blank');
    if (!janela) return;

    gerandoPdf.value = true;
    janela.document.write('<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Preparando lista</title></head><body><p style="font:16px Arial;padding:24px">Preparando a lista filtrada...</p></body></html>');
    janela.document.close();

    try {
        const primeira = (await empresaService.buscarTalentos(parametrosBusca(1))).data;
        const lista = [...(primeira.data || [])];

        for (let inicio = 2; inicio <= primeira.last_page; inicio += 10) {
            const paginas = Array.from({ length: Math.min(10, primeira.last_page - inicio + 1) }, (_, indice) => inicio + indice);
            const respostas = await Promise.all(paginas.map((pagina) => empresaService.buscarTalentos(parametrosBusca(pagina))));
            respostas.forEach(({ data }) => lista.push(...(data.data || [])));
        }

        const linhas = lista.map((candidato) => {
            const academico = cursoPrincipal(candidato);
            return `<tr><td>${escaparHtml(candidato.pessoa?.nome || '—')}</td><td>${escaparHtml(telefoneFormatado(candidato) || '—')}</td><td>${escaparHtml(academico?.curso || '—')}</td><td>${escaparHtml(academico?.unidade || '—')}</td></tr>`;
        }).join('');
        const titulo = `Candidatos encontrados - ${new Date().toLocaleDateString('pt-BR')}`;
        janela.document.open();
        janela.document.write(`<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>${escaparHtml(titulo)}</title><style>@page{size:landscape;margin:14mm}body{color:#212529;font:12px Arial,sans-serif}h1{color:#163f70;font-size:20px}p{color:#5c6670}table{border-collapse:collapse;width:100%}th,td{border:1px solid #cbd2d9;padding:8px;text-align:left}th{background:#edf2f7;color:#163f70}tr{break-inside:avoid}</style></head><body><h1>Candidatos encontrados</h1><p>${lista.length} candidato(s) · Gerado em ${new Date().toLocaleString('pt-BR')}</p><table><thead><tr><th>Candidato</th><th>Telefone</th><th>Curso</th><th>Unidade</th></tr></thead><tbody>${linhas || '<tr><td colspan="4">Nenhum candidato encontrado.</td></tr>'}</tbody></table><script>window.addEventListener('load',()=>setTimeout(()=>window.print(),250));<\/script></body></html>`);
        janela.document.close();
    } catch (error) {
        janela.close();
        toast.error('Não foi possível preparar a lista filtrada. Tente novamente.');
    } finally {
        gerandoPdf.value = false;
    }
}

function abrirModalCurriculos() {
    const paginaAtual = paginacao.current_page || 1;
    formularioCurriculos.paginaInicial = paginaAtual;
    formularioCurriculos.paginaFinal = paginaAtual;
    erroCurriculos.value = '';
    modalCurriculosAberto.value = true;
}

function fecharModalCurriculos() {
    if (baixandoCurriculos.value) return;
    modalCurriculosAberto.value = false;
    erroCurriculos.value = '';
}

async function gerarZipCurriculos({ paginaInicial, paginaFinal } = {}) {
    const inicio = Number(paginaInicial ?? formularioCurriculos.paginaInicial);
    const fim = Number(paginaFinal ?? formularioCurriculos.paginaFinal);
    await executarDownloadZipCurriculos(inicio, fim, { fecharModalAoConcluir: true });
}

async function executarDownloadZipCurriculos(inicio, fim, { fecharModalAoConcluir = false } = {}) {
    erroCurriculos.value = validarIntervaloCurriculos(inicio, fim, paginacao.last_page, limitePaginasZip);

    if (erroCurriculos.value) {
        toast.error(erroCurriculos.value);
        return;
    }

    baixandoCurriculos.value = true;
    try {
        const response = await empresaService.baixarCurriculosCandidatos(parametrosCurriculos(inicio, fim));
        baixarBlobZipCurriculos(response, inicio, fim);

        if (fecharModalAoConcluir) {
            modalCurriculosAberto.value = false;
        }
    } catch (error) {
        erroCurriculos.value = obterMensagemErroDownloadCurriculos(error);
        toast.error(erroCurriculos.value);
    } finally {
        baixandoCurriculos.value = false;
    }
}

function escaparHtml(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, (caractere) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    })[caractere]);
}

function aplicarFiltros() {
    paginacao.current_page = 1;
    buscar(1);
}

function mudarPagina(pagina) {
    if (pagina < 1 || pagina > paginacao.last_page || pagina === paginacao.current_page || carregando.value) {
        return;
    }

    buscar(pagina);
}

function limparFiltros() {
    filtros.tipo_curso = '';
    filtros.segmento = '';
    buscaTipoCurso.value = '';
    buscaSegmento.value = '';
    mostrarDropdownTiposCurso.value = false;
    mostrarDropdownSegmentos.value = false;
    segmentosAcademicos.value = [];
    erroSegmentos.value = '';
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
    carregarTiposCurso();
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

.candidato-contato-link:hover,
.candidato-contato-link:focus {
    color: var(--bs-primary) !important;
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
