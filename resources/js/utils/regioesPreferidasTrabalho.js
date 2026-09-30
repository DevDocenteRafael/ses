export function normalizarRegioesPreferidas(candidato) {
    const preferencias = candidato?.preferencias_de_trabalho || {};

    if (preferencias.aceita_todas_regioes) {
        return [{ codigo: 'todas', nome: 'Todas as regiões' }];
    }

    const regioes = Array.isArray(candidato?.regioes_preferidas_trabalho)
        ? candidato.regioes_preferidas_trabalho
        : [];

    const vistas = new Set();

    return regioes
        .map((regiao) => ({
            codigo: regiao?.codigo ?? regiao?.codigo_regiao ?? regiao?.id ?? regiao?.nome,
            nome: regiao?.nome ?? regiao?.label ?? regiao?.regiao_administrativa ?? '',
        }))
        .filter((regiao) => regiao.nome)
        .filter((regiao) => {
            const chave = regiao.codigo ?? regiao.nome;

            if (vistas.has(chave)) {
                return false;
            }

            vistas.add(chave);
            return true;
        });
}

export function formatarRegioesPreferidasCard(candidato) {
    const regioes = normalizarRegioesPreferidas(candidato);

    if (!regioes.length) {
        return {
            texto: '',
            visiveis: [],
            restantes: [],
            quantidadeRestante: 0,
        };
    }

    if (regioes.length === 1 && regioes[0].codigo === 'todas') {
        return {
            texto: 'Todas as regiões',
            visiveis: regioes,
            restantes: [],
            quantidadeRestante: 0,
        };
    }

    const visiveis = regioes.slice(0, 2);
    const restantes = regioes.slice(2);

    return {
        texto: `${visiveis.map((regiao) => regiao.nome).join(', ')} - DF`,
        visiveis,
        restantes,
        quantidadeRestante: restantes.length,
    };
}
