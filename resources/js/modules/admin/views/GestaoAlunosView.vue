<template>
    <div>
        <topbar titulo="Gestão dos Candidatos" subtitulo="Controle de acesso e sincronização de candidatos (FR37)">
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
                        <div class="d-flex align-items-stretch flex-wrap gap-2 w-100 justify-content-md-end" style="max-width: 520px;">
                            <div class="input-group flex-grow-1" style="min-width: 240px;">
                                <input
                                    v-model="busca"
                                    type="text"
                                    class="form-control"
                                    placeholder="Filtrar por nome ou CPF"
                                >
                                <span class="input-group-text bg-primary text-white"><i class="bi bi-search"></i></span>
                            </div>
                            <button class="btn btn-primary" type="button" @click="abrirModalCadastro">
                                <i class="bi bi-plus-lg me-1"></i>
                                Novo Candidato
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="mensagemSucesso"
                        class="alert alert-success py-2 px-3 mb-3 d-inline-flex align-items-center gap-2"
                        role="status"
                        aria-live="polite"
                    >
                        <i class="bi bi-check-circle"></i>
                        <span>{{ mensagemSucesso }}</span>
                    </div>

                    <transition name="app-modal">
                        <div
                            v-if="modalCadastroAberto"
                            class="modal fade show d-block"
                            tabindex="-1"
                            role="dialog"
                            aria-modal="true"
                            @click.self="fecharModalCadastro"
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

                    <p v-if="!alunosFiltrados.length" class="text-secondary small mb-0">
                        Nenhum candidato encontrado.
                    </p>

                    <div v-else class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr class="text-secondary small text-uppercase">
                                    <th>Candidato</th>
                                    <th>CPF</th>
                                    <th>Curso / Unidade</th>
                                    <th>Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="aluno in alunosFiltrados" :key="aluno.matricula">
                                    <tr>
                                        <td>
                                            <p class="fw-semibold mb-0">{{ aluno.pessoa?.nome }}</p>
                                            <p class="text-secondary small mb-0">E-mail: {{ aluno.pessoa?.email || '—' }}</p>
                                        </td>
                                        <td>{{ formatarCpf(aluno.cpf) }}</td>
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
                                        <td class="text-end">
                                            <button
                                                class="btn btn-sm btn-outline-primary me-2"
                                                @click="alternarDetalhes(aluno.matricula)"
                                            >
                                                {{ alunoExpandido === aluno.matricula ? 'Ocultar Detalhes' : 'Ver Detalhes' }}
                                            </button>
                                            <button
                                                class="btn btn-sm"
                                                :class="aluno.status ? 'btn-outline-danger' : 'btn-success'"
                                                :disabled="alterando === aluno.matricula"
                                                @click="alternarStatus(aluno)"
                                            >
                                                {{ aluno.status ? 'Bloquear Acesso' : 'Liberar Acesso' }}
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="alunoExpandido === aluno.matricula" :key="`detalhes-${aluno.matricula}`">
                                        <td colspan="5" class="bg-light-subtle">
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
                                                        <span>{{ aluno.preferencias_de_trabalho?.disponibilidade_de_horario || 'Não informado' }}</span>
                                                    </div>
                                                    <div class="col-md-6 col-lg-4">
                                                        <small class="text-secondary d-block">Região administrativa</small>
                                                        <span>{{ aluno.preferencias_de_trabalho?.regiao_administrativa || 'Não informado' }}</span>
                                                    </div>
                                                    <div class="col-md-6 col-lg-4">
                                                        <small class="text-secondary d-block">Pretensão salarial</small>
                                                        <span>{{ formatarPretensao(aluno.preferencias_de_trabalho?.pretensao_salarial) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import topbar from '../../../components/common/header.vue';
import loading from '../../../components/common/loading.vue';
import { useAdminStore } from '../../../store/admin';
import { formatarTelefone, somenteNumeros } from '../../../utils/telefone';

const DURACAO_NOTIFICACAO_SUCESSO = 4000;

const admin = useAdminStore();
const carregouUmaVez = ref(false);
const busca = ref('');
const alterando = ref(null);
const alunoExpandido = ref(null);
const modalCadastroAberto = ref(false);
const salvandoCadastro = ref(false);
const mensagemErro = ref('');
const mensagemSucesso = ref('');
const timeoutMensagemSucesso = ref(null);
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

onMounted(async () => {
    try {
        await admin.carregarAlunos();
    } finally {
        carregouUmaVez.value = true;
    }
});

// "Sincronizar SIG": no protótipo simula uma re-importação de candidatos.
// TODO(back-end): expor um endpoint de sincronização em lote com o SIG;
// hoje só existe POST /administrativo/sincronizar-alunos para um registro
// por vez. Por enquanto, o botão apenas atualiza a lista com os dados mais
// recentes já cadastrados.
async function sincronizar() {
    await admin.carregarAlunos();
}

function limparFormulario() {
    Object.assign(formulario, formularioInicial());
}

function limparMensagemSucesso() {
    if (timeoutMensagemSucesso.value) {
        clearTimeout(timeoutMensagemSucesso.value);
        timeoutMensagemSucesso.value = null;
    }
    mensagemSucesso.value = '';
}

function exibirMensagemSucesso(texto) {
    limparMensagemSucesso();
    mensagemSucesso.value = texto;
    timeoutMensagemSucesso.value = setTimeout(() => {
        mensagemSucesso.value = '';
        timeoutMensagemSucesso.value = null;
    }, DURACAO_NOTIFICACAO_SUCESSO);
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

        await admin.carregarAlunos();
        modalCadastroAberto.value = false;
        fecharModalCadastro({ limpar: false });
        limparFormulario();
        exibirMensagemSucesso('Candidato cadastrado com sucesso.');
    } catch (error) {
        mensagemErro.value = obterMensagemErro(error);
    } finally {
        salvandoCadastro.value = false;
    }
}

async function alternarStatus(aluno) {
    alterando.value = aluno.matricula;
    try {
        await admin.atualizarStatusAluno(aluno.matricula, !aluno.status);
    } finally {
        alterando.value = null;
    }
}

function alternarDetalhes(matricula) {
    alunoExpandido.value = alunoExpandido.value === matricula ? null : matricula;
}

function formatarPretensao(valor) {
    if (valor === null || valor === undefined || valor === '') return 'Não informado';
    return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

const alunosFiltrados = computed(() => {
    const termo = busca.value.trim().toLowerCase();
    if (!termo) return admin.alunos;
    const termoSemMascara = removerMascara(termo);

    return admin.alunos.filter(
        (a) => a.pessoa?.nome?.toLowerCase().includes(termo)
            || a.cpf?.includes(termo)
            || removerMascara(a.cpf).includes(termoSemMascara),
    );
});
</script>
