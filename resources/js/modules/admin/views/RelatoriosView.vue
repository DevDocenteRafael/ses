<template>
    <div>
        <topbar titulo="Indicadores de Empregabilidade" subtitulo="Acompanhamento estratégico do Portal Senac (FR38)" />

        <div class="container-fluid p-4">
            <loading v-if="admin.carregando && !dashboard" mensagem="Carregando indicadores..." />

            <div v-else-if="admin.erro" class="alert alert-danger">{{ admin.erro }}</div>

            <template v-else-if="dashboard">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <p class="text-uppercase text-secondary small fw-semibold mb-1">Perfis Ativos</p>
                                <p class="fs-3 fw-bold mb-1">{{ formatarNumero(dashboard.perfisAtivos.total) }}</p>
                                <p v-if="dashboard.perfisAtivos.variacaoPercentualVsMesAnterior !== null" class="small mb-0"
                                   :class="dashboard.perfisAtivos.variacaoPercentualVsMesAnterior >= 0 ? 'text-success' : 'text-danger'">
                                    <i class="bi" :class="dashboard.perfisAtivos.variacaoPercentualVsMesAnterior >= 0 ? 'bi-arrow-up' : 'bi-arrow-down'"></i>
                                    {{ Math.abs(dashboard.perfisAtivos.variacaoPercentualVsMesAnterior) }}% vs mês anterior
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <p class="text-uppercase text-secondary small fw-semibold mb-1">Acessos de Candidatos</p>
                                <p class="fs-3 fw-bold mb-1">{{ formatarNumero(dashboard.acessosCandidatos.ultimos30Dias) }}</p>
                                <p class="small text-secondary mb-0">Últimos 30 dias</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
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

const donutRef = ref(null);
const linhaRef = ref(null);
let donutChart = null;
let linhaChart = null;

const CORES = ['#0d3b66', '#f5a623', '#2e7d5b', '#1a9ab0', '#8c8c88'];

function formatarNumero(valor) {
    return new Intl.NumberFormat('pt-BR').format(valor || 0);
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
                        borderColor: '#0d3b66',
                        backgroundColor: 'rgba(13,59,102,0.08)',
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

onMounted(async () => {
    await admin.carregarDashboard();
    dashboard.value = admin.dashboard;
});
</script>
