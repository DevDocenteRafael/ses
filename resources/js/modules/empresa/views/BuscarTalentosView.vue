<template>
    <div>
        <topbar titulo="Buscar Talentos" />

        <div class="container-fluid p-4">
            <div class="row g-4">
                <!-- Filtros -->
                <div class="col-lg-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h2 class="h6 fw-bold mb-3">Filtros Inteligentes</h2>

                            <div class="mb-3">
                                <label class="form-label small text-uppercase text-secondary fw-semibold">
                                    Formação Acadêmica (FR20)
                                </label>
                                <select v-model="filtros.curso" class="form-select mb-2">
                                    <option value="">Todos os Cursos</option>
                                    <option v-for="c in cursosDisponiveis" :key="c" :value="c">{{ c }}</option>
                                </select>
                                <select v-model="filtros.unidade" class="form-select">
                                    <option value="">Todas as Unidades</option>
                                    <option v-for="u in unidadesDisponiveis" :key="u" :value="u">{{ u }}</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-uppercase text-secondary fw-semibold">
                                    Contratação (FR18)
                                </label>
                                <div class="form-check" v-for="opt in tiposContratacao" :key="opt.valor">
                                    <input
                                        :id="`tipo-${opt.valor}`"
                                        v-model="filtros.tiposContratacao"
                                        class="form-check-input"
                                        type="checkbox"
                                        :value="opt.valor"
                                    >
                                    <label class="form-check-label" :for="`tipo-${opt.valor}`">{{ opt.rotulo }}</label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-uppercase text-secondary fw-semibold">
                                    Disponibilidade (FR17)
                                </label>
                                <select v-model="filtros.disponibilidade" class="form-select">
                                    <option value="">Qualquer Horário</option>
                                    <option v-for="d in disponibilidadesDisponiveis" :key="d" :value="d">{{ d }}</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-uppercase text-secondary fw-semibold">
                                    Habilidades Técnicas (FR16)
                                </label>
                                <input
                                    v-model="novaHabilidade"
                                    type="text"
                                    class="form-control mb-2"
                                    placeholder="Digite uma competência..."
                                    @keyup.enter="adicionarHabilidade"
                                >
                                <div class="d-flex flex-wrap gap-2">
                                    <span v-for="h in filtros.habilidades" :key="h" class="badge text-bg-dark">
                                        {{ h }}
                                        <button
                                            type="button"
                                            class="btn-close btn-close-white ms-1"
                                            style="font-size: 0.55rem;"
                                            :aria-label="`Remover ${h}`"
                                            @click="removerHabilidade(h)"
                                        ></button>
                                    </span>
                                </div>
                            </div>

                            <button class="btn btn-primary w-100 mb-2" @click="aplicarFiltros">Aplicar Filtros</button>
                            <button class="btn btn-link w-100 text-decoration-none" @click="limparFiltros">Limpar Tudo</button>
                        </div>
                    </div>
                </div>

                <!-- Resultados -->
                <div class="col-lg-9">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h2 class="h5 fw-bold mb-0">
                            Resultados da Busca
                            <span class="badge text-bg-secondary ms-2">{{ resultados.length }} candidatos</span>
                        </h2>
                        <select v-model="ordenacao" class="form-select w-auto">
                            <option value="compatibilidade">Ordenar por Compatibilidade</option>
                            <option value="nome">Ordenar por Nome</option>
                        </select>
                    </div>

                    <loading v-if="empresa.carregando" />

                    <p v-else-if="!resultados.length" class="text-secondary">
                        Nenhum candidato encontrado para os filtros selecionados.
                    </p>

                    <div v-else class="card border-0 shadow-sm mb-3" v-for="c in resultadosOrdenados" :key="c.matricula">
                        <div class="card-body d-flex flex-wrap align-items-center gap-3">
                            <span
                                class="ses-inicial rounded-1 d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0"
                            >
                                {{ iniciais(c.pessoa?.nome) }}
                            </span>

                            <div class="flex-grow-1">
                                <h3 class="h6 fw-bold mb-1">{{ c.pessoa?.nome }}</h3>
                                <p class="small text-secondary mb-2">
                                    <i class="bi bi-mortarboard"></i>
                                    {{ c.dadosAcademicos?.[0]?.curso || 'Curso não informado' }}
                                    <span v-if="c.dadosAcademicos?.[0]?.unidade"> | {{ c.dadosAcademicos[0].unidade }}</span>
                                </p>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <span
                                        v-for="h in (c.informacoesProfissionais?.habilidades || []).slice(0, 4)"
                                        :key="h"
                                        class="badge text-bg-primary-subtle text-primary-emphasis"
                                    >{{ h }}</span>
                                </div>
                                <p class="small text-secondary mb-0">
                                    <i class="bi bi-geo-alt"></i>
                                    {{ c.preferenciasDeTrabalho?.regiao_administrativa || 'Região não informada' }}
                                    <template v-if="c.preferenciasDeTrabalho?.disponibilidade_de_horario">
                                        &nbsp;<i class="bi bi-clock"></i>
                                        Disponível: {{ c.preferenciasDeTrabalho.disponibilidade_de_horario }}
                                    </template>
                                </p>
                            </div>

                            <div class="text-end flex-shrink-0" style="min-width: 180px;">
                                <p class="fw-bold text-warning mb-0">{{ c.compatibilidade }}% Match</p>
                                <p class="small text-secondary mb-2">Compatibilidade Ponderada</p>
                                <button class="btn btn-primary btn-sm d-block w-100 mb-1" @click="abrirPerfil(c)">
                                    Ver Perfil Completo
                                </button>
                                <button class="btn btn-outline-secondary btn-sm d-block w-100" @click="alternarFavorito(c)">
                                    <i class="bi" :class="ehFavorito(c.matricula) ? 'bi-star-fill' : 'bi-star'"></i>
                                    {{ ehFavorito(c.matricula) ? 'Favoritado' : 'Favoritar' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <modal :show="modalAberto" titulo="Perfil do Candidato" @fechar="modalAberto = false">
            <div v-if="perfilSelecionado">
                <h3 class="h6 fw-bold">{{ perfilSelecionado.pessoa?.nome }}</h3>
                <p class="text-secondary small">{{ perfilSelecionado.informacoesProfissionais?.sobre_mim || 'Sem descrição.' }}</p>

                <p class="mb-1">
                    <strong>Cargo de interesse:</strong>
                    {{ perfilSelecionado.informacoesProfissionais?.cargo_de_interesse || '—' }}
                </p>
                <p class="mb-1">
                    <strong>Região:</strong>
                    {{ perfilSelecionado.preferenciasDeTrabalho?.regiao_administrativa || '—' }}
                </p>
                <p class="mb-1">
                    <strong>Pretensão salarial:</strong>
                    {{ perfilSelecionado.preferenciasDeTrabalho?.pretensao_salarial ?? '—' }}
                </p>
                <p class="mb-2">
                    <strong>Curso:</strong>
                    {{ perfilSelecionado.dadosAcademicos?.[0]?.curso || '—' }}
                    <span v-if="perfilSelecionado.dadosAcademicos?.[0]?.unidade">
                        ({{ perfilSelecionado.dadosAcademicos[0].unidade }})
                    </span>
                </p>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span
                        v-for="h in perfilSelecionado.informacoesProfissionais?.habilidades || []"
                        :key="h"
                        class="badge text-bg-primary-subtle text-primary-emphasis"
                    >{{ h }}</span>
                </div>

                <button class="btn btn-primary w-100" @click="alternarFavorito(perfilSelecionado)">
                    <i class="bi" :class="ehFavorito(perfilSelecionado.matricula) ? 'bi-star-fill' : 'bi-star'"></i>
                    {{ ehFavorito(perfilSelecionado.matricula) ? 'Remover dos Favoritos' : 'Favoritar Candidato' }}
                </button>
            </div>
        </modal>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import topbar from '../../../components/common/header.vue';
import loading from '../../../components/common/loading.vue';
import modal from '../../../components/common/modal.vue';
import { useEmpresaStore } from '../../../store/empresa';
import { useAuthStore } from '../../../store/auth';

const auth = useAuthStore();
const empresa = useEmpresaStore();

// Estado "de trabalho" dos filtros (o que o usuário está mexendo agora)
const filtros = reactive({
    curso: '',
    unidade: '',
    tiposContratacao: [],
    disponibilidade: '',
    habilidades: [],
});

// Snapshot aplicado de fato à busca — só muda quando clica em
// "Aplicar Filtros", como no protótipo.
const filtrosAplicados = ref(instantaneo());

const novaHabilidade = ref('');
const ordenacao = ref('compatibilidade');
const modalAberto = ref(false);
const perfilSelecionado = ref(null);

const tiposContratacao = [
    { valor: 0, rotulo: 'CLT / Efetivo' },
    { valor: 1, rotulo: 'Estágio' },
    { valor: 2, rotulo: 'Jovem Aprendiz' },
];

onMounted(async () => {
    await empresa.buscarTalentos();
    const cnpj = auth.pessoa?.id_pessoa;
    if (cnpj) {
        await empresa.carregarPerfil(cnpj);
    }
});

const candidatos = computed(() => empresa.candidatosEncontrados);

const cursosDisponiveis = computed(() =>
    [...new Set(candidatos.value.map((c) => c.dadosAcademicos?.[0]?.curso).filter(Boolean))]
);
const unidadesDisponiveis = computed(() =>
    [...new Set(candidatos.value.map((c) => c.dadosAcademicos?.[0]?.unidade).filter(Boolean))]
);
const disponibilidadesDisponiveis = computed(() =>
    [...new Set(candidatos.value.map((c) => c.preferenciasDeTrabalho?.disponibilidade_de_horario).filter(Boolean))]
);

function instantaneo() {
    return { curso: '', unidade: '', tiposContratacao: [], disponibilidade: '', habilidades: [] };
}

function adicionarHabilidade() {
    const valor = novaHabilidade.value.trim();
    if (valor && !filtros.habilidades.includes(valor)) {
        filtros.habilidades.push(valor);
    }
    novaHabilidade.value = '';
}

function removerHabilidade(h) {
    filtros.habilidades = filtros.habilidades.filter((x) => x !== h);
}

function aplicarFiltros() {
    filtrosAplicados.value = JSON.parse(JSON.stringify(filtros));
}

function limparFiltros() {
    filtros.curso = '';
    filtros.unidade = '';
    filtros.tiposContratacao = [];
    filtros.disponibilidade = '';
    filtros.habilidades = [];
    aplicarFiltros();
}

/**
 * Compatibilidade ponderada (FR16-FR20), calculada no front: combina
 * sobreposição de habilidades (peso maior) com aderência ao tipo de
 * contratação e à disponibilidade escolhidos nos filtros.
 */
function calcularCompatibilidade(candidato, f) {
    const habilidadesCandidato = candidato.informacoesProfissionais?.habilidades || [];
    let pontos = 0;
    let pesoTotal = 0;

    if (f.habilidades.length) {
        const encontradas = f.habilidades.filter((h) =>
            habilidadesCandidato.some((hc) => hc.toLowerCase() === h.toLowerCase())
        ).length;
        pontos += (encontradas / f.habilidades.length) * 70;
        pesoTotal += 70;
    }

    pesoTotal += 15;
    if (
        !f.tiposContratacao.length
        || f.tiposContratacao.includes(candidato.preferenciasDeTrabalho?.tipo_de_contratacao)
    ) {
        pontos += 15;
    }

    pesoTotal += 15;
    if (
        !f.disponibilidade
        || candidato.preferenciasDeTrabalho?.disponibilidade_de_horario === f.disponibilidade
    ) {
        pontos += 15;
    }

    if (!pesoTotal) return 100;
    return Math.round((pontos / pesoTotal) * 100);
}

const resultados = computed(() => {
    const f = filtrosAplicados.value;

    return candidatos.value
        .filter((c) => !f.curso || c.dadosAcademicos?.[0]?.curso === f.curso)
        .filter((c) => !f.unidade || c.dadosAcademicos?.[0]?.unidade === f.unidade)
        .filter((c) => !f.disponibilidade || c.preferenciasDeTrabalho?.disponibilidade_de_horario === f.disponibilidade)
        .filter((c) => !f.tiposContratacao.length || f.tiposContratacao.includes(c.preferenciasDeTrabalho?.tipo_de_contratacao))
        .filter((c) => !f.habilidades.length || f.habilidades.some((h) =>
            (c.informacoesProfissionais?.habilidades || []).some((hc) => hc.toLowerCase() === h.toLowerCase())
        ))
        .map((c) => ({ ...c, compatibilidade: calcularCompatibilidade(c, f) }));
});

const resultadosOrdenados = computed(() => {
    const lista = [...resultados.value];
    if (ordenacao.value === 'nome') {
        return lista.sort((a, b) => (a.pessoa?.nome || '').localeCompare(b.pessoa?.nome || ''));
    }
    return lista.sort((a, b) => b.compatibilidade - a.compatibilidade);
});

function ehFavorito(matricula) {
    return empresa.favoritosDaEmpresa.some((c) => c.matricula === matricula);
}

async function alternarFavorito(candidato) {
    if (ehFavorito(candidato.matricula)) {
        await empresa.desfavoritar(candidato.matricula);
    } else {
        await empresa.favoritar(candidato);
    }
}

function abrirPerfil(c) {
    perfilSelecionado.value = c;
    modalAberto.value = true;
}

function iniciais(nome) {
    if (!nome) return '';
    return nome.split(' ').filter(Boolean).slice(0, 2).map((p) => p[0].toUpperCase()).join('');
}
</script>

<style scoped>
.ses-inicial {
    width: 48px;
    height: 48px;
    background-color: #142a4d;
}
</style>
