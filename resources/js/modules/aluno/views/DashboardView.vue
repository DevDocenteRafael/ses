<template>
    <div class="container-fluid p-4">
        <div class="d-flex align-items-start justify-content-between mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Painel do Aluno</h1>
                <p class="text-secondary mb-0">
                    Bem-vindo, {{ auth.pessoa?.nome || 'Aluno' }}! Acompanhe seu progresso profissional.
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="text-end d-none d-sm-block">
                    <p class="fw-semibold mb-0">{{ auth.pessoa?.nome || 'Aluno' }}</p>
                    <p class="text-secondary small mb-0">Aluno</p>
                </div>
                <span class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-semibold"
                      style="width: 42px; height: 42px;">
                    {{ iniciais }}
                </span>
            </div>
        </div>

        <div v-if="carregando" class="text-center text-secondary py-5">
            <span class="spinner-border spinner-border-sm me-2"></span> Carregando painel...
        </div>

        <div v-else-if="erro" class="alert alert-danger">{{ erro }}</div>

        <template v-else>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <CardIndicador
                        titulo="Visualizações do Perfil"
                        :valor="resumo.visualizacoes"
                        icone="bi-eye"
                        variante="primary"
                    />
                </div>
                <div class="col-md-4">
                    <CardIndicador
                        titulo="Convites Recebidos"
                        :valor="resumo.convitesPendentes"
                        :subtitulo="resumo.convitesPendentes > 0 ? 'Nova oportunidade!' : 'Nenhum pendente'"
                        subtitulo-class="text-warning"
                        icone="bi-envelope"
                        variante="warning"
                    />
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex align-items-start justify-content-between">
                            <div class="w-100">
                                <p class="text-uppercase text-secondary small fw-semibold mb-1">Status do Perfil</p>
                                <p class="fs-4 fw-bold mb-2">{{ resumo.perfilCompleto }}% Completo</p>
                                <div class="progress" style="height: 6px;">
                                    <div
                                        class="progress-bar bg-primary"
                                        :style="{ width: resumo.perfilCompleto + '%' }"
                                    ></div>
                                </div>
                            </div>
                            <span class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0 ms-3"
                                  style="width: 44px; height: 44px;">
                                <i class="bi bi-check-circle"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h2 class="h6 fw-bold text-primary mb-0">Convites Recentes (FR9)</h2>
                                <router-link :to="{ name: 'aluno.convites' }" class="small">Ver todos</router-link>
                            </div>

                            <p v-if="!convitesPendentes.length" class="text-secondary mb-0">
                                Nenhum convite pendente no momento.
                            </p>

                            <div v-for="convite in convitesPendentes" :key="convite.id" class="border-bottom pb-3 mb-3">
                                <div class="d-flex align-items-start justify-content-between">
                                    <div>
                                        <p class="fw-semibold mb-1">{{ convite.empresa?.razao_social }}</p>
                                        <p class="mb-1">{{ convite.descricao }}</p>
                                    </div>
                                    <span class="badge text-bg-warning">Pendente</span>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <button type="button" class="btn btn-primary btn-sm" :disabled="processando === convite.id" @click="aceitar(convite)">
                                        Aceitar Convite
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" :disabled="processando === convite.id" @click="recusar(convite)">
                                        Recusar
                                    </button>
                                </div>
                                <p class="text-secondary small mt-2 mb-0">
                                    Recebido em: {{ formatarData(convite.data_envio) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card text-white bg-primary border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h2 class="h6 fw-bold mb-2">Dica Senac</h2>
                            <p class="small mb-3">
                                Perfis que adicionam links do LinkedIn e portfólio têm 40% mais chances de serem
                                encontrados por empresas.
                            </p>
                            <router-link :to="{ name: 'aluno.perfil' }" class="btn btn-light btn-sm w-100">
                                Atualizar Perfil
                            </router-link>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h2 class="h6 fw-bold mb-2">Últimas Visualizações</h2>
                            <p v-if="!resumo.ultimasVisualizacoes.length" class="text-secondary small mb-0">
                                Ninguém visualizou seu perfil ainda.
                            </p>
                            <div v-for="(v, i) in resumo.ultimasVisualizacoes" :key="i" class="d-flex justify-content-between small mb-2">
                                <span class="fw-semibold">{{ v.empresa }}</span>
                                <span class="text-secondary">{{ v.tempo }}</span>
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
import CardIndicador from '../../../components/common/cardIndicador.vue';
import alunosService from '../../../services/alunosServices';

const auth = useAuthStore();

const iniciais = computed(() => {
    const nome = auth.pessoa?.nome || 'Aluno';
    return nome
        .split(' ')
        .slice(0, 2)
        .map((parte) => parte[0])
        .join('')
        .toUpperCase();
});

const matricula = computed(() => auth.pessoa?.id_pessoa);

const carregando = ref(true);
const erro = ref('');
const processando = ref(null);

const resumo = reactive({
    visualizacoes: 0,
    convitesPendentes: 0,
    perfilCompleto: 0,
    ultimasVisualizacoes: [],
});

const convitesPendentes = reactive([]);

function formatarData(data) {
    if (!data) return '-';
    return new Date(data).toLocaleDateString('pt-BR');
}

async function carregar() {
    carregando.value = true;
    erro.value = '';
    try {
        const [{ data: dash }, { data: convites }] = await Promise.all([
            alunosService.dashboard(matricula.value),
            alunosService.listarConvites(matricula.value, { status: 0 }),
        ]);

        Object.assign(resumo, dash);
        convitesPendentes.splice(0, convitesPendentes.length, ...convites);
    } catch (e) {
        erro.value = 'Nao foi possivel carregar o painel. Tente novamente.';
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

async function responder(convite, status) {
    processando.value = convite.id;
    try {
        await alunosService.responderConvite(convite.id, status);
        convitesPendentes.splice(convitesPendentes.indexOf(convite), 1);
        resumo.convitesPendentes = Math.max(0, resumo.convitesPendentes - 1);
    } finally {
        processando.value = null;
    }
}

onMounted(carregar);
</script>
