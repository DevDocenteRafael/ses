<template>
    <div>
        <header class="bg-primary text-white px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
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

        <div class="d-flex">
            <aside class="bg-white border-end p-4 d-none d-lg-block" style="width: 300px; min-height: calc(100vh - 64px); flex: 0 0 300px;">
                <h2 class="h6 fw-bold mb-4">Filtros Inteligentes</h2>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-secondary text-uppercase">Filtros Principais</label>
                    <div class="mb-2">
                        <label class="form-label small text-secondary mb-1">Segmento</label>
                        <select v-model="filtros.segmento" class="form-select form-select-sm">
                            <option value="">Todos os Segmentos</option>
                            <option v-for="s in segmentos" :key="s.value" :value="s.value">{{ s.label }}</option>
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
                    <label class="form-label small fw-bold text-secondary text-uppercase">Contratação (FR18)</label>
                    <div class="form-check small mb-1">
                        <input v-model="filtros.clt" class="form-check-input" type="checkbox" id="fCLT">
                        <label class="form-check-label" for="fCLT">CLT / Efetivo</label>
                    </div>
                    <div class="form-check small mb-1">
                        <input v-model="filtros.estagio" class="form-check-input" type="checkbox" id="fEstagio">
                        <label class="form-check-label" for="fEstagio">Estágio</label>
                    </div>
                    <div class="form-check small mb-1">
                        <input v-model="filtros.jovemAprendiz" class="form-check-input" type="checkbox" id="fAprendiz">
                        <label class="form-check-label" for="fAprendiz">Jovem Aprendiz</label>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-secondary text-uppercase">Disponibilidade (FR17)</label>
                    <select v-model="filtros.disponibilidade" class="form-select form-select-sm">
                        <option value="">Qualquer Horário</option>
                        <option value="Manhã">Manhã</option>
                        <option value="Tarde">Tarde</option>
                        <option value="Noite">Noite</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-secondary text-uppercase">Habilidades Técnicas (FR16)</label>
                    <input
                        v-model="novaHabilidade"
                        type="text"
                        class="form-control form-control-sm mb-2"
                        placeholder="Digite uma competência..."
                        @keydown.enter.prevent="adicionarHabilidade"
                    >
                    <div class="d-flex flex-wrap gap-1">
                        <span v-for="(h, i) in filtros.habilidades" :key="h" class="badge bg-light text-dark border" style="font-size: 10px; cursor: pointer;" @click="filtros.habilidades.splice(i, 1)">
                            {{ h }} ×
                        </span>
                    </div>
                </div>

                <button type="button" class="btn btn-primary w-100" :disabled="buscando" @click="buscar">
                    <span v-if="buscando" class="spinner-border spinner-border-sm me-1"></span>
                    Aplicar Filtros
                </button>
                <button type="button" class="btn btn-link btn-sm w-100 mt-2 text-secondary" @click="limparFiltros">
                    Limpar Tudo
                </button>
            </aside>

            <main class="flex-grow-1 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="h5 mb-0">
                        Resultados da Busca
                        <span class="badge bg-secondary ms-2">{{ candidatos.length }} candidato{{ candidatos.length === 1 ? '' : 's' }}</span>
                    </h2>
                </div>

                <div v-if="carregando" class="text-center text-secondary py-5">
                    <span class="spinner-border spinner-border-sm me-2"></span> Carregando candidatos...
                </div>

                <p v-else-if="!candidatos.length" class="text-secondary text-center py-5">
                    Nenhum candidato encontrado com os filtros selecionados.
                </p>

                <div v-else class="row g-3">
                    <div v-for="c in candidatos" :key="c.matricula" class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <div class="row align-items-center g-3">
                                    <div class="col-auto">
                                        <span class="rounded-3 bg-primary text-white d-flex align-items-center justify-content-center fw-semibold"
                                              style="width: 80px; height: 80px; font-size: 1.4rem;">
                                            {{ iniciaisDe(c.pessoa?.nome) }}
                                        </span>
                                    </div>
                                    <div class="col">
                                        <h3 class="h5 mb-1">{{ c.pessoa?.nome }}</h3>
                                        <p class="mb-2 text-secondary small">
                                            <i class="bi bi-mortarboard me-1"></i>
                                            {{ cursoPrincipal(c)?.curso }} | {{ cursoPrincipal(c)?.unidade }}
                                        </p>
                                        <div class="d-flex flex-wrap gap-2 mb-3">
                                            <span v-for="h in (c.informacoes_profissionais?.habilidades || [])" :key="h" class="badge bg-light text-primary border">
                                                {{ h }}
                                            </span>
                                        </div>
                                        <div class="d-flex align-items-center gap-3">
                                            <small class="text-secondary"><i class="bi bi-geo-alt me-1"></i>{{ c.preferencias_de_trabalho?.regiao_administrativa }}, DF</small>
                                            <small class="text-secondary"><i class="bi bi-clock me-1"></i>Disponível: {{ c.preferencias_de_trabalho?.disponibilidade_de_horario || '-' }}</small>
                                        </div>
                                    </div>
                                    <div class="col-auto border-start ps-4">
                                        <router-link :to="{ name: 'empresa.candidato', params: { matricula: c.matricula } }" class="btn btn-primary d-block">
                                            Ver Perfil Completo
                                        </router-link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../../store/auth';
import empresaService from '../../../services/empresaServices';

const auth = useAuthStore();
const router = useRouter();

const segmentos = [
    { value: 'beleza-e-cuidado-pessoal', label: 'Beleza e Cuidado Pessoal' },
    { value: 'economia-criativa-e-design', label: 'Economia Criativa e Design' },
    { value: 'gastronomia-e-turismo', label: 'Gastronomia e Turismo' },
    { value: 'gestao-de-empresas-e-negocios', label: 'Gestão, Comércio e Moda' },
    { value: 'moda-e-costura', label: 'Moda e Costura' },
    { value: 'saude-massagem-e-estetica', label: 'Saúde, Massagem e Estética' },
    { value: 'seguranca-no-trabalho', label: 'Segurança no Trabalho' },
    { value: 'tecnologia-e-games', label: 'Tecnologia e Economia Criativa' },
];

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
    jovemAprendiz: false,
    disponibilidade: '',
    habilidades: [],
});

const novaHabilidade = ref('');
const carregando = ref(true);
const buscando = ref(false);
const candidatos = ref([]);

const iniciais = computed(() => iniciaisDe(auth.pessoa?.nome || 'Empresa'));

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

function adicionarHabilidade() {
    const valor = novaHabilidade.value.trim();
    if (valor && !filtros.habilidades.includes(valor)) {
        filtros.habilidades.push(valor);
    }
    novaHabilidade.value = '';
}

function tipoContratacaoBitmask() {
    return (filtros.clt ? 1 : 0) + (filtros.estagio ? 2 : 0) + (filtros.jovemAprendiz ? 4 : 0);
}

async function buscar() {
    buscando.value = true;
    carregando.value = true;
    try {
        const params = {};
        if (filtros.segmento) params.segmento = filtros.segmento;
        if (filtros.tipo_curso) params.tipo_curso = filtros.tipo_curso;
        if (filtros.disponibilidade) params.disponibilidade = filtros.disponibilidade;
        if (filtros.habilidades.length) params.habilidades = filtros.habilidades;
        const mascara = tipoContratacaoBitmask();
        if (mascara) params.tipo_contratacao = mascara;

        const { data } = await empresaService.buscarTalentos(params);
        candidatos.value = data;
    } finally {
        buscando.value = false;
        carregando.value = false;
    }
}

function limparFiltros() {
    filtros.segmento = '';
    filtros.tipo_curso = '';
    filtros.clt = false;
    filtros.estagio = false;
    filtros.jovemAprendiz = false;
    filtros.disponibilidade = '';
    filtros.habilidades = [];
    buscar();
}

async function sair() {
    await auth.logout();
    router.push({ name: 'login' });
}

onMounted(buscar);
</script>
