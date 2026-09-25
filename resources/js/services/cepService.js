import api from './api';

function somenteDigitos(valor) {
    return String(valor ?? '').replace(/\D/g, '');
}

export function formatarCep(valor) {
    const digitos = somenteDigitos(valor).slice(0, 8);

    if (digitos.length <= 5) {
        return digitos;
    }

    return `${digitos.slice(0, 5)}-${digitos.slice(5)}`;
}

export async function consultarCep(cep) {
    const cepNormalizado = somenteDigitos(cep);

    if (cepNormalizado.length !== 8) {
        const erro = new Error('Informe um CEP válido.');
        erro.tipo = 'invalido';
        throw erro;
    }

    try {
        const { data } = await api.get(`/cep/${cepNormalizado}`);

        return {
            cep: formatarCep(data.cep || cepNormalizado),
            logradouro: data.logradouro || '',
            bairro: data.bairro || '',
            cidade: data.cidade || '',
            uf: data.uf || '',
        };
    } catch (e) {
        if (e?.response?.status === 404) {
            const erro = new Error('CEP não encontrado.');
            erro.tipo = 'nao_encontrado';
            throw erro;
        }

        if (e?.response?.status === 422) {
            const erro = new Error(e.response.data?.message || 'Informe um CEP válido.');
            erro.tipo = 'invalido';
            throw erro;
        }

        const erro = new Error('Não foi possível consultar o CEP no momento. Tente novamente ou preencha o endereço manualmente.');
        erro.tipo = 'comunicacao';
        throw erro;
    }
}

export default {
    consultarCep,
    formatarCep,
};
