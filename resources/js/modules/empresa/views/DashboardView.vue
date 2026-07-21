<template>
    <div>
        <topbar
            titulo="Painel da Empresa"
            :subtitulo="empresa.perfil ? `${empresa.perfil.razao_social} - Gerenciando recrutamento` : ''"
        />

        <div class="container-fluid p-4">
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3" v-for="card in indicadores" :key="card.titulo">
                    <cardIndicador v-bind="card" />
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h2 class="h6 fw-bold mb-3">Candidatos Recentes</h2>

                            <loading v-if="empresa.carregando" />

                            <p v-else-if="!candidatosRecentes.length" class="text-secondary small mb-3">
                                Você ainda não favoritou nenhum candidato.
                            </p>

                            <div
                                v-else
                                v-for="c in candidatosRecentes"
                                :key="c.matricula"
                                class="d-flex align-items-center justify-content-between py-2 border-bottom"
                            >
                                <div class="d-flex align-items-center gap-3">
                                    <span
                                        class="ses-inicial rounded-1 d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0"
                                    >
                                        {{ iniciais(c.pessoa?.nome) }}
                                    </span>
                                    <div>
                                        <p class="fw-semibold mb-0">{{ c.pessoa?.nome }}</p>
                                        <p class="text-secondary small mb-0">
                                            {{ c.informacoesProfissionais?.cargo_de_interesse
                                                || c.dadosAcademicos?.[0]?.curso
                                                || 'Perfil cadastrado' }}
                                        </p>
                                    </div>
                                </div>
                                <span class="badge text-bg-warning-subtle text-warning-emphasis">Favorito</span>
                            </div>

                            <button
                                class="btn btn-primary w-100 mt-3"
                                @click="router.push({ name: 'empresa.buscar-talentos' })"
                            >
                                Buscar Novos Talentos
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 d-flex flex-column gap-3">
                    <div class="ses-dica p-3 rounded-3 text-white">
                        <h2 class="h6 fw-bold mb-2">Dica de Seleção</h2>
                        <p class="small mb-0">
                            Utilize o filtro de "Disponibilidade" para encontrar candidatos que se
                            encaixam perfeitamente nos seus turnos de trabalho.
                        </p>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h2 class="h6 fw-bold mb-3">Minhas Listas</h2>

                            <p v-if="!listas.length" class="text-secondary small mb-0">
                                Nenhuma lista criada ainda. Crie listas na tela de Favoritos.
                            </p>

                            <router-link
                                v-for="l in listas"
                                :key="l.id"
                                :to="{ name: 'empresa.favoritos' }"
                                class="d-flex align-items-center justify-content-between text-decoration-none text-dark py-1"
                            >
                                <span><i class="bi bi-folder2 me-2 text-secondary"></i>{{ l.nome }}</span>
                                <span>{{ l.matriculas.length }}</span>
                            </router-link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import topbar from '../../../components/common/header.vue';
import cardIndicador from '../../../components/common/cardIndicador.vue';
import loading from '../../../components/common/loading.vue';
import { useEmpresaStore } from '../../../store/empresa';
import { useAuthStore } from '../../../store/auth';
import { useListasFavoritos } from '../../../composables/useListasFavoritos';

const router = useRouter();
const auth = useAuthStore();
const empresa = useEmpresaStore();
const { listas } = useListasFavoritos();

onMounted(async () => {
    const cnpj = auth.pessoa?.id_pessoa;
    if (cnpj) {
        await empresa.carregarPerfil(cnpj);
    }
});

const candidatosRecentes = computed(() => empresa.favoritosDaEmpresa.slice(0, 5));

const indicadores = computed(() => [
    {
        titulo: 'Vagas Ativas',
        valor: empresa.vagasDaEmpresa.filter((v) => v.status).length,
        icone: 'bi-briefcase',
        variante: 'primary',
    },
    {
        titulo: 'Candidatos Favoritos',
        valor: empresa.favoritosDaEmpresa.length,
        icone: 'bi-star',
        variante: 'warning',
    },
    {
        titulo: 'Convites Enviados',
        valor: empresa.convitesDaEmpresa.length,
        icone: 'bi-envelope',
        variante: 'info',
    },
    {
        titulo: 'Convites Aceitos',
        valor: empresa.convitesDaEmpresa.filter((c) => c.status === 1).length,
        icone: 'bi-check-circle',
        variante: 'success',
    },
]);

function iniciais(nome) {
    if (!nome) return '';
    return nome.split(' ').filter(Boolean).slice(0, 2).map((p) => p[0].toUpperCase()).join('');
}
</script>

<style scoped>
.ses-dica {
    background-color: #f5a623;
}
.ses-inicial {
    width: 40px;
    height: 40px;
    background-color: #142a4d;
}
</style>
