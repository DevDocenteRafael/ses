<template>
    <div>
        <topbar titulo="Empresas Cadastradas" subtitulo="Aprovação e gestão de parceiros corporativos" />

        <div class="container-fluid p-4">
            <loading v-if="admin.carregando && !carregouUmaVez" mensagem="Carregando empresas..." />

            <template v-else>
                <div v-if="admin.empresasPendentes.length" class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h2 class="h6 fw-bold text-primary mb-3">Aguardando Aprovação</h2>

                        <div
                            v-for="empresa in admin.empresasPendentes"
                            :key="empresa.cnpj"
                            class="d-flex align-items-center justify-content-between py-3 border-bottom"
                        >
                            <div>
                                <span class="fw-semibold me-2">{{ empresa.razao_social }}</span>
                                <span class="badge text-bg-warning-subtle text-warning-emphasis">Pendente</span>
                                <p class="text-secondary small mb-0 mt-1">
                                    CNPJ: {{ formatarCnpj(empresa.cnpj) }} | Atividade: {{ empresa.atividade_economica }}
                                </p>
                                <p class="text-secondary small mb-0">
                                    Responsável: {{ empresa.responsavel_contratual?.pessoa?.nome || '—' }}
                                    <span v-if="empresa.pessoa?.email"> | {{ empresa.pessoa.email }}</span>
                                </p>
                            </div>
                            <div class="d-flex gap-2 flex-shrink-0">
                                <button class="btn btn-success btn-sm" @click="admin.marcarEmpresaAprovada(empresa.cnpj)">
                                    Aprovar Cadastro
                                </button>
                                <button class="btn btn-outline-danger btn-sm" @click="solicitarComplemento(empresa)">
                                    Solicitar Complemento
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <h2 class="h6 fw-bold mb-0">Histórico de Empresas</h2>
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
                            <table class="table align-middle">
                                <thead>
                                    <tr class="text-secondary small text-uppercase">
                                        <th>Empresa</th>
                                        <th>Atividade</th>
                                        <th>Status</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="empresa in empresasFiltradas" :key="empresa.cnpj">
                                        <td>
                                            <p class="fw-semibold mb-0">{{ empresa.razao_social }}</p>
                                            <p class="text-secondary small mb-0">CNPJ: {{ formatarCnpj(empresa.cnpj) }}</p>
                                        </td>
                                        <td>{{ empresa.atividade_economica }}</td>
                                        <td>
                                            <span
                                                class="badge"
                                                :class="statusEmpresa(empresa) === 'pendente'
                                                    ? 'text-bg-warning-subtle text-warning-emphasis'
                                                    : 'text-bg-success-subtle text-success-emphasis'"
                                            >
                                                {{ statusEmpresa(empresa) === 'pendente' ? 'Pendente' : 'Aprovada' }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group">
                                                <button
                                                    class="btn btn-sm btn-outline-secondary"
                                                    title="Ver vagas da empresa"
                                                    @click="verVagas(empresa)"
                                                >
                                                    <i class="bi bi-bar-chart"></i>
                                                </button>
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
            </template>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import topbar from '../../../components/common/header.vue';
import loading from '../../../components/common/loading.vue';
import { useAdminStore } from '../../../store/admin';

const admin = useAdminStore();
const router = useRouter();
const carregouUmaVez = ref(false);
const busca = ref('');

onMounted(async () => {
    await admin.carregarEmpresas();
    carregouUmaVez.value = true;
});

function statusEmpresa(empresa) {
    return admin.statusEmpresas[empresa.cnpj] === 'pendente' ? 'pendente' : 'aprovada';
}

function solicitarComplemento(empresa) {
    // TODO(back-end): notificar a empresa por e-mail pedindo documentação
    // complementar ainda depende de um endpoint próprio. Por ora, mantemos
    // o cadastro marcado como pendente na revisão da equipe do SENAC.
    admin.marcarEmpresaPendente(empresa.cnpj);
}

function verVagas(empresa) {
    router.push({ name: 'admin.vagas', query: { empresa: empresa.cnpj } });
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
