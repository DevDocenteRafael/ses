<template>
    <div>
        <div class="d-flex align-items-center justify-content-between px-4 py-3 bg-white border-bottom">
            <h1 class="h4 fw-bold mb-0">Gerenciar Minhas Vagas</h1>
            <button class="btn btn-primary" @click="abrirNovaVaga">
                <i class="bi bi-plus-lg me-1"></i> Anunciar Nova Vaga
            </button>
        </div>

        <div class="container-fluid p-4">
            <loading v-if="empresa.carregando" />

            <div v-else class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr class="text-secondary small text-uppercase">
                                <th>Título da Vaga</th>
                                <th>Tipo</th>
                                <th>Convites Enviados</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="v in empresa.vagasDaEmpresa" :key="v.id_vaga">
                                <td>
                                    <p class="fw-semibold text-primary mb-0">{{ v.titulo }}</p>
                                    <p class="small text-secondary mb-0">Publicada em: {{ formatarData(v.data_publicacao) }}</p>
                                </td>
                                <td>
                                    <span class="badge text-bg-light border">{{ rotuloTipo(v.tipo) }}</span>
                                </td>
                                <td>{{ convitesPorVaga(v.id_vaga) }} candidato(s)</td>
                                <td>
                                    <span class="badge" :class="v.status ? 'text-bg-success' : 'text-bg-secondary'">
                                        {{ v.status ? 'Ativa' : 'Pausada' }}
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-light me-1" title="Editar" @click="abrirEdicaoVaga(v)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button
                                        class="btn btn-sm btn-light"
                                        :class="v.status ? 'text-danger' : 'text-success'"
                                        :title="v.status ? 'Pausar vaga' : 'Ativar vaga'"
                                        @click="alternarStatus(v)"
                                    >
                                        <i class="bi bi-power"></i>
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="!empresa.vagasDaEmpresa.length">
                                <td colspan="5" class="text-center text-secondary py-4">
                                    Nenhuma vaga cadastrada ainda.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <modal
            :show="modalVagaAberto"
            :titulo="vagaEmEdicao ? 'Editar Vaga' : 'Anunciar Nova Vaga'"
            @fechar="modalVagaAberto = false"
        >
            <form @submit.prevent="salvarVaga">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Título da vaga</label>
                    <input v-model="form.titulo" type="text" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Tipo de contratação</label>
                    <select v-model.number="form.tipo" class="form-select" required>
                        <option :value="0">CLT / Efetivo</option>
                        <option :value="1">Estágio</option>
                        <option :value="2">Jovem Aprendiz</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Área</label>
                    <input v-model="form.area" type="text" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Data de publicação</label>
                    <input v-model="form.data_publicacao" type="date" class="form-control" required>
                </div>

                <div class="form-check mb-3">
                    <input id="vaga-ativa" v-model="form.status" class="form-check-input" type="checkbox">
                    <label class="form-check-label" for="vaga-ativa">Vaga ativa</label>
                </div>

                <p v-if="erroForm" class="text-danger small">{{ erroForm }}</p>

                <button type="submit" class="btn btn-primary w-100" :disabled="salvando">
                    {{ salvando ? 'Salvando...' : 'Salvar' }}
                </button>
            </form>
        </modal>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import loading from '../../../components/common/loading.vue';
import modal from '../../../components/common/modal.vue';
import { useEmpresaStore } from '../../../store/empresa';
import { useAuthStore } from '../../../store/auth';

const auth = useAuthStore();
const empresa = useEmpresaStore();

const modalVagaAberto = ref(false);
const vagaEmEdicao = ref(null);
const salvando = ref(false);
const erroForm = ref('');

const tiposRotulo = {
    0: 'CLT / Efetivo',
    1: 'Estágio',
    2: 'Jovem Aprendiz',
};

const form = reactive({
    titulo: '',
    tipo: 0,
    area: '',
    data_publicacao: '',
    status: true,
});

onMounted(async () => {
    const cnpj = auth.pessoa?.id_pessoa;
    if (cnpj) {
        await empresa.carregarPerfil(cnpj);
    }
});

function convitesPorVaga(idVaga) {
    return empresa.convitesDaEmpresa.filter((c) => c.vagas_id_vaga === idVaga).length;
}

function rotuloTipo(tipo) {
    return tiposRotulo[tipo] || 'Outro';
}

function formatarData(data) {
    if (!data) return '—';
    return new Date(data).toLocaleDateString('pt-BR');
}

function abrirNovaVaga() {
    vagaEmEdicao.value = null;
    erroForm.value = '';
    form.titulo = '';
    form.tipo = 0;
    form.area = '';
    form.data_publicacao = new Date().toISOString().slice(0, 10);
    form.status = true;
    modalVagaAberto.value = true;
}

function abrirEdicaoVaga(vaga) {
    vagaEmEdicao.value = vaga;
    erroForm.value = '';
    form.titulo = vaga.titulo;
    form.tipo = vaga.tipo;
    form.area = vaga.area;
    form.data_publicacao = vaga.data_publicacao?.slice(0, 10) || '';
    form.status = !!vaga.status;
    modalVagaAberto.value = true;
}

async function salvarVaga() {
    salvando.value = true;
    erroForm.value = '';
    try {
        if (vagaEmEdicao.value) {
            await empresa.atualizarVaga(vagaEmEdicao.value.id_vaga, { ...form });
        } else {
            await empresa.criarVaga({
                ...form,
                empresa_cnpj: auth.pessoa?.id_pessoa,
            });
        }
        modalVagaAberto.value = false;
    } catch (e) {
        erroForm.value = e.response?.data?.message || 'Erro ao salvar a vaga.';
    } finally {
        salvando.value = false;
    }
}

async function alternarStatus(vaga) {
    await empresa.atualizarVaga(vaga.id_vaga, { status: !vaga.status });
}
</script>
