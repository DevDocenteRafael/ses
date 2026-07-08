<template>
    <div class="container-fluid p-4">
        <div class="d-flex align-items-start justify-content-between mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Meu Perfil Profissional</h1>
                <p class="text-secondary mb-0">Mantenha seus dados atualizados para atrair mais empresas.</p>
            </div>
            <button type="button" class="btn btn-primary" :disabled="salvando" @click="salvar">
                <span v-if="salvando" class="spinner-border spinner-border-sm me-1"></span>
                Salvar Alterações
            </button>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h2 class="text-uppercase text-secondary small fw-bold mb-3">Dados Acadêmicos (SIG)</h2>

                        <div class="mb-3">
                            <p class="text-secondary small mb-0">Instituição</p>
                            <p class="fw-semibold mb-0">{{ dadosAcademicos.instituicao }}</p>
                        </div>
                        <div class="mb-3">
                            <p class="text-secondary small mb-0">Curso</p>
                            <p class="fw-semibold mb-0">{{ dadosAcademicos.curso }}</p>
                        </div>
                        <div class="mb-3">
                            <p class="text-secondary small mb-0">Unidade</p>
                            <p class="fw-semibold mb-0">{{ dadosAcademicos.unidade }}</p>
                        </div>
                        <div class="mb-3">
                            <p class="text-secondary small mb-0">Ano de Conclusão</p>
                            <p class="fw-semibold mb-0">{{ dadosAcademicos.anoConclusao }}</p>
                        </div>

                        <div class="alert alert-light border small mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Dados sincronizados via API SIG (FR4).
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="text-uppercase text-secondary small fw-bold mb-3">Links Externos</h2>

                        <div class="mb-3">
                            <label class="form-label">LinkedIn</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-linkedin"></i></span>
                                <input v-model="links.linkedin" type="text" class="form-control" placeholder="linkedin.com/in/seuuser">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Portfólio</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-globe"></i></span>
                                <input v-model="links.portfolio" type="text" class="form-control" placeholder="https://meuportfolio.com">
                            </div>
                        </div>
                        <div>
                            <label class="form-label">GitHub</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-github"></i></span>
                                <input v-model="links.github" type="text" class="form-control" placeholder="github.com/seuuser">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h2 class="text-uppercase text-secondary small fw-bold mb-3">Informações Profissionais (FR5)</h2>

                        <div class="mb-3">
                            <label class="form-label">Sobre Mim</label>
                            <textarea
                                v-model="perfil.sobreMim"
                                class="form-control"
                                rows="4"
                                placeholder="Fale um pouco sobre sua trajetória, objetivos e o que você domina..."
                            ></textarea>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label">Cargo de Interesse</label>
                                <input
                                    v-model="perfil.cargoInteresse"
                                    type="text"
                                    class="form-control"
                                    placeholder="Ex: Desenvolvedor Front-end Junior"
                                >
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Área de Atuação</label>
                                <select v-model="perfil.areaAtuacao" class="form-select">
                                    <option>Tecnologia da Informação</option>
                                    <option>Administração</option>
                                    <option>Marketing</option>
                                    <option>Recursos Humanos</option>
                                    <option>Outra</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="form-label">Habilidades (Tags)</label>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span v-for="(hab, i) in perfil.habilidades" :key="hab" class="badge text-bg-primary-subtle text-primary d-flex align-items-center gap-1 py-2 px-3">
                                    {{ hab }}
                                    <i class="bi bi-x" role="button" @click="removerHabilidade(i)"></i>
                                </span>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary rounded-circle"
                                    style="width: 28px; height: 28px;"
                                    @click="adicionarHabilidade"
                                >
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="text-uppercase text-secondary small fw-bold mb-3">Preferências de Trabalho (FR6)</h2>

                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label d-block">Tipo de Contratação</label>
                                <div class="form-check form-check-inline">
                                    <input v-model="preferencias.clt" class="form-check-input" type="checkbox" id="tipoClt">
                                    <label class="form-check-label" for="tipoClt">CLT</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input v-model="preferencias.estagio" class="form-check-input" type="checkbox" id="tipoEstagio">
                                    <label class="form-check-label" for="tipoEstagio">Estágio</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input v-model="preferencias.jovemAprendiz" class="form-check-input" type="checkbox" id="tipoJovemAprendiz">
                                    <label class="form-check-label" for="tipoJovemAprendiz">Jovem Aprendiz</label>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Disponibilidade de Horário</label>
                                <select v-model="preferencias.disponibilidade" class="form-select">
                                    <option>Manhã</option>
                                    <option>Tarde/Noite</option>
                                    <option>Integral</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Região Administrativa (RA)</label>
                                <input v-model="preferencias.regiaoAdministrativa" type="text" class="form-control" placeholder="Ex: Ceilândia">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Pretensão Salarial (Opcional)</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input v-model.number="preferencias.pretensaoSalarial" type="number" min="0" step="0.01" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';

// TODO: carregar via GET /api/candidatos/{matricula} e enviar alterações via
// os endpoints em routes/api.php (candidatos/{matricula}/perfil/*).
const dadosAcademicos = reactive({
    instituicao: 'Senac Distrito Federal',
    curso: 'Técnico em Informática',
    unidade: 'Senac Taguatinga',
    anoConclusao: '2025',
});

const links = reactive({
    linkedin: '',
    portfolio: '',
    github: '',
});

const perfil = reactive({
    sobreMim: '',
    cargoInteresse: '',
    areaAtuacao: 'Tecnologia da Informação',
    habilidades: ['JavaScript', 'HTML/CSS', 'Pacote Office'],
});

const preferencias = reactive({
    clt: true,
    estagio: true,
    jovemAprendiz: false,
    disponibilidade: 'Tarde/Noite',
    regiaoAdministrativa: 'Ceilândia',
    pretensaoSalarial: 0,
});

const salvando = ref(false);

function adicionarHabilidade() {
    const nova = window.prompt('Nova habilidade:');
    if (nova?.trim()) {
        perfil.habilidades.push(nova.trim());
    }
}

function removerHabilidade(indice) {
    perfil.habilidades.splice(indice, 1);
}

async function salvar() {
    salvando.value = true;
    try {
        // TODO: enviar perfil, links e preferencias para a API.
    } finally {
        salvando.value = false;
    }
}
</script>
