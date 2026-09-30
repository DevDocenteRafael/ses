export function formatarListaPtBr(valores, fallback = '-') {
    const lista = (Array.isArray(valores) ? valores : [valores])
        .filter((valor) => valor !== null && valor !== undefined && String(valor).trim() !== '')
        .map((valor) => String(valor).trim());

    if (!lista.length) {
        return fallback;
    }

    if (lista.length === 1) {
        return lista[0];
    }

    if (lista.length === 2) {
        return `${lista[0]} e ${lista[1]}`;
    }

    return `${lista.slice(0, -1).join(', ')} e ${lista[lista.length - 1]}`;
}

export function formatarDisponibilidadeHorario(valor, fallback = '-') {
    return formatarListaPtBr(valor, fallback);
}
