import { readonly, ref } from 'vue';

const DURACAO_PADRAO = 4500;
const tiposPermitidos = ['success', 'error', 'warning', 'info'];
const aliasesTipo = {
    sucesso: 'success',
    erro: 'error',
    aviso: 'warning',
    warning: 'warning',
    informacao: 'info',
    informação: 'info',
};

const toasts = ref([]);
let proximoId = 1;

function normalizarTipo(tipo) {
    const tipoNormalizado = aliasesTipo[tipo] || tipo;
    return tiposPermitidos.includes(tipoNormalizado) ? tipoNormalizado : 'info';
}

function removerToast(id) {
    const indice = toasts.value.findIndex((toast) => toast.id === id);

    if (indice < 0) {
        return;
    }

    const [toast] = toasts.value.splice(indice, 1);

    if (toast.timeoutId) {
        clearTimeout(toast.timeoutId);
    }
}

function adicionarToast(tipo, mensagem, opcoes = {}) {
    const id = proximoId++;
    const duracao = Number.isFinite(opcoes.duracao) ? opcoes.duracao : DURACAO_PADRAO;
    const toast = {
        id,
        tipo: normalizarTipo(tipo),
        mensagem,
        timeoutId: null,
    };

    if (duracao > 0) {
        toast.timeoutId = setTimeout(() => removerToast(id), duracao);
    }

    toasts.value.push(toast);
    return id;
}

export function useToast() {
    return {
        toasts: readonly(toasts),
        showToast: adicionarToast,
        removeToast: removerToast,
        success: (mensagem, opcoes) => adicionarToast('success', mensagem, opcoes),
        error: (mensagem, opcoes) => adicionarToast('error', mensagem, opcoes),
        warning: (mensagem, opcoes) => adicionarToast('warning', mensagem, opcoes),
        info: (mensagem, opcoes) => adicionarToast('info', mensagem, opcoes),
    };
}
