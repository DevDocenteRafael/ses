<template>
    <div>
        <topbar titulo="Gestão de Talentos" subtitulo="Visualização e correção de inconsistências">
            <template #acoes>
                <button class="btn btn-outline-primary" :disabled="admin.carregando" @click="sincronizar">
                    <i class="bi bi-arrow-repeat me-1"></i>
                    {{ admin.carregando ? 'Sincronizando...' : 'Sincronizar SIG' }}
                </button>
            </template>
        </topbar>

        <div class="container-fluid p-4">
            <loading v-if="admin.carregando && !carregouUmaVez" mensagem="Carregando alunos..." />

            <div v-else class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <h2 class="h6 fw-bold mb-0">Alunos Migrados</h2>
                        <div class="d-flex gap-2">
                            <input
                                v-model="busca"
                                type="text"
                                class="form-control"
                                placeholder="Filtrar por nome ou CPF"
                                style="max-width: 240px;"
                            >
                            <select v-model="unidadeSelecionada" class="form-select" style="max-width: 200px;">
                                <option value="">Todas as unidades</option>
                                <option v-for="u in unidades" :key="u" :value="u">{{ u }}</option>
                            </select>
                        </div>
                    </div>

                    <p v-if="!alunosFiltrados.length" class="text-secondary small mb-0">
                        Nenhum aluno encontrado.
                    </p>

                    <div v-else class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr class="text-secondary small text-uppercase">
                                    <th>Aluno</th>
                                    <th>Curso / Unidade</th>
                                    <th>Status Ativação</th>
                                    <th>Cadastrado em</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="aluno in alunosFiltrados" :key="aluno.matricula">
                                    <td>
                                        <p class="fw-semibold mb-0">{{ aluno.pessoa?.nome }}</p>
                                        <p class="text-secondary small mb-0">Matrícula: {{ aluno.matricula }}</p>
                                    </td>
                                    <td>
                                        <p class="mb-0">{{ aluno.dadosAcademicos?.[0]?.curso || '—' }}</p>
                                        <p class="text-secondary small mb-0">{{ aluno.dadosAcademicos?.[0]?.unidade || '—' }}</p>
                                    </td>
                                    <td>
                                        <span
                                            class="badge"
                                            :class="aluno.status
                                                ? 'text-bg-success-subtle text-success-emphasis'
                                                : 'text-bg-secondary-subtle text-secondary-emphasis'"
                                        >
                                            {{ aluno.status ? 'Ativo' : 'Aguardando Ativação' }}
                                        </span>
                                    </td>
                                    <td class="text-secondary small">{{ formatarData(aluno.pessoa?.data_cadastro) }}</td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <router-link
                                                :to="{ name: 'admin.dashboard' }"
                                                class="btn btn-sm btn-outline-secondary"
                                                title="Ver perfil"
                                            >
                                                <i class="bi bi-eye"></i>
                                            </router-link>
                                            <button class="btn btn-sm btn-outline-secondary" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </div>
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
const unidadeSelecionada = ref('');

onMounted(async () => {
    await admin.carregarAlunos();
    carregouUmaVez.value = true;
});

// "Sincronizar SIG": no protótipo simula uma re-importação de alunos.
// TODO(back-end): expor um endpoint de sincronização em lote com o SIG;
// hoje só existe POST /administrativo/sincronizar-alunos para um registro
// por vez. Por enquanto, o botão apenas atualiza a lista com os dados mais
// recentes já cadastrados.
async function sincronizar() {
    await admin.carregarAlunos();
}

function formatarData(data) {
    if (!data) return '—';
    return new Date(data).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

const unidades = computed(() => {
    const set = new Set(
        admin.alunos.map((a) => a.dadosAcademicos?.[0]?.unidade).filter(Boolean),
    );
    return [...set].sort();
});

const alunosFiltrados = computed(() => {
    const termo = busca.value.trim().toLowerCase();
    return admin.alunos.filter((a) => {
        const bateBusca = !termo
            || a.pessoa?.nome?.toLowerCase().includes(termo)
            || a.cpf?.includes(termo);
        const bateUnidade = !unidadeSelecionada.value
            || a.dadosAcademicos?.[0]?.unidade === unidadeSelecionada.value;
        return bateBusca && bateUnidade;
    });
});
</script>
