<template>
    <div>
        <topbar titulo="Gestão dos Candidatos" subtitulo="Controle de acesso e sincronização de candidatos (FR37)">
            <template #acoes>
                <button class="btn btn-outline-primary" :disabled="admin.carregando" @click="sincronizar">
                    <i class="bi bi-arrow-repeat me-1"></i>
                    {{ admin.carregando ? 'Sincronizando...' : 'Sincronizar SIG' }}
                </button>
            </template>
        </topbar>

        <div class="container-fluid p-4">
            <loading v-if="admin.carregando && !carregouUmaVez" mensagem="Carregando candidatos..." />

            <div v-else class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <h2 class="h6 fw-bold text-primary mb-0">Candidatos Cadastrados</h2>
                        <div class="input-group" style="max-width: 320px;">
                            <input
                                v-model="busca"
                                type="text"
                                class="form-control"
                                placeholder="Filtrar por nome ou CPF"
                            >
                            <span class="input-group-text bg-primary text-white"><i class="bi bi-search"></i></span>
                        </div>
                    </div>

                    <p v-if="!alunosFiltrados.length" class="text-secondary small mb-0">
                        Nenhum candidato encontrado.
                    </p>

                    <div v-else class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr class="text-secondary small text-uppercase">
                                    <th>Candidato</th>
                                    <th>CPF</th>
                                    <th>Curso / Unidade</th>
                                    <th>Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="aluno in alunosFiltrados" :key="aluno.matricula">
                                    <td>
                                        <p class="fw-semibold mb-0">{{ aluno.pessoa?.nome }}</p>
                                        <p class="text-secondary small mb-0">E-mail: {{ aluno.pessoa?.email || '—' }}</p>
                                    </td>
                                    <td>{{ aluno.cpf }}</td>
                                    <td>
                                        <p class="mb-0">{{ aluno.dadosAcademicos?.[0]?.curso || '—' }}</p>
                                        <p class="text-secondary small mb-0">{{ aluno.dadosAcademicos?.[0]?.unidade || '—' }}</p>
                                    </td>
                                    <td>
                                        <span
                                            class="badge"
                                            :class="aluno.status
                                                ? 'text-bg-success-subtle text-success-emphasis'
                                                : 'text-bg-danger-subtle text-danger-emphasis'"
                                        >
                                            {{ aluno.status ? 'Liberado' : 'Bloqueado' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <button
                                            class="btn btn-sm"
                                            :class="aluno.status ? 'btn-outline-danger' : 'btn-success'"
                                            :disabled="alterando === aluno.matricula"
                                            @click="alternarStatus(aluno)"
                                        >
                                            {{ aluno.status ? 'Bloquear Acesso' : 'Liberar Acesso' }}
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import topbar from '../../../components/common/header.vue';
import loading from '../../../components/common/loading.vue';
import { useAdminStore } from '../../../store/admin';

const admin = useAdminStore();
const carregouUmaVez = ref(false);
const busca = ref('');
const alterando = ref(null);

onMounted(async () => {
    await admin.carregarAlunos();
    carregouUmaVez.value = true;
});

// "Sincronizar SIG": no protótipo simula uma re-importação de candidatos.
// TODO(back-end): expor um endpoint de sincronização em lote com o SIG;
// hoje só existe POST /administrativo/sincronizar-alunos para um registro
// por vez. Por enquanto, o botão apenas atualiza a lista com os dados mais
// recentes já cadastrados.
async function sincronizar() {
    await admin.carregarAlunos();
}

async function alternarStatus(aluno) {
    alterando.value = aluno.matricula;
    try {
        await admin.atualizarStatusAluno(aluno.matricula, !aluno.status);
    } finally {
        alterando.value = null;
    }
}

const alunosFiltrados = computed(() => {
    const termo = busca.value.trim().toLowerCase();
    if (!termo) return admin.alunos;
    return admin.alunos.filter(
        (a) => a.pessoa?.nome?.toLowerCase().includes(termo) || a.cpf?.includes(termo),
    );
});
</script>
