<template>
    <div>
        <header class="bg-primary text-white px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2 sticky-top z-3">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold fs-5">Senac</span>
                <span class="vr d-none d-sm-block opacity-50 mx-1"></span>
                <div>
                    <h1 class="h5 fw-bold mb-0">Meu Perfil Profissional</h1>
                    <p class="small mb-0 opacity-75">Mantenha seus dados atualizados para atrair mais empresas.</p>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button type="button" class="perfil-pessoal-botao d-flex align-items-center gap-2 border-0 bg-transparent text-white p-0" @click="abrirModalInformacoesPessoais">
                    <div class="text-end d-none d-sm-block">
                        <p class="fw-semibold mb-0">{{ auth.pessoa?.nome || 'Aluno' }}</p>
                    </div>
                    <span class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center fw-semibold flex-shrink-0"
                          style="width: 38px; height: 38px;">
                        {{ iniciais }}
                    </span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-light ms-2" @click="sair">
                    <i class="bi bi-box-arrow-left me-1"></i> Sair
                </button>
            </div>
        </header>

        <div class="container-fluid p-4">
        <transition name="toast-fade">
            <div v-if="mensagem" class="toast-flutuante shadow-sm" :class="`toast-${mensagem.tipo || 'aviso'}`" role="status" aria-live="polite">
                <div class="d-flex align-items-start gap-2">
                    <i :class="iconeToast"></i>
                    <div class="flex-grow-1 toast-texto">{{ mensagem.texto }}</div>
                    <button type="button" class="btn-close btn-close-sm" aria-label="Fechar notificação" @click="fecharMensagem"></button>
                </div>
            </div>
        </transition>

        <transition name="app-modal">
            <div
                v-if="modalInformacoesPessoaisAberto"
                class="modal fade show d-block"
                tabindex="-1"
                role="dialog"
                aria-modal="true"
                @click.self="fecharModalInformacoesPessoais"
            >
                <div class="modal-dialog modal-dialog-centered app-modal-dialog-animated">
                    <div class="modal-content border-0 shadow-sm">
                        <div class="modal-header">
                            <h2 class="modal-title h5 mb-0">Informações Pessoais</h2>
                            <button type="button" class="btn-close" aria-label="Fechar" @click="fecharModalInformacoesPessoais"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nome</label>
                                <input v-model.trim="informacoesPessoais.nome" type="text" class="form-control" :class="campoInvalido('nome')" maxlength="100">
                                <div v-if="erroDeCampo('nome')" class="invalid-feedback d-block">{{ erroDeCampo('nome') }}</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">E-mail</label>
                                <input :value="informacoesPessoais.email" type="email" class="form-control" readonly disabled>
                                <div class="form-text">O e-mail permanece somente leitura nesta etapa.</div>
                            </div>
                            <div>
                                <label class="form-label">Telefone / WhatsApp</label>
                                <input
                                    v-model="informacoesPessoais.telefone"
                                    type="tel"
                                    inputmode="numeric"
                                    autocomplete="tel"
                                    class="form-control"
                                    :class="campoInvalido('telefone')"
                                    maxlength="16"
                                    @input="onTelefoneInformacoesPessoaisInput"
                                >
                                <div v-if="erroDeCampo('telefone')" class="invalid-feedback d-block">{{ erroDeCampo('telefone') }}</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" @click="fecharModalInformacoesPessoais">Cancelar</button>
                            <button type="button" class="btn btn-primary" :disabled="salvandoInformacoesPessoais" @click="salvarInformacoesPessoais">
                                <span v-if="salvandoInformacoesPessoais" class="spinner-border spinner-border-sm me-2"></span>
                                Salvar alterações
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
        <transition name="app-modal">
            <div v-if="modalInformacoesPessoaisAberto" class="modal-backdrop fade show"></div>
        </transition>


        <div v-if="carregando" class="text-center text-secondary py-5">
            <span class="spinner-border spinner-border-sm me-2"></span> Carregando perfil...
        </div>

        <template v-else>
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h2 class="text-uppercase text-secondary small fw-bold mb-0">Cursos Externos</h2>
                                <button type="button" class="btn btn-sm btn-primary" @click="mostrarFormCursoExterno = !mostrarFormCursoExterno">
                                    <i class="bi bi-plus-lg me-1"></i> Adicionar
                                </button>
                            </div>

                            <p class="text-secondary small mb-3">Adicione cursos concluídos ou que você está cursando atualmente.</p>

                            <p v-if="!cursosExternos.length && !mostrarFormCursoExterno" class="text-secondary small mb-0">
                                Nenhum curso externo cadastrado ainda.
                            </p>

                            <div v-for="curso in cursosExternos" :key="curso.id" class="d-flex align-items-start justify-content-between mb-3">
                                <div>
                                    <p class="fw-semibold mb-0">{{ curso.nome_curso }}</p>
                                    <p class="text-secondary small mb-0 d-flex flex-wrap align-items-center gap-1">
                                        <span>{{ curso.instituicao }}</span>
                                        <span> | </span>
                                        <template v-if="cursoExternoEstaEmAndamento(curso.concluido_em)">
                                            <span class="badge text-bg-primary-subtle text-primary fw-semibold">Cursando</span>
                                            <span> | </span>
                                            <span>Previsão de conclusão em {{ anoDe(curso.concluido_em) }}</span>
                                        </template>
                                        <template v-else>
                                            <span>Concluído em {{ anoDe(curso.concluido_em) }}</span>
                                        </template>
                                        <template v-if="curso.carga_horaria">
                                            <span> | </span>
                                            <span>{{ curso.carga_horaria }}h</span>
                                        </template>
                                    </p>
                                </div>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0" @click="removerCursoExterno(curso.id)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>

                            <div v-if="mostrarFormCursoExterno" class="border rounded p-3 mt-2">
                                <div class="mb-2">
                                    <label class="form-label small mb-1">Nome do Curso <span class="text-danger">*</span></label>
                                    <input v-model="novoCursoExterno.nome_curso" type="text" class="form-control form-control-sm" :class="campoInvalido('nome_curso')" placeholder="Ex: Inglês Intermediário">
                                    <div v-if="erroDeCampo('nome_curso')" class="invalid-feedback d-block">{{ erroDeCampo('nome_curso') }}</div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-sm-6">
                                        <label class="form-label small mb-1">Instituição <span class="text-danger">*</span></label>
                                        <input v-model="novoCursoExterno.instituicao" type="text" class="form-control form-control-sm" :class="campoInvalido('instituicao')" placeholder="Ex: CNA">
                                        <div v-if="erroDeCampo('instituicao')" class="invalid-feedback d-block">{{ erroDeCampo('instituicao') }}</div>
                                    </div>
                                    <div class="col-sm-3">
                                        <label class="form-label small mb-1">Carga Horária</label>
                                        <input
                                            :value="novoCursoExterno.carga_horaria ?? ''"
                                            type="text"
                                            inputmode="numeric"
                                            class="form-control form-control-sm sem-setas"
                                            placeholder="120"
                                            @keydown="bloquearSinalNegativo"
                                            @input="normalizarCargaHorariaCursoExterno"
                                        >
                                    </div>
                                    <div class="col-sm-3">
                                        <label class="form-label small mb-1">Término / Previsão de conclusão <span class="text-danger">*</span></label>
                                        <input v-model="novoCursoExterno.concluido_em" type="date" class="form-control form-control-sm" :class="campoInvalido('concluido_em')">
                                        <div class="form-text small">Informe a data de conclusão ou a previsão de término do curso.</div>
                                        <div v-if="erroDeCampo('concluido_em')" class="invalid-feedback d-block">{{ erroDeCampo('concluido_em') }}</div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 justify-content-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="cancelarCursoExterno">Cancelar</button>
                                    <button type="button" class="btn btn-sm btn-primary" @click="adicionarCursoExterno">Salvar</button>
                                </div>
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
                            <p class="small text-secondary mb-3"><span class="text-danger fw-semibold">*</span> Campos obrigatórios</p>

                            <div class="mb-3">
                                <label class="form-label">Sobre Mim</label>
                                <textarea
                                    v-model="perfil.sobre_mim"
                                    class="form-control"
                                    rows="4"
                                    maxlength="200"
                                    placeholder="Fale um pouco sobre sua trajetória, objetivos e o que você domina..."
                                ></textarea>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label">Cargo de Interesse</label>
                                    <input
                                        v-model="perfil.cargo_de_interesse"
                                        type="text"
                                        class="form-control"
                                        placeholder="Ex: Desenvolvedor Front-end Junior"
                                    >
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Área de Atuação <span class="text-danger">*</span></label>
                                    <select v-model="perfil.area_de_atuacao" class="form-select" :class="campoInvalido('area_de_atuacao')">
                                        <option value="">Selecione</option>
                                        <option>Tecnologia da Informação</option>
                                        <option>Administração</option>
                                        <option>Marketing</option>
                                        <option>Recursos Humanos</option>
                                        <option>Outra</option>
                                    </select>
                                    <div v-if="erroDeCampo('area_de_atuacao')" class="invalid-feedback d-block">{{ erroDeCampo('area_de_atuacao') }}</div>
                                </div>
                            </div>

                            <div class="position-relative" ref="habilidadesDropdownContainer">
                                <label class="form-label">Habilidades (Tags)</label>
                                <div class="d-flex flex-column gap-2">
                                    <div>
                                        <div v-if="perfil.habilidades.length" class="d-flex flex-wrap align-items-center gap-2">
                                            <span v-for="(hab, i) in perfil.habilidades" :key="`${hab}-${i}`" class="badge habilidade-chip d-inline-flex align-items-center gap-2 py-2 px-3">
                                                <span>{{ hab }}</span>
                                                <button type="button" class="btn-close btn-close-sm habilidade-chip-fechar" aria-label="Remover habilidade" @click="removerHabilidade(i)"></button>
                                            </span>
                                        </div>
                                        <p v-else class="small text-secondary mb-0">Nenhuma habilidade adicionada.</p>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-outline-primary" @click="alternarDropdownHabilidades">
                                            <i class="bi" :class="mostrarDropdownHabilidades ? 'bi-x-lg' : 'bi-plus-lg'"></i>
                                            <span class="ms-1">{{ mostrarDropdownHabilidades ? 'Fechar habilidades' : 'Adicionar habilidade' }}</span>
                                        </button>
                                    </div>
                                    <div v-if="mostrarDropdownHabilidades" class="habilidades-dropdown border rounded shadow-sm bg-white p-3">
                                        <div>
                                            <p class="small text-secondary fw-semibold mb-2">Habilidades Técnicas</p>
                                            <div class="d-flex flex-wrap gap-2">
                                                <button
                                                    v-for="habilidade in sugestoesHabilidadesTecnicas"
                                                    :key="habilidade"
                                                    type="button"
                                                    class="btn btn-sm sugestao-habilidade"
                                                    :class="habilidadeSelecionada(habilidade) ? 'btn-primary' : 'btn-outline-primary'"
                                                    @click="adicionarHabilidadeSugerida(habilidade)"
                                                >
                                                    {{ habilidade }}
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <p class="small text-secondary fw-semibold mb-2">Soft Skills</p>
                                            <div class="d-flex flex-wrap gap-2">
                                                <button
                                                    v-for="habilidade in sugestoesSoftSkills"
                                                    :key="habilidade"
                                                    type="button"
                                                    class="btn btn-sm sugestao-habilidade"
                                                    :class="habilidadeSelecionada(habilidade) ? 'btn-primary' : 'btn-outline-primary'"
                                                    @click="adicionarHabilidadeSugerida(habilidade)"
                                                >
                                                    {{ habilidade }}
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <p class="small text-secondary fw-semibold mb-2">Outra habilidade</p>
                                            <div class="d-flex flex-column flex-sm-row gap-2">
                                                <input
                                                    ref="habilidadeInput"
                                                    v-model="novaHabilidade"
                                                    type="text"
                                                    class="form-control"
                                                    placeholder="Digite uma habilidade..."
                                                    maxlength="45"
                                                    @keydown.enter.prevent="adicionarHabilidade"
                                                >
                                                <button type="button" class="btn btn-outline-primary flex-shrink-0" @click="adicionarHabilidade">
                                                    <i class="bi bi-plus-lg me-1"></i> Adicionar
                                                </button>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end mt-4 pt-2">
                                            <button type="button" class="btn btn-sm btn-primary px-4" @click="fecharDropdownHabilidades">Concluir</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3">
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
                                    <select v-model="preferencias.disponibilidade_de_horario" class="form-select">
                                        <option>Manhã</option>
                                        <option>Tarde</option>
                                        <option>Noite</option>
                                        <option>Integral</option>
                                    </select>
                                </div>
                                <div class="col-sm-6 position-relative">
                                    <label class="form-label">Região Administrativa (RA) <span class="text-danger">*</span></label>
                                    <input
                                        ref="raInput"
                                        v-model="buscaRegiaoAdministrativa"
                                        type="text"
                                        class="form-control"
                                        :class="campoInvalido('regiao_administrativa')"
                                        placeholder="Pesquisar Região Administrativa..."
                                        autocomplete="off"
                                        @focus="abrirDropdownRegiaoAdministrativa"
                                        @input="aoDigitarRegiaoAdministrativa"
                                        @keydown.down.prevent="destacarProximaRegiaoAdministrativa"
                                        @keydown.up.prevent="destacarRegiaoAdministrativaAnterior"
                                        @keydown.enter.prevent="selecionarRegiaoAdministrativaDestacada"
                                        @keydown.esc="fecharDropdownRegiaoAdministrativa"
                                        @blur="agendarFechamentoDropdownRegiaoAdministrativa"
                                    >
                                    <div
                                        v-if="mostrarDropdownRegiaoAdministrativa"
                                        class="dropdown-menu d-block w-100 mt-1 shadow-sm ra-dropdown"
                                    >
                                        <button
                                            v-for="(opcao, index) in regioesAdministrativasFiltradas"
                                            :key="opcao.value"
                                            type="button"
                                            class="dropdown-item"
                                            :class="{ active: index === indiceRegiaoAdministrativaDestacada }"
                                            @mousedown.prevent="selecionarRegiaoAdministrativa(opcao)"
                                        >
                                            {{ opcao.label }}
                                        </button>
                                        <span v-if="!regioesAdministrativasFiltradas.length" class="dropdown-item-text text-secondary small">
                                            Nenhuma Região Administrativa encontrada.
                                        </span>
                                    </div>
                                    <div v-if="erroDeCampo('regiao_administrativa')" class="invalid-feedback d-block">{{ erroDeCampo('regiao_administrativa') }}</div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Pretensão Salarial (Opcional)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">R$</span>
                                        <input
                                            :value="preferencias.pretensao_salarial"
                                            type="text"
                                            inputmode="numeric"
                                            class="form-control sem-setas"
                                            @keydown="bloquearSinalNegativo"
                                            @input="aplicarMascaraPretensaoSalarial"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-0">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h2 class="text-uppercase text-secondary small fw-bold mb-0">Experiências Profissionais</h2>
                                <button type="button" class="btn btn-sm btn-primary" @click="mostrarFormExperiencia = !mostrarFormExperiencia">
                                    <i class="bi bi-plus-lg me-1"></i> Adicionar
                                </button>
                            </div>

                            <p v-if="!experiencias.length && !mostrarFormExperiencia" class="text-secondary small mb-0">
                                Nenhuma experiência profissional cadastrada ainda.
                            </p>

                            <div v-for="exp in experiencias" :key="exp.id" class="d-flex align-items-start justify-content-between border-bottom pb-3 mb-3">
                                <div>
                                    <span class="badge text-bg-primary-subtle text-primary mb-1">{{ exp.tipo }}</span>
                                    <p class="fw-semibold mb-0">{{ exp.cargo }}</p>
                                    <p class="text-secondary small mb-1">{{ exp.empresa }}</p>
                                    <p class="text-secondary small mb-1">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        {{ mesAno(exp.data_inicio) }} - {{ exp.data_fim ? mesAno(exp.data_fim) : 'Atual' }}
                                        · {{ duracao(exp.data_inicio, exp.data_fim) }}
                                        <template v-if="exp.local"> · {{ exp.local }}</template>
                                    </p>
                                    <p v-if="exp.descricao" class="small mb-0">{{ exp.descricao }}</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0" @click="removerExperiencia(exp.id)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>

                            <div v-if="mostrarFormExperiencia" class="border rounded p-3">
                                <div class="row g-2 mb-2">
                                    <div class="col-sm-4">
                                        <label class="form-label small mb-1">Tipo <span class="text-danger">*</span></label>
                                        <select v-model="novaExperiencia.tipo" class="form-select form-select-sm" :class="campoInvalido('tipo')">
                                            <option>Estágio</option>
                                            <option>CLT</option>
                                            <option>PJ / Freelancer</option>
                                            <option>Jovem Aprendiz</option>
                                            <option>Voluntariado</option>
                                        </select>
                                        <div v-if="erroDeCampo('tipo')" class="invalid-feedback d-block">{{ erroDeCampo('tipo') }}</div>
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label small mb-1">Cargo <span class="text-danger">*</span></label>
                                        <input v-model="novaExperiencia.cargo" type="text" class="form-control form-control-sm" :class="campoInvalido('cargo')" placeholder="Ex: Desenvolvedor Web Estagiário">
                                        <div v-if="erroDeCampo('cargo')" class="invalid-feedback d-block">{{ erroDeCampo('cargo') }}</div>
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label small mb-1">Empresa <span class="text-danger">*</span></label>
                                        <input v-model="novaExperiencia.empresa" type="text" class="form-control form-control-sm" :class="campoInvalido('empresa')" placeholder="Ex: TechSolutions LTDA">
                                        <div v-if="erroDeCampo('empresa')" class="invalid-feedback d-block">{{ erroDeCampo('empresa') }}</div>
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-sm-3">
                                        <label class="form-label small mb-1">Data de início <span class="text-danger">*</span></label>
                                        <input v-model="novaExperiencia.data_inicio" type="date" class="form-control form-control-sm" :class="campoInvalido('data_inicio')">
                                        <div v-if="erroDeCampo('data_inicio')" class="invalid-feedback d-block">{{ erroDeCampo('data_inicio') }}</div>
                                    </div>
                                    <div class="col-sm-3">
                                        <label class="form-label small mb-1">Fim</label>
                                        <input v-model="novaExperiencia.data_fim" type="date" class="form-control form-control-sm" :disabled="novaExperiencia.atual" :class="campoInvalido('data_fim')">
                                        <div v-if="erroDeCampo('data_fim')" class="invalid-feedback d-block">{{ erroDeCampo('data_fim') }}</div>
                                    </div>
                                    <div class="col-sm-2 d-flex align-items-end">
                                        <div class="form-check">
                                            <input v-model="novaExperiencia.atual" class="form-check-input" type="checkbox" id="expAtual" @change="alternarExperienciaAtual">
                                            <label class="form-check-label small" for="expAtual">Atual</label>
                                        </div>
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label small mb-1">Local</label>
                                        <input v-model="novaExperiencia.local" type="text" class="form-control form-control-sm" placeholder="Ex: Brasília, DF">
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small mb-1">Descrição</label>
                                    <textarea v-model="novaExperiencia.descricao" class="form-control form-control-sm" rows="2" placeholder="Principais atividades e responsabilidades..."></textarea>
                                </div>
                                <div class="d-flex gap-2 justify-content-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="cancelarExperiencia">Cancelar</button>
                                    <button type="button" class="btn btn-sm btn-primary" @click="adicionarExperiencia">Salvar</button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="button" class="btn btn-primary" :disabled="salvando" @click="salvar">
                    <span v-if="salvando" class="spinner-border spinner-border-sm me-1"></span>
                    Salvar Alterações
                </button>
            </div>
        </template>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../../store/auth';
import alunosService from '../../../services/alunosServices';
import { formatarTelefone, somenteNumeros } from '../../../utils/telefone';

const auth = useAuthStore();
const router = useRouter();
const raInput = ref(null);
const habilidadeInput = ref(null);
const habilidadesDropdownContainer = ref(null);

// Esta página não usa o AlunoLayout (sem sidebar, cabeçalho próprio),
// então precisa resolver iniciais/logout localmente.
const iniciais = computed(() => {
    const nome = auth.pessoa?.nome || 'Aluno';
    return nome
        .split(' ')
        .slice(0, 2)
        .map((parte) => parte[0])
        .join('')
        .toUpperCase();
});

async function sair() {
    await auth.logout();
    router.push({ name: 'login' });
}
const matricula = computed(() => auth.pessoa?.candidato?.matricula || auth.pessoa?.matricula);

const carregando = ref(true);
const salvando = ref(false);
const mensagem = ref(null);
const errosFormulario = ref({});
let timeoutMensagem = null;

const dadosAcademicos = ref(null);
const cursosSenac = ref([]);
const cursosExternos = ref([]);
const experiencias = ref([]);
const modalInformacoesPessoaisAberto = ref(false);
const salvandoInformacoesPessoais = ref(false);

const mostrarFormCursoExterno = ref(false);
const mostrarFormExperiencia = ref(false);
const buscaRegiaoAdministrativa = ref('');
const novaHabilidade = ref('');
const mostrarDropdownRegiaoAdministrativa = ref(false);
const mostrarDropdownHabilidades = ref(false);
const indiceRegiaoAdministrativaDestacada = ref(-1);
let timeoutFechamentoDropdownRegiaoAdministrativa = null;

const links = reactive({
    linkedin: '',
    portfolio: '',
    github: '',
});

const informacoesPessoais = reactive({
    nome: '',
    email: '',
    telefone: '',
});

const sugestoesHabilidadesTecnicas = [
    'Excel',
    'Word',
    'PowerPoint',
    'Power BI',
    'HTML',
    'CSS',
    'JavaScript',
    'Vue.js',
    'PHP',
    'Laravel',
    'MySQL',
    'Git',
    'Redes',
    'Suporte Técnico',
    'Pacote Office',
    'Segurança da Informação',
    'Banco de Dados',
];

const sugestoesSoftSkills = [
    'Comunicação',
    'Trabalho em Equipe',
    'Organização',
    'Proatividade',
    'Liderança',
    'Criatividade',
    'Adaptabilidade',
    'Resolução de Problemas',
    'Pensamento Crítico',
    'Inteligência Emocional',
    'Gestão do Tempo',
    'Empatia',
    'Responsabilidade',
    'Comprometimento',
    'Flexibilidade',
    'Autonomia',
    'Atenção aos Detalhes',
    'Capacidade de Aprendizado',
    'Relacionamento Interpessoal',
    'Tomada de Decisão',
];

const regioesAdministrativas = [
    { label: 'RA 1º - Plano Piloto', value: 'Plano Piloto' },
    { label: 'RA 2º - Gama', value: 'Gama' },
    { label: 'RA 3º - Taguatinga', value: 'Taguatinga' },
    { label: 'RA 4º - Brazlândia', value: 'Brazlândia' },
    { label: 'RA 5º - Sobradinho', value: 'Sobradinho' },
    { label: 'RA 6º - Planaltina', value: 'Planaltina' },
    { label: 'RA 7º - Paranoá', value: 'Paranoá' },
    { label: 'RA 8º - Núcleo Bandeirante', value: 'Núcleo Bandeirante' },
    { label: 'RA 9º - Ceilândia', value: 'Ceilândia' },
    { label: 'RA 10º - Guará', value: 'Guará' },
    { label: 'RA 11º - Cruzeiro', value: 'Cruzeiro' },
    { label: 'RA 12º - Samambaia', value: 'Samambaia' },
    { label: 'RA 13º - Santa Maria', value: 'Santa Maria' },
    { label: 'RA 14º - São Sebastião', value: 'São Sebastião' },
    { label: 'RA 15º - Recanto das Emas', value: 'Recanto das Emas' },
    { label: 'RA 16º - Lago Sul', value: 'Lago Sul' },
    { label: 'RA 17º - Riacho Fundo', value: 'Riacho Fundo' },
    { label: 'RA 18º - Lago Norte', value: 'Lago Norte' },
    { label: 'RA 19º - Candangolândia', value: 'Candangolândia' },
    { label: 'RA 20º - Águas Claras', value: 'Águas Claras' },
    { label: 'RA 21º - Riacho Fundo II', value: 'Riacho Fundo II' },
    { label: 'RA 22º - Sudoeste/Octogonal', value: 'Sudoeste/Octogonal' },
    { label: 'RA 23º - Varjão', value: 'Varjão' },
    { label: 'RA 24º - Park Way', value: 'Park Way' },
    { label: 'RA 25º - SCIA / Estrutural', value: 'SCIA / Estrutural' },
    { label: 'RA 26º - Sobradinho II', value: 'Sobradinho II' },
    { label: 'RA 27º - Jardim Botânico', value: 'Jardim Botânico' },
    { label: 'RA 28º - Itapoã', value: 'Itapoã' },
    { label: 'RA 29º - SIA (Setor de Indústria e Abastecimento)', value: 'SIA (Setor de Indústria e Abastecimento)' },
    { label: 'RA 30º - Vicente Pires', value: 'Vicente Pires' },
    { label: 'RA 31º - Fercal', value: 'Fercal' },
    { label: 'RA 32º - Sol Nascente / Pôr do Sol', value: 'Sol Nascente / Pôr do Sol' },
    { label: 'RA 33º - Arniqueira', value: 'Arniqueira' },
    { label: 'RA 34º - Arapoanga', value: 'Arapoanga' },
    { label: 'RA 35º - Água Quente', value: 'Água Quente' },
    { label: 'RA 36º - 26 de Setembro', value: '26 de Setembro' },
    { label: 'RA 37º - Ponte Alta', value: 'Ponte Alta' },
];

const regioesAdministrativasPermitidas = regioesAdministrativas.map((regiao) => regiao.value);

const regioesAdministrativasFiltradas = computed(() => {
    const termo = normalizarTexto(buscaRegiaoAdministrativa.value);

    if (!termo) {
        return regioesAdministrativas;
    }

    return regioesAdministrativas.filter((opcao) => {
        const labelNormalizado = normalizarTexto(opcao.label);
        const valueNormalizado = normalizarTexto(opcao.value);
        return labelNormalizado.includes(termo) || valueNormalizado.includes(termo);
    });
});

const iconeToast = computed(() => {
    if (mensagem.value?.tipo === 'sucesso') {
        return 'bi bi-check-circle-fill toast-icone';
    }

    if (mensagem.value?.tipo === 'aviso') {
        return 'bi bi-exclamation-triangle-fill toast-icone';
    }

    return 'bi bi-exclamation-octagon-fill toast-icone';
});

const perfil = reactive({
    sobre_mim: '',
    cargo_de_interesse: '',
    area_de_atuacao: 'Tecnologia da Informação',
    habilidades: [],
});

// Bitmask: CLT=1, Estagio=2, Jovem Aprendiz=4
const preferencias = reactive({
    clt: false,
    estagio: false,
    jovemAprendiz: false,
    disponibilidade_de_horario: 'Manhã',
    regiao_administrativa: '',
    pretensao_salarial: '',
});

function formatarPretensaoSalarial(valor) {
    const apenasDigitos = String(valor ?? '').replace(/\D/g, '');

    if (!apenasDigitos) {
        return '';
    }

    const valorEmCentavos = Number(apenasDigitos) / 100;

    return valorEmCentavos.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function formatarDataParaApi(valor) {
    if (!valor) {
        return '';
    }

    if (/^\d{4}-\d{2}-\d{2}$/.test(valor)) {
        return valor;
    }

    const partes = String(valor).split('/');

    if (partes.length === 3) {
        const [dia, mes, ano] = partes;
        return `${ano}-${mes.padStart(2, '0')}-${dia.padStart(2, '0')}`;
    }

    return valor;
}

function normalizarTexto(valor) {
    return String(valor ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

function obterLabelRegiaoAdministrativa(valor) {
    return regioesAdministrativas.find((regiao) => regiao.value === valor)?.label || valor || '';
}

function sincronizarBuscaRegiaoAdministrativa() {
    buscaRegiaoAdministrativa.value = obterLabelRegiaoAdministrativa(preferencias.regiao_administrativa);
}

function abrirDropdownRegiaoAdministrativa() {
    if (timeoutFechamentoDropdownRegiaoAdministrativa) {
        clearTimeout(timeoutFechamentoDropdownRegiaoAdministrativa);
        timeoutFechamentoDropdownRegiaoAdministrativa = null;
    }
    mostrarDropdownRegiaoAdministrativa.value = true;
    indiceRegiaoAdministrativaDestacada.value = regioesAdministrativasFiltradas.value.length ? 0 : -1;
}

function fecharDropdownRegiaoAdministrativa() {
    mostrarDropdownRegiaoAdministrativa.value = false;
    indiceRegiaoAdministrativaDestacada.value = -1;
    sincronizarBuscaRegiaoAdministrativa();
}

function agendarFechamentoDropdownRegiaoAdministrativa() {
    timeoutFechamentoDropdownRegiaoAdministrativa = setTimeout(() => {
        fecharDropdownRegiaoAdministrativa();
    }, 150);
}

function aoDigitarRegiaoAdministrativa() {
    mostrarDropdownRegiaoAdministrativa.value = true;
    indiceRegiaoAdministrativaDestacada.value = regioesAdministrativasFiltradas.value.length ? 0 : -1;
    preferencias.regiao_administrativa = '';
}

function selecionarRegiaoAdministrativa(opcao) {
    preferencias.regiao_administrativa = opcao.value;
    buscaRegiaoAdministrativa.value = opcao.label;
    mostrarDropdownRegiaoAdministrativa.value = false;
    indiceRegiaoAdministrativaDestacada.value = -1;
}

function destacarProximaRegiaoAdministrativa() {
    if (!mostrarDropdownRegiaoAdministrativa.value) {
        abrirDropdownRegiaoAdministrativa();
        return;
    }

    if (!regioesAdministrativasFiltradas.value.length) {
        indiceRegiaoAdministrativaDestacada.value = -1;
        return;
    }

    indiceRegiaoAdministrativaDestacada.value = indiceRegiaoAdministrativaDestacada.value < regioesAdministrativasFiltradas.value.length - 1
        ? indiceRegiaoAdministrativaDestacada.value + 1
        : 0;
}

function destacarRegiaoAdministrativaAnterior() {
    if (!mostrarDropdownRegiaoAdministrativa.value) {
        abrirDropdownRegiaoAdministrativa();
        return;
    }

    if (!regioesAdministrativasFiltradas.value.length) {
        indiceRegiaoAdministrativaDestacada.value = -1;
        return;
    }

    indiceRegiaoAdministrativaDestacada.value = indiceRegiaoAdministrativaDestacada.value > 0
        ? indiceRegiaoAdministrativaDestacada.value - 1
        : regioesAdministrativasFiltradas.value.length - 1;
}

function selecionarRegiaoAdministrativaDestacada() {
    const opcao = regioesAdministrativasFiltradas.value[indiceRegiaoAdministrativaDestacada.value];
    if (opcao) {
        selecionarRegiaoAdministrativa(opcao);
    }
}

function converterPretensaoSalarialParaNumero(valor) {
    const apenasDigitos = String(valor ?? '').replace(/\D/g, '');

    if (!apenasDigitos) {
        return null;
    }

    return Number(apenasDigitos) / 100;
}

function aplicarMascaraPretensaoSalarial(evento) {
    preferencias.pretensao_salarial = formatarPretensaoSalarial(evento.target.value);
}

function bloquearSinalNegativo(evento) {
    if (evento.key === '-') {
        evento.preventDefault();
    }
}

function normalizarCargaHorariaCursoExterno(evento) {
    const apenasDigitos = String(evento.target.value ?? '').replace(/\D/g, '');

    novoCursoExterno.carga_horaria = apenasDigitos === '' ? null : Number(apenasDigitos);
}

function cursoExternoVazio() {
    return { nome_curso: '', instituicao: '', carga_horaria: null, concluido_em: '' };
}

function experienciaVazia() {
    return { tipo: 'Estágio', cargo: '', empresa: '', local: '', data_inicio: '', data_fim: '', atual: false, descricao: '' };
}

const novoCursoExterno = reactive(cursoExternoVazio());
const novaExperiencia = reactive(experienciaVazia());

function anoDe(data) {
    if (!data) return '-';
    return new Date(data).getFullYear();
}

function dataLocalNormalizada(data) {
    if (!data) return null;

    const valor = String(data);
    const [ano, mes, dia] = valor.split('-').map(Number);

    if (!ano || !mes || !dia) {
        const dataConvertida = new Date(valor);
        return Number.isNaN(dataConvertida.getTime())
            ? null
            : new Date(dataConvertida.getFullYear(), dataConvertida.getMonth(), dataConvertida.getDate());
    }

    return new Date(ano, mes - 1, dia);
}

function cursoExternoEstaEmAndamento(data) {
    const conclusao = dataLocalNormalizada(data);

    if (!conclusao) {
        return false;
    }

    const hoje = new Date();
    const hojeLocal = new Date(hoje.getFullYear(), hoje.getMonth(), hoje.getDate());

    return conclusao > hojeLocal;
}

const MESES = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

function mesAno(data) {
    if (!data) return '';
    const d = new Date(data);
    return `${MESES[d.getMonth()]} ${d.getFullYear()}`;
}

function duracao(inicio, fim) {
    if (!inicio) return '';
    const dataInicio = new Date(inicio);
    const dataFim = fim ? new Date(fim) : new Date();
    const meses = Math.max(
        1,
        (dataFim.getFullYear() - dataInicio.getFullYear()) * 12 + (dataFim.getMonth() - dataInicio.getMonth()) + 1
    );
    return meses === 1 ? '1 mês' : `${meses} meses`;
}

function aplicarTipoContratacao(valor) {
    const bitmask = valor || 0;
    preferencias.clt = Boolean(bitmask & 1);
    preferencias.estagio = Boolean(bitmask & 2);
    preferencias.jovemAprendiz = Boolean(bitmask & 4);
}

function limparErrosFormulario() {
    errosFormulario.value = {};
}

function mostrarMensagem(tipo, texto) {
    mensagem.value = { tipo, texto };

    if (timeoutMensagem) {
        clearTimeout(timeoutMensagem);
    }

    timeoutMensagem = setTimeout(() => {
        mensagem.value = null;
        timeoutMensagem = null;
    }, 4500);
}

function sincronizarInformacoesPessoais() {
    informacoesPessoais.nome = auth.pessoa?.nome || '';
    informacoesPessoais.email = auth.pessoa?.email || '';
    informacoesPessoais.telefone = formatarTelefone(auth.pessoa?.telefone);
}

function abrirModalInformacoesPessoais() {
    limparErrosFormulario();
    sincronizarInformacoesPessoais();
    modalInformacoesPessoaisAberto.value = true;
}

function fecharModalInformacoesPessoais() {
    modalInformacoesPessoaisAberto.value = false;
}

function onTelefoneInformacoesPessoaisInput(evento) {
    const valorFormatado = formatarTelefone(evento.target.value);
    informacoesPessoais.telefone = valorFormatado;
    evento.target.value = valorFormatado;
}

async function salvarInformacoesPessoais() {
    salvandoInformacoesPessoais.value = true;
    limparErrosFormulario();

    try {
        const { data } = await alunosService.atualizarPerfil(matricula.value, {
            nome: informacoesPessoais.nome,
            telefone: somenteNumeros(informacoesPessoais.telefone),
        });

        auth.pessoa = {
            ...auth.pessoa,
            nome: data.pessoa?.nome || informacoesPessoais.nome,
            telefone: data.pessoa?.telefone || somenteNumeros(informacoesPessoais.telefone),
        };

        localStorage.setItem('ses_pessoa', JSON.stringify(auth.pessoa));
        sincronizarInformacoesPessoais();
        fecharModalInformacoesPessoais();
        mostrarMensagem('sucesso', 'Informações pessoais atualizadas com sucesso.');
    } catch (e) {
        definirErrosFormulario(e?.response?.data?.errors || {});
        mostrarMensagem('erro', 'Não foi possível atualizar as informações pessoais.');
    } finally {
        salvandoInformacoesPessoais.value = false;
    }
}

function fecharMensagem() {
    if (timeoutMensagem) {
        clearTimeout(timeoutMensagem);
        timeoutMensagem = null;
    }

    mensagem.value = null;
}

function definirErrosFormulario(erros = {}) {
    errosFormulario.value = Object.fromEntries(
        Object.entries(erros).map(([campo, mensagens]) => [campo, Array.isArray(mensagens) ? mensagens[0] : mensagens])
    );
}

function erroDeCampo(campo) {
    return errosFormulario.value[campo] || '';
}

function campoInvalido(campo) {
    return erroDeCampo(campo) ? 'is-invalid' : '';
}

function tipoContratacaoBitmask() {
    return (preferencias.clt ? 1 : 0) + (preferencias.estagio ? 2 : 0) + (preferencias.jovemAprendiz ? 4 : 0);
}

async function carregar() {
    carregando.value = true;
    try {
        const { data } = await alunosService.verPerfil(matricula.value);

        dadosAcademicos.value = data.dados_academicos?.[0] || null;
        cursosSenac.value = data.cursos_senac || [];
        cursosExternos.value = data.cursos_externos || [];
        experiencias.value = (data.experiencias_profissionais || []).slice().sort((a, b) => new Date(b.data_inicio) - new Date(a.data_inicio));

        if (data.link_externo) {
            links.linkedin = data.link_externo.linkedin || '';
            links.portfolio = data.link_externo.portfolio || '';
            links.github = data.link_externo.github || '';
        }

        if (data.informacoes_profissionais) {
            perfil.sobre_mim = data.informacoes_profissionais.sobre_mim || '';
            perfil.cargo_de_interesse = data.informacoes_profissionais.cargo_de_interesse || '';
            perfil.area_de_atuacao = data.informacoes_profissionais.area_de_atuacao || perfil.area_de_atuacao;
            perfil.habilidades = data.informacoes_profissionais.habilidades || [];
        }

        if (data.preferencias_de_trabalho) {
            aplicarTipoContratacao(data.preferencias_de_trabalho.tipo_de_contratacao);
            preferencias.disponibilidade_de_horario = data.preferencias_de_trabalho.disponibilidade_de_horario || preferencias.disponibilidade_de_horario;
            preferencias.regiao_administrativa = data.preferencias_de_trabalho.regiao_administrativa || '';
            preferencias.pretensao_salarial = formatarPretensaoSalarial(data.preferencias_de_trabalho.pretensao_salarial);
            sincronizarBuscaRegiaoAdministrativa();
        }
    } finally {
        carregando.value = false;
    }
}

function adicionarHabilidade() {
    const habilidade = novaHabilidade.value.trim();

    if (habilidade && !perfil.habilidades.includes(habilidade)) {
        perfil.habilidades.push(habilidade);
        novaHabilidade.value = '';
        habilidadeInput.value?.focus();
    }
}

function alternarDropdownHabilidades() {
    mostrarDropdownHabilidades.value = !mostrarDropdownHabilidades.value;

    if (mostrarDropdownHabilidades.value) {
        setTimeout(() => habilidadeInput.value?.focus(), 0);
    }
}

function fecharDropdownHabilidades() {
    mostrarDropdownHabilidades.value = false;
}

function adicionarHabilidadeSugerida(habilidade) {
    if (!perfil.habilidades.includes(habilidade)) {
        perfil.habilidades.push(habilidade);
    }
}

function habilidadeSelecionada(habilidade) {
    return perfil.habilidades.includes(habilidade);
}

function removerHabilidade(indice) {
    perfil.habilidades.splice(indice, 1);
}

function alternarExperienciaAtual() {
    if (novaExperiencia.atual) {
        novaExperiencia.data_fim = '';
    }
}

function aoClicarForaDosDropdowns(evento) {
    if (habilidadesDropdownContainer.value && !habilidadesDropdownContainer.value.contains(evento.target)) {
        fecharDropdownHabilidades();
    }
}

function cancelarCursoExterno() {
    Object.assign(novoCursoExterno, cursoExternoVazio());
    mostrarFormCursoExterno.value = false;
}

async function adicionarCursoExterno() {
    limparErrosFormulario();
    if (!novoCursoExterno.nome_curso.trim() || !novoCursoExterno.instituicao.trim() || !novoCursoExterno.concluido_em) {
        mostrarMensagem('erro', 'Preencha nome, instituição e data de conclusão do curso externo.');
        return;
    }
    try {
        await alunosService.adicionarCursoExterno(matricula.value, {
            ...novoCursoExterno,
            concluido_em: formatarDataParaApi(novoCursoExterno.concluido_em),
        });
        cancelarCursoExterno();
        await carregar();
    } catch (e) {
        definirErrosFormulario(e?.response?.data?.errors || {});
        const erroApi = e?.response?.data?.errors?.concluido_em?.[0]
            || e?.response?.data?.errors?.nome_curso?.[0]
            || e?.response?.data?.errors?.instituicao?.[0]
            || e?.response?.data?.errors?.carga_horaria?.[0]
            || e?.response?.data?.message;

        mostrarMensagem('erro', erroApi || 'Não foi possível adicionar o curso. Verifique os campos informados.');
    }
}

async function removerCursoExterno(id) {
    try {
        await alunosService.removerCursoExterno(matricula.value, id);
        await carregar();
    } catch (e) {
        mostrarMensagem('erro', 'Não foi possível remover o curso externo.');
    }
}

function cancelarExperiencia() {
    Object.assign(novaExperiencia, experienciaVazia());
    mostrarFormExperiencia.value = false;
}

async function adicionarExperiencia() {
    limparErrosFormulario();
    if (!novaExperiencia.tipo.trim() || !novaExperiencia.cargo.trim() || !novaExperiencia.empresa.trim() || !novaExperiencia.data_inicio || (!novaExperiencia.atual && !novaExperiencia.data_fim)) {
        mostrarMensagem('erro', 'Preencha os campos obrigatórios da experiência profissional.');
        return;
    }
    try {
        await alunosService.adicionarExperiencia(matricula.value, {
            tipo: novaExperiencia.tipo,
            cargo: novaExperiencia.cargo,
            empresa: novaExperiencia.empresa,
            local: novaExperiencia.local,
            data_inicio: novaExperiencia.data_inicio,
            data_fim: novaExperiencia.atual ? null : (novaExperiencia.data_fim || null),
            descricao: novaExperiencia.descricao,
        });
        cancelarExperiencia();
        await carregar();
    } catch (e) {
        definirErrosFormulario(e?.response?.data?.errors || {});
        mostrarMensagem('erro', 'Não foi possível adicionar a experiência profissional.');
    }
}

async function removerExperiencia(id) {
    try {
        await alunosService.removerExperiencia(matricula.value, id);
        await carregar();
    } catch (e) {
        mostrarMensagem('erro', 'Não foi possível remover a experiência profissional.');
    }
}

async function salvar() {
    salvando.value = true;
    mensagem.value = null;
    limparErrosFormulario();
    try {
        if (preferencias.regiao_administrativa && !regioesAdministrativasPermitidas.includes(preferencias.regiao_administrativa)) {
            definirErrosFormulario({ regiao_administrativa: ['Selecione uma Região Administrativa válida.'] });
            mostrarMensagem('erro', 'Não foi possível salvar o perfil. Revise os campos obrigatórios destacados e tente novamente.');
            return;
        }

        await Promise.all([
            alunosService.salvarLinks(matricula.value, { ...links }),
            alunosService.salvarInfoProfissional(matricula.value, { ...perfil }),
            alunosService.salvarPreferencias(matricula.value, {
                tipo_de_contratacao: tipoContratacaoBitmask(),
                disponibilidade_de_horario: preferencias.disponibilidade_de_horario,
                regiao_administrativa: preferencias.regiao_administrativa,
                pretensao_salarial: converterPretensaoSalarialParaNumero(preferencias.pretensao_salarial),
            }),
        ]);
        mostrarMensagem('sucesso', 'Perfil atualizado com sucesso.');
    } catch (e) {
        definirErrosFormulario(e?.response?.data?.errors || {});
        mostrarMensagem('erro', 'Não foi possível salvar o perfil. Revise os campos obrigatórios destacados e tente novamente.');
    } finally {
        salvando.value = false;
    }
}

onMounted(() => {
    sincronizarInformacoesPessoais();
    carregar();
    document.addEventListener('click', aoClicarForaDosDropdowns);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', aoClicarForaDosDropdowns);
    if (timeoutMensagem) {
        clearTimeout(timeoutMensagem);
    }
});
</script>

<style scoped>
.border-dashed {
    border-style: dashed !important;
}

.sem-setas::-webkit-outer-spin-button,
.sem-setas::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.sem-setas[type='number'] {
    -moz-appearance: textfield;
    appearance: textfield;
}

.ra-dropdown {
    max-height: 240px;
    overflow-y: auto;
}

.habilidade-chip {
    background-color: var(--bs-primary-bg-subtle);
    color: var(--bs-primary);
    border: 1px solid rgba(var(--bs-primary-rgb), 0.15);
    border-radius: 999px;
    font-weight: 500;
}

.habilidade-chip-fechar {
    font-size: 0.55rem;
    opacity: 0.7;
}

.habilidade-chip-fechar:hover {
    opacity: 1;
}

.sugestao-habilidade {
    border-radius: 999px;
    font-weight: 500;
}

.habilidades-dropdown {
    width: 100%;
}

.toast-flutuante {
    position: fixed;
    right: 20px;
    bottom: 20px;
    z-index: 1080;
    max-width: min(380px, calc(100vw - 32px));
    padding: 0.9rem 1rem;
    border-radius: 0.9rem;
    border: 1px solid transparent;
}

.toast-erro {
    background: #fdeaea;
    color: #842029;
    border-color: #f5c2c7;
}

.toast-sucesso {
    background: #e8f6ec;
    color: #0f5132;
    border-color: #badbcc;
}

.toast-aviso {
    background: #fff3cd;
    color: #664d03;
    border-color: #ffecb5;
}

.toast-icone {
    margin-top: 0.1rem;
}

.toast-texto {
    word-break: break-word;
}

.toast-fade-enter-active,
.toast-fade-leave-active {
    transition: opacity 0.35s ease, transform 0.35s ease;
}

.toast-fade-enter-from,
.toast-fade-leave-to {
    opacity: 0;
    transform: translateY(12px);
}

.perfil-pessoal-botao {
    cursor: pointer;
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.perfil-pessoal-botao:hover {
    opacity: 0.92;
    transform: translateY(-1px);
}

.perfil-pessoal-botao:focus-visible {
    outline: 2px solid rgba(255, 255, 255, 0.85);
    outline-offset: 4px;
    border-radius: 999px;
}
</style>
