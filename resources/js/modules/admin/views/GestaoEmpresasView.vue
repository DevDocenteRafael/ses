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
                        <div class="d-flex align-items-stretch flex-wrap gap-2 w-100 justify-content-md-end" style="max-width: 560px;">
                            <button class="btn btn-primary" type="button" @click="abrirModalCadastro">
                                <i class="bi bi-plus-lg me-1"></i>
                                Nova Empresa
                            </button>
                            <div class="input-group flex-grow-1" style="min-width: 240px;">
                                <input
                                    v-model="busca"
                                    type="text"
                                    class="form-control"
                                    placeholder="Buscar por Nome ou CNPJ"
                                >
                                <span class="input-group-text bg-primary text-white"><i class="bi bi-search"></i></span>
                            </div>
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

                    <transition name="app-modal-overlay">
                        <div
                            v-if="modalCadastroAberto"
                            class="modal fade show d-block app-modal-overlay"
                            tabindex="-1"
                            role="dialog"
                            aria-modal="true"
                            @click.self="fecharModalCadastro"
                        >
                            <transition appear name="app-modal-panel">
                                <div v-if="modalCadastroAberto" class="modal-dialog modal-lg modal-dialog-centered mb-0 app-modal-panel">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h3 class="modal-title h5 mb-0">Cadastrar Nova Empresa</h3>
                                            <button type="button" class="btn-close" aria-label="Fechar" @click="fecharModalCadastro"></button>
                                        </div>
                                        <form @submit.prevent="salvarNovaEmpresa">
                                            <div class="modal-body">
                                                <div v-if="mensagemErro" class="alert alert-danger py-2">
                                                    {{ mensagemErro }}
                                                </div>

                                                <div class="row g-3">
                                                    <div class="col-12">
                                                        <h4 class="h6 text-primary fw-bold mb-0">Dados da Empresa</h4>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Razão Social</label>
                                                        <input v-model.trim="formulario.razaoSocial" type="text" class="form-control" maxlength="45" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">CNPJ</label>
                                                        <input
                                                            v-model="formulario.cnpj"
                                                            type="text"
                                                            inputmode="numeric"
                                                            autocomplete="off"
                                                            class="form-control"
                                                            maxlength="18"
                                                            required
                                                            @input="onCnpjInput"
                                                        >
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Atividade / Segmento</label>
                                                        <input v-model.trim="formulario.atividadeEconomica" type="text" class="form-control" maxlength="45" required>
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
                                                            @input="onTelefoneInput('telefone', $event)"
                                                        >
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">E-mail</label>
                                                        <input v-model.trim="formulario.email" type="email" class="form-control" maxlength="100" required>
                                                    </div>

                                                    <div class="col-12 pt-2">
                                                        <h4 class="h6 text-primary fw-bold mb-0">Responsável</h4>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Nome do Responsável</label>
                                                        <input v-model.trim="formulario.responsavelNome" type="text" class="form-control" maxlength="100" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">E-mail do Responsável</label>
                                                        <input v-model.trim="formulario.responsavelEmail" type="email" class="form-control" maxlength="100">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Telefone / WhatsApp do Responsável</label>
                                                        <input
                                                            v-model="formulario.responsavelTelefone"
                                                            type="text"
                                                            inputmode="numeric"
                                                            autocomplete="tel"
                                                            class="form-control"
                                                            maxlength="16"
                                                            @input="onTelefoneInput('responsavelTelefone', $event)"
                                                        >
                                                    </div>

                                                    <div class="col-12 pt-2">
                                                        <h4 class="h6 text-primary fw-bold mb-0">Acesso</h4>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Senha</label>
                                                        <input v-model="formulario.senha" type="password" class="form-control" minlength="6" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Confirmar Senha</label>
                                                        <input v-model="formulario.confirmarSenha" type="password" class="form-control" minlength="6" required>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="form-check mt-2">
                                                            <input id="status-empresa" v-model="formulario.status" class="form-check-input" type="checkbox">
                                                            <label class="form-check-label" for="status-empresa">
                                                                Liberar acesso da empresa após o cadastro
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" @click="fecharModalCadastro">Cancelar</button>
                                                <button type="submit" class="btn btn-primary" :disabled="salvandoCadastro">
                                                    <span v-if="salvandoCadastro" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                                    {{ salvandoCadastro ? 'Salvando...' : 'Salvar empresa' }}
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </transition>
                        </div>
                    </transition>

                    <p v-if="!empresasFiltradas.length" class="text-secondary small mb-0">
                        Nenhuma empresa encontrada.
                    </p>

                    <div v-else class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr class="text-secondary small text-uppercase">
                                    <th>Empresa</th>
                                    <th>Responsável</th>
                                    <th>CNPJ</th>
                                    <th>Atividade</th>
                                    <th>Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="empresa in empresasFiltradas" :key="empresa.cnpj">
                                    <td><p class="fw-semibold mb-0">{{ empresa.razao_social }}</p></td>
                                    <td>
                                        <p class="mb-0">{{ empresa.responsavel_contratual?.pessoa?.nome || '—' }}</p>
                                        <p class="text-secondary small mb-0">{{ empresa.responsavel_contratual?.pessoa?.email || '—' }}</p>
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
const modalCadastroAberto = ref(false);
const salvandoCadastro = ref(false);
const mensagemErro = ref('');
const mensagemSucesso = ref('');
const timeoutMensagemSucesso = ref(null);
const formularioInicial = () => ({
    razaoSocial: '',
    cnpj: '',
    atividadeEconomica: '',
    telefone: '',
    email: '',
    responsavelNome: '',
    responsavelEmail: '',
    responsavelTelefone: '',
    senha: '',
    confirmarSenha: '',
    status: true,
});
const formulario = reactive(formularioInicial());

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

    return 'Não foi possível cadastrar a empresa. Verifique os dados e tente novamente.';
}

function removerMascara(valor) {
    return String(valor ?? '').replace(/\D/g, '');
}

function formatarCnpjInput(valor) {
    const digitos = removerMascara(valor).slice(0, 14);

    if (digitos.length <= 2) return digitos;
    if (digitos.length <= 5) return `${digitos.slice(0, 2)}.${digitos.slice(2)}`;
    if (digitos.length <= 8) return `${digitos.slice(0, 2)}.${digitos.slice(2, 5)}.${digitos.slice(5)}`;
    if (digitos.length <= 12) return `${digitos.slice(0, 2)}.${digitos.slice(2, 5)}.${digitos.slice(5, 8)}/${digitos.slice(8)}`;

    return `${digitos.slice(0, 2)}.${digitos.slice(2, 5)}.${digitos.slice(5, 8)}/${digitos.slice(8, 12)}-${digitos.slice(12, 14)}`;
}

function onCnpjInput(evento) {
    const valorFormatado = formatarCnpjInput(evento.target.value);
    formulario.cnpj = valorFormatado;
    evento.target.value = valorFormatado;
}

function onTelefoneInput(campo, evento) {
    const valorFormatado = formatarTelefone(evento.target.value);
    formulario[campo] = valorFormatado;
    evento.target.value = valorFormatado;
}

async function salvarNovaEmpresa() {
    mensagemErro.value = '';

    if (formulario.senha !== formulario.confirmarSenha) {
        mensagemErro.value = 'As senhas não coincidem.';
        return;
    }

    salvandoCadastro.value = true;

    try {
        await admin.cadastrarEmpresa({
            razao_social: formulario.razaoSocial,
            cnpj: removerMascara(formulario.cnpj),
            atividade_economica: formulario.atividadeEconomica,
            telefone: somenteNumeros(formulario.telefone),
            email: formulario.email,
            responsavel_nome: formulario.responsavelNome,
            responsavel_email: formulario.responsavelEmail || null,
            responsavel_telefone: somenteNumeros(formulario.responsavelTelefone),
            senha: formulario.senha,
            senha_confirmation: formulario.confirmarSenha,
            status: formulario.status,
        });

        await admin.carregarEmpresas();
        modalCadastroAberto.value = false;
        fecharModalCadastro({ limpar: false });
        limparFormulario();
        exibirMensagemSucesso('Empresa cadastrada com sucesso.');
    } catch (error) {
        mensagemErro.value = obterMensagemErro(error);
    } finally {
        salvandoCadastro.value = false;
    }
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
    const termoSemMascara = removerMascara(termo);
    return admin.empresas.filter(
        (e) => e.razao_social?.toLowerCase().includes(termo)
            || e.responsavel_contratual?.pessoa?.nome?.toLowerCase().includes(termo)
            || formatarCnpj(e.cnpj).includes(termo)
            || removerMascara(e.cnpj).includes(termoSemMascara),
    );
});
</script>

<style scoped>
.app-modal-overlay {
    background: rgba(0, 0, 0, 0.5);
}

.app-modal-panel {
    opacity: 1;
    transform: translateY(0) scale(1);
}
</style>
