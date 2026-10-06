export function somenteDigitos(valor) {
    return String(valor ?? '').replace(/\D/g, '');
}

export function formatarCpf(valor) {
    const digitos = somenteDigitos(valor).slice(0, 11);

    if (digitos.length <= 3) return digitos;
    if (digitos.length <= 6) return `${digitos.slice(0, 3)}.${digitos.slice(3)}`;
    if (digitos.length <= 9) return `${digitos.slice(0, 3)}.${digitos.slice(3, 6)}.${digitos.slice(6)}`;

    return `${digitos.slice(0, 3)}.${digitos.slice(3, 6)}.${digitos.slice(6, 9)}-${digitos.slice(9, 11)}`;
}

export function formatarCnpj(valor) {
    const digitos = somenteDigitos(valor).slice(0, 14);

    if (digitos.length <= 2) return digitos;
    if (digitos.length <= 5) return `${digitos.slice(0, 2)}.${digitos.slice(2)}`;
    if (digitos.length <= 8) return `${digitos.slice(0, 2)}.${digitos.slice(2, 5)}.${digitos.slice(5)}`;
    if (digitos.length <= 12) return `${digitos.slice(0, 2)}.${digitos.slice(2, 5)}.${digitos.slice(5, 8)}/${digitos.slice(8)}`;

    return `${digitos.slice(0, 2)}.${digitos.slice(2, 5)}.${digitos.slice(5, 8)}/${digitos.slice(8, 12)}-${digitos.slice(12, 14)}`;
}

export function ehEntradaDocumento(valor) {
    const texto = String(valor ?? '');

    return /\d/.test(texto) && /^[\d\s.\-/]+$/.test(texto);
}

export function mascararBuscaCpf(valor) {
    return ehEntradaDocumento(valor) ? formatarCpf(valor) : String(valor ?? '');
}

export function mascararBuscaCnpj(valor) {
    return ehEntradaDocumento(valor) ? formatarCnpj(valor) : String(valor ?? '');
}

export function normalizarBuscaDocumentoOuTexto(valor) {
    const texto = String(valor ?? '').trim();

    return ehEntradaDocumento(texto) ? somenteDigitos(texto) : texto;
}
