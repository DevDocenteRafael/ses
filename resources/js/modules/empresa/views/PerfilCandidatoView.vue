<template>
    <div>
        <header class="bg-primary text-white px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold fs-5">Senac</span>
                <span class="vr d-none d-sm-block opacity-50 mx-1"></span>
                <h1 class="h5 fw-bold mb-0">Portal da Empresa</h1>
            </div>

            <div class="d-flex align-items-center gap-2">
                <div class="text-end d-none d-sm-block">
                    <p class="fw-semibold mb-0">{{ auth.pessoa?.nome || 'Empresa' }}</p>
                    <p class="small mb-0 opacity-75">{{ auth.pessoa?.email }}</p>
                </div>
                <span class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center fw-semibold flex-shrink-0"
                      style="width: 38px; height: 38px;">
                    {{ iniciaisDe(auth.pessoa?.nome) }}
                </span>
                <button type="button" class="btn btn-sm btn-outline-light ms-2" @click="sair">
                    <i class="bi bi-box-arrow-left me-1"></i> Sair
                </button>
            </div>
        </header>

        <div class="bg-white border-bottom p-3 d-flex align-items-center px-4 mb-4">
            <router-link :to="{ name: 'empresa.buscar-talentos' }" class="text-decoration-none text-secondary small d-flex align-items-center">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </router-link>
            <span class="text-secondary mx-2">/</span>
            <h2 class="h6 mb-0 fw-bold text-primary">Perfil do Candidato</h2>
        </div>

        <div v-if="carregando" class="text-center text-secondary py-5">
            <span class="spinner-border spinner-border-sm me-2"></span> Carregando perfil...
        </div>

        <div v-else-if="erro" class="container-fluid px-4">
            <div class="alert alert-danger">{{ erro }}</div>
        </div>

        <div v-else class="container-fluid px-4 pb-5">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4 p-md-5">
                            <div class="d-flex align-items-start gap-4 mb-4 flex-wrap flex-md-nowrap">
                                <span class="rounded-4 shadow bg-primary text-white d-flex align-items-center justify-content-center fw-semibold flex-shrink-0"
                                      style="width: 128px; height: 128px; font-size: 2.5rem;">
                                    {{ iniciaisDe(candidato.pessoa?.nome) }}
                                </span>
                                <div>
                                    <h1 class="h2 mb-2">{{ candidato.pessoa?.nome }}</h1>
                                    <p class="text-secondary lead mb-3">
                                        {{ cursoPrincipal?.curso }} <template v-if="cursoPrincipal?.unidade"> | {{ cursoPrincipal.unidade }}</template>
                                    </p>
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <span class="badge bg-secondary">{{ statusLabel }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-3 text-secondary small">
                                        <span v-if="candidato.preferencias_de_trabalho?.regiao_administrativa">
                                            <i class="bi bi-geo-alt me-1"></i>{{ candidato.preferencias_de_trabalho.regiao_administrativa }}, DF
                                        </span>
                                        <span v-if="ultimaAtualizacao">
                                            <i class="bi bi-calendar3 me-1"></i>Última atualização: {{ ultimaAtualizacao }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <h2 class="h6 mb-3 border-bottom pb-2">Sobre o Candidato</h2>
                            <p class="text-secondary mb-4">
                                {{ candidato.informacoes_profissionais?.sobre_mim || 'Candidato ainda não preencheu esta seção.' }}
                            </p>

                            <h2 class="h6 mb-3 border-bottom pb-2">Habilidades Técnicas</h2>
                            <div class="mb-4">
                                <p v-if="!habilidades.length" class="text-secondary small mb-0">Nenhuma habilidade cadastrada.</p>
                                <ul v-else class="list-unstyled mb-0">
                                    <li v-for="h in habilidades" :key="h" class="mb-2">
                                        <i class="bi bi-check text-success me-2"></i>{{ h }}
                                    </li>
                                </ul>
                            </div>

                            <h2 class="h6 mb-3 border-bottom pb-2">Preferências de Trabalho</h2>
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <small class="text-secondary d-block">Contratação</small>
                                    <span class="fw-bold">{{ tipoContratacaoLabel }}</span>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-secondary d-block">Disponibilidade</small>
                                    <span class="fw-bold">{{ candidato.preferencias_de_trabalho?.disponibilidade_de_horario || '-' }}</span>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-secondary d-block">Pretensão</small>
                                    <span class="fw-bold">{{ pretensaoFormatada }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm sticky-top" style="top: 90px;">
                        <div class="card-body p-4">
                            <h2 class="h6 fw-bold mb-4">Informações de Contato</h2>
                            <div class="mb-3">
                                <label class="small text-secondary d-block mb-1">E-mail de Contato</label>
                                <a :href="`mailto:${candidato.pessoa?.email}`" class="fw-bold text-decoration-none">
                                    {{ candidato.pessoa?.email }}
                                </a>
                            </div>
                            <div class="mb-0">
                                <label class="small text-secondary d-block mb-1">Telefone / WhatsApp</label>
                                <span class="fw-bold">{{ candidato.pessoa?.telefone || '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../../store/auth';
import empresaService from '../../../services/empresaServices';

const props = defineProps({
    matricula: { type: [String, Number], required: true },
});

const auth = useAuthStore();
const router = useRouter();

const carregando = ref(true);
const erro = ref('');
const candidato = ref({});

function iniciaisDe(nome) {
    return (nome || 'C')
        .split(' ')
        .slice(0, 2)
        .map((p) => p[0])
        .join('')
        .toUpperCase();
}

const cursoPrincipal = computed(() => (candidato.value.dados_academicos || [])[0] || null);

const habilidades = computed(() => candidato.value.informacoes_profissionais?.habilidades || []);

const statusLabel = computed(() => (candidato.value.status ? 'Ativo em busca' : 'Inativo'));

const ultimaAtualizacao = computed(() => {
    const data = candidato.value.updated_at;
    if (!data) return null;
    return new Date(data).toLocaleDateString('pt-BR');
});

const tipoContratacaoLabel = computed(() => {
    const mascara = candidato.value.preferencias_de_trabalho?.tipo_de_contratacao || 0;
    const tipos = [];
    if (mascara & 1) tipos.push('CLT');
    if (mascara & 2) tipos.push('Estágio');
    if (mascara & 4) tipos.push('Jovem Aprendiz');
    return tipos.length ? tipos.join(' / ') : '-';
});

const pretensaoFormatada = computed(() => {
    const valor = candidato.value.preferencias_de_trabalho?.pretensao_salarial;
    if (!valor) return 'A combinar';
    return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
});

async function carregar() {
    carregando.value = true;
    erro.value = '';
    try {
        const { data } = await empresaService.verTalento(props.matricula);
        candidato.value = data;
    } catch (e) {
        erro.value = 'Não foi possível carregar este candidato.';
    } finally {
        carregando.value = false;
    }
}

async function sair() {
    await auth.logout();
    router.push({ name: 'login' });
}

onMounted(carregar);
</script>
