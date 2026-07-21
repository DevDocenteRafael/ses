<template>
    <div>
        <div class="d-flex align-items-center justify-content-between px-4 py-3 bg-white border-bottom">
            <h1 class="h4 fw-bold mb-0">Favoritos e Listas</h1>
            <button class="btn btn-primary" @click="modalNovaListaAberto = true">
                <i class="bi bi-plus-lg me-1"></i> Nova Lista
            </button>
        </div>

        <div class="container-fluid p-4">
            <div class="row g-4">
                <!-- Categorias -->
                <div class="col-lg-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h2 class="small text-uppercase text-secondary fw-semibold mb-3">Minhas Categorias</h2>

                            <button
                                type="button"
                                class="btn w-100 d-flex justify-content-between align-items-center mb-1"
                                :class="categoriaAtiva === 'todos' ? 'btn-light fw-semibold' : 'btn-white text-secondary'"
                                @click="categoriaAtiva = 'todos'"
                            >
                                <span><i class="bi bi-people me-2"></i>Todos os Favoritos</span>
                                <span>{{ empresa.favoritosDaEmpresa.length }}</span>
                            </button>

                            <hr>

                            <button
                                v-for="l in listas"
                                :key="l.id"
                                type="button"
                                class="btn w-100 d-flex justify-content-between align-items-center mb-1"
                                :class="categoriaAtiva === l.id ? 'btn-light fw-semibold' : 'btn-white text-secondary'"
                                @click="categoriaAtiva = l.id"
                            >
                                <span><i class="bi bi-folder2 me-2"></i>{{ l.nome }}</span>
                                <span>{{ l.matriculas.length }}</span>
                            </button>

                            <hr>

                            <button type="button" class="btn btn-link text-decoration-none px-0" @click="modalGerenciarAberto = true">
                                <i class="bi bi-gear me-1"></i> Gerenciar Listas
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabela -->
                <div class="col-lg-9">
                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                        <input
                            v-model="busca"
                            type="text"
                            class="form-control"
                            style="max-width: 320px;"
                            placeholder="Filtrar aluno nesta lista..."
                        >
                        <button
                            class="btn btn-outline-primary"
                            :disabled="!selecionados.length"
                            @click="modalComparacaoAberto = true"
                        >
                            <i class="bi bi-columns-gap me-1"></i> Comparar Selecionados
                        </button>
                    </div>

                    <loading v-if="empresa.carregando" />

                    <div v-else class="card border-0 shadow-sm">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr class="text-secondary small text-uppercase">
                                        <th style="width: 40px;"></th>
                                        <th>Candidato</th>
                                        <th>Curso / Unidade</th>
                                        <th>Lista</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="c in candidatosFiltrados" :key="c.matricula">
                                        <td>
                                            <input
                                                v-model="selecionados"
                                                class="form-check-input"
                                                type="checkbox"
                                                :value="c.matricula"
                                            >
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span
                                                    class="ses-inicial rounded-1 d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0"
                                                >{{ iniciais(c.pessoa?.nome) }}</span>
                                                <div>
                                                    <p class="fw-semibold mb-0">{{ c.pessoa?.nome }}</p>
                                                    <p class="small text-secondary mb-0">
                                                        {{ c.preferenciasDeTrabalho?.regiao_administrativa || '—' }}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <p class="mb-0">{{ c.dadosAcademicos?.[0]?.curso || '—' }}</p>
                                            <p class="small text-secondary mb-0">{{ c.dadosAcademicos?.[0]?.unidade || '—' }}</p>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button
                                                    class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                    type="button"
                                                    data-bs-toggle="dropdown"
                                                >
                                                    Adicionar à lista
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li v-if="!listas.length">
                                                        <span class="dropdown-item-text small text-secondary">Nenhuma lista criada</span>
                                                    </li>
                                                    <li v-for="l in listas" :key="l.id">
                                                        <button class="dropdown-item" type="button" @click="adicionarNaLista(l.id, c.matricula)">
                                                            {{ l.nome }}
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-light me-1" title="Ver perfil" @click="abrirPerfil(c)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-light text-danger" title="Remover" @click="remover(c.matricula)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>

                                    <tr v-if="!candidatosFiltrados.length">
                                        <td colspan="5" class="text-center text-secondary py-4">
                                            Nenhum candidato nesta lista.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <modal :show="modalNovaListaAberto" titulo="Nova Lista" @fechar="modalNovaListaAberto = false">
            <input v-model="nomeNovaLista" type="text" class="form-control mb-3" placeholder="Ex: Estagiários TI">
            <button class="btn btn-primary w-100" @click="salvarNovaLista">Criar Lista</button>
        </modal>

        <modal :show="modalGerenciarAberto" titulo="Gerenciar Listas" @fechar="modalGerenciarAberto = false">
            <p v-if="!listas.length" class="text-secondary small">Nenhuma lista criada ainda.</p>
            <div v-for="l in listas" :key="l.id" class="d-flex justify-content-between align-items-center py-2 border-bottom">
                <span><i class="bi bi-folder2 me-2 text-secondary"></i>{{ l.nome }} ({{ l.matriculas.length }})</span>
                <button class="btn btn-sm btn-light text-danger" @click="removerLista(l.id)">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </modal>

        <modal :show="modalComparacaoAberto" titulo="Comparar Candidatos" @fechar="modalComparacaoAberto = false">
            <div v-for="c in candidatosSelecionados" :key="c.matricula" class="mb-3 pb-3 border-bottom">
                <p class="fw-bold mb-1">{{ c.pessoa?.nome }}</p>
                <p class="small mb-1"><strong>Curso:</strong> {{ c.dadosAcademicos?.[0]?.curso || '—' }}</p>
                <p class="small mb-1"><strong>Região:</strong> {{ c.preferenciasDeTrabalho?.regiao_administrativa || '—' }}</p>
                <p class="small mb-1"><strong>Disponibilidade:</strong> {{ c.preferenciasDeTrabalho?.disponibilidade_de_horario || '—' }}</p>
                <div class="d-flex flex-wrap gap-1">
                    <span
                        v-for="h in c.informacoesProfissionais?.habilidades || []"
                        :key="h"
                        class="badge text-bg-primary-subtle text-primary-emphasis"
                    >{{ h }}</span>
                </div>
            </div>
        </modal>

        <modal :show="modalPerfilAberto" titulo="Perfil do Candidato" @fechar="modalPerfilAberto = false">
            <div v-if="perfilSelecionado">
                <h3 class="h6 fw-bold">{{ perfilSelecionado.pessoa?.nome }}</h3>
                <p class="text-secondary small">{{ perfilSelecionado.informacoesProfissionais?.sobre_mim || 'Sem descrição.' }}</p>
                <p class="mb-1"><strong>Cargo de interesse:</strong> {{ perfilSelecionado.informacoesProfissionais?.cargo_de_interesse || '—' }}</p>
                <p class="mb-1"><strong>Pretensão salarial:</strong> {{ perfilSelecionado.preferenciasDeTrabalho?.pretensao_salarial ?? '—' }}</p>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <span
                        v-for="h in perfilSelecionado.informacoesProfissionais?.habilidades || []"
                        :key="h"
                        class="badge text-bg-primary-subtle text-primary-emphasis"
                    >{{ h }}</span>
                </div>
            </div>
        </modal>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import loading from '../../../components/common/loading.vue';
import modal from '../../../components/common/modal.vue';
import { useEmpresaStore } from '../../../store/empresa';
import { useAuthStore } from '../../../store/auth';
import { useListasFavoritos } from '../../../composables/useListasFavoritos';

const auth = useAuthStore();
const empresa = useEmpresaStore();
const { listas, criarLista, removerLista, adicionarNaLista } = useListasFavoritos();

const categoriaAtiva = ref('todos');
const busca = ref('');
const selecionados = ref([]);

const modalNovaListaAberto = ref(false);
const nomeNovaLista = ref('');
const modalGerenciarAberto = ref(false);
const modalComparacaoAberto = ref(false);
const modalPerfilAberto = ref(false);
const perfilSelecionado = ref(null);

onMounted(async () => {
    const cnpj = auth.pessoa?.id_pessoa;
    if (cnpj) {
        await empresa.carregarPerfil(cnpj);
    }
});

const candidatosDaCategoria = computed(() => {
    if (categoriaAtiva.value === 'todos') {
        return empresa.favoritosDaEmpresa;
    }
    const lista = listas.find((l) => l.id === categoriaAtiva.value);
    if (!lista) return [];
    return empresa.favoritosDaEmpresa.filter((c) => lista.matriculas.includes(c.matricula));
});

const candidatosFiltrados = computed(() => {
    const termo = busca.value.trim().toLowerCase();
    if (!termo) return candidatosDaCategoria.value;
    return candidatosDaCategoria.value.filter((c) => (c.pessoa?.nome || '').toLowerCase().includes(termo));
});

const candidatosSelecionados = computed(() =>
    empresa.favoritosDaEmpresa.filter((c) => selecionados.value.includes(c.matricula))
);

function salvarNovaLista() {
    if (!nomeNovaLista.value.trim()) return;
    criarLista(nomeNovaLista.value);
    nomeNovaLista.value = '';
    modalNovaListaAberto.value = false;
}

async function remover(matricula) {
    await empresa.desfavoritar(matricula);
    selecionados.value = selecionados.value.filter((m) => m !== matricula);
}

function abrirPerfil(c) {
    perfilSelecionado.value = c;
    modalPerfilAberto.value = true;
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
.btn-white {
    background-color: transparent;
}
</style>
