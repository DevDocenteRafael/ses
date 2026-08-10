<template>
    <div>
        <topbar
            titulo="Indicadores de Empregabilidade"
            subtitulo="Acompanhamento estratégico do Portal Senac (FR38)"
        />

        <div class="container-fluid p-4">
            <loading v-if="admin.carregando && !carregouUmaVez" mensagem="Carregando indicadores..." />

            <template v-else-if="dash">
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-4">
                        <cardIndicador
                            titulo="Perfis Ativos"
                            :valor="dash.perfisAtivos.total"
                            :subtitulo="variacaoPerfisLabel"
                            :subtituloClass="variacaoPerfisClass"
                            icone="bi-person-check"
                            variante="primary"
                        />
                    </div>
                    <div class="col-6 col-lg-4">
                        <cardIndicador
                            titulo="Acessos de Candidatos"
                            :valor="dash.acessosCandidatos.ultimos30Dias"
                            subtitulo="Últimos 30 dias"
                            icone="bi-eye"
                            variante="info"
                        />
                    </div>
                    <div class="col-6 col-lg-4">
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

                <p class="text-secondary small mt-3 mb-0">
                    "Buscas Realizadas" e "Filtros Mais Acessados pelas Empresas" dependem de um registro de
                    buscas em Buscar Talentos que ainda não existe no sistema — por isso não aparecem aqui.
                </p>
            </template>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import topbar from '../../../components/common/header.vue';
import cardIndicador from '../../../components/common/cardIndicador.vue';
import loading from '../../../components/common/loading.vue';
import { useAdminStore } from '../../../store/admin';

const admin = useAdminStore();
const carregouUmaVez = ref(false);

onMounted(async () => {
    await admin.carregarDashboard();
    carregouUmaVez.value = true;
});

const dash = computed(() => admin.dashboard);

// ── Card "Perfis Ativos" ────────────────────────────────────────
const variacaoPerfisLabel = computed(() => {
    const v = dash.value?.perfisAtivos?.variacaoPercentualVsMesAnterior;
    if (v === null || v === undefined) return 'Sem dados do mês anterior';
    const seta = v >= 0 ? '↑' : '↓';
    return `${seta} ${Math.abs(v)}% vs mês anterior`;
});
const variacaoPerfisClass = computed(() => {
    const v = dash.value?.perfisAtivos?.variacaoPercentualVsMesAnterior;
    if (v === null || v === undefined) return 'text-secondary';
    return v >= 0 ? 'text-success' : 'text-danger';
});

// ── Donut: acessos por segmento/área de interesse ────────────────
const paleta = ['#142a4d', '#f5a623', '#2f9e44', '#1c7ed6', '#868e96', '#e8590c'];
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
    fill: #142a4d;
}
</style>
