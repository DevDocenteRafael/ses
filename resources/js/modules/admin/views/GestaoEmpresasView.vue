<template>
    <div>
        <topbar titulo="Gestão de Empresas" subtitulo="Aprovação e controle de acesso de parceiros corporativos (FR35)">
            <template #acoes>
                <button class="btn btn-outline-primary" :disabled="admin.carregando" @click="sincronizar">
                    <i class="bi bi-arrow-repeat me-1"></i>
                    {{ admin.carregando ? 'Sincronizando...' : 'Sincronizar SIG' }}
                </button>
            </template>
        </topbar>

        <div class="container-fluid p-4">
            <loading v-if="admin.carregando && !carregouUmaVez" mensagem="Carregando empresas..." />

            <div v-else class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <h2 class="h6 fw-bold text-primary mb-0">Histórico de Empresas</h2>
                        <div class="input-group" style="max-width: 320px;">
                            <input
                                v-model="busca"
                                type="text"
                                class="form-control"
                                placeholder="Buscar por Nome ou CNPJ"
                            >
                            <span class="input-group-text bg-primary text-white"><i class="bi bi-search"></i></span>
                        </div>
                    </div>

                    <p v-if="!empresasFiltradas.length" class="text-secondary small mb-0">
                        Nenhuma empresa encontrada.
                    </p>

                    <div v-else class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr class="text-secondary small text-uppercase">
                                    <th>Empresa</th>
                                    <th>CNPJ</th>
                                    <th>Atividade</th>
                                    <th>Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="empresa in empresasFiltradas" :key="empresa.cnpj">
                                    <td>
                                        <p class="fw-semibold mb-0">{{ empresa.razao_social }}</p>
                                        <p class="text-secondary small mb-0">
                                            Responsável: {{ empresa.responsavel_contratual?.pessoa?.nome || '—' }}
                                        </p>
                                    </td>
                                    <td>{{ formatarCnpj(empresa.cnpj) }}</td>
                                    <td>{{ empresa.atividade_economica }}</td>
                                    <td>
                                        <span
                                            class="badge"
                                            :class="empresa.status
                                                ? 'text-bg-success-subtle text-success-emphasis'
                                                : 'text-bg-danger-subtle text-danger-emphasis'"
                                        >
                                            {{ empresa.status ? 'Liberado' : 'Bloqueado' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <button
                                            class="btn btn-sm"
                                            :class="empresa.status ? 'btn-outline-danger' : 'btn-success'"
                                            :disabled="alterando === empresa.cnpj"
                                            @click="alternarStatus(empresa)"
                                        >
                                            {{ empresa.status ? 'Bloquear Acesso' : 'Liberar Acesso' }}
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
    await admin.carregarEmpresas();
    carregouUmaVez.value = true;
});

// "Sincronizar SIG": ainda não existe uma integração real com o SIG para
// empresas (nenhuma tabela de log equivalente à `alunos_migrados`), então
// por ora o botão só recarrega a lista com os dados mais recentes do banco.
async function sincronizar() {
    await admin.carregarEmpresas();
}

async function alternarStatus(empresa) {
    alterando.value = empresa.cnpj;
    try {
        await admin.atualizarStatusEmpresa(empresa.cnpj, !empresa.status);
    } finally {
        alterando.value = null;
    }
}

function formatarCnpj(cnpj) {
    const digitos = String(cnpj).padStart(14, '0');
    return digitos.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
}

const empresasFiltradas = computed(() => {
    const termo = busca.value.trim().toLowerCase();
    if (!termo) return admin.empresas;
    return admin.empresas.filter(
        (e) => e.razao_social?.toLowerCase().includes(termo) || String(e.cnpj).includes(termo),
    );
});
</script>
