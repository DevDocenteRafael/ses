<template>
    <div>
        <topbar titulo="Indicadores de Empregabilidade" subtitulo="Acompanhamento estratégico do Portal Senac (FR38)" />

        <div class="container-fluid p-4">
            <loading v-if="carregando && !dashboard" mensagem="Carregando indicadores..." />

            <div v-else-if="erro" class="alert alert-danger d-flex align-items-center justify-content-between gap-3">
                <span>{{ erro }}</span>
                <button type="button" class="btn btn-outline-danger btn-sm" @click="carregarIndicadores">
                    Tentar novamente
                </button>
            </div>

            <template v-else-if="dashboard">
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <p class="text-uppercase text-secondary small fw-semibold mb-1">Perfis Ativos</p>
                                <p class="fs-3 fw-bold mb-1">{{ formatarNumero(dashboard.perfisAtivos.total) }}</p>
                                <p v-if="dashboard.perfisAtivos.variacaoPercentualVsMesAnterior !== null" class="small mb-0"
                                   :class="dashboard.perfisAtivos.variacaoPercentualVsMesAnterior >= 0 ? 'text-success' : 'text-danger'">
                                    <i class="bi" :class="dashboard.perfisAtivos.variacaoPercentualVsMesAnterior >= 0 ? 'bi-arrow-up' : 'bi-arrow-down'"></i>
                                    {{ Math.abs(dashboard.perfisAtivos.variacaoPercentualVsMesAnterior) }}% vs mês anterior
                                </p>
                                <p v-else class="small text-secondary mb-0">{{ dashboard.perfisAtivos.subtitulo || 'Candidatos com status ativo' }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body d-flex align-items-start justify-content-between">
                                <div>
                                    <p class="text-uppercase text-secondary small fw-semibold mb-1">Contratados</p>
                                    <p class="fs-3 fw-bold mb-1">{{ formatarNumero(dashboard.contratados.ultimos30Dias) }}</p>
                                    <p class="small text-secondary mb-0">Últimos 30 dias</p>
                                </div>
                                <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 bg-primary-subtle text-primary" style="width: 44px; height: 44px;">
                                    <i class="bi bi-person-check"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <p class="text-uppercase text-secondary small fw-semibold mb-1">Acessos de Candidatos</p>
                                <p class="fs-3 fw-bold mb-1">{{ formatarNumero(dashboard.acessosCandidatos.ultimos30Dias) }}</p>
                                <p class="small text-secondary mb-0">Últimos 30 dias</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <p class="text-uppercase text-secondary small fw-semibold mb-1">Empresas Ativas</p>
                                <p class="fs-3 fw-bold mb-1">{{ formatarNumero(dashboard.empresasAtivas.total) }}</p>
                                <p class="small text-success mb-0">
                                    <i class="bi bi-graph-up-arrow"></i> {{ dashboard.empresasAtivas.engajamentoPercentual }}% de engajamento
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-lg-5">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h2 class="h6 fw-bold text-primary mb-3">Acessos por Área de Interesse</h2>
                                <p v-if="!dashboard.acessosPorSegmento.length" class="text-secondary small mb-0">
                                    Ainda não há visualizações de perfil registradas.
                                </p>
                                <canvas v-else ref="donutRef" height="260"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h2 class="h6 fw-bold text-primary mb-3">Engajamento de Empresas (Histórico)</h2>
                                <p v-if="!dashboard.visualizacoesPorMes.length && !dashboard.buscasPorMes.length" class="text-secondary small mb-0">
                                    Ainda não há dados suficientes nos últimos 6 meses.
                                </p>
                                <canvas v-else ref="linhaRef" height="260"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 fw-bold text-primary mb-3">Filtros Mais Acessados pelas Empresas</h2>

                        <p v-if="!dashboard.filtrosMaisAcessados.length" class="text-secondary small mb-0">
                            Nenhuma busca de talentos com filtros foi registrada ainda.
                        </p>

                        <div v-else class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr class="text-secondary small text-uppercase">
                                        <th>Filtro / Categoria</th>
                                        <th>Valor Filtrado</th>
                                        <th>Total de Buscas</th>
                                        <th>Última Pesquisa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in dashboard.filtrosMaisAcessados" :key="item.filtro + item.valor">
                                        <td class="fw-semibold">{{ item.filtro }}</td>
                                        <td><span class="badge text-bg-primary-subtle text-primary">{{ item.valor }}</span></td>
                                        <td>{{ item.totalBuscas }} pesquisa{{ item.totalBuscas === 1 ? '' : 's' }}</td>
                                        <td class="text-secondary">{{ item.ultimaPesquisa }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { onMounted, nextTick, ref, watch } from 'vue';
import Chart from 'chart.js/auto';
import topbar from '../../../components/common/header.vue';
import loading from '../../../components/common/loading.vue';
import { useAdminStore } from '../../../store/admin';

const admin = useAdminStore();
const dashboard = ref(null);
const carregando = ref(false);
const erro = ref(null);

const donutRef = ref(null);
const linhaRef = ref(null);
let donutChart = null;
let linhaChart = null;

const CORES = ['#004587', '#f5a623', '#2e7d5b', '#1a9ab0', '#8c8c88'];

function formatarNumero(valor) {
    return new Intl.NumberFormat('pt-BR').format(valor || 0);
}

function dashboardPadrao() {
    return {
        perfisAtivos: { total: 0, variacaoPercentualVsMesAnterior: null, subtitulo: 'Candidatos disponíveis pelo estado efetivo' },
        contratados: { ultimos30Dias: 0, periodo: 'Últimos 30 dias' },
        acessosCandidatos: { ultimos30Dias: 0, periodo: 'Últimos 30 dias' },
        empresasAtivas: { total: 0, deUmTotalDe: 0, engajamentoPercentual: 0 },
        acessosPorSegmento: [],
        visualizacoesPorMes: [],
        buscasPorMes: [],
        filtrosMaisAcessados: [],
        candidatosPorCurso: [],
    };
}

function normalizarDashboard(dados) {
    if (!dados || typeof dados !== 'object') {
        return null;
    }

    const base = dashboardPadrao();

    return {
        ...base,
        ...dados,
        perfisAtivos: { ...base.perfisAtivos, ...(dados.perfisAtivos || {}) },
        contratados: { ...base.contratados, ...(dados.contratados || {}) },
        acessosCandidatos: { ...base.acessosCandidatos, ...(dados.acessosCandidatos || {}) },
        empresasAtivas: { ...base.empresasAtivas, ...(dados.empresasAtivas || {}) },
        acessosPorSegmento: Array.isArray(dados.acessosPorSegmento) ? dados.acessosPorSegmento : [],
        visualizacoesPorMes: Array.isArray(dados.visualizacoesPorMes) ? dados.visualizacoesPorMes : [],
        buscasPorMes: Array.isArray(dados.buscasPorMes) ? dados.buscasPorMes : [],
        filtrosMaisAcessados: Array.isArray(dados.filtrosMaisAcessados) ? dados.filtrosMaisAcessados : [],
        candidatosPorCurso: Array.isArray(dados.candidatosPorCurso) ? dados.candidatosPorCurso : [],
    };
}

async function carregarIndicadores() {
    carregando.value = true;
    erro.value = null;

    try {
        await admin.carregarDashboard();

        if (admin.erro) {
            dashboard.value = null;
            erro.value = admin.erro;
            return;
        }

        dashboard.value = normalizarDashboard(admin.dashboard);

        if (!dashboard.value) {
            erro.value = 'Não foi possível carregar os indicadores.';
        }
    } catch (e) {
        dashboard.value = null;
        erro.value = e?.response?.data?.message || 'Não foi possível carregar os indicadores.';
    } finally {
        carregando.value = false;
    }
}

function formatarMes(chave) {
    if (!chave) return '';
    const [ano, mes] = chave.split('-');
    const nomes = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    return nomes[Number(mes) - 1] || chave;
}

function montarSerieMensal(pontos) {
    // Garante os últimos 6 meses no eixo, mesmo com meses zerados.
    const meses = [];
    const hoje = new Date();
    for (let i = 5; i >= 0; i--) {
        const d = new Date(hoje.getFullYear(), hoje.getMonth() - i, 1);
        meses.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
    }
    const porMes = Object.fromEntries(pontos.map((p) => [p.mes, p.total]));
    return meses.map((m) => porMes[m] || 0);
}

function montarGraficos() {
    if (!dashboard.value) return;

    if (dashboard.value.acessosPorSegmento.length && donutRef.value) {
        donutChart?.destroy();
        donutChart = new Chart(donutRef.value, {
            type: 'doughnut',
            data: {
                labels: dashboard.value.acessosPorSegmento.map((s) => s.segmento || 'Outros'),
                datasets: [{
                    data: dashboard.value.acessosPorSegmento.map((s) => s.total),
                    backgroundColor: CORES,
                    borderWidth: 0,
                }],
            },
            options: {
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
            },
        });
    }

    if ((dashboard.value.visualizacoesPorMes.length || dashboard.value.buscasPorMes.length) && linhaRef.value) {
        const meses = [];
        const hoje = new Date();
        for (let i = 5; i >= 0; i--) {
            const d = new Date(hoje.getFullYear(), hoje.getMonth() - i, 1);
            meses.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
        }

        linhaChart?.destroy();
        linhaChart = new Chart(linhaRef.value, {
            type: 'line',
            data: {
                labels: meses.map(formatarMes),
                datasets: [
                    {
                        label: 'Buscas Realizadas',
                        data: montarSerieMensal(dashboard.value.buscasPorMes),
                        borderColor: '#004587',
                        backgroundColor: 'rgba(0,69,135,0.08)',
                        tension: 0.3,
                        fill: true,
                    },
                    {
                        label: 'Visualizações de Perfil',
                        data: montarSerieMensal(dashboard.value.visualizacoesPorMes),
                        borderColor: '#f5a623',
                        backgroundColor: 'rgba(245,166,35,0.08)',
                        tension: 0.3,
                        fill: true,
                    },
                ],
            },
            options: {
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
                scales: { y: { beginAtZero: true } },
            },
        });
    }
}

watch(dashboard, () => nextTick(montarGraficos));

onMounted(carregarIndicadores);
</script>
