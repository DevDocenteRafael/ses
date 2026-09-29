<template>
    <div v-if="deveExibir" class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 flex-wrap">
        <small v-if="mostrarContador" class="text-secondary text-center text-sm-start">
            Mostrando {{ inicio }}–{{ fim }} de {{ total }} {{ rotuloResultados }}
        </small>

        <nav class="d-flex justify-content-center" :aria-label="ariaLabel">
            <ul class="pagination pagination-sm mb-0 flex-wrap justify-content-center">
                <li class="page-item" :class="{ disabled: estaNaPrimeiraPagina || loading }">
                    <button
                        class="page-link py-1"
                        type="button"
                        aria-label="Voltar 5 páginas"
                        title="Voltar 5 páginas"
                        :disabled="estaNaPrimeiraPagina || loading"
                        @click="emitirMudanca(Math.max(1, currentPage - salto))"
                    >
                        &laquo;
                    </button>
                </li>
                <li class="page-item" :class="{ disabled: estaNaPrimeiraPagina || loading }">
                    <button
                        class="page-link py-1"
                        type="button"
                        aria-label="Página anterior"
                        title="Página anterior"
                        :disabled="estaNaPrimeiraPagina || loading"
                        @click="emitirMudanca(currentPage - 1)"
                    >
                        &lsaquo;
                    </button>
                </li>
                <li
                    v-for="pagina in paginasVisiveis"
                    :key="pagina"
                    class="page-item"
                    :class="{ active: pagina === currentPage, disabled: loading }"
                >
                    <button
                        class="page-link py-1"
                        type="button"
                        :aria-label="`Página ${pagina}`"
                        :aria-current="pagina === currentPage ? 'page' : undefined"
                        :disabled="loading"
                        @click="emitirMudanca(pagina)"
                    >
                        {{ pagina }}
                    </button>
                </li>
                <li class="page-item" :class="{ disabled: estaNaUltimaPagina || loading }">
                    <button
                        class="page-link py-1"
                        type="button"
                        aria-label="Próxima página"
                        title="Próxima página"
                        :disabled="estaNaUltimaPagina || loading"
                        @click="emitirMudanca(currentPage + 1)"
                    >
                        &rsaquo;
                    </button>
                </li>
                <li class="page-item" :class="{ disabled: estaNaUltimaPagina || loading }">
                    <button
                        class="page-link py-1"
                        type="button"
                        aria-label="Avançar 5 páginas"
                        title="Avançar 5 páginas"
                        :disabled="estaNaUltimaPagina || loading"
                        @click="emitirMudanca(Math.min(lastPage, currentPage + salto))"
                    >
                        &raquo;
                    </button>
                </li>
            </ul>
        </nav>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    currentPage: { type: Number, default: 1 },
    lastPage: { type: Number, default: 1 },
    perPage: { type: Number, default: 10 },
    total: { type: Number, default: 0 },
    from: { type: Number, default: null },
    to: { type: Number, default: null },
    loading: { type: Boolean, default: false },
    itemLabel: { type: String, default: 'resultados' },
    ariaLabel: { type: String, default: 'Paginação' },
    maxVisiblePages: { type: Number, default: 5 },
    jumpSize: { type: Number, default: 5 },
    showSinglePage: { type: Boolean, default: false },
});

const emit = defineEmits(['change']);

const salto = computed(() => Math.max(1, props.jumpSize));
const deveExibir = computed(() => props.total > 0 && (props.showSinglePage || props.lastPage > 1));
const mostrarContador = computed(() => props.total > 0);
const estaNaPrimeiraPagina = computed(() => props.currentPage <= 1);
const estaNaUltimaPagina = computed(() => props.currentPage >= props.lastPage);
const inicio = computed(() => props.from ?? ((props.currentPage - 1) * props.perPage + 1));
const fim = computed(() => props.to ?? Math.min(props.currentPage * props.perPage, props.total));
const rotuloResultados = computed(() => props.total === 1 ? props.itemLabel.replace(/s$/, '') : props.itemLabel);

const paginasVisiveis = computed(() => {
    const total = props.lastPage || 1;
    const atual = props.currentPage || 1;
    const maximo = Math.max(1, props.maxVisiblePages);
    const metade = Math.floor(maximo / 2);
    let inicioJanela = Math.max(1, atual - metade);
    let fimJanela = Math.min(total, inicioJanela + maximo - 1);

    inicioJanela = Math.max(1, fimJanela - maximo + 1);

    return Array.from({ length: fimJanela - inicioJanela + 1 }, (_, i) => inicioJanela + i);
});

function emitirMudanca(pagina) {
    const destino = Math.min(Math.max(Number(pagina), 1), props.lastPage || 1);

    if (destino === props.currentPage || props.loading) {
        return;
    }

    emit('change', destino);
}
</script>
