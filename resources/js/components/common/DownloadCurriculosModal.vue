<template>
    <transition name="app-modal">
        <div
            v-if="show"
            class="modal fade show d-block"
            tabindex="-1"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-curriculos-titulo"
        >
            <div class="modal-dialog modal-dialog-centered app-modal-dialog-animated">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="modal-curriculos-titulo" class="modal-title h5 mb-0">Baixar currículos</h3>
                        <button type="button" class="btn-close" aria-label="Fechar" :disabled="loading" @click="$emit('fechar')"></button>
                    </div>
                    <form @submit.prevent="emitirGeracao">
                        <div class="modal-body">
                            <p class="text-secondary mb-3">Escolha uma página ou um intervalo de páginas para baixar os currículos.</p>

                            <div v-if="erro" id="erro-curriculos" class="alert alert-danger py-2">
                                {{ erro }}
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="pagina-inicial-curriculos" class="form-label">Página inicial</label>
                                    <input
                                        id="pagina-inicial-curriculos"
                                        v-model="paginaInicial"
                                        type="number"
                                        inputmode="numeric"
                                        min="1"
                                        step="1"
                                        class="form-control"
                                        :max="lastPage"
                                        :aria-describedby="erro ? 'erro-curriculos' : undefined"
                                        required
                                    >
                                </div>
                                <div class="col-md-6">
                                    <label for="pagina-final-curriculos" class="form-label">Página final</label>
                                    <input
                                        id="pagina-final-curriculos"
                                        v-model="paginaFinal"
                                        type="number"
                                        inputmode="numeric"
                                        min="1"
                                        step="1"
                                        class="form-control"
                                        :max="lastPage"
                                        :aria-describedby="erro ? 'erro-curriculos' : undefined"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="small text-secondary mt-3">
                                <p class="mb-1">Para baixar somente uma página, informe o mesmo número nos dois campos.</p>
                                <p class="mb-1">{{ perPage || 10 }} candidatos por página</p>
                                <p class="mb-1">{{ resumoPaginasSelecionadas }}</p>
                                <p class="mb-1">Até {{ estimativaCurriculos }} currículos serão gerados.</p>
                                <p class="mb-0">Você pode gerar até {{ limitePaginas }} páginas por arquivo.</p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary" :disabled="loading">
                                <span v-if="loading" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                {{ loading ? 'Gerando currículos...' : 'Gerar ZIP' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </transition>
    <transition name="app-modal">
        <div v-if="show" class="modal-backdrop fade show"></div>
    </transition>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    erro: { type: String, default: '' },
    perPage: { type: Number, default: 10 },
    lastPage: { type: Number, default: 1 },
    limitePaginas: { type: Number, default: 10 },
    paginaInicialModel: { type: [Number, String], default: 1 },
    paginaFinalModel: { type: [Number, String], default: 1 },
});

const emit = defineEmits(['fechar', 'gerar', 'update:paginaInicialModel', 'update:paginaFinalModel']);

const paginaInicial = computed({
    get: () => props.paginaInicialModel,
    set: (valor) => emit('update:paginaInicialModel', valor),
});

const paginaFinal = computed({
    get: () => props.paginaFinalModel,
    set: (valor) => emit('update:paginaFinalModel', valor),
});

const paginasSelecionadas = computed(() => {
    const inicio = Number(paginaInicial.value);
    const fim = Number(paginaFinal.value);

    if (!Number.isInteger(inicio) || !Number.isInteger(fim) || inicio < 1 || fim < inicio) {
        return 0;
    }

    return (fim - inicio) + 1;
});

const estimativaCurriculos = computed(() => paginasSelecionadas.value * Number(props.perPage || 10));

const resumoPaginasSelecionadas = computed(() => {
    const total = paginasSelecionadas.value;
    return `${total} ${total === 1 ? 'página selecionada' : 'páginas selecionadas'}`;
});

function emitirGeracao() {
    emit('gerar', {
        paginaInicial: Number(paginaInicial.value),
        paginaFinal: Number(paginaFinal.value),
    });
}
</script>
