import catalogoHabilidades from '../../catalogos/habilidades.json';

export const habilidadesPorArea = catalogoHabilidades.habilidadesPorArea;

export const areasAtuacao = Object.keys(habilidadesPorArea);

export const sugestoesSoftSkills = catalogoHabilidades.sugestoesSoftSkills;

export function normalizarChaveHabilidade(valor) {
    return String(valor ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

export function normalizarRotuloHabilidade(valor) {
    return String(valor ?? '').replace(/\s+/g, ' ').trim();
}

export function deduplicarHabilidades(habilidades) {
    const mapa = new Map();

    habilidades.forEach((habilidade) => {
        const rotulo = normalizarRotuloHabilidade(habilidade);
        const chave = normalizarChaveHabilidade(rotulo);

        if (rotulo && !mapa.has(chave)) {
            mapa.set(chave, rotulo);
        }
    });

    return Array.from(mapa.values());
}

export const habilidadesTecnicasPadrao = deduplicarHabilidades(Object.values(habilidadesPorArea).flat());

export const habilidadesPadrao = deduplicarHabilidades([
    ...habilidadesTecnicasPadrao,
    ...sugestoesSoftSkills,
]);
