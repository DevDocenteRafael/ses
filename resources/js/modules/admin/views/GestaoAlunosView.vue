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
                        <div class="d-flex align-items-stretch flex-wrap gap-2 w-100 justify-content-md-end">
                            <div class="input-group flex-grow-1 position-relative" style="min-width: 240px;">
                                 <input
                                     v-model="busca"
                                     type="text"
                                     class="form-control"
                                     placeholder="Filtrar por nome ou CPF"
                                     autocomplete="off"
                                     @focus="buscaDropdownAberto = Boolean(busca)"
                                     @input="onBuscaCpfInput"
                                     @blur="fecharSugestaoBusca"
                                 >
                                <span class="input-group-text bg-primary text-white"><i class="bi bi-search"></i></span>
                                <div v-if="buscaDropdownAberto && busca && candidatosBuscaSugeridos.length" class="list-group position-absolute w-100 shadow" style="z-index: 1060; top: 100%; max-height: 220px; overflow-y: auto;">
                                    <button
                                        v-for="aluno in candidatosBuscaSugeridos"
                                        :key="aluno.matricula"
                                        type="button"
                                        class="list-group-item list-group-item-action text-start"
                                        @mousedown.prevent="selecionarSugestaoBusca(aluno)"
                                    >
                                        <span class="d-block fw-semibold">{{ aluno.pessoa?.nome || 'Candidato' }}</span>
                                        <small class="text-secondary">CPF {{ formatarCpf(aluno.cpf) }}</small>
                                    </button>
                                </div>
                            </div>
                            <div class="position-relative" style="min-width: 180px; max-width: 200px;">
                                <input
                                    v-model="statusBusca"
                                    class="form-control"
                                    type="search"
                                    placeholder="Pesquisar status"
                                    aria-label="Pesquisar status do candidato"
                                    autocomplete="off"
                                    @focus="statusDropdownAberto = true"
                                    @input="statusFiltro = ''; statusDropdownAberto = true"
                                    @blur="fecharSugestaoStatus"
                                >
                                <div v-if="statusDropdownAberto && statusOpcoesFiltradas.length" class="list-group position-absolute w-100 shadow mt-1" style="z-index: 1060;">
                                    <button v-for="opcao in statusOpcoesFiltradas" :key="opcao.value" type="button" class="list-group-item list-group-item-action text-start" @mousedown.prevent="selecionarStatus(opcao)">
                                        {{ opcao.label }}
                                    </button>
                                </div>
                            </div>
                            <div class="position-relative" style="min-width: 200px; max-width: 240px;">
                                <input
                                    v-model="unidadeFiltro"
                                    class="form-control"
                                    type="search"
                                    placeholder="Pesquisar unidade"
                                    aria-label="Pesquisar unidade"
                                    autocomplete="off"
                                    @focus="unidadeDropdownAberto = true"
                                    @blur="fecharSugestaoUnidade"
                                >
                                <div v-if="unidadeDropdownAberto && unidadesFiltradas.length" class="list-group position-absolute w-100 shadow mt-1" style="z-index: 1060; max-height: 220px; overflow-y: auto;">
                                    <button v-for="unidade in unidadesFiltradas" :key="unidade" type="button" class="list-group-item list-group-item-action text-start" @mousedown.prevent="selecionarUnidade(unidade)">
                                        {{ unidade }}
                                    </button>
                                </div>
                            </div>
                            <div class="position-relative" style="min-width: 200px; max-width: 240px;">
                                <input
                                    v-model="cursoFiltro"
                                    class="form-control"
                                    type="search"
                                    placeholder="Pesquisar curso"
                                    aria-label="Pesquisar curso"
                                    autocomplete="off"
                                    @focus="abrirSugestoesCurso"
                                    @blur="fecharSugestaoCurso"
                                >
                                <div v-if="cursoDropdownAberto && cursosEncontrados.length" class="list-group position-absolute w-100 shadow mt-1" style="z-index: 1060; max-height: 220px; overflow-y: auto;">
                                    <button v-for="curso in cursosEncontrados" :key="curso" type="button" class="list-group-item list-group-item-action text-start" @mousedown.prevent="selecionarCurso(curso)">
                                        {{ curso }}
                                    </button>
                                </div>
                            </div>
                            <div class="d-flex flex-nowrap align-items-stretch gap-2">
                                <button
                                    class="btn btn-outline-secondary text-nowrap flex-shrink-0"
                                    type="button"
                                    :disabled="admin.carregando || gerandoZip || gerandoRelatorioPdf || !admin.alunos.length"
                                    @click="baixarCandidatosFiltradosPdf"
                                >
                                    <span v-if="gerandoRelatorioPdf" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    <i v-else class="bi bi-file-earmark-pdf me-1"></i>
                                    {{ gerandoRelatorioPdf ? 'Preparando PDF...' : 'Relatório de Busca' }}
                                </button>
                                <button
                                    class="btn btn-outline-primary text-nowrap flex-shrink-0"
                                    type="button"
                                    :disabled="admin.carregando || gerandoZip || !admin.alunos.length"
                                    @click="abrirModalCurriculos"
                                >
                                    <i class="bi bi-download me-1"></i>
                                    Baixar currículos
                                </button>
                            </div>
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

                    <download-curriculos-modal
                        v-model:pagina-inicial-model="formularioCurriculos.paginaInicial"
                        v-model:pagina-final-model="formularioCurriculos.paginaFinal"
                        :show="modalCurriculosAberto"
                        :loading="gerandoZip"
                        :erro="erroCurriculos"
                        :per-page="Number(admin.alunosPaginacao.per_page || 10)"
                        :last-page="Number(admin.alunosPaginacao.last_page || 1)"
                        :limite-paginas="limitePaginasZip"
                        @fechar="fecharModalCurriculos"
                        @gerar="gerarZipCurriculos"
                    />

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
                                                    :class="classeEstadoAluno(aluno)"
                                                >
                                                    {{ rotuloEstadoAluno(aluno) }}
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
                                                <button class="btn btn-sm btn-outline-success flex-shrink-0" :disabled="salvandoContratacao || alunoContratado(aluno)" @click="abrirModalContratacao(aluno)">
                                                    <i class="bi bi-person-check me-1"></i>{{ alunoContratado(aluno) ? 'Contratado' : 'Contratado(a)' }}
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
import DownloadCurriculosModal from '../../../components/common/DownloadCurriculosModal.vue';
import { useAdminStore } from '../../../store/admin';
import adminService from '../../../services/adminServices';
import { useToast } from '../../../composables/useToast';
import { formatarTelefone, somenteNumeros } from '../../../utils/telefone';
import { formatarFaixaPretensaoSalarial } from '../../../utils/faixasPretensaoSalarial';
import { formatarDisponibilidadeHorario } from '../../../utils/listasPtBr';
import {
    formatarCpf as formatarCpfDocumento,
    formatarCnpj as formatarCnpjDocumento,
    mascararBuscaCpf,
    normalizarBuscaDocumentoOuTexto,
    somenteDigitos,
} from '../../../utils/documentos';
import {
    LIMITE_PAGINAS_CURRICULOS_ZIP,
    baixarBlobZipCurriculos,
    obterMensagemErroDownloadCurriculos,
    validarIntervaloCurriculos as validarIntervaloCurriculosCompartilhado,
} from '../../../utils/downloadCurriculosZip';

const admin = useAdminStore();
const toast = useToast();
const carregouUmaVez = ref(false);
const busca = ref('');
const buscaDropdownAberto = ref(false);
const statusFiltro = ref('');
const statusBusca = ref('');
const statusDropdownAberto = ref(false);
const unidadeFiltro = ref('');
const unidadeDropdownAberto = ref(false);
const cursoFiltro = ref('');
const cursoDropdownAberto = ref(false);
const cursosEncontrados = ref([]);
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
const gerandoRelatorioPdf = ref(false);
const erroCurriculos = ref('');
const limitePaginasZip = LIMITE_PAGINAS_CURRICULOS_ZIP;
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
const statusOpcoes = [
    { value: '', label: 'Todos os status' },
    { value: '1', label: 'Liberado' },
    { value: '0', label: 'Bloqueado' },
    { value: 'inativo', label: 'Bloqueado por inatividade' },
    { value: 'contratado', label: 'Contratado' },
];
const statusOpcoesFiltradas = computed(() => {
    const termo = statusBusca.value.trim().toLocaleLowerCase('pt-BR');
    return statusOpcoes.filter((opcao) => opcao.label.toLocaleLowerCase('pt-BR').includes(termo));
});
const unidadesFiltradas = computed(() => {
    const termo = unidadeFiltro.value.trim().toLocaleLowerCase('pt-BR');
    return admin.unidadesAlunos
        .filter((unidade) => !termo || unidade.toLocaleLowerCase('pt-BR').includes(termo))
        .slice(0, 10);
});
const candidatosBuscaSugeridos = computed(() => {
    const termo = busca.value.trim().toLocaleLowerCase('pt-BR');
    const numeros = somenteNumeros(termo);
    if (!termo) return [];

    return admin.alunos.filter((aluno) => {
        const nome = String(aluno.pessoa?.nome || '').toLocaleLowerCase('pt-BR');
        const cpf = somenteNumeros(aluno.cpf);
        return nome.includes(termo) || (numeros && cpf.includes(numeros));
    }).slice(0, 10);
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
watch([busca, statusFiltro, unidadeFiltro, cursoFiltro], () => {
    clearTimeout(temporizadorFiltro);
    temporizadorFiltro = setTimeout(() => {
        carregarAlunosFiltrados(1);
    }, 300);
});

let temporizadorCursos = null;
let requisicaoCursos = 0;
watch(cursoFiltro, (termo) => {
    clearTimeout(temporizadorCursos);
    temporizadorCursos = setTimeout(() => carregarSugestoesCurso(termo), 200);
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
    const buscaNormalizada = normalizarBuscaDocumentoOuTexto(busca.value);

    return {
        page: pagina,
        per_page: 10,
        ...(buscaNormalizada ? { busca: buscaNormalizada } : {}),
        ...(statusFiltro.value !== '' ? { status: statusFiltro.value } : {}),
        ...(unidadeFiltro.value !== '' ? { unidade: unidadeFiltro.value } : {}),
        ...(cursoFiltro.value.trim() ? { curso: cursoFiltro.value.trim() } : {}),
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

function selecionarSugestaoBusca(aluno) {
    busca.value = aluno.pessoa?.nome || aluno.cpf || '';
    buscaDropdownAberto.value = false;
}

function fecharSugestaoBusca() {
    setTimeout(() => { buscaDropdownAberto.value = false; }, 150);
}

function selecionarStatus(opcao) {
    statusFiltro.value = opcao.value;
    statusBusca.value = opcao.value ? opcao.label : '';
    statusDropdownAberto.value = false;
}

function fecharSugestaoStatus() {
    setTimeout(() => { statusDropdownAberto.value = false; }, 150);
}

function selecionarUnidade(unidade) {
    unidadeFiltro.value = unidade;
    unidadeDropdownAberto.value = false;
}

function fecharSugestaoUnidade() {
    setTimeout(() => { unidadeDropdownAberto.value = false; }, 150);
}

function abrirSugestoesCurso() {
    cursoDropdownAberto.value = true;
    carregarSugestoesCurso(cursoFiltro.value);
}

function selecionarCurso(curso) {
    cursoFiltro.value = curso;
    cursoDropdownAberto.value = false;
}

function fecharSugestaoCurso() {
    setTimeout(() => { cursoDropdownAberto.value = false; }, 150);
}

async function carregarSugestoesCurso(termo = '') {
    const requisicao = ++requisicaoCursos;
    try {
        const { data } = await adminService.listarCursosAlunos(termo.trim());
        if (requisicao === requisicaoCursos) {
            cursosEncontrados.value = Array.isArray(data) ? data : [];
        }
    } catch (error) {
        if (requisicao === requisicaoCursos) cursosEncontrados.value = [];
    }
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
    return formatarCnpjDocumento(String(cnpj ?? '').padStart(14, '0'));
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

function validarIntervaloCurriculos(inicio, fim) {
    return validarIntervaloCurriculosCompartilhado(inicio, fim, admin.alunosPaginacao.last_page, limitePaginasZip);
}

function removerMascara(valor) {
    return somenteDigitos(valor);
}

function limitarDigitos(valor, limite = 11) {
    return somenteDigitos(valor).slice(0, limite);
}

function formatarCpf(valor) {
    return formatarCpfDocumento(valor);
}

function formatarTelefoneListagem(valor) {
    if (!valor) {
        return 'Não informado';
    }

    return formatarTelefone(valor) || 'Não informado';
}

function alunoContratado(aluno) {
    return aluno?.estado_efetivo === 'CONTRATADO';
}

function rotuloEstadoAluno(aluno) {
    if (aluno?.estado_efetivo_rotulo) {
        return aluno.estado_efetivo_rotulo;
    }

    return aluno?.status ? 'Liberado' : 'Bloqueado';
}

function classeEstadoAluno(aluno) {
    switch (aluno?.estado_efetivo) {
        case 'CONTRATADO':
            return 'text-bg-primary-subtle text-primary-emphasis';
        case 'BLOQUEADO_MANUALMENTE':
            return 'text-bg-danger-subtle text-danger-emphasis';
        case 'BLOQUEADO_POR_INATIVIDADE':
            return 'text-bg-warning-subtle text-warning-emphasis';
        case 'ATIVO':
            return 'text-bg-success-subtle text-success-emphasis';
        default:
            return aluno?.status
                ? 'text-bg-success-subtle text-success-emphasis'
                : 'text-bg-danger-subtle text-danger-emphasis';
    }
}

function rotuloFiltroStatusExportacao(status) {
    const opcao = statusOpcoes.find((item) => item.value === status);
    return opcao?.value ? `Status: ${opcao.label}` : null;
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

function onBuscaCpfInput(evento) {
    const valorFormatado = mascararBuscaCpf(evento.target.value);
    busca.value = valorFormatado;
    evento.target.value = valorFormatado;
    buscaDropdownAberto.value = true;
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

async function baixarCandidatosFiltradosPdf() {
    const filtrosExportacao = { ...parametrosFiltro(1) };
    clearTimeout(temporizadorFiltro);
    const janelaImpressao = window.open('', '_blank');
    if (!janelaImpressao) {
        toast.error('Permita a abertura da janela para preparar o PDF.');
        return;
    }

    gerandoRelatorioPdf.value = true;
    janelaImpressao.document.write('<!doctype html><html><head><title>Preparando lista de candidatos</title></head><body><p style="font:16px Arial;padding:24px">Preparando a lista para impressão...</p></body></html>');
    janelaImpressao.document.close();

    try {
        const respostaInicial = await adminService.listarAlunos({ ...filtrosExportacao, page: 1 });
        const paginacao = respostaInicial.data;
        const candidatos = [...(paginacao.data || [])];

        for (let inicio = 2; inicio <= paginacao.last_page; inicio += 10) {
            const paginas = Array.from({ length: Math.min(10, paginacao.last_page - inicio + 1) }, (_, indice) => inicio + indice);
            const respostas = await Promise.all(paginas.map((pagina) =>
                adminService.listarAlunos({ ...filtrosExportacao, page: pagina })
            ));
            respostas.forEach(({ data }) => candidatos.push(...(data.data || [])));
        }

        const resumoFiltros = [
            filtrosExportacao.busca ? `Busca: ${filtrosExportacao.busca}` : null,
            rotuloFiltroStatusExportacao(filtrosExportacao.status),
            filtrosExportacao.unidade ? `Unidade: ${filtrosExportacao.unidade}` : null,
            filtrosExportacao.curso ? `Curso: ${filtrosExportacao.curso}` : null,
        ].filter(Boolean).join(' | ') || 'Sem filtros adicionais';

        const linhas = candidatos.map((aluno) => `
            <tr>
                <td>${escaparHtml(aluno.pessoa?.nome || '—')}</td>
                <td>${escaparHtml(formatarCpf(aluno.cpf) || '—')}</td>
                <td>${escaparHtml(formatarTelefoneListagem(aluno.pessoa?.telefone))}</td>
                <td>${escaparHtml(aluno.dados_academicos?.[0]?.curso || '—')}</td>
                <td>${escaparHtml(aluno.dados_academicos?.[0]?.unidade || '—')}</td>
            </tr>
        `).join('');
        const titulo = `Candidatos cadastrados - ${new Date().toLocaleDateString('pt-BR')}`;

        janelaImpressao.document.open();
        janelaImpressao.document.write(`<!doctype html>
            <html lang="pt-BR">
                <head>
                    <meta charset="utf-8">
                    <title>${escaparHtml(titulo)}</title>
                    <style>
                        @page { size: landscape; margin: 14mm; }
                        body { color: #212529; font: 12px Arial, sans-serif; }
                        h1 { color: #163f70; font-size: 20px; margin: 0 0 6px; }
                        p { color: #5c6670; margin: 0 0 16px; }
                        table { border-collapse: collapse; width: 100%; }
                        th, td { border: 1px solid #cbd2d9; padding: 8px; text-align: left; }
                        th { background: #edf2f7; color: #163f70; }
                        tr { break-inside: avoid; }
                        footer { color: #6c757d; margin-top: 12px; }
                    </style>
                </head>
                <body>
                    <h1>Candidatos Cadastrados</h1>
                    <p>${escaparHtml(resumoFiltros)} · ${candidatos.length} candidato(s) · Gerado em ${new Date().toLocaleString('pt-BR')}</p>
                    <table>
                        <thead><tr><th>Candidato</th><th>CPF</th><th>Telefone</th><th>Curso</th><th>Unidade</th></tr></thead>
                        <tbody>${linhas || '<tr><td colspan="5">Nenhum candidato encontrado.</td></tr>'}</tbody>
                    </table>
                    <footer>Senac DF · Gestão dos Candidatos</footer>
                    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));<\/script>
                </body>
            </html>`);
        janelaImpressao.document.close();
    } catch (error) {
        janelaImpressao.close();
        toast.error('Não foi possível preparar o PDF com os filtros selecionados.');
    } finally {
        gerandoRelatorioPdf.value = false;
    }
}

function escaparHtml(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, (caractere) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    })[caractere]);
}

async function gerarZipCurriculos({ paginaInicial, paginaFinal } = {}) {
    const inicio = Number(paginaInicial ?? formularioCurriculos.paginaInicial);
    const fim = Number(paginaFinal ?? formularioCurriculos.paginaFinal);
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
        baixarBlobZipCurriculos(response, inicio, fim);

        if (fecharModalAoConcluir) {
            modalCurriculosAberto.value = false;
        }
    } catch (error) {
        erroCurriculos.value = obterMensagemErroDownloadCurriculos(error);
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

function alternarDetalhes(matricula) {
    alunoExpandido.value = alunoExpandido.value === matricula ? null : matricula;
}

function formatarPretensao(valor) {
    return formatarFaixaPretensaoSalarial(valor, 'Não informado');
}

function formatarDisponibilidade(valor) {
    return formatarDisponibilidadeHorario(valor, 'Não informado');
}

</script>
