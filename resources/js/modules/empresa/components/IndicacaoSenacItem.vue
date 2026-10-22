<template>
    <!-- Lista: linha horizontal com ações à direita. Grade: card vertical. -->
    <article
        class="card border-0 shadow-sm indicacao-item"
        :class="grade ? 'h-100 indicacao-item--grade' : 'indicacao-item--lista'"
        :data-testid="`indicacao-${indicacao.id}`"
        :aria-labelledby="`indicacao-nome-${indicacao.id}`"
    >
        <div class="card-body d-flex flex-column" :class="{ 'gap-3': grade }">
            <div class="row g-3 align-items-start flex-grow-1">
                <div class="col-12" :class="{ 'col-lg': !grade }">
                    <div class="d-flex align-items-start gap-3">
                        <span
                            class="indicacoes-avatar rounded-1 d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                            :class="{ 'indicacoes-avatar--indisponivel': !candidato.disponivel }"
                            aria-hidden="true"
                        >
                            {{ iniciais }}
                        </span>
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <h3 :id="`indicacao-nome-${indicacao.id}`" class="h6 fw-bold mb-1 text-break">{{ candidato.nome || 'Candidato' }}</h3>
                                <span v-if="grade" class="badge rounded-pill flex-shrink-0 fw-semibold" :class="classeStatus" data-testid="badge-status">
                                    {{ indicacao.status_rotulo }}
                                </span>
                            </div>

                            <!-- Candidato indisponível: somente nome e aviso, nos dois modos. -->
                            <template v-if="candidato.disponivel">
                                <template v-if="grade">
                                    <p class="small mb-0 indicacoes-texto-suave" data-testid="formacao">
                                        <i class="bi bi-mortarboard me-1" aria-hidden="true"></i>{{ candidato.curso || 'Formação não informada' }}
                                    </p>
                                    <p class="small mb-1 indicacoes-texto-suave" data-testid="segmento">
                                        <i class="bi bi-diagram-3 me-1" aria-hidden="true"></i>{{ candidato.segmento || 'Segmento não informado' }}
                                    </p>
                                </template>
                                <p v-else class="small mb-1 indicacoes-texto-suave" data-testid="formacao">
                                    {{ [candidato.curso, candidato.segmento].filter(Boolean).join(' · ') || 'Formação não informada' }}
                                </p>
                                <div v-if="candidato.habilidades?.length" class="d-flex flex-wrap gap-1 mb-1">
                                    <span v-for="h in candidato.habilidades.slice(0, limiteHabilidades)" :key="h" class="badge bg-primary-subtle text-primary-emphasis fw-normal text-wrap text-start">{{ h }}</span>
                                    <span v-if="candidato.habilidades.length > limiteHabilidades" class="badge bg-secondary-subtle text-secondary-emphasis fw-normal">+{{ candidato.habilidades.length - limiteHabilidades }}</span>
                                </div>
                                <p class="small mb-0 indicacoes-texto-suave">
                                    <i class="bi bi-clock me-1" aria-hidden="true"></i>{{ formatarDisponibilidadeHorario(candidato.disponibilidade) }}
                                    <span v-if="candidato.tipos_contratacao?.length"> · {{ candidato.tipos_contratacao.join(' / ') }}</span>
                                </p>
                            </template>
                            <p v-else class="small mb-0 indicacoes-aviso-indisponivel" data-testid="aviso-indisponivel">
                                <i class="bi bi-eye-slash me-1" aria-hidden="true"></i>Perfil indisponível no momento.
                            </p>
                            <p class="small mt-2 mb-0 indicacoes-texto-suave">
                                <i class="bi bi-calendar-event me-1" aria-hidden="true"></i>Indicado em {{ dataIndicacao }}
                            </p>
                        </div>
                    </div>
                    <blockquote v-if="indicacao.observacao" class="indicacoes-observacao small mt-3 mb-0">
                        <span class="fw-semibold d-block mb-1">Observação do Senac</span>
                        {{ indicacao.observacao }}
                    </blockquote>
                </div>

                <div class="col-12 d-flex flex-column gap-2" :class="grade ? 'mt-auto' : 'col-lg-auto indicacoes-acoes'">
                    <label class="small fw-semibold mb-0" :for="`status-${indicacao.id}`">Andamento</label>
                    <select
                        :id="`status-${indicacao.id}`"
                        class="form-select indicacoes-controle"
                        :value="indicacao.status"
                        :disabled="salvando"
                        @change="emit('alterar-status', indicacao, $event)"
                    >
                        <option v-if="indicacao.status === 'NOVA'" value="NOVA" disabled>Nova indicação</option>
                        <option v-for="opcao in opcoesStatus" :key="opcao.valor" :value="opcao.valor">{{ opcao.rotulo }}</option>
                    </select>
                    <router-link
                        v-if="candidato.disponivel"
                        :to="{ name: 'empresa.candidato', params: { matricula: candidato.matricula }, query: { de: 'indicacoes' } }"
                        class="btn btn-primary indicacoes-controle"
                        :aria-label="`Ver perfil de ${candidato.nome}`"
                    >
                        <i class="bi bi-person-vcard me-1" aria-hidden="true"></i>Ver perfil
                    </router-link>
                </div>
            </div>
        </div>
    </article>
</template>

<script setup>
import { computed } from 'vue';
import { formatarDisponibilidadeHorario } from '../../../utils/listasPtBr';

const props = defineProps({
    indicacao: { type: Object, required: true },
    modo: { type: String, default: 'lista' },
    salvando: { type: Boolean, default: false },
    opcoesStatus: { type: Array, required: true },
});

const emit = defineEmits(['alterar-status']);

const grade = computed(() => props.modo === 'grade');
const candidato = computed(() => props.indicacao.candidato || {});
const limiteHabilidades = computed(() => (grade.value ? 4 : 5));

const iniciais = computed(() => String(candidato.value.nome || '?').trim().split(/\s+/).slice(0, 2)
    .map((p) => p[0]?.toUpperCase() || '').join(''));

const dataIndicacao = computed(() => {
    const iso = props.indicacao.indicado_em;
    return iso ? new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—';
});

const classeStatus = computed(() => ({
    NOVA: 'bg-primary-subtle text-primary-emphasis border border-primary-subtle',
    EM_ANALISE: 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
    TENHO_INTERESSE: 'bg-success-subtle text-success-emphasis border border-success-subtle',
    SEM_INTERESSE: 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle',
}[props.indicacao.status] || 'bg-secondary-subtle text-secondary-emphasis'));
</script>

<style scoped>
.min-w-0 {
    min-width: 0;
}

/* Texto auxiliar com contraste AA nos dois temas. */
.indicacoes-texto-suave {
    color: #475569;
}

html.dark .indicacoes-texto-suave {
    color: var(--ses-muted);
}

.indicacoes-aviso-indisponivel {
    color: #8a4b00;
}

html.dark .indicacoes-aviso-indisponivel {
    color: #fbbf24;
}

.indicacoes-avatar {
    width: 48px;
    height: 48px;
    background: var(--ses-primary);
    color: #ffffff;
}

.indicacoes-avatar--indisponivel {
    background: #64748b;
}

.indicacoes-observacao {
    border-left: 4px solid var(--ses-accent);
    background: rgba(var(--ses-accent-rgb), 0.08);
    padding: 0.6rem 0.85rem;
    border-radius: 0.375rem;
}

.indicacoes-acoes {
    min-width: 13rem;
}

/* Área de toque confortável (≥ 44px) nos controles do card. */
.indicacoes-controle {
    min-height: 44px;
}

.indicacao-item--grade .indicacoes-observacao {
    display: -webkit-box;
    -webkit-line-clamp: 4;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
