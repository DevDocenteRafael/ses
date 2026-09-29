<template>
    <div>
        <topbar titulo="Gestão dos Candidatos" subtitulo="Controle de acesso e sincronização de candidatos">
            <template #acoes>
                <button class="btn btn-outline-primary" :disabled="admin.carregando" @click="sincronizar">
                    <i class="bi bi-arrow-repeat me-1"></i>
                    {{ admin.carregando ? 'Sincronizando...' : 'Sincronizar SIG' }}
                </button>
            </template>
        </topbar>

        <div class="container-fluid p-4">
            <loading v-if="admin.carregando && !carregouUmaVez" mensagem="Carregando candidatos..." />

            <div v-else-if="admin.erro" class="alert alert-danger">
                {{ admin.erro }}
            </div>

            <div v-else class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <h2 class="h6 fw-bold text-primary mb-0">Candidatos Cadastrados</h2>
                        <div class="d-flex align-items-stretch flex-wrap gap-2 w-100 justify-content-md-end" style="max-width: 900px;">
                            <div class="input-group flex-grow-1" style="min-width: 240px;">
                                <input
                                    v-model="busca"
                                    type="text"
                                    class="form-control"
                                    placeholder="Filtrar por nome ou CPF"
                                >
                                <span class="input-group-text bg-primary text-white"><i class="bi bi-search"></i></span>
                            </div>
                            <select v-model="statusFiltro" class="form-select" aria-label="Filtrar candidatos por status" style="max-width: 180px;">
                                <option value="">Todos os status</option>
                                <option value="1">Liberado</option>
                                <option value="0">Bloqueado</option>
                            </select>
                            <select v-model="unidadeFiltro" class="form-select" aria-label="Filtrar candidatos por unidade" style="max-width: 220px;">
                                <option value="">Todas as unidades</option>
                                <option v-for="unidade in admin.unidadesAlunos" :key="unidade" :value="unidade">
                                    {{ unidade }}
                                </option>
                            </select>
                            <button
                                class="btn btn-outline-secondary"
                                type="button"
                                :disabled="admin.carregando || gerandoZip || !admin.alunos.length"
                                @click="baixarCurriculosPaginaAtual"
                            >
                                <span v-if="gerandoZip && geracaoRapidaEmAndamento" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                <i v-else class="bi bi-file-earmark-zip me-1"></i>
                                Baixar currículos desta página
                            </button>
                            <button
                                class="btn btn-outline-primary"
                                type="button"
                                :disabled="admin.carregando || gerandoZip || !admin.alunos.length"
                                @click="abrirModalCurriculos"
                            >
                                <i class="bi bi-download me-1"></i>
                                Baixar currículos
                            </button>
                        </div>
                    </div>

                    <base-pagination
                        class="mb-3"
                        :current-page="admin.alunosPaginacao.current_page"
                        :last-page="admin.alunosPaginacao.last_page"
                        :per-page="admin.alunosPaginacao.per_page"
                        :total="admin.alunosPaginacao.total"
                        :from="admin.alunosPaginacao.from"
                        :to="admin.alunosPaginacao.to"
                        :loading="admin.carregando"
                        item-label="candidatos"
                        aria-label="Paginação superior de candidatos"
                        @change="mudarPagina"
                    />

                    <transition name="app-modal">
                        <div v-if="modalContratacaoAberto" class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true">
                            <div class="modal-dialog modal-dialog-centered app-modal-dialog-animated">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h3 class="modal-title h5 mb-0">Registrar contratação</h3>
                                        <button type="button" class="btn-close" aria-label="Fechar" @click="fecharModalContratacao"></button>
                                    </div>
                                    <form @submit.prevent="registrarContratacao">
                                        <div class="modal-body">
                                            <p class="mb-3">Confirme a contratação de <strong>{{ candidatoParaContratar?.pessoa?.nome }}</strong>.</p>
                                            <label class="form-label" for="empresa-contratante">Empresa contratante</label>
                                            <div class="position-relative">
                                                <input
                                                    id="empresa-contratante"
                                                    v-model="buscaEmpresaContratante"
                                                    class="form-control"
                                                    type="search"
                                                    placeholder="Pesquisar empresa cadastrada"
                                                    autocomplete="off"
                                                    role="combobox"
                                                    :aria-expanded="!empresaContratanteSelecionada && empresasEncontradas.length > 0"
                                                >
                                                <div v-if="!empresaContratanteSelecionada && empresasEncontradas.length" class="list-group position-absolute w-100 shadow mt-1" style="z-index: 1060; max-height: 220px; overflow-y: auto;">
                                                    <button
                                                        v-for="empresa in empresasEncontradas"
                                                        :key="empresa.cnpj"
                                                        type="button"
                                                        class="list-group-item list-group-item-action text-start"
                                                        @click="selecionarEmpresaContratante(empresa)"
                                                    >
                                                        <span class="d-block fw-semibold">{{ empresa.razao_social }}</span>
                                                        <small class="text-secondary">{{ formatarCnpj(empresa.cnpj) }}</small>
                                                    </button>
                                                </div>
                                            </div>
                                            <div v-if="empresaContratanteSelecionada" class="d-flex align-items-center justify-content-between border rounded p-2 mt-2">
                                                <span class="small">{{ buscaEmpresaContratante }}</span>
                                                <button type="button" class="btn btn-sm btn-link" @click="limparEmpresaContratante">Trocar</button>
                                            </div>
                                            <small v-else-if="!carregandoEmpresas && !empresasEncontradas.length && buscaEmpresaContratante" class="text-secondary d-block mt-2">
                                                Nenhuma empresa encontrada. Confira o cadastro em Gestão de Empresas.
                                            </small>
                                            <small v-if="carregandoEmpresas" class="text-secondary d-block mt-2">Buscando empresas...</small>
                                            <div v-if="erroContratacao" class="alert alert-danger py-2 mt-3 mb-0">{{ erroContratacao }}</div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" :disabled="salvandoContratacao" @click="fecharModalContratacao">Cancelar</button>
                                            <button type="submit" class="btn btn-primary" :disabled="salvandoContratacao || !empresaContratanteSelecionada">
                                                <span v-if="salvandoContratacao" class="spinner-border spinner-border-sm me-2"></span>
                                                Confirmar contratação
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </transition>

                    <transition name="app-modal">
                        <div
                            v-if="modalCadastroAberto"
                            class="modal fade show d-block"
                            tabindex="-1"
                            role="dialog"
                            aria-modal="true"
                        >
                            <div class="modal-dialog modal-lg modal-dialog-centered app-modal-dialog-animated">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h3 class="modal-title h5 mb-0">Novo Candidato</h3>
                                        <button type="button" class="btn-close" aria-label="Fechar" @click="fecharModalCadastro"></button>
                                    </div>
                                    <form @submit.prevent="salvarNovoCandidato">
                                        <div class="modal-body">
                                            <div v-if="mensagemErro" class="alert alert-danger py-2">
                                                {{ mensagemErro }}
                                            </div>

                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <label class="form-label">Nome</label>
                                                    <input v-model.trim="formulario.nome" type="text" class="form-control" maxlength="100" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">E-mail</label>
                                                    <input v-model.trim="formulario.email" type="email" class="form-control" maxlength="100" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Telefone</label>
                                                    <input
                                                        v-model="formulario.telefone"
                                                        type="text"
                                                        inputmode="numeric"
                                                        autocomplete="tel"
                                                        class="form-control"
                                                        maxlength="16"
                                                        required
                                                        @input="onTelefoneInput"
                                                    >
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Matrícula</label>
                                                    <input v-model="formulario.matricula" type="text" inputmode="numeric" class="form-control" maxlength="15" required @input="onMatriculaInput">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">CPF</label>
                                                    <input
                                                        v-model="formulario.cpf"
                                                        type="text"
                                                        inputmode="numeric"
                                                        autocomplete="off"
                                                        class="form-control"
                                                        maxlength="14"
                                                        required
                                                        @input="onCpfInput"
                                                    >
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Curso</label>
                                                    <input v-model.trim="formulario.curso" type="text" class="form-control" maxlength="45">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Unidade</label>
                                                    <input v-model.trim="formulario.unidade" type="text" class="form-control" maxlength="45">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Senha</label>
                                                    <input v-model="formulario.senha" type="password" class="form-control" minlength="6" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Confirmar senha</label>
                                                    <input v-model="formulario.confirmarSenha" type="password" class="form-control" minlength="6" required>
                                                </div>
                                                <div class="col-12">
                                                    <div class="form-check mt-2">
                                                        <input id="status-candidato" v-model="formulario.status" class="form-check-input" type="checkbox">
                                                        <label class="form-check-label" for="status-candidato">
                                                            Liberar acesso ao candidato após o cadastro
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" @click="fecharModalCadastro">Cancelar</button>
                                            <button type="submit" class="btn btn-primary" :disabled="salvandoCadastro">
                                                <span v-if="salvandoCadastro" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                                {{ salvandoCadastro ? 'Salvando...' : 'Salvar candidato' }}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </transition>
                    <transition name="app-modal">
                        <div v-if="modalCadastroAberto" class="modal-backdrop fade show"></div>
                    </transition>

                    <transition name="app-modal">
                        <div
                            v-if="modalCurriculosAberto"
                            class="modal fade show d-block"
                            tabindex="-1"
                            role="dialog"
                            aria-modal="true"
                            aria-labelledby="modal-curriculos-titulo"
                        >
                            <div class="modal-dialog modal-dialog-centered app-modal-dialog-animated">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h3 id="modal-curriculos-titulo" class="modal-title h5 mb-0">Baixar currículos</h3>
                                        <button type="button" class="btn-close" aria-label="Fechar" :disabled="gerandoZip" @click="fecharModalCurriculos"></button>
                                    </div>
                                    <form @submit.prevent="gerarZipCurriculos">
                                        <div class="modal-body">
                                            <p class="text-secondary mb-3">Escolha quais páginas de candidatos deseja incluir no arquivo ZIP.</p>

                                            <div v-if="erroCurriculos" id="erro-curriculos" class="alert alert-danger py-2">
                                                {{ erroCurriculos }}
                                            </div>

                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="pagina-inicial-curriculos" class="form-label">Página inicial</label>
                                                    <input
                                                        id="pagina-inicial-curriculos"
                                                        v-model="formularioCurriculos.paginaInicial"
                                                        type="number"
                                                        inputmode="numeric"
                                                        min="1"
                                                        step="1"
                                                        class="form-control"
                                                        :max="admin.alunosPaginacao.last_page"
                                                        :aria-describedby="erroCurriculos ? 'erro-curriculos' : undefined"
                                                        required
                                                    >
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="pagina-final-curriculos" class="form-label">Página final</label>
                                                    <input
                                                        id="pagina-final-curriculos"
                                                        v-model="formularioCurriculos.paginaFinal"
                                                        type="number"
                                                        inputmode="numeric"
                                                        min="1"
                                                        step="1"
                                                        class="form-control"
                                                        :max="admin.alunosPaginacao.last_page"
                                                        :aria-describedby="erroCurriculos ? 'erro-curriculos' : undefined"
                                                        required
                                                    >
                                                </div>
                                            </div>

                                            <div class="small text-secondary mt-3">
                                                <p class="mb-1">{{ admin.alunosPaginacao.per_page || 10 }} candidatos por página</p>
                                                <p class="mb-1">Páginas selecionadas: {{ paginasSelecionadasCurriculos }}</p>
                                                <p class="mb-1">Até {{ estimativaCurriculos }} currículos serão gerados.</p>
                                                <p class="mb-0">Você pode gerar até {{ limitePaginasZip }} páginas por arquivo.</p>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" :disabled="gerandoZip" @click="fecharModalCurriculos">Cancelar</button>
                                            <button type="submit" class="btn btn-primary" :disabled="gerandoZip">
                                                <span v-if="gerandoZip" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                                {{ gerandoZip ? 'Gerando currículos...' : 'Gerar ZIP' }}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </transition>
                    <transition name="app-modal">
                        <div v-if="modalCurriculosAberto" class="modal-backdrop fade show"></div>
                    </transition>

                    <p v-if="!admin.alunos.length" class="text-secondary small mb-0">
                        Nenhum candidato encontrado.
                    </p>

                    <template v-else>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr class="text-secondary small text-uppercase">
                                        <th>Candidato</th>
                                        <th>CPF</th>
                                        <th>Telefone</th>
                                        <th>Curso / Unidade</th>
                                        <th>Status</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="aluno in admin.alunos" :key="aluno.matricula">
                                        <tr>
                                            <td>
                                                <p class="fw-semibold mb-0">{{ aluno.pessoa?.nome }}</p>
                                                <p class="text-secondary small mb-0">E-mail: {{ aluno.pessoa?.email || '—' }}</p>
                                            </td>
                                            <td>{{ formatarCpf(aluno.cpf) }}</td>
                                            <td>
                                                {{ formatarTelefoneListagem(aluno.pessoa?.telefone) }}
                                            </td>
                                            <td>
                                                <p class="mb-0">{{ aluno.dados_academicos?.[0]?.curso || '—' }}</p>
                                                <p class="text-secondary small mb-0">{{ aluno.dados_academicos?.[0]?.unidade || '—' }}</p>
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
                                            <td class="text-end" style="min-width: 430px;">
                                                <div class="d-flex flex-nowrap justify-content-end gap-2">
                                                <button
                                                    class="btn btn-sm btn-outline-primary flex-shrink-0"
                                                    @click="alternarDetalhes(aluno.matricula)"
                                                >
                                                    {{ alunoExpandido === aluno.matricula ? 'Ocultar Detalhes' : 'Ver Detalhes' }}
                                                </button>
                                                <button class="btn btn-sm btn-outline-success flex-shrink-0" :disabled="salvandoContratacao" @click="abrirModalContratacao(aluno)">
                                                    <i class="bi bi-person-check me-1"></i>Contratado(a)
                                                </button>
                                                <button
                                                    class="btn btn-sm flex-shrink-0"
                                                    :class="aluno.status ? 'btn-outline-danger' : 'btn-success'"
                                                    :disabled="alterando === aluno.matricula"
                                                    @click="alternarStatus(aluno)"
                                                >
                                                    {{ aluno.status ? 'Bloquear Acesso' : 'Liberar Acesso' }}
                                                </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="alunoExpandido === aluno.matricula" :key="`detalhes-${aluno.matricula}`">
                                            <td colspan="6" class="bg-light-subtle">
                                                <div class="p-3">
                                                    <div class="row g-3">
                                                        <div class="col-12">
                                                            <small class="text-secondary d-block">Sobre mim</small>
                                                            <span>{{ aluno.informacoes_profissionais?.sobre_mim || 'Não informado' }}</span>
                                                        </div>
                                                        <div class="col-md-6 col-lg-4">
                                                            <small class="text-secondary d-block">CPF</small>
                                                            <span>{{ formatarCpf(aluno.cpf) }}</span>
                                                        </div>
                                                        <div class="col-md-6 col-lg-4">
                                                            <small class="text-secondary d-block">Cargo de interesse</small>
                                                            <span>{{ aluno.informacoes_profissionais?.cargo_de_interesse || 'Não informado' }}</span>
                                                        </div>
                                                        <div class="col-md-6 col-lg-4">
                                                            <small class="text-secondary d-block">Disponibilidade de horário</small>
                                                            <span>{{ formatarDisponibilidade(aluno.preferencias_de_trabalho?.disponibilidade_de_horario) }}</span>
                                                        </div>
                                                        <div class="col-md-6 col-lg-4">
                                                            <small class="text-secondary d-block">Região administrativa</small>
                                                            <span>{{ aluno.preferencias_de_trabalho?.regiao_administrativa || 'Não informado' }}</span>
                                                        </div>
                                                        <div class="col-md-6 col-lg-4">
                                                            <small class="text-secondary d-block">Pretensão salarial</small>
                                                            <span>{{ formatarPretensao(aluno.preferencias_de_trabalho?.pretensao_salarial) }}</span>
                                                        </div>
                                                        <div class="col-md-6 col-lg-4 d-flex align-items-end justify-content-lg-start">
                                                            <button
                                                                class="btn btn-sm btn-outline-primary"
                                                                :disabled="curriculoGerando === aluno.matricula"
                                                                @click="baixarCurriculo(aluno)"
                                                            >
                                                                <span v-if="curriculoGerando === aluno.matricula" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                                                <i v-else class="bi bi-file-earmark-arrow-down me-1"></i>
                                                                {{ curriculoGerando === aluno.matricula ? 'Gerando...' : 'Baixar currículo' }}
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <base-pagination
                            class="mt-3"
                            :current-page="admin.alunosPaginacao.current_page"
                            :last-page="admin.alunosPaginacao.last_page"
                            :per-page="admin.alunosPaginacao.per_page"
                            :total="admin.alunosPaginacao.total"
                            :from="admin.alunosPaginacao.from"
                            :to="admin.alunosPaginacao.to"
                            :loading="admin.carregando"
                            item-label="candidatos"
                            aria-label="Paginação inferior de candidatos"
                            @change="mudarPagina"
                        />
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import topbar from '../../../components/common/header.vue';
import loading from '../../../components/common/loading.vue';
import BasePagination from '../../../components/common/BasePagination.vue';
import { useAdminStore } from '../../../store/admin';
import adminService from '../../../services/adminServices';
import { useToast } from '../../../composables/useToast';
import { formatarTelefone, somenteNumeros } from '../../../utils/telefone';
import { formatarFaixaPretensaoSalarial } from '../../../utils/faixasPretensaoSalarial';

const admin = useAdminStore();
const toast = useToast();
const carregouUmaVez = ref(false);
const busca = ref('');
const statusFiltro = ref('');
const unidadeFiltro = ref('');
const alterando = ref(null);
const curriculoGerando = ref(null);
const alunoExpandido = ref(null);
const modalContratacaoAberto = ref(false);
const candidatoParaContratar = ref(null);
const empresasEncontradas = ref([]);
const buscaEmpresaContratante = ref('');
const empresaContratanteSelecionada = ref('');
const carregandoEmpresas = ref(false);
const salvandoContratacao = ref(false);
const erroContratacao = ref('');
const modalCadastroAberto = ref(false);
const modalCurriculosAberto = ref(false);
const gerandoZip = ref(false);
const geracaoRapidaEmAndamento = ref(false);
const erroCurriculos = ref('');
const limitePaginasZip = 10;
const salvandoCadastro = ref(false);
const mensagemErro = ref('');
const formularioInicial = () => ({
    nome: '',
    email: '',
    telefone: '',
    matricula: '',
    cpf: '',
    curso: '',
    unidade: '',
    senha: '',
    confirmarSenha: '',
    status: true,
});
const formulario = reactive(formularioInicial());
const formularioCurriculos = reactive({
    paginaInicial: 1,
    paginaFinal: 1,
});
onMounted(async () => {
    try {
        await Promise.all([
            admin.carregarUnidadesAlunos(),
            carregarAlunosFiltrados(),
        ]);
    } finally {
        carregouUmaVez.value = true;
    }
});

let temporizadorFiltro = null;
watch([busca, statusFiltro, unidadeFiltro], () => {
    clearTimeout(temporizadorFiltro);
    temporizadorFiltro = setTimeout(() => {
        carregarAlunosFiltrados(1);
    }, 300);
});

let temporizadorBuscaEmpresa = null;
watch(buscaEmpresaContratante, (termo) => {
    clearTimeout(temporizadorBuscaEmpresa);
    const empresaSelecionada = empresasEncontradas.value.find((empresa) => empresa.cnpj === empresaContratanteSelecionada.value);
    if (empresaSelecionada?.razao_social === termo) return;
    empresaContratanteSelecionada.value = '';
    temporizadorBuscaEmpresa = setTimeout(() => carregarEmpresasParaContratacao(termo), 250);
});

function parametrosFiltro(pagina = admin.alunosPaginacao.current_page || 1) {
    return {
        page: pagina,
        per_page: 10,
        ...(busca.value.trim() ? { busca: busca.value.trim() } : {}),
        ...(statusFiltro.value !== '' ? { status: statusFiltro.value } : {}),
        ...(unidadeFiltro.value !== '' ? { unidade: unidadeFiltro.value } : {}),
    };
}

function parametrosCurriculos(paginaInicial, paginaFinal) {
    const { page, per_page, ...filtros } = parametrosFiltro(admin.alunosPaginacao.current_page || 1);

    return {
        ...filtros,
        pagina_inicial: paginaInicial,
        pagina_final: paginaFinal,
    };
}

const paginasSelecionadasCurriculos = computed(() => {
    const inicio = Number(formularioCurriculos.paginaInicial);
    const fim = Number(formularioCurriculos.paginaFinal);

    if (!Number.isInteger(inicio) || !Number.isInteger(fim) || inicio < 1 || fim < inicio) {
        return 0;
    }

    return (fim - inicio) + 1;
});

const estimativaCurriculos = computed(() => paginasSelecionadasCurriculos.value * Number(admin.alunosPaginacao.per_page || 10));

async function carregarAlunosFiltrados(pagina = admin.alunosPaginacao.current_page || 1) {
    await admin.carregarAlunos(parametrosFiltro(pagina));

    if (admin.erro) {
        toast.error(admin.erro);
    }
}

function mudarPagina(pagina) {
    if (pagina < 1 || pagina > admin.alunosPaginacao.last_page || pagina === admin.alunosPaginacao.current_page || admin.carregando) {
        return;
    }

    alunoExpandido.value = null;
    carregarAlunosFiltrados(pagina);
}

async function carregarEmpresasParaContratacao(termo = '') {
    carregandoEmpresas.value = true;
    try {
        const { data } = await adminService.listarEmpresas({ busca: termo.trim(), per_page: 10 });
        empresasEncontradas.value = data.data || [];
    } catch (error) {
        empresasEncontradas.value = [];
        erroContratacao.value = 'Não foi possível carregar as empresas.';
    } finally {
        carregandoEmpresas.value = false;
    }
}

function selecionarEmpresaContratante(empresa) {
    empresaContratanteSelecionada.value = empresa.cnpj;
    buscaEmpresaContratante.value = empresa.razao_social;
}

function limparEmpresaContratante() {
    empresaContratanteSelecionada.value = '';
    buscaEmpresaContratante.value = '';
}

function formatarCnpj(cnpj) {
    const digitos = String(cnpj ?? '').replace(/\D/g, '').padStart(14, '0');
    return digitos.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
}

function abrirModalContratacao(aluno) {
    candidatoParaContratar.value = aluno;
    empresaContratanteSelecionada.value = '';
    buscaEmpresaContratante.value = '';
    erroContratacao.value = '';
    modalContratacaoAberto.value = true;
    carregarEmpresasParaContratacao();
}

function fecharModalContratacao() {
    if (salvandoContratacao.value) return;
    modalContratacaoAberto.value = false;
    candidatoParaContratar.value = null;
}

async function registrarContratacao() {
    if (!candidatoParaContratar.value || !empresaContratanteSelecionada.value) return;
    salvandoContratacao.value = true;
    erroContratacao.value = '';
    try {
        await adminService.registrarContratacao(candidatoParaContratar.value.matricula, {
            empresa_cnpj: empresaContratanteSelecionada.value,
        });
        toast.success('Contratação registrada. O candidato foi movido para Candidatos Contratados.');
        fecharModalContratacao();
        await carregarAlunosFiltrados(1);
    } catch (error) {
        erroContratacao.value = error.response?.data?.message || 'Não foi possível registrar a contratação.';
    } finally {
        salvandoContratacao.value = false;
    }
}

async function sincronizar() {
    await carregarAlunosFiltrados();
}

function limparFormulario() {
    Object.assign(formulario, formularioInicial());
}

function exibirMensagemSucesso(texto) {
    toast.success(texto);
}

function abrirModalCadastro() {
    mensagemErro.value = '';
    modalCadastroAberto.value = true;
}

function fecharModalCadastro({ limpar = true } = {}) {
    if (salvandoCadastro.value) return;
    modalCadastroAberto.value = false;
    mensagemErro.value = '';
    if (limpar) {
        limparFormulario();
    }
}

function abrirModalCurriculos() {
    const paginaAtual = admin.alunosPaginacao.current_page || 1;
    formularioCurriculos.paginaInicial = paginaAtual;
    formularioCurriculos.paginaFinal = paginaAtual;
    erroCurriculos.value = '';
    modalCurriculosAberto.value = true;
}

function fecharModalCurriculos() {
    if (gerandoZip.value) return;
    modalCurriculosAberto.value = false;
    erroCurriculos.value = '';
}

function obterMensagemErro(error) {
    if (error?.response?.data?.errors) {
        const primeiroCampo = Object.values(error.response.data.errors)[0];
        if (Array.isArray(primeiroCampo) && primeiroCampo.length) {
            return primeiroCampo[0];
        }
    }

    if (error?.response?.data?.message) {
        return error.response.data.message;
    }

    if (error?.response?.data?.error) {
        return error.response.data.error;
    }

    return 'Não foi possível cadastrar o candidato. Verifique os dados e tente novamente.';
}

function obterMensagemErroDownload(error) {
    if (error?.response?.data instanceof Blob) {
        return 'Não foi possível gerar o arquivo de currículos. Tente novamente.';
    }

    if (error?.response?.data?.errors) {
        const primeiroCampo = Object.values(error.response.data.errors)[0];
        if (Array.isArray(primeiroCampo) && primeiroCampo.length) {
            return primeiroCampo[0];
        }
    }

    return error?.response?.data?.message || 'Não foi possível gerar o arquivo de currículos. Tente novamente.';
}

function validarIntervaloCurriculos(inicio, fim) {
    if (!Number.isInteger(inicio) || inicio < 1) {
        return 'A página inicial deve ser um número inteiro maior ou igual a 1.';
    }

    if (!Number.isInteger(fim) || fim < 1) {
        return 'A página final deve ser um número inteiro maior ou igual a 1.';
    }

    if (inicio > fim) {
        return 'A página inicial deve ser menor ou igual à página final.';
    }

    if (fim > admin.alunosPaginacao.last_page) {
        return 'A página final não pode ser maior que a última página disponível.';
    }

    if (((fim - inicio) + 1) > limitePaginasZip) {
        return `Você pode gerar até ${limitePaginasZip} páginas por arquivo.`;
    }

    return '';
}

function removerMascara(valor) {
    return String(valor ?? '').replace(/\D/g, '');
}

function limitarDigitos(valor, limite = 11) {
    return somenteNumeros(valor).slice(0, limite);
}

function formatarCpf(valor) {
    const digitos = limitarDigitos(valor, 11);

    if (digitos.length <= 3) return digitos;
    if (digitos.length <= 6) return `${digitos.slice(0, 3)}.${digitos.slice(3)}`;
    if (digitos.length <= 9) return `${digitos.slice(0, 3)}.${digitos.slice(3, 6)}.${digitos.slice(6)}`;

    return `${digitos.slice(0, 3)}.${digitos.slice(3, 6)}.${digitos.slice(6, 9)}-${digitos.slice(9, 11)}`;
}

function formatarTelefoneListagem(valor) {
    if (!valor) {
        return 'Não informado';
    }

    return formatarTelefone(valor) || 'Não informado';
}

function censurarCpf(valor) {
    const digitos = limitarDigitos(valor, 11);

    if (digitos.length !== 11) {
        return formatarCpf(digitos);
    }

    return `***.${digitos.slice(3, 6)}.***-**`;
}

function onCpfInput(evento) {
    const valorFormatado = formatarCpf(evento.target.value);
    formulario.cpf = valorFormatado;
    evento.target.value = valorFormatado;
}

function onTelefoneInput(evento) {
    const valorFormatado = formatarTelefone(evento.target.value);
    formulario.telefone = valorFormatado;
    evento.target.value = valorFormatado;
}

function onMatriculaInput(evento) {
    const valor = somenteNumeros(evento.target.value).slice(0, 15);
    formulario.matricula = valor;
    evento.target.value = valor;
}

async function salvarNovoCandidato() {
    mensagemErro.value = '';

    if (formulario.senha !== formulario.confirmarSenha) {
        mensagemErro.value = 'As senhas informadas não coincidem.';
        toast.error(mensagemErro.value);
        return;
    }

    salvandoCadastro.value = true;

    try {
        await admin.cadastrarAluno({
            nome: formulario.nome,
            email: formulario.email,
            telefone: somenteNumeros(formulario.telefone),
            matricula: somenteNumeros(formulario.matricula).slice(0, 15),
            cpf: removerMascara(formulario.cpf),
            curso: formulario.curso,
            unidade: formulario.unidade,
            senha: formulario.senha,
            status: formulario.status,
        });

        await admin.carregarUnidadesAlunos();
        await carregarAlunosFiltrados();
        modalCadastroAberto.value = false;
        fecharModalCadastro({ limpar: false });
        limparFormulario();
        exibirMensagemSucesso('Candidato cadastrado com sucesso.');
    } catch (error) {
        mensagemErro.value = obterMensagemErro(error);
        toast.error(mensagemErro.value);
    } finally {
        salvandoCadastro.value = false;
    }
}

async function alternarStatus(aluno) {
    alterando.value = aluno.matricula;
    const novoStatus = !aluno.status;
    try {
        await admin.atualizarStatusAluno(aluno.matricula, novoStatus);
        await carregarAlunosFiltrados();
        toast.info(`Acesso do candidato ${novoStatus ? 'liberado' : 'bloqueado'} com sucesso.`);
    } catch (error) {
        toast.error('Não foi possível alterar o status do candidato.');
    } finally {
        alterando.value = null;
    }
}

async function baixarCurriculo(aluno) {
    curriculoGerando.value = aluno.matricula;

    try {
        const response = await adminService.baixarCurriculoAluno(aluno.matricula);
        const blob = new Blob([response.data], { type: 'application/pdf' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = nomeArquivoCurriculo(response, aluno);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        toast.error('Não foi possível gerar o currículo deste candidato.');
    } finally {
        curriculoGerando.value = null;
    }
}

async function baixarCurriculosPaginaAtual() {
    const paginaAtual = Number(admin.alunosPaginacao.current_page || 1);
    geracaoRapidaEmAndamento.value = true;
    await executarDownloadZip(paginaAtual, paginaAtual, { fecharModalAoConcluir: false });
    geracaoRapidaEmAndamento.value = false;
}

async function gerarZipCurriculos() {
    const inicio = Number(formularioCurriculos.paginaInicial);
    const fim = Number(formularioCurriculos.paginaFinal);
    await executarDownloadZip(inicio, fim, { fecharModalAoConcluir: true });
}

async function executarDownloadZip(inicio, fim, { fecharModalAoConcluir = false } = {}) {
    erroCurriculos.value = validarIntervaloCurriculos(inicio, fim);

    if (erroCurriculos.value) {
        toast.error(erroCurriculos.value);
        return;
    }

    gerandoZip.value = true;

    try {
        const response = await adminService.baixarCurriculosAlunos(parametrosCurriculos(inicio, fim));
        const blob = new Blob([response.data], { type: 'application/zip' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = nomeArquivoZip(response, inicio, fim);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);

        if (fecharModalAoConcluir) {
            modalCurriculosAberto.value = false;
        }
    } catch (error) {
        erroCurriculos.value = obterMensagemErroDownload(error);
        toast.error(erroCurriculos.value);
    } finally {
        gerandoZip.value = false;
    }
}

function nomeArquivoCurriculo(response, aluno) {
    const disposicao = response.headers?.['content-disposition'] || '';
    const encontrado = disposicao.match(/filename="?([^";]+)"?/i);

    if (encontrado?.[1]) {
        return encontrado[1];
    }

    const nome = (aluno.pessoa?.nome || 'Candidato')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/gi, '_')
        .replace(/^_+|_+$/g, '');

    return `Curriculo_${nome || 'Candidato'}.pdf`;
}

function nomeArquivoZip(response, inicio, fim) {
    const disposicao = response.headers?.['content-disposition'] || '';
    const encontrado = disposicao.match(/filename="?([^";]+)"?/i);

    if (encontrado?.[1]) {
        return encontrado[1];
    }

    return inicio === fim ? `Curriculos_Pagina_${inicio}.zip` : `Curriculos_Paginas_${inicio}_a_${fim}.zip`;
}

function alternarDetalhes(matricula) {
    alunoExpandido.value = alunoExpandido.value === matricula ? null : matricula;
}

function formatarPretensao(valor) {
    return formatarFaixaPretensaoSalarial(valor, 'Não informado');
}

function formatarDisponibilidade(valor) {
    const lista = Array.isArray(valor) ? valor : [valor].filter(Boolean);
    return lista.length ? lista.join(' + ') : 'Não informado';
}

</script>
