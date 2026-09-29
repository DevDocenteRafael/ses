<template>
    <div>
        <topbar titulo="Candidatos Contratados" subtitulo="Acompanhe as contratações registradas por empresas e pela administração" />

        <div class="container-fluid p-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <h2 class="h6 fw-bold text-primary mb-0">Histórico de Contratações</h2>
                        <div class="row g-2 w-100">
                            <div class="col-md-6 col-xl-3">
                                <div class="input-group position-relative">
                                    <input v-model="filtros.empresa" class="form-control" type="search" placeholder="Pesquisar empresa" aria-label="Pesquisar empresa" autocomplete="off" @focus="abrirSugestoes('empresa')" @input="abrirSugestoes('empresa')" @blur="fecharSugestoes">
                                    <span class="input-group-text bg-primary text-white"><i class="bi bi-search"></i></span>
                                    <div v-if="filtroAberto === 'empresa' && sugestoes.empresa.length" class="list-group position-absolute w-100 shadow" style="z-index: 1060; top: 100%;">
                                        <button v-for="opcao in sugestoes.empresa" :key="opcao" type="button" class="list-group-item list-group-item-action text-start" @mousedown.prevent="selecionarSugestao('empresa', opcao)">{{ opcao }}</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <div class="input-group position-relative">
                                    <input v-model="filtros.nome" class="form-control" type="search" placeholder="Pesquisar candidato" aria-label="Pesquisar candidato" autocomplete="off" @focus="abrirSugestoes('nome')" @input="abrirSugestoes('nome')" @blur="fecharSugestoes">
                                    <span class="input-group-text bg-primary text-white"><i class="bi bi-search"></i></span>
                                    <div v-if="filtroAberto === 'nome' && sugestoes.nome.length" class="list-group position-absolute w-100 shadow" style="z-index: 1060; top: 100%;">
                                        <button v-for="opcao in sugestoes.nome" :key="opcao" type="button" class="list-group-item list-group-item-action text-start" @mousedown.prevent="selecionarSugestao('nome', opcao)">{{ opcao }}</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <div class="input-group position-relative">
                                    <input v-model="filtros.cpf" class="form-control" type="search" inputmode="numeric" placeholder="Pesquisar CPF" aria-label="Pesquisar CPF" autocomplete="off" @focus="abrirSugestoes('cpf')" @input="abrirSugestoes('cpf')" @blur="fecharSugestoes">
                                    <span class="input-group-text bg-primary text-white"><i class="bi bi-search"></i></span>
                                    <div v-if="filtroAberto === 'cpf' && sugestoes.cpf.length" class="list-group position-absolute w-100 shadow" style="z-index: 1060; top: 100%;">
                                        <button v-for="opcao in sugestoes.cpf" :key="opcao" type="button" class="list-group-item list-group-item-action text-start" @mousedown.prevent="selecionarSugestao('cpf', opcao)">{{ formatarCpf(opcao) }}</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <div class="input-group position-relative">
                                    <input v-model="filtros.curso" class="form-control" type="search" placeholder="Pesquisar curso" aria-label="Pesquisar curso" autocomplete="off" @focus="abrirSugestoes('curso')" @input="abrirSugestoes('curso')" @blur="fecharSugestoes">
                                    <span class="input-group-text bg-primary text-white"><i class="bi bi-search"></i></span>
                                    <div v-if="filtroAberto === 'curso' && sugestoes.curso.length" class="list-group position-absolute w-100 shadow" style="z-index: 1060; top: 100%;">
                                        <button v-for="opcao in sugestoes.curso" :key="opcao" type="button" class="list-group-item list-group-item-action text-start" @mousedown.prevent="selecionarSugestao('curso', opcao)">{{ opcao }}</button>
                                    </div>
                                </div>
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
                            :show-single-page="true"
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
                            :show-single-page="true"
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
const filtroAberto = ref('');
const sugestoes = reactive({ empresa: [], nome: [], cpf: [], curso: [] });
const requisicoesSugestoes = { empresa: 0, nome: 0, cpf: 0, curso: 0 };
const temporizadoresSugestoes = {};

onMounted(async () => {
    await carregarContratacoes();
    carregouUmaVez.value = true;
});

let temporizadorFiltro = null;
let requisicaoContratacoes = 0;
watch(filtros, () => {
    clearTimeout(temporizadorFiltro);
    temporizadorFiltro = setTimeout(() => carregarContratacoes(1), 300);
});

async function carregarContratacoes(pagina = paginacao.current_page) {
    const requisicao = ++requisicaoContratacoes;
    carregando.value = true;
    erro.value = '';
    try {
        const { data } = await adminService.listarContratacoes({
            page: pagina,
            per_page: 10,
            ...Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor.trim())),
        });
        if (requisicao !== requisicaoContratacoes) return;

        contratacoes.value = data.data || [];
        paginacao.current_page = data.current_page || 1;
        paginacao.last_page = data.last_page || 1;
        paginacao.per_page = Number(data.per_page || 10);
        paginacao.total = Number(data.total || 0);
        paginacao.from = data.from || null;
        paginacao.to = data.to || null;
    } catch (errorResposta) {
        if (requisicao === requisicaoContratacoes) {
            erro.value = errorResposta.response?.data?.message || 'Não foi possível carregar as contratações.';
        }
    } finally {
        if (requisicao === requisicaoContratacoes) carregando.value = false;
    }
}

function mudarPagina(pagina) {
    if (pagina < 1 || pagina > paginacao.last_page || pagina === paginacao.current_page || carregando.value) return;
    carregarContratacoes(pagina);
}

function abrirSugestoes(campo) {
    filtroAberto.value = campo;
    const termo = filtros[campo].trim();
    const requisicao = ++requisicoesSugestoes[campo];
    clearTimeout(temporizadoresSugestoes[campo]);

    temporizadoresSugestoes[campo] = setTimeout(async () => {
        try {
            const parametros = {
                page: 1,
                per_page: 10,
                ...(termo ? { [campo]: termo } : {}),
            };
            const { data } = await adminService.listarContratacoes(parametros);
            if (requisicao !== requisicoesSugestoes[campo]) return;

            const registros = data.data || [];
            const valores = registros.map((registro) => {
                if (campo === 'empresa') return registro.empresa?.razao_social;
                if (campo === 'nome') return registro.candidato?.pessoa?.nome;
                if (campo === 'cpf') return registro.candidato?.cpf;
                return (registro.candidato?.dados_academicos || []).map((item) => item.curso);
            }).flat();

            sugestoes[campo] = [...new Set(valores.filter(Boolean))].slice(0, 10);
        } catch (errorResposta) {
            if (requisicao === requisicoesSugestoes[campo]) sugestoes[campo] = [];
        }
    }, 200);
}

function selecionarSugestao(campo, valor) {
    filtros[campo] = valor;
    filtroAberto.value = '';
}

function fecharSugestoes() {
    setTimeout(() => { filtroAberto.value = ''; }, 150);
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
