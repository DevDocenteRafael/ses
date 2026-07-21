import { reactive, watch } from 'vue';
import { useAuthStore } from '../store/auth';

/**
 * Gerencia as "Listas" de favoritos da empresa (ex: "Estagiários TI",
 * "Vendas 2026"). Ainda não existe uma tabela para isso no banco —
 * por enquanto ficam salvas no localStorage, isoladas por empresa
 * logada (id_pessoa/cnpj). Dá pra promover isso pra uma tabela própria
 * (`listas_favoritos` + pivô) mais adiante sem mudar quem consome
 * este composable.
 */
export function useListasFavoritos() {
    const auth = useAuthStore();
    const chave = `ses_listas_${auth.pessoa?.id_pessoa || 'anon'}`;

    const listas = reactive(JSON.parse(localStorage.getItem(chave) || '[]'));

    watch(
        listas,
        () => {
            localStorage.setItem(chave, JSON.stringify(listas));
        },
        { deep: true }
    );

    function criarLista(nome) {
        const nomeLimpo = nome.trim();
        if (!nomeLimpo) return;
        listas.push({ id: Date.now(), nome: nomeLimpo, matriculas: [] });
    }

    function removerLista(id) {
        const i = listas.findIndex((l) => l.id === id);
        if (i !== -1) listas.splice(i, 1);
    }

    function adicionarNaLista(id, matricula) {
        const lista = listas.find((l) => l.id === id);
        if (lista && !lista.matriculas.includes(matricula)) {
            lista.matriculas.push(matricula);
        }
    }

    function removerDaLista(id, matricula) {
        const lista = listas.find((l) => l.id === id);
        if (lista) {
            lista.matriculas = lista.matriculas.filter((m) => m !== matricula);
        }
    }

    return { listas, criarLista, removerLista, adicionarNaLista, removerDaLista };
}
