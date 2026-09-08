<template>
    <div>
        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between px-4 py-3 bg-white border-bottom">
            <h1 class="h4 fw-bold mb-0">Convites Enviados</h1>
            <div class="d-flex gap-2">
                <input v-model="busca" type="text" class="form-control" placeholder="Buscar candidato...">
                <select v-model="statusFiltro" class="form-select">
                    <option value="">Todos os Status</option>
                    <option v-for="(s, valor) in statusMap" :key="valor" :value="valor">{{ s.label }}</option>
                </select>
            </div>
        </div>

        <div class="container-fluid p-4">
            <loading v-if="empresa.carregando" />

            <div v-else class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr class="text-secondary small text-uppercase">
                                <th>Candidato</th>
                                <th>Vaga / Objetivo</th>
                                <th>Data de Envio</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in convitesFiltrados" :key="c.id">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span
                                            class="ses-inicial rounded-1 d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0"
                                        >{{ iniciais(c.candidato?.pessoa?.nome) }}</span>
                                        <div>
                                            <p class="fw-semibold mb-0">{{ c.candidato?.pessoa?.nome || '—' }}</p>
                                            <p class="small text-secondary mb-0">
                                                {{ c.candidato?.dadosAcademicos?.[0]?.curso || c.descricao }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ c.vaga?.titulo || c.descricao }}</td>
                                <td>{{ formatarData(c.data_envio) }}</td>
                                <td>
                                    <span class="badge" :class="statusMap[c.status]?.classe">
                                        {{ statusMap[c.status]?.label || 'Desconhecido' }}
                                    </span>
                                </td>
                                <td>
                                    <button
                                        v-if="c.status === 0"
                                        class="btn btn-sm btn-outline-danger"
                                        title="Cancelar convite"
                                        @click="cancelar(c.id)"
                                    >
                                        <i class="bi bi-x-lg"></i>
                                    </button>

                                    <template v-else-if="c.status === 1">
                                        <button
                                            class="btn btn-sm btn-primary"
                                            @click="contatoVisivel = contatoVisivel === c.id ? null : c.id"
                                        >
                                            <i class="bi bi-telephone me-1"></i> Ver Contato
                                        </button>
                                        <p v-if="contatoVisivel === c.id" class="small text-secondary mt-2 mb-0">
                                            {{ c.candidato?.pessoa?.email || 'E-mail não informado' }}<br>
                                            {{ formatarTelefone(c.candidato?.pessoa?.telefone) || 'Telefone não informado' }}
                                        </p>
                                    </template>
                                </td>
                            </tr>

                            <tr v-if="!convitesFiltrados.length">
                                <td colspan="5" class="text-center text-secondary py-4">
                                    Nenhum convite encontrado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import loading from '../../../components/common/loading.vue';
import { useEmpresaStore } from '../../../store/empresa';
import { useAuthStore } from '../../../store/auth';
import { formatarTelefone } from '../../../utils/telefone';

const auth = useAuthStore();
const empresa = useEmpresaStore();

const busca = ref('');
const statusFiltro = ref('');
const contatoVisivel = ref(null);

const statusMap = {
    0: { label: 'Pendente', classe: 'text-bg-warning-subtle text-warning-emphasis' },
    1: { label: 'Aceito', classe: 'text-bg-success-subtle text-success-emphasis' },
    2: { label: 'Recusado', classe: 'text-bg-danger-subtle text-danger-emphasis' },
    3: { label: 'Arquivado', classe: 'text-bg-secondary-subtle text-secondary-emphasis' },
};

onMounted(async () => {
    const cnpj = auth.pessoa?.id_pessoa;
    if (cnpj) {
        await empresa.carregarPerfil(cnpj);
    }
});

const convitesFiltrados = computed(() => {
    const termo = busca.value.trim().toLowerCase();
    return empresa.convitesDaEmpresa
        .filter((c) => !termo || (c.candidato?.pessoa?.nome || '').toLowerCase().includes(termo))
        .filter((c) => statusFiltro.value === '' || String(c.status) === String(statusFiltro.value));
});

async function cancelar(id) {
    await empresa.excluirConvite(id);
}

function formatarData(data) {
    if (!data) return '—';
    return new Date(data).toLocaleDateString('pt-BR');
}

function iniciais(nome) {
    if (!nome) return '';
    return nome.split(' ').filter(Boolean).slice(0, 2).map((p) => p[0].toUpperCase()).join('');
}
</script>

<style scoped>
.ses-inicial {
    width: 36px;
    height: 36px;
    background-color: #142a4d;
    font-size: 0.75rem;
}
</style>
