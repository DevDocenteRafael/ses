<template>
    <div class="container-fluid p-4">
        <h1 class="h3 fw-bold mb-1">Convites Recebidos</h1>
        <p class="text-secondary mb-4">Acompanhe as oportunidades enviadas diretamente por empresas interessadas em seu perfil.</p>

        <div v-if="carregando" class="text-center text-secondary py-5">
            <span class="spinner-border spinner-border-sm me-2"></span> Carregando convites...
        </div>

        <template v-else>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item" v-for="aba in abas" :key="aba.chave">
                            <button
                                type="button"
                                class="nav-link"
                                :class="{ active: abaAtiva === aba.chave }"
                                @click="abaAtiva = aba.chave"
                            >
                                {{ aba.label }}
                                <span v-if="aba.chave === 'pendentes'">({{ pendentes.length }})</span>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card-body">
                    <template v-if="abaAtiva === 'pendentes'">
                        <p v-if="!pendentes.length" class="text-secondary mb-0">Nenhum convite pendente.</p>

                        <div
                            v-for="convite in pendentes"
                            :key="convite.id"
                            class="border rounded p-3 mb-3 border-warning-subtle bg-warning-subtle bg-opacity-10"
                        >
                            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                                <div class="d-flex gap-3">
                                    <span class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0"
                                          style="width: 44px; height: 44px;">
                                        <i class="bi bi-briefcase"></i>
                                    </span>
                                    <div>
                                        <p class="fw-semibold mb-1">
                                            {{ convite.empresa?.razao_social }}
                                            <span class="badge text-bg-warning ms-1">Pendente</span>
                                        </p>
                                        <p class="fw-semibold mb-1">Vaga: {{ convite.vaga?.titulo }}</p>
                                        <p class="mb-0">{{ convite.descricao }}</p>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                    <div class="text-end me-2 d-none d-md-block">
                                        <p class="text-secondary small mb-0">
                                            <i class="bi bi-calendar3 me-1"></i>Recebido em: {{ formatarData(convite.data_envio) }}
                                        </p>
                                    </div>
                                    <button type="button" class="btn btn-primary btn-sm" :disabled="processando === convite.id" @click="aceitar(convite)">Aceitar</button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" :disabled="processando === convite.id" @click="recusar(convite)">Recusar</button>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><button type="button" class="dropdown-item" @click="arquivar(convite)">Arquivar</button></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template v-else-if="abaAtiva === 'respondidos'">
                        <p v-if="!respondidos.length" class="text-secondary mb-0">Nenhum convite respondido ainda.</p>
                        <div v-for="convite in respondidos" :key="convite.id" class="border rounded p-3 mb-3">
                            <p class="fw-semibold mb-1">
                                {{ convite.empresa?.razao_social }}
                                <span class="badge ms-1" :class="convite.status === 1 ? 'text-bg-success' : 'text-bg-secondary'">
                                    {{ convite.status === 1 ? 'Aceito' : 'Recusado' }}
                                </span>
                            </p>
                            <p class="mb-0">Vaga: {{ convite.vaga?.titulo }}</p>
                        </div>
                    </template>

                    <template v-else>
                        <p v-if="!arquivados.length" class="text-secondary mb-0">Nenhum convite arquivado.</p>
                        <div v-for="convite in arquivados" :key="convite.id" class="border rounded p-3 mb-3 text-secondary">
                            <p class="fw-semibold mb-1">{{ convite.empresa?.razao_social }}</p>
                            <p class="mb-0">Vaga: {{ convite.vaga?.titulo }}</p>
                        </div>
                    </template>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card text-white bg-primary border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h2 class="h6 fw-bold text-uppercase mb-2">Dica de Ouro</h2>
                            <p class="mb-2">
                                Interessado em mais propostas? Empresas costumam priorizar candidatos que respondem
                                convites em menos de 24 horas.
                            </p>
                            <p class="fw-semibold mb-0"><i class="bi bi-lightning-charge-fill me-1"></i>Destaque-se pela agilidade!</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h2 class="h6 fw-bold text-uppercase mb-3">Últimas Visualizações</h2>
                            <p v-if="!ultimasVisualizacoes.length" class="text-secondary small mb-0">
                                Ninguém visualizou seu perfil ainda.
                            </p>
                            <div v-for="(v, i) in ultimasVisualizacoes" :key="i" class="d-flex align-items-center gap-2 mb-3">
                                <span class="rounded d-flex align-items-center justify-content-center fw-semibold text-secondary bg-body-tertiary"
                                      style="width: 40px; height: 40px;">
                                    {{ iniciaisDe(v.empresa) }}
                                </span>
                                <div>
                                    <p class="fw-semibold mb-0">{{ v.empresa }}</p>
                                    <p class="text-secondary small mb-0">Visualizou há {{ v.tempo }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, reactive, ref, onMounted } from 'vue';
import { useAuthStore } from '../../../store/auth';
import alunosService from '../../../services/alunosServices';

const auth = useAuthStore();
const matricula = computed(() => auth.pessoa?.id_pessoa);

const abas = [
    { chave: 'pendentes', label: 'Pendentes' },
    { chave: 'respondidos', label: 'Respondidos' },
    { chave: 'arquivados', label: 'Arquivados' },
];

const abaAtiva = ref('pendentes');
const carregando = ref(true);
const processando = ref(null);

const pendentes = reactive([]);
const respondidos = reactive([]);
const arquivados = reactive([]);
const ultimasVisualizacoes = reactive([]);

function formatarData(data) {
    if (!data) return '-';
    return new Date(data).toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' });
}

function iniciaisDe(nome) {
    return (nome || '')
        .split(' ')
        .slice(0, 2)
        .map((p) => p[0])
        .join('')
        .toUpperCase();
}

async function carregar() {
    carregando.value = true;
    try {
        const [{ data: convites }, { data: dash }] = await Promise.all([
            alunosService.listarConvites(matricula.value),
            alunosService.dashboard(matricula.value),
        ]);

        pendentes.splice(0, pendentes.length, ...convites.filter((c) => c.status === 0));
        respondidos.splice(0, respondidos.length, ...convites.filter((c) => c.status === 1 || c.status === 2));
        arquivados.splice(0, arquivados.length, ...convites.filter((c) => c.status === 3));
        ultimasVisualizacoes.splice(0, ultimasVisualizacoes.length, ...dash.ultimasVisualizacoes);
    } finally {
        carregando.value = false;
    }
}

async function aceitar(convite) {
    await responder(convite, 1);
}

async function recusar(convite) {
    await responder(convite, 2);
}

async function arquivar(convite) {
    await responder(convite, 3);
}

async function responder(convite, status) {
    processando.value = convite.id;
    try {
        const { data: atualizado } = await alunosService.responderConvite(convite.id, status);

        pendentes.splice(pendentes.indexOf(convite), 1);
        if (status === 3) {
            arquivados.push(atualizado);
        } else {
            respondidos.push(atualizado);
        }
    } finally {
        processando.value = null;
    }
}

onMounted(carregar);
</script>
