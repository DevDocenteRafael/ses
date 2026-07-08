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

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <CardIndicador
                    titulo="Visualizações do Perfil"
                    :valor="resumo.visualizacoes"
                    subtitulo="+3 esta semana"
                    icone="bi-eye"
                    variante="primary"
                />
            </div>
            <div class="col-md-4">
                <CardIndicador
                    titulo="Convites Recebidos"
                    :valor="convitesPendentes.length"
                    subtitulo="Nova oportunidade!"
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
                                    <p class="fw-semibold mb-1">{{ convite.empresa }}</p>
                                    <p class="mb-1">
                                        Convidamos você para participar do processo seletivo para a vaga de
                                        <strong>{{ convite.vaga }}</strong>.
                                    </p>
                                </div>
                                <span class="badge text-bg-warning">Pendente</span>
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-primary btn-sm" @click="aceitar(convite)">
                                    Aceitar Convite
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm" @click="recusar(convite)">
                                    Recusar
                                </button>
                            </div>
                            <p class="text-secondary small mt-2 mb-0">
                                Recebido em: {{ convite.recebidoEm }}
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
                        <h2 class="h6 fw-bold mb-2">Continue Evoluindo</h2>
                        <p class="text-secondary small mb-3">
                            Confira os novos cursos de especialização disponíveis para você no Senac DF.
                        </p>
                        <button type="button" class="btn btn-outline-primary btn-sm w-100">Ver Cursos</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { useAuthStore } from '../../../store/auth';
import CardIndicador from '../../../components/common/cardIndicador.vue';

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

// TODO: substituir por dados vindos da API (GET /api/candidatos/{matricula}/dashboard
// ou endpoint equivalente ainda não implementado no backend).
const resumo = reactive({
    visualizacoes: 12,
    perfilCompleto: 95,
});

const convitesPendentes = reactive([
    {
        id: 1,
        empresa: 'Tech Solutions DF',
        vaga: 'Estagiário de Desenvolvimento',
        recebidoEm: '16/03/2026',
    },
]);

function aceitar(convite) {
    // TODO: chamar API para aceitar o convite.
    convitesPendentes.splice(convitesPendentes.indexOf(convite), 1);
}

function recusar(convite) {
    // TODO: chamar API para recusar o convite.
    convitesPendentes.splice(convitesPendentes.indexOf(convite), 1);
}
</script>
