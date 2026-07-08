<template>
    <div class="container-fluid p-4">
        <h1 class="h3 fw-bold mb-1">Convites Recebidos</h1>
        <p class="text-secondary mb-4">Acompanhe as oportunidades enviadas diretamente por empresas interessadas em seu perfil.</p>

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
                        <div class="d-flex align-items-start justify-content-between">
                            <div class="d-flex gap-3">
                                <span class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center shrink-0"
                                      style="width: 44px; height: 44px;">
                                    <i class="bi bi-briefcase"></i>
                                </span>
                                <div>
                                    <p class="fw-semibold mb-1">
                                        {{ convite.empresa }}
                                        <span class="badge text-bg-warning ms-1">Pendente</span>
                                    </p>
                                    <p class="fw-semibold mb-1">Vaga: {{ convite.vaga }}</p>
                                    <p class="mb-0">{{ convite.mensagem }}</p>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-2 shrink-0">
                                <div class="text-end me-2 d-none d-md-block">
                                    <p class="text-secondary small mb-0">
                                        <i class="bi bi-calendar3 me-1"></i>Recebido em: {{ convite.recebidoEm }}
                                    </p>
                                </div>
                                <button type="button" class="btn btn-primary btn-sm" @click="aceitar(convite)">Aceitar</button>
                                <button type="button" class="btn btn-outline-danger btn-sm" @click="recusar(convite)">Recusar</button>
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
                            {{ convite.empresa }}
                            <span class="badge ms-1" :class="convite.status === 'aceito' ? 'text-bg-success' : 'text-bg-secondary'">
                                {{ convite.status === 'aceito' ? 'Aceito' : 'Recusado' }}
                            </span>
                        </p>
                        <p class="mb-0">Vaga: {{ convite.vaga }}</p>
                    </div>
                </template>

                <template v-else>
                    <p v-if="!arquivados.length" class="text-secondary mb-0">Nenhum convite arquivado.</p>
                    <div v-for="convite in arquivados" :key="convite.id" class="border rounded p-3 mb-3 text-secondary">
                        <p class="fw-semibold mb-1">{{ convite.empresa }}</p>
                        <p class="mb-0">Vaga: {{ convite.vaga }}</p>
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
                        <div v-for="visualizacao in ultimasVisualizacoes" :key="visualizacao.empresa" class="d-flex align-items-center gap-2 mb-3">
                            <span class="rounded d-flex align-items-center justify-content-center fw-semibold text-secondary bg-body-tertiary"
                                  style="width: 40px; height: 40px;">
                                {{ visualizacao.iniciais }}
                            </span>
                            <div>
                                <p class="fw-semibold mb-0">{{ visualizacao.empresa }}</p>
                                <p class="text-secondary small mb-0">Visualizou há {{ visualizacao.tempo }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';

const abas = [
    { chave: 'pendentes', label: 'Pendentes' },
    { chave: 'respondidos', label: 'Respondidos' },
    { chave: 'arquivados', label: 'Arquivados' },
];

const abaAtiva = ref('pendentes');

// TODO: substituir pelos dados reais vindos de GET /api/convites (filtrado
// pelo candidato autenticado).
const pendentes = reactive([
    {
        id: 1,
        empresa: 'Tech Solutions DF',
        vaga: 'Estagiário de Desenvolvimento Front-end',
        mensagem: 'Olá Lucas, analisamos seu perfil no portal e ficamos impressionados com suas habilidades em JavaScript. Gostaríamos de te convidar para uma entrevista inicial...',
        recebidoEm: '16 de Março, 2026',
    },
]);

const respondidos = reactive([]);
const arquivados = reactive([]);

const ultimasVisualizacoes = reactive([
    { empresa: 'Inova Soft', iniciais: 'EA', tempo: '2 horas' },
    { empresa: 'Global Tech', iniciais: 'EB', tempo: '5 horas' },
]);

function aceitar(convite) {
    mover(convite, 'aceito');
}

function recusar(convite) {
    mover(convite, 'recusado');
}

function mover(convite, status) {
    // TODO: chamar API para atualizar o status do convite.
    pendentes.splice(pendentes.indexOf(convite), 1);
    respondidos.push({ ...convite, status });
}

function arquivar(convite) {
    // TODO: chamar API para arquivar o convite.
    pendentes.splice(pendentes.indexOf(convite), 1);
    arquivados.push(convite);
}
</script>
