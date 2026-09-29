<template>
    <div>
        <topbar titulo="Candidatos Contratados" subtitulo="Acompanhe as contratações registradas por empresas e pela administração" />

        <div class="container-fluid p-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <h2 class="h6 fw-bold text-primary mb-0">Histórico de Contratações</h2>
                        <div class="row g-2 w-100" style="max-width: 1000px;">
                            <div class="col-md-6 col-xl-3">
                                <input v-model="filtros.empresa" class="form-control" type="search" placeholder="Filtrar por empresa" aria-label="Filtrar por empresa">
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <input v-model="filtros.nome" class="form-control" type="search" placeholder="Filtrar por nome" aria-label="Filtrar por nome">
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <input v-model="filtros.cpf" class="form-control" type="search" inputmode="numeric" placeholder="Filtrar por CPF" aria-label="Filtrar por CPF">
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <input v-model="filtros.curso" class="form-control" type="search" placeholder="Filtrar por curso" aria-label="Filtrar por curso">
                            </div>
                        </div>
                    </div>

                    <div v-if="erro" class="alert alert-danger">{{ erro }}</div>
                    <loading v-else-if="carregando && !carregouUmaVez" mensagem="Carregando contratações..." />
                    <template v-else>
                        <base-pagination
                            class="mb-3"
                            :current-page="paginacao.current_page"
                            :last-page="paginacao.last_page"
                            :per-page="paginacao.per_page"
                            :total="paginacao.total"
                            :from="paginacao.from"
                            :to="paginacao.to"
                            :loading="carregando"
                            item-label="contratações"
                            aria-label="Paginação de candidatos contratados"
                            @change="mudarPagina"
                        />

                        <p v-if="!contratacoes.length" class="text-secondary small mb-0">
                            Nenhuma contratação encontrada.
                        </p>
                        <div v-else class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr class="text-secondary small text-uppercase">
                                        <th>Candidato</th>
                                        <th>CPF</th>
                                        <th>Empresa</th>
                                        <th>Curso</th>
                                        <th>Contratado em</th>
                                        <th>Registrado por</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="registro in contratacoes" :key="registro.id">
                                        <td>
                                            <p class="fw-semibold mb-0">{{ registro.candidato?.pessoa?.nome || '—' }}</p>
                                            <p class="text-secondary small mb-0">{{ registro.candidato?.pessoa?.email || '—' }}</p>
                                        </td>
                                        <td>{{ formatarCpf(registro.candidato?.cpf) }}</td>
                                        <td>{{ registro.empresa?.razao_social || '—' }}</td>
                                        <td>{{ registro.candidato?.dados_academicos?.[0]?.curso || '—' }}</td>
                                        <td>{{ formatarData(registro.contratado_em) }}</td>
                                        <td>{{ registro.registrado_por?.nome || (registro.origem === 'empresa' ? 'Empresa' : 'Administração') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <base-pagination
                            class="mt-3"
                            :current-page="paginacao.current_page"
                            :last-page="paginacao.last_page"
                            :per-page="paginacao.per_page"
                            :total="paginacao.total"
                            :from="paginacao.from"
                            :to="paginacao.to"
                            :loading="carregando"
                            item-label="contratações"
                            aria-label="Paginação inferior de candidatos contratados"
                            @change="mudarPagina"
                        />
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import topbar from '../../../components/common/header.vue';
import loading from '../../../components/common/loading.vue';
import BasePagination from '../../../components/common/BasePagination.vue';
import adminService from '../../../services/adminServices';

const filtros = reactive({ empresa: '', nome: '', cpf: '', curso: '' });
const contratacoes = ref([]);
const carregando = ref(false);
const carregouUmaVez = ref(false);
const erro = ref('');
const paginacao = reactive({ current_page: 1, last_page: 1, per_page: 10, total: 0, from: null, to: null });

onMounted(async () => {
    await carregarContratacoes();
    carregouUmaVez.value = true;
});

let temporizadorFiltro = null;
watch(filtros, () => {
    clearTimeout(temporizadorFiltro);
    temporizadorFiltro = setTimeout(() => carregarContratacoes(1), 300);
});

async function carregarContratacoes(pagina = paginacao.current_page) {
    carregando.value = true;
    erro.value = '';
    try {
        const { data } = await adminService.listarContratacoes({
            page: pagina,
            per_page: 10,
            ...Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor.trim())),
        });
        contratacoes.value = data.data || [];
        paginacao.current_page = data.current_page || 1;
        paginacao.last_page = data.last_page || 1;
        paginacao.per_page = Number(data.per_page || 10);
        paginacao.total = Number(data.total || 0);
        paginacao.from = data.from || null;
        paginacao.to = data.to || null;
    } catch (errorResposta) {
        erro.value = errorResposta.response?.data?.message || 'Não foi possível carregar as contratações.';
    } finally {
        carregando.value = false;
    }
}

function mudarPagina(pagina) {
    if (pagina < 1 || pagina > paginacao.last_page || pagina === paginacao.current_page || carregando.value) return;
    carregarContratacoes(pagina);
}

function formatarCpf(valor) {
    const digitos = String(valor || '').replace(/\D/g, '').padStart(11, '0');
    return digitos.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
}

function formatarData(valor) {
    if (!valor) return '—';
    return new Date(`${String(valor).slice(0, 10)}T00:00:00`).toLocaleDateString('pt-BR');
}
</script>
