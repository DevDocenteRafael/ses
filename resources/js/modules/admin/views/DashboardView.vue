<template>
    <div>
        <topbar
            titulo="Indicadores de Empregabilidade"
            subtitulo="Acompanhamento estratégico do Portal Senac"
        />

        <div class="container-fluid p-4">
            <loading v-if="admin.carregando && !carregouUmaVez" mensagem="Carregando indicadores..." />

            <template v-else>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3" v-for="card in indicadores" :key="card.titulo">
                        <cardIndicador v-bind="card" />
                    </div>
                    <div class="col-6 col-lg-3">
                        <cardIndicador
                            titulo="Convites Enviados"
                            :valor="admin.convites.length"
                            :subtitulo="`${convitesAceitos} aceitos`"
                            icone="bi-envelope-paper"
                            variante="secondary"
                        />
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h2 class="h6 fw-bold mb-3">Histórico de Engajamento</h2>
                                <p class="text-secondary small mb-3">
                                    Crescimento acumulado de perfis de alunos cadastrados no portal.
                                </p>

                                <p v-if="!serieEngajamento.length" class="text-secondary small mb-0">
                                    Ainda não há dados suficientes para montar o histórico.
                                </p>
                                <svg v-else viewBox="0 0 700 320" class="w-100" role="img" aria-label="Histórico de engajamento">
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

                                    <polyline :points="pontosLinha" fill="none" stroke="#142a4d" stroke-width="3" />

                                    <circle
                                        v-for="(p, i) in pontosCirculo"
                                        :key="i"
                                        :cx="p.x" :cy="p.y" r="4"
                                        fill="#142a4d"
                                    />

                                    <text
                                        v-for="(m, i) in serieEngajamento"
                                        :key="`mes-${i}`"
                                        :x="pontosCirculo[i].x" y="310" text-anchor="middle"
                                        class="ses-chart-label"
                                    >{{ m.label }}</text>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h2 class="h6 fw-bold mb-3">Vagas por Área</h2>

                                <p v-if="!vagasPorArea.length" class="text-secondary small mb-0">
                                    Nenhuma vaga cadastrada ainda.
                                </p>

                                <template v-else>
                                    <svg viewBox="0 0 200 200" class="mx-auto d-block mb-3" style="max-width: 220px;" role="img" aria-label="Vagas por área">
                                        <circle
                                            v-for="fatia in fatiasDonut"
                                            :key="fatia.area"
                                            cx="100" cy="100" r="70"
                                            fill="none"
                                            :stroke="fatia.cor"
                                            stroke-width="34"
                                            :stroke-dasharray="`${fatia.tamanho} ${circunferencia - fatia.tamanho}`"
                                            :stroke-dashoffset="fatia.offset"
                                            transform="rotate(-90 100 100)"
                                        />
                                        <text x="100" y="95" text-anchor="middle" class="ses-donut-total">{{ admin.vagas.length }}</text>
                                        <text x="100" y="113" text-anchor="middle" class="ses-chart-label">vagas</text>
                                    </svg>

                                    <div v-for="fatia in fatiasDonut" :key="`legenda-${fatia.area}`" class="d-flex align-items-center justify-content-between small mb-1">
                                        <span class="d-flex align-items-center gap-2">
                                            <span class="rounded-circle d-inline-block" :style="{ width: '10px', height: '10px', backgroundColor: fatia.cor }"></span>
                                            {{ fatia.area }}
                                        </span>
                                        <span class="text-secondary">{{ fatia.total }}</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
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
    await Promise.all([
        admin.carregarAlunos(),
        admin.carregarEmpresas(),
        admin.carregarVagas(),
        admin.carregarConvites(),
    ]);
    carregouUmaVez.value = true;
});

// ── Indicadores ───────────────────────────────────────────────
const STATUS_ACEITO = 1;

const convitesAceitos = computed(
    () => admin.convites.filter((c) => c.status === STATUS_ACEITO).length,
);

const indicadores = computed(() => [
    {
        titulo: 'Perfis Ativos',
        valor: admin.alunos.length,
        subtitulo: `${admin.alunos.filter((a) => a.status).length} com status ativo`,
        icone: 'bi-person-check',
        variante: 'primary',
    },
    {
        titulo: 'Empresas Cadastradas',
        valor: admin.empresas.length,
        subtitulo: admin.empresasPendentes.length
            ? `${admin.empresasPendentes.length} aguardando revisão`
            : 'Nenhuma pendência',
        subtituloClass: admin.empresasPendentes.length ? 'text-warning' : 'text-success',
        icone: 'bi-building',
        variante: 'success',
    },
    {
        titulo: 'Vagas Publicadas',
        valor: admin.vagas.length,
        subtitulo: `${admin.vagas.filter((v) => v.status).length} ativas no momento`,
        icone: 'bi-briefcase',
        variante: 'info',
    },
]);

// ── Gráfico de linha: crescimento de perfis por mês ─────────────
const serieEngajamento = computed(() => {
    const porMes = {};
    admin.alunos.forEach((a) => {
        const data = a.pessoa?.data_cadastro;
        if (!data) return;
        const chave = data.slice(0, 7); // YYYY-MM
        porMes[chave] = (porMes[chave] || 0) + 1;
    });

    const chaves = Object.keys(porMes).sort();
    let acumulado = 0;
    return chaves.slice(-6).map((chave) => {
        acumulado += porMes[chave];
        const [ano, mes] = chave.split('-');
        const nomesMes = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        return { label: `${nomesMes[Number(mes) - 1]}/${ano.slice(2)}`, valor: acumulado };
    });
});

const maiorValor = computed(() => Math.max(1, ...serieEngajamento.value.map((s) => s.valor)));

const linhasGrade = computed(() => {
    const passos = 4;
    return Array.from({ length: passos + 1 }, (_, i) => {
        const valor = Math.round((maiorValor.value / passos) * (passos - i));
        return { y: 30 + (260 / passos) * i, valor };
    });
});

const pontosCirculo = computed(() => {
    const n = serieEngajamento.value.length;
    if (n <= 1) return serieEngajamento.value.map(() => ({ x: 365, y: 290 }));
    return serieEngajamento.value.map((s, i) => ({
        x: 50 + (630 / (n - 1)) * i,
        y: 290 - (s.valor / maiorValor.value) * 260,
    }));
});

const pontosLinha = computed(() => pontosCirculo.value.map((p) => `${p.x},${p.y}`).join(' '));

// ── Donut: vagas por área ───────────────────────────────────────
const paleta = ['#142a4d', '#f5a623', '#2f9e44', '#1c7ed6', '#868e96', '#e8590c'];
const circunferencia = 2 * Math.PI * 70;

const vagasPorArea = computed(() => {
    const contagem = {};
    admin.vagas.forEach((v) => {
        const area = v.area || 'Outras';
        contagem[area] = (contagem[area] || 0) + 1;
    });
    return Object.entries(contagem)
        .map(([area, total]) => ({ area, total }))
        .sort((a, b) => b.total - a.total);
});

const fatiasDonut = computed(() => {
    let acumulado = 0;
    return vagasPorArea.value.map((item, i) => {
        const tamanho = (item.total / admin.vagas.length) * circunferencia;
        const fatia = {
            ...item,
            cor: paleta[i % paleta.length],
            tamanho,
            offset: -acumulado,
        };
        acumulado += tamanho;
        return fatia;
    });
});
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
