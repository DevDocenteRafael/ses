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
                <div class="dropdown">
                    <button
                        type="button"
                        class="perfil-pessoal-botao d-flex align-items-center gap-2 border-0 bg-transparent text-white p-0 dropdown-toggle"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                    >
                        <div class="text-end d-none d-sm-block">
                            <p class="fw-semibold mb-0">{{ auth.pessoa?.nome || 'Aluno' }}</p>
                        </div>
                        <span class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center fw-semibold flex-shrink-0"
                              style="width: 38px; height: 38px;">
                            {{ iniciais }}
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li>
                            <button type="button" class="dropdown-item" @click="abrirModalInformacoesPessoais">
                                <i class="bi bi-person-lines-fill me-2"></i> Meus dados
                            </button>
                        </li>
                    </ul>
                </div>
                <button type="button" class="btn btn-sm btn-outline-light ms-2" @click="sair">
                    <i class="bi bi-box-arrow-left me-1"></i> Sair
                </button>
            </div>
        </header>

        <div class="container-fluid p-4">

        <transition name="app-modal">
            <div
                v-if="modalInformacoesPessoaisAberto"
                class="modal fade show d-block"
                tabindex="-1"
                role="dialog"
                aria-modal="true"
                @click.self="fecharModalInformacoesPessoais"
            >
                <div class="modal-dialog modal-lg modal-dialog-centered app-modal-dialog-animated">
                    <div class="modal-content border-0 shadow-sm">
                        <div class="modal-header">
                            <h2 class="modal-title h5 mb-0">Informações Pessoais</h2>
                            <button type="button" class="btn-close" aria-label="Fechar" @click="fecharModalInformacoesPessoais"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">E-mail</label>
                                <input v-model.trim="informacoesPessoais.email" type="email" class="form-control" :class="campoInvalido('email')" maxlength="100" autocomplete="email">
                                <div v-if="erroDeCampo('email')" class="invalid-feedback d-block">{{ erroDeCampo('email') }}</div>
                            </div>
                            <div class="mb-3">
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
                            <div>
                                <label class="form-label">Endereço</label>
                                <div class="row g-2">
                                    <div class="col-sm-4">
                                        <label class="form-label small mb-1">CEP <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input
                                                v-model="informacoesPessoais.endereco.cep"
                                                type="text"
                                                inputmode="numeric"
                                                class="form-control"
                                                :class="campoInvalido('endereco') || campoInvalido('endereco.cep')"
                                                maxlength="9"
                                                autocomplete="postal-code"
                                                placeholder="00000-000"
                                                @input="onCepInformacoesPessoaisInput"
                                                @blur="consultarCepInformacoesPessoais"
                                            >
                                            <button type="button" class="btn btn-outline-primary" :disabled="consultandoCep || cepInformacoesPessoaisIncompleto" @click="consultarCepInformacoesPessoais">
                                                <span v-if="consultandoCep" class="spinner-border spinner-border-sm"></span>
                                                <i v-else class="bi bi-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-sm-8">
                                        <label class="form-label small mb-1">Logradouro</label>
                                        <input v-model.trim="informacoesPessoais.endereco.logradouro" type="text" class="form-control" maxlength="120" autocomplete="address-line1" placeholder="Preenchido automaticamente">
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label small mb-1">Número <span class="text-danger">*</span></label>
                                        <input v-model.trim="informacoesPessoais.endereco.numero" type="text" class="form-control" :class="campoInvalido('endereco.numero')" maxlength="20" autocomplete="address-line2">
                                    </div>
                                    <div class="col-sm-8">
                                        <label class="form-label small mb-1">Complemento</label>
                                        <input v-model.trim="informacoesPessoais.endereco.complemento" type="text" class="form-control" maxlength="80" autocomplete="address-line3">
                                    </div>
                                    <div class="col-sm-5">
                                        <label class="form-label small mb-1">Bairro</label>
                                        <input v-model.trim="informacoesPessoais.endereco.bairro" type="text" class="form-control" maxlength="80" placeholder="Preenchido automaticamente">
                                    </div>
                                    <div class="col-sm-5">
                                        <label class="form-label small mb-1">Cidade</label>
                                        <input v-model.trim="informacoesPessoais.endereco.cidade" type="text" class="form-control" maxlength="80" autocomplete="address-level2" placeholder="Preenchido automaticamente">
                                    </div>
                                    <div class="col-sm-2">
                                        <label class="form-label small mb-1">UF</label>
                                        <input v-model.trim="informacoesPessoais.endereco.uf" type="text" class="form-control text-uppercase" maxlength="2" autocomplete="address-level1" placeholder="UF">
                                    </div>
                                </div>
                                <div v-if="erroDeCampo('endereco')" class="invalid-feedback d-block">{{ erroDeCampo('endereco') }}</div>
                                <div v-if="erroDeCampo('endereco.cep')" class="invalid-feedback d-block">{{ erroDeCampo('endereco.cep') }}</div>
                                <div v-if="erroDeCampo('endereco.numero')" class="invalid-feedback d-block">{{ erroDeCampo('endereco.numero') }}</div>
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
                            <h2 class="text-uppercase text-secondary small fw-bold mb-3">Informações Profissionais</h2>
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
                                        <option v-for="area in areasAtuacao" :key="area" :value="area">{{ area }}</option>
                                    </select>
                                    <div v-if="erroDeCampo('area_de_atuacao')" class="invalid-feedback d-block">{{ erroDeCampo('area_de_atuacao') }}</div>
                                </div>
                            </div>

                            <div class="position-relative" ref="habilidadesDropdownContainer">
                                <label class="form-label">Habilidades <span class="text-danger">*</span></label>
                                <button
                                    type="button"
                                    class="form-select habilidades-select text-start d-flex align-items-center justify-content-between"
                                    :aria-expanded="mostrarDropdownHabilidades"
                                    @click.stop="alternarDropdownHabilidades"
                                >
                                    <span :class="habilidadesDaAreaAtual.length ? 'text-body' : 'text-secondary'">
                                        {{ rotuloHabilidadesSelecionadas }}
                                    </span>
                                    <i class="bi bi-chevron-down ms-2"></i>
                                </button>

                                <div v-if="mostrarDropdownHabilidades" class="habilidades-dropdown border rounded shadow-sm bg-white mt-1">
                                    <div class="p-3 border-bottom">
                                        <label class="form-label small text-secondary fw-semibold mb-1" for="busca-habilidade">Pesquisar habilidade...</label>
                                        <input
                                            id="busca-habilidade"
                                            ref="habilidadeInput"
                                            v-model="buscaHabilidade"
                                            type="text"
                                            class="form-control"
                                            placeholder="Digite para pesquisar..."
                                            maxlength="45"
                                            autocomplete="off"
                                            @keydown.enter.prevent="adicionarHabilidade"
                                        >
                                    </div>

                                    <div class="habilidades-dropdown-lista p-3">
                                        <div>
                                            <p class="small text-secondary fw-bold text-uppercase mb-2">Habilidades Técnicas</p>
                                            <div v-if="habilidadesTecnicasFiltradas.length" :key="perfil.area_de_atuacao" class="d-flex flex-column gap-1">
                                                <label v-for="habilidade in habilidadesTecnicasFiltradas" :key="habilidade" class="habilidade-opcao form-check rounded px-2 py-1 mb-0">
                                                    <input class="form-check-input ms-0 me-2" type="checkbox" :checked="habilidadeSelecionada(habilidade)" @change="alternarHabilidade(habilidade)">
                                                    <span class="form-check-label">{{ habilidade }}</span>
                                                </label>
                                            </div>
                                            <p v-else class="small text-secondary mb-0">Nenhuma habilidade técnica encontrada.</p>
                                        </div>

                                        <div class="mt-3">
                                            <p class="small text-secondary fw-bold text-uppercase mb-2">Soft Skills</p>
                                            <div v-if="softSkillsFiltradas.length" class="d-flex flex-column gap-1">
                                                <label v-for="habilidade in softSkillsFiltradas" :key="habilidade" class="habilidade-opcao form-check rounded px-2 py-1 mb-0">
                                                    <input class="form-check-input ms-0 me-2" type="checkbox" :checked="habilidadeSelecionada(habilidade)" @change="alternarHabilidade(habilidade)">
                                                    <span class="form-check-label">{{ habilidade }}</span>
                                                </label>
                                            </div>
                                            <p v-else class="small text-secondary mb-0">Nenhuma soft skill encontrada.</p>
                                        </div>

                                        <div v-if="habilidadesDaAreaAtual.length" class="mt-3 pt-3 border-top">
                                            <p class="small text-secondary fw-bold text-uppercase mb-2">Selecionadas em {{ perfil.area_de_atuacao }}</p>
                                            <div class="d-flex flex-column gap-1">
                                                <div v-for="(habilidade, indice) in habilidadesDaAreaAtual" :key="`${habilidade}-${indice}`" class="habilidade-selecionada d-flex align-items-center justify-content-between rounded px-2 py-1">
                                                    <span>{{ habilidade }}</span>
                                                    <button type="button" class="btn btn-sm btn-link text-danger p-0" aria-label="Remover habilidade" @click="removerHabilidade(indice)">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="border-top p-3 d-flex flex-column flex-sm-row gap-2 align-items-sm-center justify-content-between">
                                        <input
                                            v-if="mostrarCriacaoHabilidade"
                                            ref="novaHabilidadeInput"
                                            v-model.trim="novaHabilidade"
                                            type="text"
                                            class="form-control form-control-sm nova-habilidade-input"
                                            placeholder="Digite uma habilidade..."
                                            maxlength="45"
                                            autocomplete="off"
                                            @keydown.enter.prevent.stop="adicionarHabilidadePersonalizada"
                                        >
                                        <button type="button" class="btn btn-outline-primary flex-shrink-0" @click="acionarNovaHabilidade">
                                            <i class="bi bi-plus-lg me-1"></i> Adicionar nova habilidade
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary px-4" @click="fecharDropdownHabilidades">Concluir</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h2 class="text-uppercase text-secondary small fw-bold mb-3">Preferências de Trabalho</h2>

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
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label d-block">Disponibilidade de Horário</label>
                                    <div class="d-flex flex-wrap gap-3">
                                        <div v-for="opcao in opcoesDisponibilidadeHorario" :key="opcao" class="form-check form-check-inline mb-0">
                                            <input
                                                v-model="preferencias.disponibilidade_de_horario"
                                                class="form-check-input"
                                                type="checkbox"
                                                :id="`disponibilidade-${opcao}`"
                                                :value="opcao"
                                            >
                                            <label class="form-check-label" :for="`disponibilidade-${opcao}`">{{ opcao }}</label>
                                        </div>
                                    </div>
                                    <div v-if="erroDeCampo('disponibilidade_de_horario') || erroDeCampo('disponibilidade_de_horario.0')" class="invalid-feedback d-block">
                                        {{ erroDeCampo('disponibilidade_de_horario') || erroDeCampo('disponibilidade_de_horario.0') }}
                                    </div>
                                </div>
                                <div class="col-12 position-relative" ref="regioesTrabalhoDropdownContainer">
                                    <label class="form-label">Região Administrativa (RA) <span class="text-danger">*</span></label>
                                    <p class="form-text mt-0 mb-2">Selecione uma ou mais regiões onde você tem preferência em trabalhar.</p>
                                    <button
                                        type="button"
                                        class="form-select regioes-trabalho-select text-start d-flex align-items-center justify-content-between"
                                        :class="campoInvalido('regiao_administrativa') || campoInvalido('regioes_preferidas') || campoInvalido('regioes_preferidas.0')"
                                        :aria-expanded="mostrarDropdownRegioesTrabalho"
                                        aria-haspopup="listbox"
                                        @click.stop="alternarDropdownRegioesTrabalho"
                                        @keydown.down.prevent="abrirDropdownRegioesTrabalho"
                                        @keydown.enter.prevent="alternarDropdownRegioesTrabalho"
                                        @keydown.space.prevent="alternarDropdownRegioesTrabalho"
                                    >
                                        <span :class="rotuloRegioesTrabalhoSelecionadas === 'Selecione uma ou mais regiões...' ? 'text-secondary' : 'text-body'">
                                            {{ rotuloRegioesTrabalhoSelecionadas }}
                                        </span>
                                        <i class="bi bi-chevron-down ms-2"></i>
                                    </button>

                                    <div v-if="mostrarDropdownRegioesTrabalho" class="regioes-trabalho-dropdown border rounded shadow-sm bg-white mt-1">
                                        <div class="p-3 border-bottom">
                                            <label class="form-label small text-secondary fw-semibold mb-1" for="busca-regiao-trabalho">Pesquisar região...</label>
                                            <input
                                                id="busca-regiao-trabalho"
                                                ref="regiaoTrabalhoInput"
                                                v-model="buscaRegiaoTrabalho"
                                                type="text"
                                                class="form-control"
                                                placeholder="Digite para pesquisar..."
                                                maxlength="80"
                                                autocomplete="off"
                                                @keydown.esc="fecharDropdownRegioesTrabalho"
                                            >
                                        </div>
                                        <div class="regioes-trabalho-dropdown-lista p-3" role="listbox" aria-multiselectable="true">
                                            <label class="regiao-trabalho-opcao form-check rounded px-2 py-1 mb-2">
                                                <input
                                                    class="form-check-input ms-0 me-2"
                                                    type="checkbox"
                                                    :checked="preferencias.aceita_todas_regioes"
                                                    @change="alternarTodasRegioesTrabalho"
                                                >
                                                <span class="form-check-label fw-semibold">Todas as regiões</span>
                                            </label>
                                            <hr class="my-2">
                                            <div v-if="regioesTrabalhoFiltradas.length" class="d-flex flex-column gap-1">
                                                <label v-for="regiao in regioesTrabalhoFiltradas" :key="regiao.codigo" class="regiao-trabalho-opcao form-check rounded px-2 py-1 mb-0">
                                                    <input
                                                        class="form-check-input ms-0 me-2"
                                                        type="checkbox"
                                                        :checked="regiaoTrabalhoSelecionada(regiao.codigo)"
                                                        @change="alternarRegiaoTrabalho(regiao.codigo)"
                                                    >
                                                    <span class="form-check-label">{{ regiao.label }}</span>
                                                </label>
                                            </div>
                                            <p v-else class="small text-secondary mb-0">Nenhuma região encontrada.</p>
                                        </div>
                                        <div class="border-top p-3 d-flex justify-content-end">
                                            <button type="button" class="btn btn-sm btn-primary px-4" @click="fecharDropdownRegioesTrabalho">Concluir</button>
                                        </div>
                                    </div>
                                    <div v-if="erroDeCampo('regiao_administrativa') || erroDeCampo('regioes_preferidas') || erroDeCampo('regioes_preferidas.0')" class="invalid-feedback d-block">
                                        {{ erroDeCampo('regiao_administrativa') || erroDeCampo('regioes_preferidas') || erroDeCampo('regioes_preferidas.0') }}
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Pretensão Salarial (Opcional)</label>
                                    <select v-model="preferencias.pretensao_salarial" class="form-select" :class="campoInvalido('pretensao_salarial')">
                                        <option value="">Selecione uma faixa salarial...</option>
                                        <option
                                            v-for="faixa in faixasPretensaoSalarial"
                                            :key="faixa.value"
                                            :value="faixa.value"
                                        >
                                            {{ faixa.label }}
                                        </option>
                                    </select>
                                    <div v-if="erroDeCampo('pretensao_salarial')" class="invalid-feedback d-block">{{ erroDeCampo('pretensao_salarial') }}</div>
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
import { computed, nextTick, reactive, ref, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../../store/auth';
import alunosService from '../../../services/alunosServices';
import cepService, { formatarCep } from '../../../services/cepService';
import { useToast } from '../../../composables/useToast';
import { formatarTelefone, somenteNumeros } from '../../../utils/telefone';
import { regioesAdministrativasDf } from '../../../utils/regioesAdministrativasDf';
import { areasAtuacao, habilidadesPorArea, sugestoesSoftSkills } from '../../../utils/habilidadesCatalogo';
import {
    converterFaixaPretensaoSalarialParaPayload,
    converterPretensaoSalarialApiParaFaixa,
    faixasPretensaoSalarial,
} from '../../../utils/faixasPretensaoSalarial';

const auth = useAuthStore();
const router = useRouter();
const toast = useToast();
const habilidadeInput = ref(null);
const novaHabilidadeInput = ref(null);
const habilidadesDropdownContainer = ref(null);
const regioesTrabalhoDropdownContainer = ref(null);
const regiaoTrabalhoInput = ref(null);

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
const errosFormulario = ref({});

const dadosAcademicos = ref(null);
const cursosSenac = ref([]);
const cursosExternos = ref([]);
const experiencias = ref([]);
const modalInformacoesPessoaisAberto = ref(false);
const salvandoInformacoesPessoais = ref(false);
const consultandoCep = ref(false);

const mostrarFormCursoExterno = ref(false);
const mostrarFormExperiencia = ref(false);
const buscaRegiaoTrabalho = ref('');
const novaHabilidade = ref('');
const buscaHabilidade = ref('');
const mostrarDropdownHabilidades = ref(false);
const mostrarDropdownRegioesTrabalho = ref(false);
const mostrarCriacaoHabilidade = ref(false);

const links = reactive({
    linkedin: '',
    portfolio: '',
    github: '',
});

const informacoesPessoais = reactive({
    email: '',
    telefone: '',
    endereco: enderecoVazio(),
});

const cepInformacoesPessoaisIncompleto = computed(() => somenteNumeros(informacoesPessoais.endereco.cep).length !== 8);

const regioesAdministrativas = regioesAdministrativasDf;
const opcoesDisponibilidadeHorario = ['Manhã', 'Tarde', 'Noite', 'Integral'];

const perfil = reactive({
    sobre_mim: '',
    cargo_de_interesse: '',
    area_de_atuacao: 'Tecnologia da Informação',
    habilidades: [],
    habilidades_por_area: {},
});

function obterChaveHabilidadesPorArea(areaDeAtuacao) {
    const areaNormalizada = normalizarTexto(areaDeAtuacao);

    return Object.keys(habilidadesPorArea).find((area) => normalizarTexto(area) === areaNormalizada) || 'Outra';
}

const chaveHabilidadesTecnicasEncontrada = computed(() => obterChaveHabilidadesPorArea(perfil.area_de_atuacao));

const sugestoesHabilidadesTecnicas = computed(() => habilidadesPorArea[chaveHabilidadesTecnicasEncontrada.value]);

const termoBuscaHabilidade = computed(() => normalizarTexto(buscaHabilidade.value));

const habilidadesTecnicasFiltradas = computed(() => filtrarHabilidades(sugestoesHabilidadesTecnicas.value));

const softSkillsFiltradas = computed(() => filtrarHabilidades(sugestoesSoftSkills));

const habilidadesDaAreaAtual = computed(() => habilidadesPorAreaSelecionada(perfil.area_de_atuacao));

const rotuloHabilidadesSelecionadas = computed(() => {
    const total = habilidadesDaAreaAtual.value.length;

    if (!total) {
        return 'Selecione habilidades...';
    }

    return total === 1 ? '1 habilidade selecionada' : `${total} habilidades selecionadas`;
});

const regioesTrabalhoFiltradas = computed(() => {
    const termo = normalizarTexto(buscaRegiaoTrabalho.value);
    const termoSemEspacos = termo.replace(/\s+/g, '');

    if (!termo) {
        return regioesAdministrativas;
    }

    return regioesAdministrativas.filter((regiao) => {
        const texto = normalizarTexto(`${regiao.label} ${regiao.nome}`);
        return texto.includes(termo) || texto.replace(/\s+/g, '').includes(termoSemEspacos);
    });
});

const rotuloRegioesTrabalhoSelecionadas = computed(() => {
    if (preferencias.aceita_todas_regioes) {
        return 'Todas as regiões';
    }

    const total = preferencias.regioes_preferidas.length;

    if (!total) {
        return 'Selecione uma ou mais regiões...';
    }

    if (total === 1) {
        return regioesAdministrativas.find((regiao) => regiao.codigo === preferencias.regioes_preferidas[0])?.label || '1 região selecionada';
    }

    return `${total} regiões selecionadas`;
});

// Bitmask: CLT=1, Estágio=2
const preferencias = reactive({
    clt: false,
    estagio: false,
    disponibilidade_de_horario: ['Manhã'],
    regiao_administrativa: '',
    aceita_todas_regioes: false,
    regioes_preferidas: [],
    pretensao_salarial: '',
});

function normalizarDisponibilidadesHorario(valor) {
    const lista = Array.isArray(valor) ? valor : [valor].filter(Boolean);

    return opcoesDisponibilidadeHorario.filter((opcao) => lista.includes(opcao));
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
        .replace(/\s+/g, ' ')
        .trim();
}

function habilidadesPorAreaSelecionada(area = perfil.area_de_atuacao) {
    const areaTratada = String(area || '').trim();

    if (!areaTratada) {
        return [];
    }

    if (!Array.isArray(perfil.habilidades_por_area[areaTratada])) {
        perfil.habilidades_por_area[areaTratada] = [];
    }

    return perfil.habilidades_por_area[areaTratada];
}

function sincronizarHabilidadesPlanas() {
    perfil.habilidades = Object.values(perfil.habilidades_por_area)
        .flat()
        .filter((habilidade, indice, lista) => {
            const habilidadeNormalizada = normalizarTexto(habilidade);
            return habilidadeNormalizada && lista.findIndex((item) => normalizarTexto(item) === habilidadeNormalizada) === indice;
        });
}

function normalizarHabilidadesPorAreaRecebidas(informacoesProfissionais) {
    const porArea = informacoesProfissionais?.habilidades_por_area;

    if (porArea && typeof porArea === 'object' && !Array.isArray(porArea)) {
        return Object.fromEntries(
            Object.entries(porArea)
                .map(([area, habilidades]) => [String(area).trim(), Array.isArray(habilidades) ? habilidades.filter(Boolean) : []])
                .filter(([area, habilidades]) => area && habilidades.length)
        );
    }

    const areaLegada = informacoesProfissionais?.area_de_atuacao || perfil.area_de_atuacao;
    const habilidadesLegadas = Array.isArray(informacoesProfissionais?.habilidades) ? informacoesProfissionais.habilidades : [];

    return habilidadesLegadas.length ? { [areaLegada]: habilidadesLegadas } : {};
}

function abrirDropdownRegioesTrabalho() {
    mostrarDropdownRegioesTrabalho.value = true;
    nextTick(() => regiaoTrabalhoInput.value?.focus());
}

function alternarDropdownRegioesTrabalho() {
    mostrarDropdownRegioesTrabalho.value = !mostrarDropdownRegioesTrabalho.value;

    if (mostrarDropdownRegioesTrabalho.value) {
        nextTick(() => regiaoTrabalhoInput.value?.focus());
    }
}

function fecharDropdownRegioesTrabalho() {
    mostrarDropdownRegioesTrabalho.value = false;
    buscaRegiaoTrabalho.value = '';
}

function alternarTodasRegioesTrabalho() {
    preferencias.aceita_todas_regioes = !preferencias.aceita_todas_regioes;

    if (preferencias.aceita_todas_regioes) {
        preferencias.regioes_preferidas = [];
    }
}

function alternarRegiaoTrabalho(codigo) {
    preferencias.aceita_todas_regioes = false;

    const codigoNumerico = Number(codigo);
    const indice = preferencias.regioes_preferidas.indexOf(codigoNumerico);

    if (indice >= 0) {
        preferencias.regioes_preferidas.splice(indice, 1);
        return;
    }

    preferencias.regioes_preferidas.push(codigoNumerico);
    preferencias.regioes_preferidas.sort((a, b) => a - b);
}

function regiaoTrabalhoSelecionada(codigo) {
    return preferencias.regioes_preferidas.includes(Number(codigo));
}

function bloquearSinalNegativo(evento) {
    if (evento.key === '-') {
        evento.preventDefault();
    }
}

function obterRegiaoAdministrativaLegadaParaApi() {
    if (preferencias.aceita_todas_regioes) {
        return 'Todas as regiões';
    }

    return regioesAdministrativas.find((regiao) => regiao.codigo === preferencias.regioes_preferidas[0])?.value || '';
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
}

function limparErrosFormulario() {
    errosFormulario.value = {};
}

function mostrarMensagem(tipo, texto) {
    toast.showToast(tipo, texto);
}

function enderecoVazio() {
    return {
        cep: '',
        logradouro: '',
        numero: '',
        complemento: '',
        bairro: '',
        cidade: '',
        uf: '',
    };
}

function normalizarUf(valor) {
    return String(valor ?? '').replace(/[^a-zA-Z]/g, '').slice(0, 2).toUpperCase();
}

function formatarEnderecoParaPersistencia(endereco) {
    return {
        cep: somenteNumeros(endereco.cep),
        logradouro: String(endereco.logradouro ?? '').trim(),
        numero: String(endereco.numero ?? '').trim(),
        complemento: String(endereco.complemento ?? '').trim(),
        bairro: String(endereco.bairro ?? '').trim(),
        cidade: String(endereco.cidade ?? '').trim(),
        uf: normalizarUf(endereco.uf),
    };
}

function obterCampoEnderecoRotulado(endereco, rotulo) {
    const correspondencia = endereco.match(new RegExp(`${rotulo}:\\s*([^;]+)`, 'i'));
    return correspondencia?.[1]?.trim() || '';
}

function aplicarEnderecoSalvo(enderecoSalvo) {
    Object.assign(informacoesPessoais.endereco, enderecoVazio());

    if (enderecoSalvo && typeof enderecoSalvo === 'object' && !Array.isArray(enderecoSalvo)) {
        informacoesPessoais.endereco.cep = formatarCep(enderecoSalvo.cep);
        informacoesPessoais.endereco.logradouro = enderecoSalvo.logradouro || '';
        informacoesPessoais.endereco.numero = enderecoSalvo.numero || '';
        informacoesPessoais.endereco.complemento = enderecoSalvo.complemento || '';
        informacoesPessoais.endereco.bairro = enderecoSalvo.bairro || '';
        informacoesPessoais.endereco.cidade = enderecoSalvo.cidade || '';
        informacoesPessoais.endereco.uf = normalizarUf(enderecoSalvo.uf);
        return;
    }

    const endereco = String(enderecoSalvo ?? '').trim();

    if (!endereco) {
        return;
    }

    const cepEncontrado = endereco.match(/\b\d{5}-?\d{3}\b/);

    if (cepEncontrado) {
        informacoesPessoais.endereco.cep = formatarCep(cepEncontrado[0]);
    }

    const logradouroRotulado = obterCampoEnderecoRotulado(endereco, 'Logradouro');
    const numeroRotulado = obterCampoEnderecoRotulado(endereco, 'N(?:ú|u)mero');
    const complementoRotulado = obterCampoEnderecoRotulado(endereco, 'Complemento');
    const bairroRotulado = obterCampoEnderecoRotulado(endereco, 'Bairro');
    const cidadeRotulada = obterCampoEnderecoRotulado(endereco, 'Cidade');
    const ufRotulada = obterCampoEnderecoRotulado(endereco, 'UF');

    if (logradouroRotulado || numeroRotulado || complementoRotulado || bairroRotulado || cidadeRotulada || ufRotulada) {
        informacoesPessoais.endereco.logradouro = logradouroRotulado;
        informacoesPessoais.endereco.numero = numeroRotulado;
        informacoesPessoais.endereco.complemento = complementoRotulado;
        informacoesPessoais.endereco.bairro = bairroRotulado;
        informacoesPessoais.endereco.cidade = cidadeRotulada;
        informacoesPessoais.endereco.uf = normalizarUf(ufRotulada);
        return;
    }

    const semCep = endereco
        .replace(/^CEP\s*/i, '')
        .replace(/\b\d{5}-?\d{3}\b\s*\|?\s*/i, '')
        .trim();
    const [logradouroNumero = '', bairro = '', cidadeUf = ''] = semCep.split(' - ').map((parte) => parte.trim());
    const numeroEncontrado = logradouroNumero.match(/(?:n[ºo.]?\s*)([^-]+)/i);

    informacoesPessoais.endereco.logradouro = logradouroNumero.replace(/,?\s*n[ºo.]?\s*[^-]+/i, '').trim();
    informacoesPessoais.endereco.numero = numeroEncontrado?.[1]?.trim() || '';
    informacoesPessoais.endereco.bairro = bairro;

    const [cidade = '', uf = ''] = cidadeUf.split('/');
    informacoesPessoais.endereco.cidade = cidade.trim();
    informacoesPessoais.endereco.uf = normalizarUf(uf);

    if (!informacoesPessoais.endereco.logradouro && !informacoesPessoais.endereco.bairro && !informacoesPessoais.endereco.cidade) {
        informacoesPessoais.endereco.logradouro = endereco;
    }
}

function sincronizarInformacoesPessoais() {
    informacoesPessoais.email = auth.pessoa?.email || '';
    informacoesPessoais.telefone = formatarTelefone(auth.pessoa?.telefone);
    aplicarEnderecoSalvo(auth.pessoa?.endereco || '');
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

function onCepInformacoesPessoaisInput(evento) {
    const valorFormatado = formatarCep(evento.target.value);
    informacoesPessoais.endereco.cep = valorFormatado;
    evento.target.value = valorFormatado;

    if (somenteNumeros(valorFormatado).length === 8) {
        consultarCepInformacoesPessoais();
    }
}

async function consultarCepInformacoesPessoais() {
    const cep = somenteNumeros(informacoesPessoais.endereco.cep);

    if (cep.length !== 8 || consultandoCep.value) {
        return;
    }

    consultandoCep.value = true;

    try {
        const endereco = await cepService.consultarCep(cep);

        informacoesPessoais.endereco.cep = endereco.cep;
        informacoesPessoais.endereco.logradouro = endereco.logradouro;
        informacoesPessoais.endereco.bairro = endereco.bairro;
        informacoesPessoais.endereco.cidade = endereco.cidade;
        informacoesPessoais.endereco.uf = endereco.uf;
        errosFormulario.value = Object.fromEntries(Object.entries(errosFormulario.value).filter(([campo]) => !campo.startsWith('endereco')));
    } catch (e) {
        const mensagem = e?.message || 'Não foi possível consultar o CEP no momento. Tente novamente ou preencha o endereço manualmente.';
        definirErrosFormulario({ endereco: [mensagem] });
        mostrarMensagem(e?.tipo === 'nao_encontrado' ? 'aviso' : 'erro', mensagem);
    } finally {
        consultandoCep.value = false;
    }
}

async function salvarInformacoesPessoais() {
    salvandoInformacoesPessoais.value = true;
    limparErrosFormulario();

    try {
        if (cepInformacoesPessoaisIncompleto.value) {
            definirErrosFormulario({ endereco: ['Informe um CEP válido com 8 dígitos.'] });
            mostrarMensagem('erro', 'Informe um CEP válido com 8 dígitos.');
            return;
        }

        if (!informacoesPessoais.endereco.numero.trim()) {
            definirErrosFormulario({ 'endereco.numero': ['Informe o número do endereço.'] });
            mostrarMensagem('erro', 'Informe o número do endereço.');
            return;
        }

        const enderecoParaPersistir = formatarEnderecoParaPersistencia(informacoesPessoais.endereco);

        const { data } = await alunosService.atualizarPerfil(matricula.value, {
            email: informacoesPessoais.email,
            telefone: somenteNumeros(informacoesPessoais.telefone),
            endereco: enderecoParaPersistir,
        });

        auth.pessoa = {
            ...auth.pessoa,
            nome: data.pessoa?.nome || auth.pessoa?.nome,
            email: data.pessoa?.email || informacoesPessoais.email,
            telefone: data.pessoa?.telefone || somenteNumeros(informacoesPessoais.telefone),
            endereco: data.pessoa?.endereco ?? enderecoParaPersistir,
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
    return (preferencias.clt ? 1 : 0) + (preferencias.estagio ? 2 : 0);
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
            const areaRecebidaCarregar = data.informacoes_profissionais.area_de_atuacao || perfil.area_de_atuacao;

            perfil.sobre_mim = data.informacoes_profissionais.sobre_mim || '';
            perfil.cargo_de_interesse = data.informacoes_profissionais.cargo_de_interesse || '';
            perfil.area_de_atuacao = areaRecebidaCarregar;
            perfil.habilidades_por_area = normalizarHabilidadesPorAreaRecebidas(data.informacoes_profissionais);
            sincronizarHabilidadesPlanas();
        }

        if (data.preferencias_de_trabalho) {
            aplicarTipoContratacao(data.preferencias_de_trabalho.tipo_de_contratacao);
            const disponibilidadeRecebida = normalizarDisponibilidadesHorario(data.preferencias_de_trabalho.disponibilidade_de_horario);
            preferencias.disponibilidade_de_horario = disponibilidadeRecebida.length ? disponibilidadeRecebida : preferencias.disponibilidade_de_horario;
            preferencias.regiao_administrativa = data.preferencias_de_trabalho.regiao_administrativa || '';
            preferencias.aceita_todas_regioes = Boolean(data.preferencias_de_trabalho.aceita_todas_regioes);
            preferencias.regioes_preferidas = preferencias.aceita_todas_regioes
                ? []
                : (data.regioes_preferidas_trabalho || []).map((regiao) => Number(regiao.codigo)).filter(Boolean);

            if (!preferencias.aceita_todas_regioes && !preferencias.regioes_preferidas.length && preferencias.regiao_administrativa) {
                const regiaoLegada = regioesAdministrativas.find((regiao) => regiao.value === preferencias.regiao_administrativa);
                preferencias.regioes_preferidas = regiaoLegada ? [regiaoLegada.codigo] : [];
            }

            preferencias.pretensao_salarial = converterPretensaoSalarialApiParaFaixa(data.preferencias_de_trabalho.pretensao_salarial);
        }
    } finally {
        carregando.value = false;
    }
}

function registrarHabilidade(habilidade) {
    const habilidadeTratada = String(habilidade ?? '').trim();

    if (!habilidadeTratada) {
        return false;
    }

    if (habilidadeSelecionada(habilidadeTratada)) {
        mostrarMensagem('aviso', 'Essa habilidade já foi adicionada.');
        return false;
    }

    habilidadesPorAreaSelecionada().push(habilidadeTratada);
    sincronizarHabilidadesPlanas();
    return true;
}

function adicionarHabilidade() {
    if (registrarHabilidade(buscaHabilidade.value)) {
        buscaHabilidade.value = '';
    }

    habilidadeInput.value?.focus();
}

function adicionarHabilidadePersonalizada() {
    if (registrarHabilidade(novaHabilidade.value)) {
        novaHabilidade.value = '';
        mostrarCriacaoHabilidade.value = false;
    } else {
        novaHabilidadeInput.value?.focus();
    }
}

function acionarNovaHabilidade() {
    if (!mostrarCriacaoHabilidade.value) {
        mostrarCriacaoHabilidade.value = true;
        nextTick(() => novaHabilidadeInput.value?.focus());
        return;
    }

    adicionarHabilidadePersonalizada();
}

function filtrarHabilidades(habilidades) {
    if (!termoBuscaHabilidade.value) {
        return habilidades;
    }

    return habilidades.filter((habilidade) => normalizarTexto(habilidade).includes(termoBuscaHabilidade.value));
}

function alternarDropdownHabilidades() {
    mostrarDropdownHabilidades.value = !mostrarDropdownHabilidades.value;

    if (mostrarDropdownHabilidades.value) {
        setTimeout(() => habilidadeInput.value?.focus(), 0);
    }
}

function fecharDropdownHabilidades() {
    mostrarDropdownHabilidades.value = false;
    novaHabilidade.value = '';
    mostrarCriacaoHabilidade.value = false;
}

function alternarHabilidade(habilidade) {
    const indice = indiceHabilidadeSelecionada(habilidade);

    if (indice >= 0) {
        habilidadesPorAreaSelecionada().splice(indice, 1);
    } else {
        habilidadesPorAreaSelecionada().push(habilidade);
    }

    sincronizarHabilidadesPlanas();
}

function indiceHabilidadeSelecionada(habilidade) {
    const habilidadeNormalizada = normalizarTexto(habilidade);

    return habilidadesPorAreaSelecionada().findIndex((item) => normalizarTexto(item) === habilidadeNormalizada);
}

function habilidadeSelecionada(habilidade) {
    return indiceHabilidadeSelecionada(habilidade) >= 0;
}

function removerHabilidade(indice) {
    habilidadesPorAreaSelecionada().splice(indice, 1);
    sincronizarHabilidadesPlanas();
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

    if (regioesTrabalhoDropdownContainer.value && !regioesTrabalhoDropdownContainer.value.contains(evento.target)) {
        fecharDropdownRegioesTrabalho();
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
    limparErrosFormulario();
    try {
        if (!preferencias.aceita_todas_regioes && !preferencias.regioes_preferidas.length) {
            definirErrosFormulario({ regiao_administrativa: ['Selecione uma Região Administrativa válida.'] });
            mostrarMensagem('erro', 'Não foi possível salvar o perfil. Revise os campos obrigatórios destacados e tente novamente.');
            return;
        }

        preferencias.regiao_administrativa = obterRegiaoAdministrativaLegadaParaApi();

        await Promise.all([
            alunosService.salvarLinks(matricula.value, { ...links }),
            alunosService.salvarInfoProfissional(matricula.value, {
                ...perfil,
                habilidades_por_area: { ...perfil.habilidades_por_area },
            }),
            alunosService.salvarPreferencias(matricula.value, {
                tipo_de_contratacao: tipoContratacaoBitmask(),
                disponibilidade_de_horario: [...preferencias.disponibilidade_de_horario],
                regiao_administrativa: obterRegiaoAdministrativaLegadaParaApi(),
                aceita_todas_regioes: preferencias.aceita_todas_regioes,
                regioes_preferidas: preferencias.aceita_todas_regioes ? [] : preferencias.regioes_preferidas,
                pretensao_salarial: converterFaixaPretensaoSalarialParaPayload(preferencias.pretensao_salarial),
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
});
</script>

<style scoped>
.border-dashed {
    border-style: dashed !important;
}

.habilidades-select,
.regioes-trabalho-select {
    min-height: 38px;
    background-image: none;
}

.habilidades-dropdown,
.regioes-trabalho-dropdown {
    position: absolute;
    left: 0;
    top: 100%;
    z-index: 1050;
    width: 100%;
}

.habilidades-dropdown-lista,
.regioes-trabalho-dropdown-lista {
    max-height: 320px;
    overflow-y: auto;
}

.regioes-trabalho-dropdown {
    max-height: min(520px, 70vh);
    overflow: hidden;
}

.nova-habilidade-input {
    min-width: 0;
}

.habilidade-opcao,
.regiao-trabalho-opcao,
.habilidade-selecionada {
    transition: background-color 0.15s ease;
}

.habilidade-opcao,
.regiao-trabalho-opcao {
    cursor: pointer;
}

.habilidade-opcao:hover,
.regiao-trabalho-opcao:hover,
.habilidade-selecionada:hover {
    background-color: var(--bs-primary-bg-subtle);
}

.habilidade-opcao .form-check-input,
.regiao-trabalho-opcao .form-check-input {
    float: none;
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
