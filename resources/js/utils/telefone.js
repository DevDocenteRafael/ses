export function somenteNumeros(valor) {
    return String(valor ?? '').replace(/\D/g, '');
}

export function formatarTelefone(valor) {
    const digitos = somenteNumeros(valor).slice(0, 11);

    if (!digitos) return '';
    if (digitos.length <= 2) return `(${digitos}`;

    const ddd = digitos.slice(0, 2);
    const restante = digitos.slice(2);

    if (digitos.length <= 6) {
        return `(${ddd}) ${restante}`;
    }

    if (digitos.length <= 10) {
        return `(${ddd}) ${restante.slice(0, 4)}-${restante.slice(4)}`;
    }

    return `(${ddd}) ${restante.slice(0, 1)} ${restante.slice(1, 5)}-${restante.slice(5, 9)}`;
}
