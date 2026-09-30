export const LIMITE_PAGINAS_CURRICULOS_ZIP = 10;

export function validarIntervaloCurriculos(inicio, fim, ultimaPagina = 1, limitePaginas = LIMITE_PAGINAS_CURRICULOS_ZIP) {
    if (!Number.isInteger(inicio) || inicio < 1) {
        return 'A página inicial deve ser um número inteiro maior ou igual a 1.';
    }

    if (!Number.isInteger(fim) || fim < 1) {
        return 'A página final deve ser um número inteiro maior ou igual a 1.';
    }

    if (inicio > fim) {
        return 'A página inicial deve ser menor ou igual à página final.';
    }

    if (fim > Number(ultimaPagina || 1)) {
        return 'A página final não pode ser maior que a última página disponível.';
    }

    if (((fim - inicio) + 1) > limitePaginas) {
        return `Você pode gerar até ${limitePaginas} páginas por arquivo.`;
    }

    return '';
}

export function nomeArquivoZipCurriculos(response, inicio, fim) {
    const disposicao = response.headers?.['content-disposition'] || '';
    const encontradoUtf8 = disposicao.match(/filename\*=UTF-8''([^;]+)/i);
    const encontrado = disposicao.match(/filename="?([^";]+)"?/i);

    if (encontradoUtf8?.[1]) {
        return decodeURIComponent(encontradoUtf8[1]);
    }

    if (encontrado?.[1]) {
        return encontrado[1];
    }

    return inicio === fim ? `Curriculos_Pagina_${inicio}.zip` : `Curriculos_Paginas_${inicio}_a_${fim}.zip`;
}

export function obterMensagemErroDownloadCurriculos(error) {
    if (error?.response?.data instanceof Blob) {
        return 'Não foi possível gerar o arquivo de currículos. Tente novamente.';
    }

    if (error?.response?.data?.errors) {
        const primeiroCampo = Object.values(error.response.data.errors)[0];
        if (Array.isArray(primeiroCampo) && primeiroCampo.length) {
            return primeiroCampo[0];
        }
    }

    return error?.response?.data?.message || 'Não foi possível gerar o arquivo de currículos. Tente novamente.';
}

export function baixarBlobZipCurriculos(response, inicio, fim) {
    const blob = new Blob([response.data], { type: 'application/zip' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = nomeArquivoZipCurriculos(response, inicio, fim);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
}
