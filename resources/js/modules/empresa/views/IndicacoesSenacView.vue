<template>
    <div class="indicacoes-senac-page">
        <!-- Cabeçalho fixo: sticky no fluxo normal (sem espaço reservado nem rolagem dupla). -->
        <header ref="cabecalho" class="indicacoes-header bg-primary text-white px-3 px-sm-4 py-2 py-sm-3 d-flex align-items-center justify-content-between flex-wrap gap-2" data-testid="cabecalho-empresa">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <img :src="logoSenacSrc" alt="Logo Senac" class="senac-header-logo flex-shrink-0">
                <span class="vr d-none d-sm-block opacity-50 mx-1"></span>
                <h1 class="h5 fw-bold mb-0 text-nowrap">Portal da Empresa</h1>
            </div>

            <div class="d-flex align-items-center gap-2">
                <router-link :to="{ name: 'empresa.buscar-talentos' }" class="btn btn-sm btn-outline-light indicacoes-header-btn" aria-label="Buscar Talentos">
                    <i class="bi bi-search" aria-hidden="true"></i><span class="d-none d-md-inline ms-1">Buscar Talentos</span>
                </router-link>
                <AccessibilityMenu />
                <span class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center fw-semibold flex-shrink-0" style="width: 38px; height: 38px;" :title="auth.pessoa?.nome">
                    {{ iniciais }}
                </span>
                <button type="button" class="btn btn-sm btn-outline-light indicacoes-header-btn" aria-label="Sair" @click="sair">
                    <i class="bi bi-box-arrow-left" aria-hidden="true"></i><span class="d-none d-md-inline ms-1">Sair</span>
                </button>
            </div>
        </header>

        <main class="container-fluid p-3 p-sm-4">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h4 fw-bold mb-1 indicacoes-titulo">
                        <i class="bi bi-person-check me-2" aria-hidden="true"></i>Indicações Senac
                    </h2>
                    <p class="mb-0 indicacoes-texto-suave">Candidatos indicados pelo Senac especialmente para a sua empresa.</p>
                </div>
                <span v-if="novas" class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fs-6" data-testid="contador-novas">
                    {{ novas }} {{ novas === 1 ? 'nova indicação' : 'novas indicações' }}
                </span>
            </div>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="d-flex flex-wrap gap-2" role="group" aria-label="Filtrar indicações por status">
                    <button v-for="opcao in FILTROS" :key="opcao.valor" type="button" class="btn btn-sm indicacoes-filtro" :class="filtroStatus === opcao.valor ? 'btn-primary' : 'btn-outline-primary indicacoes-btn-contorno'" :aria-pressed="filtroStatus === opcao.valor" @click="filtrar(opcao.valor)">
                        {{ opcao.rotulo }}
                    </button>
                </div>

                <div class="btn-group indicacoes-modos" role="group" aria-label="Modo de visualização" data-testid="alternar-visualizacao">
                    <button
                        v-for="opcao in MODOS"
                        :key="opcao.valor"
                        type="button"
                        class="btn btn-sm"
                        :class="modo === opcao.valor ? 'btn-primary' : 'btn-outline-primary indicacoes-btn-contorno'"
                        :aria-pressed="modo === opcao.valor"
                        :title="`Exibir em ${opcao.rotulo.toLowerCase()}`"
                        @click="escolherModo(opcao.valor)"
                    >
                        <i class="bi me-1" :class="opcao.icone" aria-hidden="true"></i>{{ opcao.rotulo }}
                    </button>
                </div>
            </div>

            <p ref="resumoLista" class="small mb-3 indicacoes-texto-suave indicacoes-resumo" role="status" aria-live="polite" data-testid="resumo-paginacao">
                <template v-if="paginacao.total">
                    <strong>{{ paginacao.total }}</strong> {{ paginacao.total === 1 ? 'indicação' : 'indicações' }}{{ filtroAplicado ? ` com status “${rotuloFiltro}”` : '' }}
                    · Página <strong>{{ paginacao.current_page }}</strong> de <strong>{{ paginacao.last_page }}</strong>
                </template>
            </p>

            <loading v-if="carregando && !indicacoes.length" mensagem="Carregando indicações..." />
            <div v-else-if="erro" class="alert alert-danger d-flex justify-content-between align-items-center flex-wrap gap-2" role="alert">
                <span>{{ erro }}</span>
                <button type="button" class="btn btn-sm btn-outline-danger" @click="carregar(paginacao.current_page)">Tentar novamente</button>
            </div>
            <div v-else-if="!indicacoes.length" class="card border-0 shadow-sm">
                <div class="card-body text-center py-5 indicacoes-texto-suave">
                    <i class="bi bi-person-check fs-1 d-block mb-2" aria-hidden="true"></i>
                    <p class="mb-0">{{ filtroAplicado ? 'Nenhuma indicação com este status.' : 'Sua empresa ainda não recebeu indicações do Senac.' }}</p>
                </div>
            </div>
            <template v-else>
                <!-- Um só contêiner nos dois modos: os cards continuam montados e só o layout muda. -->
                <div
                    ref="listagem"
                    class="row row-cols-1 g-3"
                    :class="{ 'row-cols-md-2 row-cols-xl-3': modoExibido === 'grade', 'opacity-50': carregando }"
                    :data-testid="modoExibido === 'grade' ? 'indicacoes-grade' : 'indicacoes-lista'"
                >
                    <div v-for="i in indicacoes" :key="i.id" class="col">
                        <indicacao-senac-item :indicacao="i" :modo="modoExibido" :salvando="salvandoId === i.id" :opcoes-status="STATUS_EMPRESA" @alterar-status="alterarStatus" />
                    </div>
                </div>

                <base-pagination
                    class="mt-3 indicacoes-paginacao"
                    show-single-page
                    :current-page="paginacao.current_page"
                    :last-page="paginacao.last_page"
                    :per-page="paginacao.per_page"
                    :total="paginacao.total"
                    :from="paginacao.from"
                    :to="paginacao.to"
                    :loading="carregando"
                    item-label="indicações"
                    aria-label="Paginação das indicações"
                    @change="irParaPagina"
                />
            </template>
        </main>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../../store/auth';
import empresaService from '../../../services/empresaServices';
import loading from '../../../components/common/loading.vue';
import BasePagination from '../../../components/common/BasePagination.vue';
import AccessibilityMenu from '../../../components/common/AccessibilityMenu.vue';
import IndicacaoSenacItem from '../components/IndicacaoSenacItem.vue';
import { useToast } from '../../../composables/useToast';

const FILTROS = [
    { valor: '', rotulo: 'Todas' },
    { valor: 'NOVA', rotulo: 'Nova indicação' },
    { valor: 'EM_ANALISE', rotulo: 'Em análise' },
    { valor: 'TENHO_INTERESSE', rotulo: 'Tenho interesse' },
    { valor: 'SEM_INTERESSE', rotulo: 'Sem interesse' },
];
const STATUS_EMPRESA = FILTROS.filter((f) => ['EM_ANALISE', 'TENHO_INTERESSE', 'SEM_INTERESSE'].includes(f.valor));
const MODOS = [
    { valor: 'lista', rotulo: 'Lista', icone: 'bi-list-ul' },
    { valor: 'grade', rotulo: 'Grade', icone: 'bi-grid-3x3-gap' },
];

// Preferência de visualização só no navegador: guarda apenas "lista" ou "grade", nenhum dado de candidato.
const CHAVE_MODO = 'ses:empresa:indicacoes:visualizacao';

function lerModoSalvo() {
    try {
        return window.localStorage.getItem(CHAVE_MODO) === 'grade' ? 'grade' : 'lista';
    } catch {
        return 'lista';
    }
}

const logoSenacSrc = '/img/senac-logo.png';
const router = useRouter();
const auth = useAuthStore();
const toast = useToast();

const indicacoes = ref([]);
const paginacao = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0, from: null, to: null });
const novas = ref(0);
const carregando = ref(false);
const erro = ref('');
const filtroStatus = ref('');
// Filtro da resposta exibida: o resumo não mistura o rótulo novo com o total ainda antigo.
const filtroAplicado = ref('');
const salvandoId = ref(null);
// "modo" é a última escolha (botões e preferência); "modoExibido" é o layout na tela,
// que só muda no meio da animação de troca.
const modo = ref(lerModoSalvo());
const modoExibido = ref(modo.value);
const cabecalho = ref(null);
const resumoLista = ref(null);
const listagem = ref(null);

const iniciais = computed(() => iniciaisDe(auth.pessoa?.nome || 'Empresa'));
const rotuloFiltro = computed(() => FILTROS.find((f) => f.valor === filtroAplicado.value)?.rotulo || '');

// Animação da troca Lista/Grade: saída curta (fade + leve redução), troca do layout e entrada
// dos cards (fade + subida discreta + leve escala, com pequeno escalonamento) enquanto a altura
// do contêiner acompanha. Total ≈ 280 ms.
const SAIDA_MS = 110;
const ENTRADA_MS = 170;
const ESCALONAMENTO_MS = 10;
const CURVA_SAIDA = 'cubic-bezier(0.4, 0, 1, 1)';
const CURVA_ENTRADA = 'cubic-bezier(0.2, 0, 0, 1)';
let trocaAtual = 0;

function semAnimacao(el) {
    return !el || typeof el.animate !== 'function' || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
}

function cancelarAnimacoes(el) {
    if (!el) return;
    el.getAnimations?.({ subtree: true }).forEach((animacao) => animacao.cancel());
    el.style.overflow = '';
}

function animarEntrada(el, alturaAntes = null) {
    if (alturaAntes !== null) {
        const alturaDepois = el.offsetHeight;
        if (Math.abs(alturaDepois - alturaAntes) > 1) {
            el.style.overflow = 'hidden';
            el.animate([{ height: `${alturaAntes}px` }, { height: `${alturaDepois}px` }], { duration: ENTRADA_MS, easing: CURVA_ENTRADA })
                .finished.then(() => { el.style.overflow = ''; }, () => {});
        }
    }
    [...el.children].forEach((card, i) => {
        card.animate(
            [{ opacity: 0, transform: 'translateY(8px) scale(0.98)' }, { opacity: 1, transform: 'none' }],
            { duration: ENTRADA_MS, delay: Math.min(i, 6) * ESCALONAMENTO_MS, easing: CURVA_ENTRADA, fill: 'backwards' },
        );
    });
}

// Trocar o modo só muda a apresentação: mesma página, mesmo filtro, sem nova requisição.
// Cliques rápidos: cada clique cancela a animação em curso e parte do estado visível atual,
// então o layout final é sempre o do último botão escolhido.
async function escolherModo(valor) {
    if (valor === modo.value) return;
    modo.value = valor;
    try {
        window.localStorage.setItem(CHAVE_MODO, valor);
    } catch {
        // Sem armazenamento disponível: a escolha vale só nesta visita.
    }

    const troca = ++trocaAtual;
    const el = listagem.value;
    if (semAnimacao(el)) {
        cancelarAnimacoes(el);
        modoExibido.value = valor;
        return;
    }

    const opacidade = Number(getComputedStyle(el).opacity);
    cancelarAnimacoes(el);

    // Voltou ao layout que ainda está na tela antes do fim da saída: só reaparece.
    if (valor === modoExibido.value) {
        el.animate([{ opacity: opacidade }, { opacity: 1 }], { duration: ENTRADA_MS * (1 - opacidade), easing: CURVA_ENTRADA });
        return;
    }

    const saida = el.animate(
        [{ opacity: opacidade, transform: 'none' }, { opacity: 0, transform: 'scale(0.985)' }],
        { duration: SAIDA_MS * opacidade, easing: CURVA_SAIDA, fill: 'forwards' },
    );
    try {
        await saida.finished;
    } catch {
        return; // cancelada por um clique mais novo
    }
    if (troca !== trocaAtual) return;

    const alturaAntes = el.offsetHeight;
    modoExibido.value = valor;
    await nextTick();
    if (troca !== trocaAtual) return;

    animarEntrada(el, alturaAntes);
    saida.cancel(); // os cards já começam invisíveis (fill "backwards"): sem piscada
}

// Altura real do cabeçalho fixo, usada como margem de rolagem para ele não cobrir títulos.
let observadorCabecalho = null;
function medirCabecalho() {
    const altura = cabecalho.value?.offsetHeight || 0;
    document.documentElement.style.setProperty('--empresa-header-altura', `${altura}px`);
}

function iniciaisDe(nome) {
    return String(nome || '?').trim().split(/\s+/).slice(0, 2).map((p) => p[0]?.toUpperCase() || '').join('');
}

// Só a resposta da requisição mais recente é aplicada (troca rápida de filtro não mistura resultados).
let ultimaRequisicao = 0;

async function carregar(pagina = 1) {
    const requisicao = ++ultimaRequisicao;
    carregando.value = true;
    erro.value = '';
    try {
        const status = filtroStatus.value;
        const params = { page: pagina, per_page: 10 };
        if (status) params.status = status;
        const { data } = await empresaService.listarIndicacoes(params);
        if (requisicao !== ultimaRequisicao) return;
        // A página pedida deixou de existir (ex.: último item saiu do filtro): vai para a última válida.
        if (!(data.data || []).length && pagina > 1 && Number(data.last_page) < pagina) {
            carregar(Math.max(1, Number(data.last_page) || 1));
            return;
        }
        indicacoes.value = data.data || [];
        filtroAplicado.value = status;
        paginacao.value = { ...paginacao.value, ...data };
        novas.value = Number(data.novas || 0);
    } catch (error) {
        if (requisicao !== ultimaRequisicao) return;
        erro.value = !error?.response
            ? 'Sem conexão com o servidor. Verifique sua internet e tente novamente.'
            : 'Não foi possível carregar as indicações. Tente novamente.';
    } finally {
        if (requisicao === ultimaRequisicao) carregando.value = false;
    }
}

function filtrar(valor) {
    filtroStatus.value = valor;
    carregar(1);
}

async function irParaPagina(pagina) {
    await carregar(pagina);
    await nextTick();
    const semMovimento = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    resumoLista.value?.scrollIntoView({ block: 'start', behavior: semMovimento ? 'auto' : 'smooth' });
}

async function alterarStatus(indicacao, evento) {
    const novo = evento.target.value;
    const anterior = indicacao.status;
    if (novo === anterior) return;
    // Outra indicação ainda está salvando: desfaz a troca visual em vez de ignorá-la em silêncio.
    if (salvandoId.value) {
        evento.target.value = anterior;
        return;
    }

    salvandoId.value = indicacao.id;
    try {
        const { data } = await empresaService.atualizarStatusIndicacao(indicacao.id, novo);
        Object.assign(indicacao, data.indicacao);
        if (anterior === 'NOVA') novas.value = Math.max(0, novas.value - 1);
        toast.success(`Andamento atualizado para "${data.indicacao.status_rotulo}".`);
        // Com filtro ativo o item deixa de pertencer à lista; e uma listagem já em andamento
        // traria o status anterior. Nos dois casos recarrega a página para manter total,
        // páginas e contador de novas iguais aos do servidor.
        if ((filtroStatus.value && filtroStatus.value !== data.indicacao.status) || carregando.value) {
            carregar(paginacao.value.current_page || 1);
        }
    } catch (error) {
        // Volta o seletor ao valor salvo: nada de sucesso falso.
        evento.target.value = anterior;
        const mensagem = error?.response?.status === 422 ? error.response.data?.message : null;
        toast.error(mensagem || 'Não foi possível atualizar o andamento. Tente novamente.');
    } finally {
        salvandoId.value = null;
    }
}

async function sair() {
    try {
        await auth.logout();
    } finally {
        router.push({ name: 'login' });
    }
}

onMounted(() => {
    medirCabecalho();
    if (window.ResizeObserver && cabecalho.value) {
        observadorCabecalho = new ResizeObserver(medirCabecalho);
        observadorCabecalho.observe(cabecalho.value);
    }
    carregar(1);
});

onBeforeUnmount(() => {
    trocaAtual++;
    cancelarAnimacoes(listagem.value);
    observadorCabecalho?.disconnect();
    document.documentElement.style.removeProperty('--empresa-header-altura');
});
</script>

<style scoped>
.indicacoes-senac-page {
    min-height: 100vh;
    background: var(--ses-bg);
}

.senac-header-logo {
    display: block;
    width: auto;
    height: clamp(28px, 4vw, 36px);
    max-width: 120px;
}

.indicacoes-titulo {
    color: #1e3a5f;
}

html.dark .indicacoes-titulo {
    color: var(--ses-text);
}

/* Texto auxiliar com contraste AA nos dois temas. */
.indicacoes-texto-suave {
    color: #475569;
}

html.dark .indicacoes-texto-suave {
    color: var(--ses-muted);
}

/* Filtros não selecionados: no escuro, o azul-marinho padrão do contorno fica ilegível. */
html.dark .indicacoes-btn-contorno {
    --bs-btn-color: var(--bs-link-color);
    --bs-btn-border-color: var(--bs-link-color);
    --bs-btn-hover-color: #0f172a;
    --bs-btn-hover-bg: var(--bs-link-color);
    --bs-btn-hover-border-color: var(--bs-link-color);
    --bs-btn-active-color: #0f172a;
    --bs-btn-active-bg: var(--bs-link-hover-color);
    --bs-btn-active-border-color: var(--bs-link-hover-color);
}

/* Cabeçalho do Portal da Empresa sempre visível. Fica abaixo de dropdowns (1000+),
   offcanvas (1045) e modais (1055) do Bootstrap, que continuam por cima dele. */
.indicacoes-header {
    position: sticky;
    top: 0;
    z-index: 1020;
}

/* Rolagem programática (troca de página) para logo abaixo do cabeçalho fixo. */
.indicacoes-resumo {
    min-height: 1.5em;
    scroll-margin-top: calc(var(--empresa-header-altura, 72px) + 1rem);
}

.indicacoes-header-btn,
.indicacoes-filtro,
.indicacoes-modos .btn {
    min-height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.indicacoes-header-btn {
    min-width: 40px;
}

@media (max-width: 575.98px) {
    .indicacoes-filtro,
    .indicacoes-modos .btn,
    .indicacoes-header-btn {
        min-height: 44px;
    }

    .indicacoes-header-btn {
        min-width: 44px;
    }

    /* Área de toque confortável na paginação (o tamanho "sm" fica com ~30px). */
    .indicacoes-paginacao :deep(.page-link) {
        min-width: 44px;
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .indicacoes-modos {
        width: 100%;
    }

    .indicacoes-modos .btn {
        flex: 1 1 0;
    }
}

.indicacoes-senac-page :is(button, a, select):focus-visible {
    outline: 3px solid var(--ses-accent, #f7941d);
    outline-offset: 2px;
    box-shadow: none;
}

/* Paginação: o azul-marinho padrão some sobre o fundo escuro. */
html.dark .indicacoes-paginacao :deep(.pagination) {
    --bs-pagination-color: var(--bs-link-color);
    --bs-pagination-hover-color: var(--bs-link-hover-color);
    --bs-pagination-focus-color: var(--bs-link-hover-color);
    --bs-pagination-active-bg: var(--bs-link-color);
    --bs-pagination-active-border-color: var(--bs-link-color);
    --bs-pagination-active-color: #0f172a;
}

@media (prefers-reduced-motion: reduce) {
    .indicacoes-senac-page :deep(*) {
        transition: none !important;
        scroll-behavior: auto !important;
    }
}
</style>
