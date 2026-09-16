export const faixasPretensaoSalarial = [
    { value: 'ate_1000', label: 'Até R$ 1.000,00', min: 0, max: 1000, payload: 1000 },
    { value: '1000_2000', label: 'R$ 1.000,00 a R$ 2.000,00', min: 1000, max: 2000, payload: 2000 },
    { value: '2000_3000', label: 'R$ 2.000,00 a R$ 3.000,00', min: 2000, max: 3000, payload: 3000 },
    { value: '3000_5000', label: 'R$ 3.000,00 a R$ 5.000,00', min: 3000, max: 5000, payload: 5000 },
    { value: '5000_7000', label: 'R$ 5.000,00 a R$ 7.000,00', min: 5000, max: 7000, payload: 7000 },
    { value: '7000_10000', label: 'R$ 7.000,00 a R$ 10.000,00', min: 7000, max: 10000, payload: 10000 },
    { value: 'acima_10000', label: 'Acima de R$ 10.000,00', min: 10000, max: null, payload: 10000.01 },
];

export function obterFaixaPretensaoSalarialPorValor(value) {
    return faixasPretensaoSalarial.find((faixa) => faixa.value === value) || null;
}

export function obterFaixaPretensaoSalarialPorNumero(valor) {
    if (valor === null || valor === undefined || valor === '') {
        return null;
    }

    const numero = Number(valor);

    if (Number.isNaN(numero) || numero < 0) {
        return null;
    }

    if (numero > 10000) {
        return obterFaixaPretensaoSalarialPorValor('acima_10000');
    }

    return faixasPretensaoSalarial.find((faixa) => (
        faixa.max !== null
            && numero > faixa.min
            && numero <= faixa.max
    )) || obterFaixaPretensaoSalarialPorValor('ate_1000');
}

export function formatarFaixaPretensaoSalarial(valor, fallback = 'Não informado') {
    const faixaPorValor = obterFaixaPretensaoSalarialPorValor(valor);

    if (faixaPorValor) {
        return faixaPorValor.label;
    }

    return obterFaixaPretensaoSalarialPorNumero(valor)?.label || fallback;
}

export function converterFaixaPretensaoSalarialParaPayload(value) {
    return obterFaixaPretensaoSalarialPorValor(value)?.payload ?? null;
}

export function converterPretensaoSalarialApiParaFaixa(valor) {
    return obterFaixaPretensaoSalarialPorNumero(valor)?.value || '';
}
