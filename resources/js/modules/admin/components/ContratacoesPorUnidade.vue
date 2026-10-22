<template>
    <section class="card border-0 shadow-sm" aria-labelledby="contratacoes-unidade-titulo" data-testid="contratacoes-unidade">
        <div class="card-body cpu-container">
            <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-3">
                <div>
                    <h2 id="contratacoes-unidade-titulo" class="h6 fw-bold mb-1">Contratações por Unidade Senac</h2>
                    <p class="text-secondary small mb-0">
                        Alunos distintos com contratação vigente, pela unidade do primeiro registro acadêmico.
                    </p>
                </div>

                <form class="d-flex flex-wrap align-items-end gap-2 cpu-filtros" @submit.prevent="aplicarPersonalizado">
                    <div>
                        <label for="cpu-periodo" class="form-label small fw-semibold mb-1">Período</label>
                        <select id="cpu-periodo" v-model="periodo" class="form-select form-select-sm cpu-controle cpu-periodo-select">
                            <option v-for="opcao in PERIODOS" :key="opcao.valor" :value="opcao.valor">{{ opcao.rotulo }}</option>
                        </select>
                    </div>
                    <template v-if="periodo === 'personalizado'">
                        <div>
                            <label for="cpu-data-inicial" class="form-label small fw-semibold mb-1">Data inicial</label>
                            <input id="cpu-data-inicial" v-model="dataInicial" type="date" class="form-control form-control-sm cpu-controle" :class="{ 'is-invalid': erroDatas }" :max="dataFinal || undefined">
                        </div>
                        <div>
                            <label for="cpu-data-final" class="form-label small fw-semibold mb-1">Data final</label>
                            <input id="cpu-data-final" v-model="dataFinal" type="date" class="form-control form-control-sm cpu-controle" :class="{ 'is-invalid': erroDatas }" :min="dataInicial || undefined">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm cpu-controle" :disabled="!dataInicial || !dataFinal || Boolean(erroDatas)">Aplicar</button>
                    </template>
                </form>
            </div>
            <p v-if="erroDatas" class="text-danger small" role="alert">{{ erroDatas }}</p>

            <div v-if="erro" class="alert alert-danger d-flex justify-content-between align-items-center flex-wrap gap-2 mb-0" role="alert">
                <span>{{ erro }}</span>
                <button type="button" class="btn btn-sm btn-outline-danger" @click="carregar">Tentar novamente</button>
            </div>

            <div v-else-if="!dados" class="d-flex align-items-center gap-2 text-secondary small py-4 justify-content-center" role="status">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Carregando contratações por unidade...
            </div>

            <div v-else :class="{ 'cpu-atualizando': carregando }" :aria-busy="carregando">
                <p class="small text-secondary mb-3" role="status" aria-live="polite" data-testid="cpu-periodo">
                    Período: <strong class="cpu-texto-forte">{{ dados.periodo.rotulo }}</strong>
                </p>

                <dl class="row g-2 mb-4 cpu-resumo">
                    <div class="col-12 col-sm-4">
                        <div class="cpu-tile">
                            <dt>Alunos contratados</dt>
                            <dd data-testid="cpu-total-alunos">{{ numero(dados.totalAlunos) }}</dd>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="cpu-tile">
                            <dt>Contratações vigentes</dt>
                            <dd data-testid="cpu-total-contratacoes">{{ numero(dados.totalContratacoes) }}</dd>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="cpu-tile">
                            <dt>Unidades com contratações</dt>
                            <dd data-testid="cpu-total-unidades">{{ numero(dados.totalUnidades) }}</dd>
                        </div>
                    </div>
                </dl>

                <p v-if="!dados.unidades.length" class="text-secondary small text-center py-4 mb-0" data-testid="cpu-vazio">
                    <i class="bi bi-bar-chart d-block fs-3 mb-2" aria-hidden="true"></i>
                    Nenhuma contratação vigente no período selecionado.
                </p>

                <template v-else>
                    <div class="cpu-cabecalho-ranking small text-secondary" aria-hidden="true">
                        <span>#</span><span>Unidade</span><span class="cpu-col-barra"></span><span class="text-end">Alunos · % do total</span>
                    </div>
                    <ol class="list-unstyled mb-0 cpu-ranking" aria-label="Ranking de unidades por alunos contratados" data-testid="cpu-ranking">
                        <li
                            v-for="item in unidadesVisiveis"
                            :key="item.unidade"
                            class="cpu-linha"
                            :title="`${item.unidade}: ${rotuloAlunos(item.alunos)} (${rotuloContratacoes(item.contratacoes)}) · ${percentual(item.percentual)} do total`"
                        >
                            <span class="cpu-posicao" :aria-label="`${item.posicao}º lugar`">{{ item.posicao }}º</span>
                            <span class="cpu-unidade" :class="{ 'fst-italic text-secondary': item.unidade === SEM_UNIDADE }">{{ item.unidade }}</span>
                            <span class="cpu-trilho" aria-hidden="true">
                                <span class="cpu-barra" :style="{ width: `${larguraBarra(item.alunos)}%` }"></span>
                            </span>
                            <span class="cpu-valor">
                                <strong class="cpu-texto-forte">{{ numero(item.alunos) }}</strong>
                                <span class="visually-hidden"> {{ item.alunos === 1 ? 'aluno' : 'alunos' }},</span>
                                <span class="text-secondary"> · {{ percentual(item.percentual) }}</span>
                                <span v-if="item.contratacoes !== item.alunos" class="cpu-sub small text-secondary">{{ rotuloContratacoes(item.contratacoes) }}</span>
                            </span>
                        </li>
                    </ol>

                    <button
                        v-if="dados.unidades.length > LIMITE_INICIAL"
                        type="button"
                        class="btn btn-sm btn-outline-primary cpu-btn-contorno mt-3 cpu-controle"
                        :aria-expanded="mostrarTodas"
                        data-testid="cpu-ver-todas"
                        @click="mostrarTodas = !mostrarTodas"
                    >
                        {{ mostrarTodas ? 'Mostrar as 10 primeiras' : `Ver todas as ${dados.unidades.length} unidades` }}
                    </button>
                </template>

                <p v-if="dados.unidadesSemContratacao.length" class="small text-secondary mt-3 mb-0" data-testid="cpu-sem-contratacao">
                    <span class="fw-semibold">Sem contratações no período:</span> {{ dados.unidadesSemContratacao.join(', ') }}.
                </p>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import adminService from '../../../services/adminServices';

const PERIODOS = [
    { valor: 'ultimos_30_dias', rotulo: 'Últimos 30 dias' },
    { valor: 'ultimos_90_dias', rotulo: 'Últimos 90 dias' },
    { valor: 'ultimos_12_meses', rotulo: 'Últimos 12 meses' },
    { valor: 'todo_periodo', rotulo: 'Todo o período' },
    { valor: 'personalizado', rotulo: 'Personalizado' },
];
const LIMITE_INICIAL = 10;
const SEM_UNIDADE = 'Unidade não informada';

const periodo = ref('ultimos_30_dias');
const dataInicial = ref('');
const dataFinal = ref('');
const dados = ref(null);
const carregando = ref(false);
const erro = ref('');
const mostrarTodas = ref(false);

const erroDatas = computed(() => (
    periodo.value === 'personalizado' && dataInicial.value && dataFinal.value && dataInicial.value > dataFinal.value
        ? 'A data inicial não pode ser posterior à data final.'
        : ''
));
const maiorQuantidade = computed(() => Math.max(1, ...(dados.value?.unidades || []).map((u) => u.alunos)));
const unidadesVisiveis = computed(() => {
    const unidades = dados.value?.unidades || [];
    return mostrarTodas.value ? unidades : unidades.slice(0, LIMITE_INICIAL);
});

const formatoNumero = new Intl.NumberFormat('pt-BR');
const formatoPercentual = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
const numero = (valor) => formatoNumero.format(Number(valor) || 0);
const percentual = (valor) => `${formatoPercentual.format(Number(valor) || 0)}%`;
const rotuloAlunos = (n) => `${numero(n)} ${n === 1 ? 'aluno' : 'alunos'}`;
const rotuloContratacoes = (n) => `${numero(n)} ${n === 1 ? 'contratação' : 'contratações'}`;
// Barra mínima visível para unidades pequenas ao lado de uma muito grande (ex.: 3 vs 80).
const larguraBarra = (alunos) => Math.max(1.5, (alunos / maiorQuantidade.value) * 100);

// Só a resposta mais recente é aplicada (troca rápida de período não mistura resultados).
let ultimaRequisicao = 0;

async function carregar() {
    const params = { periodo: periodo.value };
    if (periodo.value === 'personalizado') {
        if (!dataInicial.value || !dataFinal.value || erroDatas.value) return;
        params.data_inicial = dataInicial.value;
        params.data_final = dataFinal.value;
    }

    const requisicao = ++ultimaRequisicao;
    carregando.value = true;
    erro.value = '';
    try {
        const { data } = await adminService.getContratacoesPorUnidade(params);
        if (requisicao !== ultimaRequisicao) return;
        dados.value = data;
        mostrarTodas.value = false;
    } catch (error) {
        if (requisicao !== ultimaRequisicao) return;
        erro.value = error?.response?.status === 422
            ? (error.response.data?.message || 'Período inválido.')
            : 'Não foi possível carregar as contratações por unidade. Tente novamente.';
    } finally {
        if (requisicao === ultimaRequisicao) carregando.value = false;
    }
}

function aplicarPersonalizado() {
    if (periodo.value === 'personalizado') carregar();
}

// Atalhos de período atualizam na hora; o personalizado espera "Aplicar".
watch(periodo, (valor) => {
    if (valor !== 'personalizado') carregar();
});

onMounted(carregar);

defineExpose({ recarregar: carregar });
</script>

<style scoped>
.cpu-controle {
    min-height: 38px;
}

.cpu-texto-forte {
    color: var(--bs-body-color);
}

.cpu-tile {
    height: 100%;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    background: rgba(var(--ses-primary-rgb), 0.06);
}

.cpu-tile dt {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--bs-secondary-color);
}

.cpu-tile dd {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--ses-primary);
    font-variant-numeric: tabular-nums;
}

.cpu-atualizando {
    opacity: 0.6;
    transition: opacity 0.15s ease;
}

/* Ranking: posição | unidade | barra | valor. No celular a barra desce para a linha de baixo. */
.cpu-cabecalho-ranking,
.cpu-linha {
    display: grid;
    grid-template-columns: 2.5rem minmax(9rem, 14rem) minmax(0, 1fr) 9.5rem;
    align-items: center;
    column-gap: 0.75rem;
}

.cpu-cabecalho-ranking {
    padding: 0 0.5rem 0.35rem;
    border-bottom: 1px solid var(--bs-border-color);
}

.cpu-linha {
    padding: 0.55rem 0.5rem;
    border-bottom: 1px solid var(--bs-border-color);
    border-radius: 0.25rem;
}

.cpu-linha:hover {
    background: rgba(var(--ses-primary-rgb), 0.05);
}

.cpu-posicao {
    font-weight: 700;
    color: var(--bs-secondary-color);
    font-variant-numeric: tabular-nums;
}

.cpu-unidade {
    min-width: 0;
    overflow-wrap: break-word;
    font-weight: 600;
}

.cpu-trilho {
    display: block;
    height: 12px;
    border-radius: 4px;
    background: rgba(var(--ses-primary-rgb), 0.08);
    overflow: hidden;
}

.cpu-barra {
    display: block;
    height: 100%;
    border-radius: 0 4px 4px 0;
    background: var(--ses-primary);
    transition: width 0.3s ease;
}

.cpu-valor {
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.cpu-sub {
    display: block;
}

/* Tema escuro: o azul institucional fica escuro demais sobre o fundo; usa um passo mais claro do mesmo azul. */
html.dark .cpu-tile {
    background: rgba(91, 155, 224, 0.1);
}

html.dark .cpu-tile dd {
    color: #7db7ff;
}

html.dark .cpu-trilho {
    background: rgba(148, 163, 184, 0.14);
}

html.dark .cpu-barra {
    background: #5b9be0;
}

html.dark .cpu-linha:hover {
    background: rgba(148, 163, 184, 0.08);
}

html.dark .cpu-btn-contorno {
    --bs-btn-color: var(--bs-link-color);
    --bs-btn-border-color: var(--bs-link-color);
    --bs-btn-hover-color: #0f172a;
    --bs-btn-hover-bg: var(--bs-link-color);
    --bs-btn-hover-border-color: var(--bs-link-color);
}

/* O espaço útil depende do menu lateral (aberto ou recolhido), não só da tela:
   o ranking se adapta à largura da própria seção. */
.cpu-container {
    container: cpu / inline-size;
}

.cpu-periodo-select {
    min-width: 11rem;
}

@container cpu (max-width: 640px) {
    .cpu-cabecalho-ranking {
        display: none;
    }

    /* Posição | nome; abaixo, o valor e a barra em largura cheia (igual em todas as linhas,
       para as barras continuarem comparáveis entre si). */
    .cpu-linha {
        grid-template-columns: 2.25rem minmax(0, 1fr);
        row-gap: 0.3rem;
    }

    .cpu-valor {
        grid-column: 2;
        grid-row: 2;
        text-align: left;
    }

    .cpu-sub {
        display: inline;
    }

    .cpu-sub::before {
        content: ' · ';
    }

    .cpu-trilho {
        grid-column: 2;
        grid-row: 3;
    }

    .cpu-filtros,
    .cpu-filtros > * {
        width: 100%;
    }
}

/* Área de toque confortável em telas de toque pequenas. */
@media (max-width: 767.98px) {
    .cpu-controle {
        min-height: 44px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .cpu-barra,
    .cpu-atualizando {
        transition: none;
    }
}
</style>
