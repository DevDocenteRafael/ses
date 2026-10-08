<template>
    <div>
        <topbar
            titulo="Indicadores de Empregabilidade"
            subtitulo="Acompanhamento estratégico do Portal Senac"
        />

        <div class="container-fluid p-4">
            <loading v-if="admin.carregando && !carregouUmaVez" mensagem="Carregando indicadores..." />

            <template v-else-if="dash">
                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-3">
                    <div>
                        <h1 class="h5 fw-bold mb-1">Resumo dos indicadores</h1>
                        <p class="text-secondary small mb-0">Gere um relatório PDF com os dados agregados do dashboard.</p>
                    </div>
                    <button
                        ref="botaoRelatorioRef"
                        type="button"
                        class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 ses-btn-relatorio"
                        @click="abrirModalRelatorio"
                    >
                        <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                        <span>Relatório Geral</span>
                    </button>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">
                        <cardIndicador
                            titulo="Perfis Ativos"
                            :valor="dash.perfisAtivos.total"
                            :subtitulo="variacaoPerfisLabel"
                            :subtituloClass="variacaoPerfisClass"
                            icone="bi-person-check"
                            variante="primary"
                        />
                    </div>
                    <div class="col-6 col-lg-3">
                        <cardIndicador
                            titulo="Contratados"
                            :valor="dash.contratados?.ultimos30Dias || 0"
                            subtitulo="Últimos 30 dias"
                            icone="bi-person-check"
                            variante="primary"
                        />
                    </div>
                    <div class="col-6 col-lg-3">
                        <cardIndicador
                            titulo="Acessos de Candidatos"
                            :valor="dash.acessosCandidatos.ultimos30Dias"
                            subtitulo="Últimos 30 dias"
                            icone="bi-eye"
                            variante="info"
                        />
                    </div>
                    <div class="col-6 col-lg-3">
                        <cardIndicador
                            titulo="Empresas Ativas"
                            :valor="dash.empresasAtivas.total"
                            :subtitulo="`${dash.empresasAtivas.engajamentoPercentual}% de engajamento`"
                            icone="bi-building"
                            variante="success"
                        />
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h2 class="h6 fw-bold mb-3">Acessos por Área de Interesse</h2>

                                <p v-if="!dash.acessosPorSegmento.length" class="text-secondary small mb-0">
                                    Ainda não há visualizações de perfil registradas.
                                </p>

                                <template v-else>
                                    <svg viewBox="0 0 200 200" class="mx-auto d-block mb-3" style="max-width: 220px;" role="img" aria-label="Acessos por área de interesse">
                                        <circle
                                            v-for="fatia in fatiasDonut"
                                            :key="fatia.segmento"
                                            cx="100" cy="100" r="70"
                                            fill="none"
                                            :stroke="fatia.cor"
                                            stroke-width="34"
                                            :stroke-dasharray="`${fatia.tamanho} ${circunferencia - fatia.tamanho}`"
                                            :stroke-dashoffset="fatia.offset"
                                            transform="rotate(-90 100 100)"
                                        />
                                        <text x="100" y="95" text-anchor="middle" class="ses-donut-total">{{ totalAcessosSegmento }}</text>
                                        <text x="100" y="113" text-anchor="middle" class="ses-chart-label">acessos</text>
                                    </svg>

                                    <div v-for="fatia in fatiasDonut" :key="`legenda-${fatia.segmento}`" class="d-flex align-items-center justify-content-between small mb-1">
                                        <span class="d-flex align-items-center gap-2">
                                            <span class="rounded-circle d-inline-block" :style="{ width: '10px', height: '10px', backgroundColor: fatia.cor }"></span>
                                            {{ fatia.segmento }}
                                        </span>
                                        <span class="text-secondary">{{ fatia.total }}</span>
                                    </div>
                                </template>

                                <hr class="my-4">
                                <h3 class="h6 fw-bold mb-1">Cursos com mais candidatos cadastrados</h3>
                                <p class="text-secondary small mb-2">Os 10 cursos com maior quantidade de candidatos.</p>
                                <p v-if="!dash.candidatosPorCurso?.length" class="text-secondary small mb-0">
                                    Ainda não há candidatos com curso acadêmico registrado.
                                </p>
                                <div v-else style="height: 330px;">
                                    <canvas ref="graficoCandidatosCursoRef" role="img" aria-label="Quantidade de candidatos cadastrados por curso"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h2 class="h6 fw-bold mb-1">Engajamento de Empresas (Histórico)</h2>
                                <p class="text-secondary small mb-3">
                                    Visualizações de perfil de candidatos feitas por empresas, mês a mês.
                                </p>

                                <p v-if="!dash.visualizacoesPorMes.length" class="text-secondary small mb-0">
                                    Ainda não há dados suficientes para montar o histórico.
                                </p>
                                <svg v-else viewBox="0 0 700 320" class="w-100" role="img" aria-label="Engajamento de empresas">
                                    <line
                                        v-for="linha in linhasGrade"
                                        :key="linha.y"
                                        :x1="50" :x2="680" :y1="linha.y" :y2="linha.y"
                                        stroke="#e9ecef" stroke-width="1"
                                    />
                                    <text
                                        v-for="linha in linhasGrade"
                                        :key="`label-${linha.y}`"
                                        :x="40" :y="linha.y + 4" text-anchor="end"
                                        class="ses-chart-label"
                                    >{{ linha.valor }}</text>

                                    <polyline :points="pontosLinha" fill="none" stroke="#f5a623" stroke-width="3" />

                                    <circle
                                        v-for="(p, i) in pontosCirculo"
                                        :key="i"
                                        :cx="p.x" :cy="p.y" r="4"
                                        fill="#f5a623"
                                    />

                                    <text
                                        v-for="(m, i) in serieVisualizacoes"
                                        :key="`mes-${i}`"
                                        :x="pontosCirculo[i].x" y="310" text-anchor="middle"
                                        class="ses-chart-label"
                                    >{{ m.label }}</text>
                                </svg>
                                <p class="small mb-0 mt-2">
                                    <span class="d-inline-flex align-items-center gap-2">
                                        <span class="rounded-circle d-inline-block" style="width:10px;height:10px;background-color:#f5a623;"></span>
                                        Visualizações de Perfil
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h2 class="h6 fw-bold mb-1">Cursos com mais contratações</h2>
                                <p class="text-secondary small mb-3">Os 10 cursos com mais contratações nos últimos 30 dias.</p>
                                <p v-if="!dash.cursosMaisContratados?.length" class="text-secondary small mb-0">
                                    Ainda não há contratações com cursos acadêmicos registrados.
                                </p>
                                <div v-else style="height: 340px;">
                                    <canvas ref="graficoCursosRef" role="img" aria-label="Quantidade de contratações por curso"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="text-secondary small mt-3 mb-0">
                    "Buscas Realizadas" e "Filtros Mais Acessados pelas Empresas" dependem de um registro de
                    buscas em Buscar Talentos que ainda não existe no sistema — por isso não aparecem aqui.
                </p>
            </template>
        </div>

        <modal :show="modalRelatorioAberto" titulo="Gerar Relatório" @fechar="fecharModalRelatorio">
            <p class="text-secondary mb-4" id="descricao-relatorio-geral">
                Selecione quais informações deseja incluir no relatório.
            </p>

            <div class="vstack gap-3" role="radiogroup" aria-describedby="descricao-relatorio-geral">
                <label class="ses-opcao-relatorio">
                    <input v-model="modoRelatorio" class="form-check-input" type="radio" value="todos">
                    <span>
                        <strong>Todos os dados</strong>
                        <small>Inclui Perfis Ativos, Contratados, Acessos de Candidatos e Empresas Ativas.</small>
                    </span>
                </label>

                <label class="ses-opcao-relatorio">
                    <input v-model="modoRelatorio" class="form-check-input" type="radio" value="especificos">
                    <span>
                        <strong>Selecionar dados específicos</strong>
                        <small>Escolha uma ou mais seções para compor o PDF.</small>
                    </span>
                </label>
            </div>

            <hr class="my-4">

            <fieldset :disabled="modoRelatorio !== 'especificos'" class="ses-fieldset-relatorio">
                <legend class="h6 fw-bold mb-3">Informações do relatório</legend>
                <div class="row g-2">
                    <div v-for="opcao in opcoesRelatorio" :key="opcao.valor" class="col-12 col-sm-6">
                        <label class="ses-checkbox-relatorio" :class="{ 'is-disabled': modoRelatorio !== 'especificos' }">
                            <input v-model="secoesRelatorio" class="form-check-input" type="checkbox" :value="opcao.valor">
                            <span>{{ opcao.label }}</span>
                        </label>
                    </div>
                </div>
            </fieldset>

            <hr class="my-4">

            <fieldset class="ses-fieldset-relatorio ses-periodo-relatorio">
                <legend class="visually-hidden">Período do relatório</legend>
                <button
                    id="relatorio-periodo-toggle"
                    type="button"
                    class="ses-periodo-toggle"
                    :aria-expanded="periodoRelatorioAberto ? 'true' : 'false'"
                    aria-controls="relatorio-periodo-conteudo"
                    @click="periodoRelatorioAberto = !periodoRelatorioAberto"
                >
                    <span class="ses-periodo-toggle-texto">
                        <span class="h6 fw-bold mb-0">Período do relatório</span>
                        <span v-if="periodoRelatorioSelecionadoLabel" class="ses-periodo-resumo">
                            {{ periodoRelatorioSelecionadoLabel }}
                        </span>
                    </span>
                    <i :class="['bi', periodoRelatorioAberto ? 'bi-chevron-up' : 'bi-chevron-down']" aria-hidden="true"></i>
                </button>

                <Transition name="ses-periodo-collapse">
                    <div
                        v-show="periodoRelatorioAberto"
                        id="relatorio-periodo-conteudo"
                        class="ses-periodo-conteudo"
                        role="region"
                        aria-labelledby="relatorio-periodo-toggle"
                    >
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="relatorio-data-inicial">Data inicial</label>
                                <input
                                    id="relatorio-data-inicial"
                                    v-model="dataInicialRelatorio"
                                    type="date"
                                    class="form-control"
                                    :class="{ 'is-invalid': erroPeriodoRelatorio }"
                                >
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="relatorio-data-final">Data final</label>
                                <input
                                    id="relatorio-data-final"
                                    v-model="dataFinalRelatorio"
                                    type="date"
                                    class="form-control"
                                    :class="{ 'is-invalid': erroPeriodoRelatorio }"
                                >
                            </div>
                        </div>
                        <p class="text-secondary small mt-2 mb-0">
                            Se o período não for preenchido por completo, será considerado o mês atual.
                        </p>
                    </div>
                </Transition>
            </fieldset>

            <p v-if="erroPeriodoRelatorio" class="text-danger small mt-3 mb-0" role="alert">
                {{ erroPeriodoRelatorio }}
            </p>

            <p v-if="erroSelecaoRelatorio" class="text-danger small mt-3 mb-0" role="alert">
                {{ erroSelecaoRelatorio }}
            </p>

            <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 mt-4">
                <button type="button" class="btn btn-primary" :disabled="gerandoRelatorio || Boolean(erroSelecaoRelatorio)" @click="gerarRelatorio">
                    <span v-if="gerandoRelatorio" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                    {{ gerandoRelatorio ? 'Gerando relatório...' : 'Gerar PDF' }}
                </button>
            </div>
        </modal>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import Chart from 'chart.js/auto';
import topbar from '../../../components/common/header.vue';
import cardIndicador from '../../../components/common/cardIndicador.vue';
import loading from '../../../components/common/loading.vue';
import modal from '../../../components/common/modal.vue';
import { useAdminStore } from '../../../store/admin';
import adminService from '../../../services/adminServices';
import { useToast } from '../../../composables/useToast';

const admin = useAdminStore();
const toast = useToast();
const carregouUmaVez = ref(false);
const graficoCursosRef = ref(null);
const graficoCandidatosCursoRef = ref(null);
const botaoRelatorioRef = ref(null);
const modalRelatorioAberto = ref(false);
const modoRelatorio = ref('todos');
const secoesRelatorio = ref([]);
const gerandoRelatorio = ref(false);
const dataInicialRelatorio = ref('');
const dataFinalRelatorio = ref('');
const periodoRelatorioAberto = ref(false);
let graficoCursos = null;
let graficoCandidatosCurso = null;

const opcoesRelatorio = [
    { valor: 'perfis_ativos', label: 'Perfis Ativos' },
    { valor: 'contratados', label: 'Contratados' },
    { valor: 'acessos_candidatos', label: 'Acessos de Candidatos' },
    { valor: 'empresas_ativas', label: 'Empresas Ativas' },
];

onMounted(async () => {
    await admin.carregarDashboard();
    carregouUmaVez.value = true;
    await nextTick();

    if (admin.dashboard?.cursosMaisContratados?.length && graficoCursosRef.value) {
        graficoCursos = new Chart(graficoCursosRef.value, {
            type: 'bar',
            data: {
                labels: admin.dashboard.cursosMaisContratados.map((item) => quebrarRotuloCurso(item.curso)),
                datasets: [{
                    label: 'Contratados',
                    data: admin.dashboard.cursosMaisContratados.map((item) => Number(item.total)),
                    backgroundColor: '#0b4f91',
                    hoverBackgroundColor: '#083b6d',
                    borderRadius: 6,
                    maxBarThickness: 56,
                }],
            },
            options: {
                maintainAspectRatio: false,
                layout: { padding: { top: 22 } },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (context) => `${context.parsed.y} contratado(s)` } },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#596579', maxRotation: 0, minRotation: 0 },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, stepSize: 1, color: '#596579' },
                        grid: { color: '#e9eef4' },
                    },
                },
            },
            plugins: [{
                id: 'rotulosQuantidade',
                afterDatasetsDraw(chart) {
                    const { ctx } = chart;
                    chart.getDatasetMeta(0).data.forEach((barra, indice) => {
                        ctx.save();
                        ctx.fillStyle = '#163f70';
                        ctx.font = '600 12px sans-serif';
                        ctx.textAlign = 'center';
                        ctx.fillText(String(chart.data.datasets[0].data[indice]), barra.x, barra.y - 7);
                        ctx.restore();
                    });
                },
            }],
        });
    }

    if (admin.dashboard?.candidatosPorCurso?.length && graficoCandidatosCursoRef.value) {
        const cursos = admin.dashboard.candidatosPorCurso;
        const maiorQuantidade = Math.max(1, ...cursos.map((item) => Number(item.total)));
        graficoCandidatosCurso = new Chart(graficoCandidatosCursoRef.value, {
            type: 'bar',
            data: {
                labels: cursos.map((item) => resumirRotuloCurso(item.curso)),
                datasets: [{
                    label: 'Candidatos cadastrados',
                    data: cursos.map((item) => Number(item.total)),
                    backgroundColor: '#0b4f91',
                    hoverBackgroundColor: '#083b6d',
                    borderRadius: 5,
                    maxBarThickness: 22,
                }],
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                layout: { padding: { right: 28 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: (itens) => cursos[itens[0]?.dataIndex]?.curso || '',
                            label: (context) => `${context.parsed.x} candidato(s)`,
                        },
                    },
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        suggestedMax: maiorQuantidade + Math.max(1, Math.ceil(maiorQuantidade * 0.15)),
                        ticks: { display: false },
                        grid: { display: false },
                    },
                    y: { grid: { display: false }, ticks: { color: '#596579' } },
                },
            },
            plugins: [{
                id: 'rotulosCandidatosPorCurso',
                afterDatasetsDraw(chart) {
                    const { ctx } = chart;
                    chart.getDatasetMeta(0).data.forEach((barra, indice) => {
                        ctx.save();
                        ctx.fillStyle = '#163f70';
                        ctx.font = '600 11px sans-serif';
                        ctx.textAlign = 'left';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(String(chart.data.datasets[0].data[indice]), barra.x + 6, barra.y);
                        ctx.restore();
                    });
                },
            }],
        });
    }
});

onBeforeUnmount(() => {
    graficoCursos?.destroy();
    graficoCandidatosCurso?.destroy();
});

function quebrarRotuloCurso(valor) {
    const palavras = String(valor || 'Curso não informado').split(/\s+/);
    const linhas = [];
    let linha = '';

    palavras.forEach((palavra) => {
        if (linha && `${linha} ${palavra}`.length > 18) {
            linhas.push(linha);
            linha = palavra;
        } else {
            linha = linha ? `${linha} ${palavra}` : palavra;
        }
    });

    if (linha) linhas.push(linha);
    return linhas;
}

function resumirRotuloCurso(valor) {
    let rotulo = String(valor || 'Curso não informado')
        .replace(/^pós-graduação em\s*/i, 'Pós-grad. ')
        .replace(/^graduação em\s*/i, 'Grad. ')
        .replace(/^certificação em\s*/i, 'Cert. ')
        .replace(/^curso de\s*/i, '')
        .replace(/\s+/g, ' ')
        .trim();

    if (rotulo.length > 34) {
        rotulo = `${rotulo.slice(0, 31).trimEnd()}…`;
    }

    return quebrarRotuloCurso(rotulo);
}

const dash = computed(() => admin.dashboard);
const erroSelecaoRelatorio = computed(() => (
    modoRelatorio.value === 'especificos' && secoesRelatorio.value.length === 0
        ? 'Selecione pelo menos uma informação para gerar o relatório.'
        : ''
));
const erroPeriodoRelatorio = computed(() => (
    dataInicialRelatorio.value && dataFinalRelatorio.value && dataInicialRelatorio.value > dataFinalRelatorio.value
        ? 'A data inicial não pode ser posterior à data final.'
        : ''
));
const podeGerarRelatorio = computed(() => !gerandoRelatorio.value && !erroSelecaoRelatorio.value && !erroPeriodoRelatorio.value);
const periodoRelatorioSelecionadoLabel = computed(() => {
    if (!dataInicialRelatorio.value || !dataFinalRelatorio.value) return '';
    return `${formatarDataRelatorio(dataInicialRelatorio.value)} a ${formatarDataRelatorio(dataFinalRelatorio.value)}`;
});

function abrirModalRelatorio() {
    modalRelatorioAberto.value = true;
    modoRelatorio.value = 'todos';
    secoesRelatorio.value = [];
    dataInicialRelatorio.value = '';
    dataFinalRelatorio.value = '';
    periodoRelatorioAberto.value = false;
}

function fecharModalRelatorio() {
    if (gerandoRelatorio.value) return;
    modalRelatorioAberto.value = false;
    nextTick(() => botaoRelatorioRef.value?.focus());
}

function nomeArquivoRelatorio(headers) {
    const disposition = headers?.['content-disposition'] || headers?.get?.('content-disposition') || '';
    const match = disposition.match(/filename="?([^";]+)"?/i);
    return match?.[1] || 'Relatorio_Geral.pdf';
}

function formatarDataRelatorio(valor) {
    const [ano, mes, dia] = String(valor || '').split('-');
    return ano && mes && dia ? `${dia}/${mes}/${ano}` : '';
}

async function gerarRelatorio() {
    if (!podeGerarRelatorio.value) {
        if (erroPeriodoRelatorio.value) {
            periodoRelatorioAberto.value = true;
        }
        return;
    }

    gerandoRelatorio.value = true;
    try {
        const payload = modoRelatorio.value === 'todos'
            ? { modo: 'todos' }
            : { modo: 'especificos', secoes: secoesRelatorio.value };
        payload.data_inicial = dataInicialRelatorio.value || null;
        payload.data_final = dataFinalRelatorio.value || null;
        const response = await adminService.gerarRelatorioDashboard(payload);
        const blob = new Blob([response.data], { type: 'application/pdf' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = nomeArquivoRelatorio(response.headers);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
        modalRelatorioAberto.value = false;
        toast.success('Relatório gerado com sucesso.');
        nextTick(() => botaoRelatorioRef.value?.focus());
    } catch (error) {
        toast.error('Não foi possível gerar o relatório. Tente novamente.');
    } finally {
        gerandoRelatorio.value = false;
    }
}

// ── Card "Perfis Ativos" ────────────────────────────────────────
const variacaoPerfisLabel = computed(() => {
    const v = dash.value?.perfisAtivos?.variacaoPercentualVsMesAnterior;
    if (v === null || v === undefined) return dash.value?.perfisAtivos?.subtitulo || 'Candidatos com status ativo';
    const seta = v >= 0 ? '↑' : '↓';
    return `${seta} ${Math.abs(v)}% vs mês anterior`;
});
const variacaoPerfisClass = computed(() => {
    const v = dash.value?.perfisAtivos?.variacaoPercentualVsMesAnterior;
    if (v === null || v === undefined) return 'text-secondary';
    return v >= 0 ? 'text-success' : 'text-danger';
});

// ── Donut: acessos por segmento/área de interesse ────────────────
const paleta = ['#004587', '#f5a623', '#2f9e44', '#1c7ed6', '#868e96', '#e8590c'];
const circunferencia = 2 * Math.PI * 70;

const totalAcessosSegmento = computed(
    () => (dash.value?.acessosPorSegmento || []).reduce((soma, s) => soma + s.total, 0),
);

const fatiasDonut = computed(() => {
    let acumulado = 0;
    const total = totalAcessosSegmento.value || 1;
    return (dash.value?.acessosPorSegmento || []).map((item, i) => {
        const tamanho = (item.total / total) * circunferencia;
        const fatia = {
            segmento: item.segmento || 'Não informado',
            total: item.total,
            cor: paleta[i % paleta.length],
            tamanho,
            offset: -acumulado,
        };
        acumulado += tamanho;
        return fatia;
    });
});

// ── Linha: visualizações de perfil por mês ───────────────────────
const nomesMes = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

const serieVisualizacoes = computed(() => (dash.value?.visualizacoesPorMes || []).map((item) => {
    const [ano, mes] = item.mes.split('-');
    return { label: `${nomesMes[Number(mes) - 1]}/${ano.slice(2)}`, valor: item.total };
}));

const maiorValor = computed(() => Math.max(1, ...serieVisualizacoes.value.map((s) => s.valor)));

const linhasGrade = computed(() => {
    const passos = 4;
    return Array.from({ length: passos + 1 }, (_, i) => {
        const valor = Math.round((maiorValor.value / passos) * (passos - i));
        return { y: 30 + (260 / passos) * i, valor };
    });
});

const pontosCirculo = computed(() => {
    const n = serieVisualizacoes.value.length;
    if (n <= 1) return serieVisualizacoes.value.map(() => ({ x: 365, y: 290 }));
    return serieVisualizacoes.value.map((s, i) => ({
        x: 50 + (630 / (n - 1)) * i,
        y: 290 - (s.valor / maiorValor.value) * 260,
    }));
});

const pontosLinha = computed(() => pontosCirculo.value.map((p) => `${p.x},${p.y}`).join(' '));
</script>

<style scoped>
.ses-chart-label {
    font-size: 10px;
    fill: #6c757d;
}
.ses-donut-total {
    font-size: 22px;
    font-weight: 700;
    fill: var(--ses-primary);
}

.ses-btn-relatorio {
    min-height: 42px;
}

.ses-opcao-relatorio,
.ses-checkbox-relatorio {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
    width: 100%;
    border: 1px solid var(--bs-border-color);
    border-radius: .75rem;
    padding: .85rem 1rem;
    cursor: pointer;
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
}

.ses-opcao-relatorio:hover,
.ses-checkbox-relatorio:hover {
    border-color: var(--ses-primary);
}

.ses-opcao-relatorio small {
    display: block;
    color: var(--bs-secondary-color);
}

.ses-checkbox-relatorio {
    align-items: center;
    min-height: 48px;
}

.ses-checkbox-relatorio.is-disabled {
    opacity: .6;
    cursor: not-allowed;
}

.ses-fieldset-relatorio:disabled .ses-checkbox-relatorio {
    pointer-events: none;
}

.ses-periodo-relatorio {
    border-top: 1px solid var(--bs-border-color);
    border-bottom: 1px solid var(--bs-border-color);
    padding: .25rem 0;
}

.ses-periodo-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    width: 100%;
    min-height: 48px;
    border: 0;
    padding: .75rem 0;
    background: transparent;
    color: var(--bs-body-color);
    text-align: left;
    cursor: pointer;
}

.ses-periodo-toggle:focus-visible {
    outline: 3px solid rgba(var(--bs-primary-rgb), .35);
    outline-offset: 2px;
    border-radius: .5rem;
}

.ses-periodo-toggle-texto {
    display: flex;
    flex-direction: column;
    gap: .15rem;
    min-width: 0;
}

.ses-periodo-resumo {
    color: var(--bs-secondary-color);
    font-size: .875rem;
    line-height: 1.25;
}

.ses-periodo-conteudo {
    padding: .5rem 0 1rem;
}

.ses-periodo-collapse-enter-active,
.ses-periodo-collapse-leave-active {
    overflow: hidden;
    transition: max-height .2s ease, opacity .2s ease;
}

.ses-periodo-collapse-enter-from,
.ses-periodo-collapse-leave-to {
    max-height: 0;
    opacity: 0;
}

.ses-periodo-collapse-enter-to,
.ses-periodo-collapse-leave-from {
    max-height: 14rem;
    opacity: 1;
}

@media (max-width: 430px) {
    .ses-opcao-relatorio,
    .ses-checkbox-relatorio {
        padding: .75rem;
    }

    .ses-periodo-toggle {
        gap: .75rem;
    }
}
</style>
