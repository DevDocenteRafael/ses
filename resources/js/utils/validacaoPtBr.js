const MENSAGEM_OBRIGATORIO = 'Este campo é obrigatório.';

const TIPOS_COM_MENSAGEM_ESPECIFICA = {
    email: 'Informe um e-mail válido.',
    url: 'Informe uma URL válida.',
};

function obterMensagemValidacao(campo) {
    const validade = campo.validity;

    if (validade.valueMissing) {
        return MENSAGEM_OBRIGATORIO;
    }

    if (validade.typeMismatch) {
        return TIPOS_COM_MENSAGEM_ESPECIFICA[campo.type] || 'Informe um valor válido.';
    }

    if (validade.tooShort) {
        return `Informe pelo menos ${campo.minLength} caracteres.`;
    }

    if (validade.tooLong) {
        return `Informe no máximo ${campo.maxLength} caracteres.`;
    }

    if (validade.patternMismatch) {
        return campo.dataset.validationPatternMessage || 'Informe um valor no formato válido.';
    }

    if (validade.rangeUnderflow) {
        return `Informe um valor maior ou igual a ${campo.min}.`;
    }

    if (validade.rangeOverflow) {
        return `Informe um valor menor ou igual a ${campo.max}.`;
    }

    if (validade.stepMismatch || validade.badInput) {
        return 'Informe um valor válido.';
    }

    return '';
}

function atualizarMensagemCustomizada(campo) {
    if (!(campo instanceof HTMLInputElement || campo instanceof HTMLSelectElement || campo instanceof HTMLTextAreaElement)) {
        return;
    }

    const mensagemAnterior = campo.validationMessage;
    campo.setCustomValidity('');

    if (campo.validity.valid) {
        return;
    }

    const mensagem = obterMensagemValidacao(campo);
    campo.setCustomValidity(mensagem || mensagemAnterior || 'Informe um valor válido.');
}

export function registrarValidacaoPtBr() {
    document.addEventListener('invalid', (evento) => {
        atualizarMensagemCustomizada(evento.target);
    }, true);

    document.addEventListener('input', (evento) => {
        atualizarMensagemCustomizada(evento.target);
    }, true);

    document.addEventListener('change', (evento) => {
        atualizarMensagemCustomizada(evento.target);
    }, true);
}

