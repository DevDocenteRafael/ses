<template>
    <div>
        <topbar titulo="Gestão dos Candidatos" subtitulo="Controle de acesso e sincronização de candidatos">
            <template #acoes>
                <button class="btn btn-outline-primary" :disabled="admin.carregando" @click="sincronizar">
                    <i class="bi bi-arrow-repeat me-1"></i>
                    {{ admin.carregando ? 'Sincronizando...' : 'Sincronizar SIG' }}
                </button>
            </template>
        </topbar>

        <div class="container-fluid p-4">
            <loading v-if="admin.carregando && !carregouUmaVez" mensagem="Carregando candidatos..." />

            <div v-else-if="admin.erro" class="alert alert-danger">
                {{ admin.erro }}
            </div>

            <div v-else class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <h2 class="h6 fw-bold text-primary mb-0">Candidatos Cadastrados</h2>
                        <div class="d-flex align-items-stretch flex-wrap gap-2 w-100 justify-content-md-end">
                            <div class="input-group flex-grow-1 position-relative" style="min-width: 240px;">
                                 <input
                                     v-model="busca"
                                     type="text"
                                     class="form-control"
                                     placeholder="Filtrar por nome ou CPF"
                                     autocomplete="off"
                                     @focus="buscaDropdownAberto = Boolean(busca)"
                                     @input="onBuscaCpfInput"
                                     @blur="fecharSugestaoBusca"
                                 >
                                <span class="input-group-text bg-primary text-white"><i class="bi bi-search"></i></span>
                                <div v-if="buscaDropdownAberto && busca && candidatosBuscaSugeridos.length" class="list-group position-absolute w-100 shadow" style="z-index: 1060; top: 100%; max-height: 220px; overflow-y: auto;">
                                    <button
                                        v-for="aluno in candidatosBuscaSugeridos"
                                        :key="aluno.matricula"
                                        type="button"
                                        class="list-group-item list-group-item-action text-start"
                                        @mousedown.prevent="selecionarSugestaoBusca(aluno)"
                                    >
                                        <span class="d-block fw-semibold">{{ aluno.pessoa?.nome || 'Candidato' }}</span>
                                        <small class="text-secondary">CPF {{ formatarCpf(aluno.cpf) }}</small>
                                    </button>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="btn admin-filter-toggle text-nowrap flex-shrink-0"
                                :class="{ 'admin-filter-toggle--open': bandejaFiltrosAberta }"
                                :aria-expanded="bandejaFiltrosAberta"
                                aria-controls="admin-candidatos-bandeja-filtros"
                                @click="bandejaFiltrosAberta = !bandejaFiltrosAberta"
                            >
                                <i class="bi bi-funnel me-1"></i>
                                Filtros
                                <span v-if="totalFiltrosAtivos" class="badge rounded-pill bg-warning text-dark ms-1">{{ totalFiltrosAtivos }}</span>
                                <i class="bi ms-1" :class="bandejaFiltrosAberta ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                            </button>
                            <div class="d-flex flex-nowrap align-items-stretch gap-2">
                                <button
                                    class="btn btn-outline-secondary text-nowrap flex-shrink-0"
                                    type="button"
                                    :disabled="admin.carregando || gerandoZip || gerandoRelatorioPdf || !admin.alunos.length"
                                    @click="baixarCandidatosFiltradosPdf"
                                >
                                    <span v-if="gerandoRelatorioPdf" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    <i v-else class="bi bi-file-earmark-pdf me-1"></i>
                                    {{ gerandoRelatorioPdf ? 'Preparando PDF...' : 'Relatório de Busca' }}
                                </button>
                                <button
                                    class="btn btn-outline-primary text-nowrap flex-shrink-0"
                                    type="button"
                                    :disabled="admin.carregando || gerandoZip || !admin.alunos.length"
                                    @click="abrirModalCurriculos"
                                >
                                    <i class="bi bi-download me-1"></i>
                                    Baixar currículos
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="bandejaFiltrosAberta" id="admin-candidatos-bandeja-filtros" class="admin-filter-tray rounded-3 p-3 mb-3">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <div>
                                <h3 class="h6 fw-bold mb-1 text-white">Bandeja de filtros</h3>
                                <p class="small text-white-50 mb-0">Filtros compartilhados com a Pesquisa de Talentos, preservando o escopo administrativo.</p>
                            </div>
                        </div>
                        <div class="row g-3 admin-filter-grid">
                            <div class="col-md-6 col-xl-3">
                                <label class="form-label small fw-bold text-secondary text-uppercase">Contratação</label>
                                <div class="d-flex gap-3 flex-wrap">
                                    <div class="form-check small"><input id="admin-filtro-clt" v-model="filtroClt" class="form-check-input" type="checkbox"><label class="form-check-label" for="admin-filtro-clt">CLT / Efetivo</label></div>
                                    <div class="form-check small"><input id="admin-filtro-estagio" v-model="filtroEstagio" class="form-check-input" type="checkbox"><label class="form-check-label" for="admin-filtro-estagio">Estágio</label></div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <label class="form-label small fw-bold text-secondary text-uppercase">Status administrativo</label>
                                <select v-model="statusFiltro" class="form-select form-select-sm">
                                    <option v-for="opcao in statusOpcoes" :key="opcao.value" :value="opcao.value">{{ opcao.label }}</option>
                                </select>
                            </div>
                            <div class="col-md-6 col-xl-3 position-relative" ref="segmentosDropdownContainer">
                                <label class="form-label small fw-bold text-secondary text-uppercase">Segmento</label>
                                <button type="button" class="form-select form-select-sm filtro-multiselect text-start d-flex align-items-center" :aria-expanded="mostrarDropdownSegmentos" @click.stop="alternarDropdownSegmentos">
                                    <span class="text-truncate" :class="filtros.segmento.length ? 'text-body' : 'text-secondary'">{{ rotuloFiltroSegmentos }}</span>
                                    <i class="bi bi-chevron-down filtro-multiselect-seta"></i>
                                </button>
                                <div v-if="mostrarDropdownSegmentos" class="filtro-multiselect-dropdown border rounded shadow-sm mt-1">
                                    <div class="p-2 border-bottom"><input ref="segmentoBuscaInput" v-model="buscaSegmento" type="text" class="form-control form-control-sm" placeholder="Pesquisar segmento..." autocomplete="off"></div>
                                    <div class="filtro-multiselect-lista p-2"><label v-for="segmento in segmentosFiltrados" :key="segmento.id" class="filtro-multiselect-opcao form-check rounded px-2 py-1 mb-1"><input class="form-check-input ms-0 me-2" type="checkbox" :checked="segmentoSelecionado(segmento.id)" @change="alternarSegmento(segmento.id)"><span class="form-check-label">{{ segmento.nome }}</span></label><p v-if="!segmentosFiltrados.length" class="small text-secondary mb-0 py-2 text-center">Nenhum segmento encontrado.</p></div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <label class="form-label small fw-bold text-secondary text-uppercase">Disponibilidade</label>
                                <select v-model="disponibilidadeFiltro" class="form-select form-select-sm">
                                    <option value="">Qualquer Horário</option>
                                    <option value="Manhã">Manhã</option>
                                    <option value="Tarde">Tarde</option>
                                    <option value="Noite">Noite</option>
                                </select>
                            </div>
                            <div class="col-md-6 col-xl-3 position-relative">
                                <label class="form-label small fw-bold text-secondary text-uppercase">Unidade</label>
                                <input v-model="unidadeFiltro" class="form-control form-control-sm" type="search" placeholder="Pesquisar unidade" autocomplete="off" @focus="unidadeDropdownAberto = true" @blur="fecharSugestaoUnidade">
                                <div v-if="unidadeDropdownAberto && unidadesFiltradas.length" class="list-group admin-filter-suggestions position-absolute w-100 shadow mt-1"><button v-for="unidade in unidadesFiltradas" :key="unidade" type="button" class="list-group-item list-group-item-action text-start" @mousedown.prevent="selecionarUnidade(unidade)">{{ unidade }}</button></div>
                            </div>
                            <div class="col-md-6 col-xl-3 position-relative" ref="regioesDropdownContainer">
                                <label class="form-label small fw-bold text-secondary text-uppercase">Região Administrativa</label>
                                <button type="button" class="form-select form-select-sm filtro-multiselect text-start d-flex align-items-center" :aria-expanded="mostrarDropdownRegioes" @click.stop="alternarDropdownRegioes">
                                    <span class="text-truncate" :class="filtros.regioes_administrativas.length ? 'text-body' : 'text-secondary'">{{ rotuloFiltroRegioes }}</span>
                                    <i class="bi bi-chevron-down filtro-multiselect-seta"></i>
                                </button>
                                <div v-if="mostrarDropdownRegioes" class="filtro-multiselect-dropdown border rounded shadow-sm mt-1">
                                    <div class="p-2 border-bottom"><input ref="regiaoBuscaInput" v-model="buscaRegiao" type="text" class="form-control form-control-sm" placeholder="Pesquisar região..." autocomplete="off"></div>
                                    <div class="filtro-multiselect-lista p-2"><label v-for="regiao in regioesFiltradas" :key="regiao.codigo" class="filtro-multiselect-opcao form-check rounded px-2 py-1 mb-1"><input class="form-check-input ms-0 me-2" type="checkbox" :checked="regiaoSelecionada(regiao.codigo)" @change="alternarRegiao(regiao.codigo)"><span class="form-check-label text-truncate">{{ regiao.label }}</span></label><p v-if="!regioesFiltradas.length" class="small text-secondary mb-0 py-2 text-center">Nenhuma região encontrada.</p></div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3 position-relative" ref="habilidadesDropdownContainer">
                                <label class="form-label small fw-bold text-secondary text-uppercase">Habilidades Técnicas</label>
                                <button type="button" class="form-select form-select-sm filtro-multiselect text-start d-flex align-items-center" :aria-expanded="mostrarDropdownHabilidades" @click.stop="alternarDropdownHabilidades">
                                    <span class="text-truncate" :class="filtros.habilidades.length ? 'text-body' : 'text-secondary'">{{ rotuloFiltroHabilidades }}</span>
                                    <i class="bi bi-chevron-down filtro-multiselect-seta"></i>
                                </button>
                                <div v-if="mostrarDropdownHabilidades" class="filtro-multiselect-dropdown border rounded shadow-sm mt-1">
                                    <div class="p-2 border-bottom"><input ref="habilidadeBuscaInput" v-model="buscaHabilidade" type="text" class="form-control form-control-sm" placeholder="Buscar habilidade..." autocomplete="off"></div>
                                    <div class="filtro-multiselect-lista p-2"><div v-if="carregandoHabilidades" class="small text-secondary py-2 text-center"><span class="spinner-border spinner-border-sm me-1"></span> Carregando...</div><template v-else><label v-for="habilidade in habilidadesFiltradas" :key="habilidade" class="filtro-multiselect-opcao form-check rounded px-2 py-1 mb-1"><input class="form-check-input ms-0 me-2" type="checkbox" :checked="habilidadeSelecionada(habilidade)" @change="alternarHabilidade(habilidade)"><span class="form-check-label text-truncate">{{ habilidade }}</span></label><p v-if="!habilidadesFiltradas.length" class="small text-secondary mb-0 py-2 text-center">Nenhuma habilidade encontrada.</p></template></div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 flex-wrap mt-3">
                            <button type="button" class="btn btn-link text-secondary" @click="limparFiltros">Limpar Tudo</button>
                            <button type="button" class="btn btn-primary" :disabled="admin.carregando" @click="carregarAlunosFiltrados(1)">Aplicar Filtros</button>
                        </div>
                    </div>

                    <base-pagination
                        class="mb-3"
                        :current-page="admin.alunosPaginacao.current_page"
                        :last-page="admin.alunosPaginacao.last_page"
                        :per-page="admin.alunosPaginacao.per_page"
                        :total="admin.alunosPaginacao.total"
                        :from="admin.alunosPaginacao.from"
                        :to="admin.alunosPaginacao.to"
                        :loading="admin.carregando"
                        item-label="candidatos"
                        aria-label="Paginação superior de candidatos"
                        @change="mudarPagina"
                    />

                    <transition name="app-modal">
                        <div v-if="modalContratacaoAberto" class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true">
                            <div class="modal-dialog modal-dialog-centered app-modal-dialog-animated">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h3 class="modal-title h5 mb-0">Registrar contratação</h3>
                                        <button type="button" class="btn-close" aria-label="Fechar" @click="fecharModalContratacao"></button>
                                    </div>
                                    <form @submit.prevent="registrarContratacao">
                                        <div class="modal-body">
                                            <p class="mb-3">Confirme a contratação de <strong>{{ candidatoParaContratar?.pessoa?.nome }}</strong>.</p>
                                            <label class="form-label" for="empresa-contratante">Empresa contratante</label>
                                            <div class="position-relative">
                                                <input
                                                    id="empresa-contratante"
                                                    :value="empresaContratanteDisplay"
                                                    class="form-control"
                                                    type="search"
                                                    placeholder="Pesquisar empresa cadastrada"
                                                    autocomplete="off"
                                                    role="combobox"
                                                    :aria-expanded="!empresaContratanteSelecionada && empresasEncontradas.length > 0"
                                                    @input="onBuscaEmpresaContratanteInput"
                                                >
                                                <div v-if="!empresaContratanteSelecionada && empresasEncontradas.length" class="list-group position-absolute w-100 shadow mt-1" style="z-index: 1060; max-height: 220px; overflow-y: auto;">
                                                    <button
                                                        v-for="empresa in empresasEncontradas"
                                                        :key="empresa.cnpj"
                                                        type="button"
                                                        class="list-group-item list-group-item-action text-start"
                                                        @click="selecionarEmpresaContratante(empresa)"
                                                    >
                                                        <span class="d-block fw-semibold">{{ empresa.razao_social }}</span>
                                                        <small class="text-secondary">{{ formatarCnpj(empresa.cnpj) }}</small>
                                                    </button>
                                                </div>
                                            </div>
                                            <div v-if="empresaContratanteSelecionada" class="d-flex align-items-center justify-content-between border rounded p-2 mt-2">
                                                <span class="small">{{ empresaContratanteDisplay }}</span>
                                                <button type="button" class="btn btn-sm btn-link" @click="limparEmpresaContratante">Trocar</button>
                                            </div>
                                            <small v-else-if="!carregandoEmpresas && !empresasEncontradas.length && empresaContratanteDisplay" class="text-secondary d-block mt-2">
                                                Nenhuma empresa encontrada. Confira o cadastro em Gestão de Empresas.
                                            </small>
                                            <small v-if="carregandoEmpresas" class="text-secondary d-block mt-2">Buscando empresas...</small>
                                            <div v-if="erroContratacao" class="alert alert-danger py-2 mt-3 mb-0">{{ erroContratacao }}</div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" class="btn btn-primary" :disabled="salvandoContratacao || !empresaContratanteSelecionada">
                                                <span v-if="salvandoContratacao" class="spinner-border spinner-border-sm me-2"></span>
                                                Confirmar contratação
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </transition>

                    <transition name="app-modal">
                        <div
                            v-if="modalCadastroAberto"
                            class="modal fade show d-block"
                            tabindex="-1"
                            role="dialog"
                            aria-modal="true"
                        >
                            <div class="modal-dialog modal-lg modal-dialog-centered app-modal-dialog-animated">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h3 class="modal-title h5 mb-0">Novo Candidato</h3>
                                        <button type="button" class="btn-close" aria-label="Fechar" @click="fecharModalCadastro"></button>
                                    </div>
                                    <form @submit.prevent="salvarNovoCandidato">
                                        <div class="modal-body">
                                            <div v-if="mensagemErro" class="alert alert-danger py-2">
                                                {{ mensagemErro }}
                                            </div>

                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <label class="form-label">Nome</label>
                                                    <input v-model.trim="formulario.nome" type="text" class="form-control" maxlength="100" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">E-mail</label>
                                                    <input v-model.trim="formulario.email" type="email" class="form-control" maxlength="100" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Telefone</label>
                                                    <input
                                                        v-model="formulario.telefone"
                                                        type="text"
                                                        inputmode="numeric"
                                                        autocomplete="tel"
                                                        class="form-control"
                                                        maxlength="16"
                                                        required
                                                        @input="onTelefoneInput"
                                                    >
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Matrícula</label>
                                                    <input v-model="formulario.matricula" type="text" inputmode="numeric" class="form-control" maxlength="15" required @input="onMatriculaInput">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">CPF</label>
                                                    <input
                                                        v-model="formulario.cpf"
                                                        type="text"
                                                        inputmode="numeric"
                                                        autocomplete="off"
                                                        class="form-control"
                                                        maxlength="14"
                                                        required
                                                        @input="onCpfInput"
                                                    >
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Curso</label>
                                                    <input v-model.trim="formulario.curso" type="text" class="form-control" maxlength="45">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Unidade</label>
                                                    <input v-model.trim="formulario.unidade" type="text" class="form-control" maxlength="45">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Senha</label>
                                                    <input v-model="formulario.senha" type="password" class="form-control" minlength="6" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Confirmar senha</label>
                                                    <input v-model="formulario.confirmarSenha" type="password" class="form-control" minlength="6" required>
                                                </div>
                                                <div class="col-12">
                                                    <div class="form-check mt-2">
                                                        <input id="status-candidato" v-model="formulario.status" class="form-check-input" type="checkbox">
                                                        <label class="form-check-label" for="status-candidato">
                                                            Liberar acesso ao candidato após o cadastro
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" class="btn btn-primary" :disabled="salvandoCadastro">
                                                <span v-if="salvandoCadastro" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                                {{ salvandoCadastro ? 'Salvando...' : 'Salvar candidato' }}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </transition>
                    <transition name="app-modal">
                        <div v-if="modalCadastroAberto" class="modal-backdrop fade show"></div>
                    </transition>

                    <download-curriculos-modal
                        v-model:pagina-inicial-model="formularioCurriculos.paginaInicial"
                        v-model:pagina-final-model="formularioCurriculos.paginaFinal"
                        :show="modalCurriculosAberto"
                        :loading="gerandoZip"
                        :erro="erroCurriculos"
                        :per-page="Number(admin.alunosPaginacao.per_page || 10)"
                        :last-page="Number(admin.alunosPaginacao.last_page || 1)"
                        :limite-paginas="limitePaginasZip"
                        @fechar="fecharModalCurriculos"
                        @gerar="gerarZipCurriculos"
                    />

                    <p v-if="!admin.alunos.length" class="text-secondary small mb-0">
                        Nenhum candidato encontrado.
                    </p>

                    <template v-else>
                        <div class="table-responsive admin-candidates-table-wrapper">
                            <table class="table align-middle mb-0 admin-candidates-table">
                                <colgroup>
                                    <col class="admin-candidates-col-candidate">
                                    <col class="admin-candidates-col-cpf">
                                    <col class="admin-candidates-col-phone">
                                    <col class="admin-candidates-col-course">
                                    <col class="admin-candidates-col-status">
                                    <col class="admin-candidates-col-actions">
                                </colgroup>
                                <thead>
                                    <tr class="text-secondary small text-uppercase">
                                        <th class="admin-candidates-cell-candidate">Candidato</th>
                                        <th class="admin-candidates-cell-cpf">CPF</th>
                                        <th class="admin-candidates-cell-phone">Telefone</th>
                                        <th class="admin-candidates-cell-course">Curso / Unidade</th>
                                        <th class="admin-candidates-cell-status">Status</th>
                                        <th class="admin-candidates-cell-actions admin-candidates-actions-header">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="aluno in admin.alunos" :key="aluno.matricula">
                                        <tr>
                                            <td class="admin-candidates-cell-candidate">
                                                <p class="fw-semibold mb-0">{{ aluno.pessoa?.nome }}</p>
                                                <p class="text-secondary small mb-0">E-mail: {{ aluno.pessoa?.email || '—' }}</p>
                                            </td>
                                            <td class="admin-candidates-cell-cpf">{{ formatarCpf(aluno.cpf) }}</td>
                                            <td class="admin-candidates-cell-phone">
                                                {{ formatarTelefoneListagem(aluno.pessoa?.telefone) }}
                                            </td>
                                            <td class="admin-candidates-cell-course">
                                                <p class="mb-0">{{ aluno.dados_academicos?.[0]?.curso || '—' }}</p>
                                                <p class="text-secondary small mb-0">{{ aluno.dados_academicos?.[0]?.unidade || '—' }}</p>
                                            </td>
                                            <td class="admin-candidates-cell-status">
                                                <span
                                                    class="badge admin-candidates-status-badge"
                                                    :class="classeEstadoAluno(aluno)"
                                                >
                                                    {{ rotuloEstadoAluno(aluno) }}
                                                </span>
                                            </td>
                                            <td class="admin-candidates-cell-actions admin-candidates-actions-cell">
                                                <div class="admin-candidates-actions">
                                                <button
                                                    class="btn btn-sm btn-outline-primary admin-candidates-action-button admin-candidates-action-button--details"
                                                    @click="alternarDetalhes(aluno.matricula)"
                                                >
                                                    {{ alunoExpandido === aluno.matricula ? 'Ocultar Detalhes' : 'Ver Detalhes' }}
                                                </button>
                                                <button class="btn btn-sm btn-outline-success admin-candidates-action-button admin-candidates-action-button--hiring" :disabled="salvandoContratacao || alunoContratado(aluno)" @click="abrirModalContratacao(aluno)">
                                                    <i class="bi bi-person-check me-1"></i>Contratado(a)
                                                </button>
                                                <button
                                                    class="btn btn-sm admin-candidates-action-button admin-candidates-action-button--access"
                                                    :class="aluno.status ? 'btn-outline-danger' : 'btn-success'"
                                                    :disabled="alterando === aluno.matricula"
                                                    @click="alternarStatus(aluno)"
                                                >
                                                    {{ aluno.status ? 'Bloquear Acesso' : 'Liberar Acesso' }}
                                                </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="alunoExpandido === aluno.matricula" :key="`detalhes-${aluno.matricula}`">
                                            <td colspan="6" class="bg-light-subtle">
                                                <div class="p-3">
                                                    <div class="row g-3">
                                                        <div class="col-12">
                                                            <small class="text-secondary d-block">Sobre mim</small>
                                                            <span>{{ aluno.informacoes_profissionais?.sobre_mim || 'Não informado' }}</span>
                                                        </div>
                                                        <div class="col-md-6 col-lg-4">
                                                            <small class="text-secondary d-block">CPF</small>
                                                            <span>{{ formatarCpf(aluno.cpf) }}</span>
                                                        </div>
                                                        <div class="col-md-6 col-lg-4">
                                                            <small class="text-secondary d-block">Cargo de interesse</small>
                                                            <span>{{ aluno.informacoes_profissionais?.cargo_de_interesse || 'Não informado' }}</span>
                                                        </div>
                                                        <div class="col-md-6 col-lg-4">
                                                            <small class="text-secondary d-block">Disponibilidade de horário</small>
                                                            <span>{{ formatarDisponibilidade(aluno.preferencias_de_trabalho?.disponibilidade_de_horario) }}</span>
                                                        </div>
                                                        <div class="col-md-6 col-lg-4">
                                                            <small class="text-secondary d-block">Região administrativa</small>
                                                            <span>{{ aluno.preferencias_de_trabalho?.regiao_administrativa || 'Não informado' }}</span>
                                                        </div>
                                                        <div class="col-md-6 col-lg-4">
                                                            <small class="text-secondary d-block">Pretensão salarial</small>
                                                            <span>{{ formatarPretensao(aluno.preferencias_de_trabalho?.pretensao_salarial) }}</span>
                                                        </div>
                                                         <div class="col-md-6 col-lg-4 d-flex align-items-end justify-content-lg-start">
                                                            <div class="d-flex flex-wrap gap-2">
                                                             <button
                                                                 class="btn btn-sm btn-outline-primary"
                                                                 :disabled="curriculoGerando === aluno.matricula"
                                                                 @click="baixarCurriculo(aluno)"
                                                             >
                                                                <span v-if="curriculoGerando === aluno.matricula" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                                                 <i v-else class="bi bi-file-earmark-arrow-down me-1"></i>
                                                                 {{ curriculoGerando === aluno.matricula ? 'Gerando...' : 'Baixar currículo' }}
                                                             </button>
                                                            </div>
                                                         </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <base-pagination
                            class="mt-3"
                            :current-page="admin.alunosPaginacao.current_page"
                            :last-page="admin.alunosPaginacao.last_page"
                            :per-page="admin.alunosPaginacao.per_page"
                            :total="admin.alunosPaginacao.total"
                            :from="admin.alunosPaginacao.from"
                            :to="admin.alunosPaginacao.to"
                            :loading="admin.carregando"
                            item-label="candidatos"
                            aria-label="Paginação inferior de candidatos"
                            @change="mudarPagina"
                        />
                    </template>

                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import topbar from '../../../components/common/header.vue';
import loading from '../../../components/common/loading.vue';
import BasePagination from '../../../components/common/BasePagination.vue';
import DownloadCurriculosModal from '../../../components/common/DownloadCurriculosModal.vue';
import { useAdminStore } from '../../../store/admin';
import adminService from '../../../services/adminServices';
import { useToast } from '../../../composables/useToast';
import { formatarTelefone, somenteNumeros } from '../../../utils/telefone';
import { formatarFaixaPretensaoSalarial } from '../../../utils/faixasPretensaoSalarial';
import { formatarDisponibilidadeHorario } from '../../../utils/listasPtBr';
import { areasAtuacao, deduplicarHabilidades, habilidadesPadrao } from '../../../utils/habilidadesCatalogo';
import { regioesAdministrativasDf } from '../../../utils/regioesAdministrativasDf';
import {
    formatarCpf as formatarCpfDocumento,
    formatarCnpj as formatarCnpjDocumento,
    mascararBuscaCpf,
    mascararBuscaCnpj,
    normalizarBuscaDocumentoOuTexto,
    ehEntradaDocumento,
} from '../../../utils/documentos';
import {
    LIMITE_PAGINAS_CURRICULOS_ZIP,
    baixarBlobZipCurriculos,
    obterMensagemErroDownloadCurriculos,
    validarIntervaloCurriculos as validarIntervaloCurriculosCompartilhado,
} from '../../../utils/downloadCurriculosZip';

const admin = useAdminStore();
const toast = useToast();
const carregouUmaVez = ref(false);
const busca = ref('');
const buscaDropdownAberto = ref(false);
const statusFiltro = ref('');
const unidadeFiltro = ref('');
const unidadeDropdownAberto = ref(false);
const bandejaFiltrosAberta = ref(false);
const filtroClt = ref(false);
const filtroEstagio = ref(false);
const disponibilidadeFiltro = ref('');
const filtros = reactive({ segmento: [], regioes_administrativas: [], habilidades: [] });
const segmentosAcademicos = ref(areasAtuacao.map((area) => ({ id: area, nome: area })));
const buscaSegmento = ref('');
const buscaRegiao = ref('');
const buscaHabilidade = ref('');
const mostrarDropdownSegmentos = ref(false);
const mostrarDropdownRegioes = ref(false);
const mostrarDropdownHabilidades = ref(false);
const segmentoBuscaInput = ref(null);
const regiaoBuscaInput = ref(null);
const habilidadeBuscaInput = ref(null);
const segmentosDropdownContainer = ref(null);
const regioesDropdownContainer = ref(null);
const habilidadesDropdownContainer = ref(null);
const habilidadesDisponiveis = ref([]);
const carregandoHabilidades = ref(false);
const alterando = ref(null);
const curriculoGerando = ref(null);
const alunoExpandido = ref(null);
const modalContratacaoAberto = ref(false);
const candidatoParaContratar = ref(null);
const empresasEncontradas = ref([]);
const empresaContratanteDisplay = ref('');
const empresaContratanteSearch = ref('');
const empresaContratanteSelecionada = ref('');
const carregandoEmpresas = ref(false);
const salvandoContratacao = ref(false);
const erroContratacao = ref('');
const modalCadastroAberto = ref(false);
const modalCurriculosAberto = ref(false);
const gerandoZip = ref(false);
const gerandoRelatorioPdf = ref(false);
const erroCurriculos = ref('');
const limitePaginasZip = LIMITE_PAGINAS_CURRICULOS_ZIP;
const salvandoCadastro = ref(false);
const mensagemErro = ref('');
const formularioInicial = () => ({
    nome: '',
    email: '',
    telefone: '',
    matricula: '',
    cpf: '',
    curso: '',
    unidade: '',
    senha: '',
    confirmarSenha: '',
    status: true,
});
const formulario = reactive(formularioInicial());
const formularioCurriculos = reactive({
    paginaInicial: 1,
    paginaFinal: 1,
});
const statusOpcoes = [
    { value: '', label: 'Todos os status' },
    { value: 'ATIVO', label: 'Ativos' },
    { value: 'BLOQUEADO_POR_INATIVIDADE', label: 'Bloqueados por inatividade' },
    { value: 'BLOQUEADO_MANUALMENTE', label: 'Bloqueados administrativamente' },
];
const totalFiltrosAtivos = computed(() => [
    statusFiltro.value !== '',
    Boolean(unidadeFiltro.value),
    filtros.segmento.length > 0,
    filtros.regioes_administrativas.length > 0,
    filtros.habilidades.length > 0,
    Boolean(disponibilidadeFiltro.value),
    filtroClt.value || filtroEstagio.value,
].filter(Boolean).length);
const rotuloFiltroSegmentos = computed(() => filtros.segmento.length === 0 ? 'Selecione segmentos...' : (filtros.segmento.length === 1 ? filtros.segmento[0] : `${filtros.segmento.length} segmentos selecionados`));
const rotuloFiltroRegioes = computed(() => filtros.regioes_administrativas.length === 0 ? 'Selecione regiões...' : (filtros.regioes_administrativas.length === 1 ? regioesAdministrativasDf.find((regiao) => regiao.codigo === filtros.regioes_administrativas[0])?.label || '1 região selecionada' : `${filtros.regioes_administrativas.length} regiões selecionadas`));
const rotuloFiltroHabilidades = computed(() => filtros.habilidades.length === 0 ? 'Selecione habilidades...' : (filtros.habilidades.length === 1 ? '1 habilidade selecionada' : `${filtros.habilidades.length} habilidades selecionadas`));
const segmentosFiltrados = computed(() => segmentosAcademicos.value.filter((segmento) => normalizarTexto(segmento.nome).includes(normalizarTexto(buscaSegmento.value))));
const regioesFiltradas = computed(() => regioesAdministrativasDf.filter((regiao) => normalizarTexto(`${regiao.label} ${regiao.nome}`).includes(normalizarTexto(buscaRegiao.value))));
const habilidadesFiltradas = computed(() => habilidadesDisponiveis.value.filter((habilidade) => normalizarTexto(habilidade).includes(normalizarTexto(buscaHabilidade.value))));
const unidadesFiltradas = computed(() => {
    const termo = unidadeFiltro.value.trim().toLocaleLowerCase('pt-BR');
    return admin.unidadesAlunos
        .filter((unidade) => !termo || unidade.toLocaleLowerCase('pt-BR').includes(termo))
        .slice(0, 10);
});
const candidatosBuscaSugeridos = computed(() => {
    const termo = busca.value.trim().toLocaleLowerCase('pt-BR');
    const numeros = somenteNumeros(termo);
    if (!termo) return [];

    return admin.alunos.filter((aluno) => {
        const nome = String(aluno.pessoa?.nome || '').toLocaleLowerCase('pt-BR');
        const cpf = somenteNumeros(aluno.cpf);
        return nome.includes(termo) || (numeros && cpf.includes(numeros));
    }).slice(0, 10);
});
onMounted(async () => {
    try {
        await Promise.all([
            admin.carregarUnidadesAlunos(),
            carregarHabilidadesDisponiveis(),
            carregarAlunosFiltrados(),
        ]);
        document.addEventListener('click', aoClicarForaDosDropdowns);
    } finally {
        carregouUmaVez.value = true;
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('click', aoClicarForaDosDropdowns);
});

let temporizadorFiltro = null;
watch([busca], () => {
    clearTimeout(temporizadorFiltro);
    temporizadorFiltro = setTimeout(() => {
        carregarAlunosFiltrados(1);
    }, 300);
});

let temporizadorBuscaEmpresa = null;

function parametrosFiltro(pagina = admin.alunosPaginacao.current_page || 1) {
    const buscaNormalizada = normalizarBuscaDocumentoOuTexto(busca.value);

    return {
        page: pagina,
        per_page: 10,
        ...(buscaNormalizada ? { busca: buscaNormalizada } : {}),
        ...(statusFiltro.value !== '' ? { status: statusFiltro.value } : {}),
        ...(unidadeFiltro.value !== '' ? { unidade: unidadeFiltro.value } : {}),
        ...(filtros.segmento.length ? { segmento: filtros.segmento } : {}),
        ...(filtros.regioes_administrativas.length ? { regioes_administrativas: filtros.regioes_administrativas } : {}),
        ...(filtros.habilidades.length ? { habilidades: filtros.habilidades } : {}),
        ...(disponibilidadeFiltro.value ? { disponibilidade: disponibilidadeFiltro.value } : {}),
        ...((filtroClt.value || filtroEstagio.value) ? { tipo_contratacao: (filtroClt.value ? 1 : 0) + (filtroEstagio.value ? 2 : 0) } : {}),
    };
}

function limparFiltros() {
    statusFiltro.value = '';
    unidadeFiltro.value = '';
    filtros.segmento = [];
    filtros.regioes_administrativas = [];
    filtros.habilidades = [];
    buscaSegmento.value = '';
    buscaRegiao.value = '';
    buscaHabilidade.value = '';
    disponibilidadeFiltro.value = '';
    filtroClt.value = false;
    filtroEstagio.value = false;
    fecharDropdownSegmentos();
    fecharDropdownRegioes();
    fecharDropdownHabilidades();
    carregarAlunosFiltrados(1);
}

function parametrosCurriculos(paginaInicial, paginaFinal) {
    const { page, per_page, ...filtros } = parametrosFiltro(admin.alunosPaginacao.current_page || 1);

    return {
        ...filtros,
        pagina_inicial: paginaInicial,
        pagina_final: paginaFinal,
    };
}

async function carregarAlunosFiltrados(pagina = admin.alunosPaginacao.current_page || 1) {
    await admin.carregarAlunos(parametrosFiltro(pagina));

    if (admin.erro) {
        toast.error(admin.erro);
    }
}

function mudarPagina(pagina) {
    if (pagina < 1 || pagina > admin.alunosPaginacao.last_page || pagina === admin.alunosPaginacao.current_page || admin.carregando) {
        return;
    }

    alunoExpandido.value = null;
    carregarAlunosFiltrados(pagina);
}

function selecionarSugestaoBusca(aluno) {
    busca.value = aluno.pessoa?.nome || aluno.cpf || '';
    buscaDropdownAberto.value = false;
}

function fecharSugestaoBusca() {
    setTimeout(() => { buscaDropdownAberto.value = false; }, 150);
}

function selecionarUnidade(unidade) {
    unidadeFiltro.value = unidade;
    unidadeDropdownAberto.value = false;
}

function normalizarTexto(valor) {
    return String(valor ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
}

function segmentoSelecionado(segmento) { return filtros.segmento.includes(segmento); }
function alternarSegmento(segmento) { const indice = filtros.segmento.indexOf(segmento); indice >= 0 ? filtros.segmento.splice(indice, 1) : filtros.segmento.push(segmento); }
function regiaoSelecionada(codigo) { return filtros.regioes_administrativas.includes(Number(codigo)); }
function alternarRegiao(codigo) { const valor = Number(codigo); const indice = filtros.regioes_administrativas.indexOf(valor); indice >= 0 ? filtros.regioes_administrativas.splice(indice, 1) : filtros.regioes_administrativas.push(valor); filtros.regioes_administrativas.sort((a, b) => a - b); }
function habilidadeSelecionada(habilidade) { const valor = normalizarTexto(habilidade); return filtros.habilidades.some((item) => normalizarTexto(item) === valor); }
function alternarHabilidade(habilidade) { const valor = normalizarTexto(habilidade); const indice = filtros.habilidades.findIndex((item) => normalizarTexto(item) === valor); indice >= 0 ? filtros.habilidades.splice(indice, 1) : filtros.habilidades.push(habilidade); }
function alternarDropdownSegmentos() { mostrarDropdownSegmentos.value = !mostrarDropdownSegmentos.value; if (mostrarDropdownSegmentos.value) nextTick(() => segmentoBuscaInput.value?.focus()); }
function alternarDropdownRegioes() { mostrarDropdownRegioes.value = !mostrarDropdownRegioes.value; if (mostrarDropdownRegioes.value) nextTick(() => regiaoBuscaInput.value?.focus()); }
function alternarDropdownHabilidades() { mostrarDropdownHabilidades.value = !mostrarDropdownHabilidades.value; if (mostrarDropdownHabilidades.value) nextTick(() => habilidadeBuscaInput.value?.focus()); }
function fecharDropdownSegmentos() { mostrarDropdownSegmentos.value = false; buscaSegmento.value = ''; }
function fecharDropdownRegioes() { mostrarDropdownRegioes.value = false; buscaRegiao.value = ''; }
function fecharDropdownHabilidades() { mostrarDropdownHabilidades.value = false; buscaHabilidade.value = ''; }
function aoClicarForaDosDropdowns(evento) {
    if (segmentosDropdownContainer.value && !segmentosDropdownContainer.value.contains(evento.target)) fecharDropdownSegmentos();
    if (regioesDropdownContainer.value && !regioesDropdownContainer.value.contains(evento.target)) fecharDropdownRegioes();
    if (habilidadesDropdownContainer.value && !habilidadesDropdownContainer.value.contains(evento.target)) fecharDropdownHabilidades();
}

async function carregarHabilidadesDisponiveis() {
    carregandoHabilidades.value = true;
    try {
        const { data } = await adminService.listarHabilidadesCandidatos();
        habilidadesDisponiveis.value = deduplicarHabilidades([...habilidadesPadrao, ...(Array.isArray(data) ? data : [])]).sort((a, b) => a.localeCompare(b, 'pt-BR', { sensitivity: 'base' }));
    } finally {
        carregandoHabilidades.value = false;
    }
}

function fecharSugestaoUnidade() {
    setTimeout(() => { unidadeDropdownAberto.value = false; }, 150);
}

async function carregarEmpresasParaContratacao(termo = '') {
    carregandoEmpresas.value = true;
    try {
        const { data } = await adminService.listarEmpresas({ busca: termo, per_page: 10 });
        empresasEncontradas.value = data.data || [];
    } catch (error) {
        empresasEncontradas.value = [];
        erroContratacao.value = 'Não foi possível carregar as empresas.';
    } finally {
        carregandoEmpresas.value = false;
    }
}

function onBuscaEmpresaContratanteInput(event) {
    const valorDigitado = String(event.target.value ?? '');
    const valorVisual = ehEntradaDocumento(valorDigitado)
        ? mascararBuscaCnpj(valorDigitado)
        : valorDigitado;
    const valorBusca = normalizarBuscaDocumentoOuTexto(valorVisual);

    empresaContratanteDisplay.value = valorVisual;
    empresaContratanteSearch.value = valorBusca;
    empresaContratanteSelecionada.value = '';
    event.target.value = valorVisual;

    clearTimeout(temporizadorBuscaEmpresa);
    temporizadorBuscaEmpresa = setTimeout(() => carregarEmpresasParaContratacao(valorBusca), 250);
}

function selecionarEmpresaContratante(empresa) {
    empresaContratanteSelecionada.value = empresa.cnpj;
    empresaContratanteDisplay.value = empresa.razao_social;
    empresaContratanteSearch.value = String(empresa.cnpj ?? '');
}

function limparEmpresaContratante() {
    empresaContratanteSelecionada.value = '';
    empresaContratanteDisplay.value = '';
    empresaContratanteSearch.value = '';
    empresasEncontradas.value = [];
}

function formatarCnpj(cnpj) {
    return formatarCnpjDocumento(String(cnpj ?? '').padStart(14, '0'));
}

function abrirModalContratacao(aluno) {
    candidatoParaContratar.value = aluno;
    empresaContratanteSelecionada.value = '';
    empresaContratanteDisplay.value = '';
    empresaContratanteSearch.value = '';
    erroContratacao.value = '';
    modalContratacaoAberto.value = true;
    carregarEmpresasParaContratacao();
}

function fecharModalContratacao() {
    if (salvandoContratacao.value) return;
    modalContratacaoAberto.value = false;
    candidatoParaContratar.value = null;
    limparEmpresaContratante();
    erroContratacao.value = '';
}

async function registrarContratacao() {
    if (salvandoContratacao.value) return;
    if (!candidatoParaContratar.value || !empresaContratanteSelecionada.value) return;
    salvandoContratacao.value = true;
    erroContratacao.value = '';
    try {
        await adminService.registrarContratacao(candidatoParaContratar.value.matricula, {
            empresa_cnpj: empresaContratanteSelecionada.value,
        });
        toast.success('Contratação registrada. O candidato foi movido para Candidatos Contratados.');
        modalContratacaoAberto.value = false;
        candidatoParaContratar.value = null;
        limparEmpresaContratante();
        await carregarAlunosFiltrados(1);
    } catch (error) {
        const mensagem = obterMensagemErro(error);
        if (error.response?.status === 409 && mensagem.includes('contratação ativa')) {
            toast.info('Este candidato já está contratado. A listagem foi atualizada.');
            modalContratacaoAberto.value = false;
            candidatoParaContratar.value = null;
            limparEmpresaContratante();
            await carregarAlunosFiltrados(admin.alunosPaginacao.current_page || 1);
            return;
        }

        erroContratacao.value = mensagem || 'Não foi possível registrar a contratação.';
    } finally {
        salvandoContratacao.value = false;
    }
}

async function sincronizar() {
    await carregarAlunosFiltrados();
}

function limparFormulario() {
    Object.assign(formulario, formularioInicial());
}

function exibirMensagemSucesso(texto) {
    toast.success(texto);
}

function abrirModalCadastro() {
    mensagemErro.value = '';
    modalCadastroAberto.value = true;
}

function fecharModalCadastro({ limpar = true } = {}) {
    if (salvandoCadastro.value) return;
    modalCadastroAberto.value = false;
    mensagemErro.value = '';
    if (limpar) {
        limparFormulario();
    }
}

function abrirModalCurriculos() {
    const paginaAtual = admin.alunosPaginacao.current_page || 1;
    formularioCurriculos.paginaInicial = paginaAtual;
    formularioCurriculos.paginaFinal = paginaAtual;
    erroCurriculos.value = '';
    modalCurriculosAberto.value = true;
}

function fecharModalCurriculos() {
    if (gerandoZip.value) return;
    modalCurriculosAberto.value = false;
    erroCurriculos.value = '';
}

function obterMensagemErro(error) {
    if (error?.response?.data?.errors) {
        const primeiroCampo = Object.values(error.response.data.errors)[0];
        if (Array.isArray(primeiroCampo) && primeiroCampo.length) {
            return primeiroCampo[0];
        }
    }

    if (error?.response?.data?.message) {
        if (String(error.response.data.message).includes('SQLSTATE')) {
            return 'Não foi possível carregar os candidatos. Tente novamente.';
        }
        return error.response.data.message;
    }

    if (error?.response?.data?.error) {
        return error.response.data.error;
    }

    return 'Não foi possível cadastrar o candidato. Verifique os dados e tente novamente.';
}

function validarIntervaloCurriculos(inicio, fim) {
    return validarIntervaloCurriculosCompartilhado(inicio, fim, admin.alunosPaginacao.last_page, limitePaginasZip);
}

function removerMascara(valor) {
    return somenteDigitos(valor);
}

function limitarDigitos(valor, limite = 11) {
    return somenteDigitos(valor).slice(0, limite);
}

function formatarCpf(valor) {
    return formatarCpfDocumento(valor);
}

function formatarTelefoneListagem(valor) {
    if (!valor) {
        return 'Não informado';
    }

    return formatarTelefone(valor) || 'Não informado';
}

function alunoContratado(aluno) {
    return aluno?.estado_efetivo === 'CONTRATADO';
}

function rotuloEstadoAluno(aluno) {
    if (aluno?.estado_efetivo_rotulo) {
        return aluno.estado_efetivo_rotulo;
    }

    return aluno?.status ? 'Liberado' : 'Bloqueado';
}

function classeEstadoAluno(aluno) {
    switch (aluno?.estado_efetivo) {
        case 'CONTRATADO':
            return 'text-bg-primary-subtle text-primary-emphasis';
        case 'BLOQUEADO_MANUALMENTE':
            return 'text-bg-danger-subtle text-danger-emphasis';
        case 'BLOQUEADO_POR_INATIVIDADE':
            return 'text-bg-warning-subtle text-warning-emphasis';
        case 'ATIVO':
            return 'text-bg-success-subtle text-success-emphasis';
        default:
            return aluno?.status
                ? 'text-bg-success-subtle text-success-emphasis'
                : 'text-bg-danger-subtle text-danger-emphasis';
    }
}

function rotuloFiltroStatusExportacao(status) {
    const opcao = statusOpcoes.find((item) => item.value === status);
    return opcao?.value ? `Status: ${opcao.label}` : null;
}

function censurarCpf(valor) {
    const digitos = limitarDigitos(valor, 11);

    if (digitos.length !== 11) {
        return formatarCpf(digitos);
    }

    return `***.${digitos.slice(3, 6)}.***-**`;
}

function onCpfInput(evento) {
    const valorFormatado = formatarCpf(evento.target.value);
    formulario.cpf = valorFormatado;
    evento.target.value = valorFormatado;
}

function onBuscaCpfInput(evento) {
    const valorFormatado = mascararBuscaCpf(evento.target.value);
    busca.value = valorFormatado;
    evento.target.value = valorFormatado;
    buscaDropdownAberto.value = true;
}

function onTelefoneInput(evento) {
    const valorFormatado = formatarTelefone(evento.target.value);
    formulario.telefone = valorFormatado;
    evento.target.value = valorFormatado;
}

function onMatriculaInput(evento) {
    const valor = somenteNumeros(evento.target.value).slice(0, 15);
    formulario.matricula = valor;
    evento.target.value = valor;
}

async function salvarNovoCandidato() {
    mensagemErro.value = '';

    if (formulario.senha !== formulario.confirmarSenha) {
        mensagemErro.value = 'As senhas informadas não coincidem.';
        toast.error(mensagemErro.value);
        return;
    }

    salvandoCadastro.value = true;

    try {
        await admin.cadastrarAluno({
            nome: formulario.nome,
            email: formulario.email,
            telefone: somenteNumeros(formulario.telefone),
            matricula: somenteNumeros(formulario.matricula).slice(0, 15),
            cpf: removerMascara(formulario.cpf),
            curso: formulario.curso,
            unidade: formulario.unidade,
            senha: formulario.senha,
            status: formulario.status,
        });

        await admin.carregarUnidadesAlunos();
        await carregarAlunosFiltrados();
        modalCadastroAberto.value = false;
        fecharModalCadastro({ limpar: false });
        limparFormulario();
        exibirMensagemSucesso('Candidato cadastrado com sucesso.');
    } catch (error) {
        mensagemErro.value = obterMensagemErro(error);
        toast.error(mensagemErro.value);
    } finally {
        salvandoCadastro.value = false;
    }
}

async function alternarStatus(aluno) {
    alterando.value = aluno.matricula;
    const novoStatus = !aluno.status;
    const motivoBloqueioPublico = novoStatus ? null : window.prompt('Informe um motivo público para o candidato (opcional):') || null;
    try {
        await admin.atualizarStatusAluno(aluno.matricula, novoStatus, motivoBloqueioPublico);
        await carregarAlunosFiltrados();
        toast.info(`Acesso do candidato ${novoStatus ? 'liberado' : 'bloqueado'} com sucesso.`);
    } catch (error) {
        toast.error('Não foi possível alterar o status do candidato.');
    } finally {
        alterando.value = null;
    }
}

async function baixarCurriculo(aluno) {
    curriculoGerando.value = aluno.matricula;

    try {
        const response = await adminService.baixarCurriculoAluno(aluno.matricula);
        const blob = new Blob([response.data], { type: 'application/pdf' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = nomeArquivoCurriculo(response, aluno);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        toast.error('Não foi possível gerar o currículo deste candidato.');
    } finally {
        curriculoGerando.value = null;
    }
}

async function baixarCandidatosFiltradosPdf() {
    const filtrosExportacao = { ...parametrosFiltro(1) };
    clearTimeout(temporizadorFiltro);
    const janelaImpressao = window.open('', '_blank');
    if (!janelaImpressao) {
        toast.error('Permita a abertura da janela para preparar o PDF.');
        return;
    }

    gerandoRelatorioPdf.value = true;
    janelaImpressao.document.write('<!doctype html><html><head><title>Preparando lista de candidatos</title></head><body><p style="font:16px Arial;padding:24px">Preparando a lista para impressão...</p></body></html>');
    janelaImpressao.document.close();

    try {
        const respostaInicial = await adminService.listarAlunos({ ...filtrosExportacao, page: 1 });
        const paginacao = respostaInicial.data;
        const candidatos = [...(paginacao.data || [])];

        for (let inicio = 2; inicio <= paginacao.last_page; inicio += 10) {
            const paginas = Array.from({ length: Math.min(10, paginacao.last_page - inicio + 1) }, (_, indice) => inicio + indice);
            const respostas = await Promise.all(paginas.map((pagina) =>
                adminService.listarAlunos({ ...filtrosExportacao, page: pagina })
            ));
            respostas.forEach(({ data }) => candidatos.push(...(data.data || [])));
        }

        const resumoFiltros = [
            filtrosExportacao.busca ? `Busca: ${filtrosExportacao.busca}` : null,
            rotuloFiltroStatusExportacao(filtrosExportacao.status),
                            filtrosExportacao.unidade ? `Unidade: ${filtrosExportacao.unidade}` : null,
                            filtrosExportacao.segmento?.length ? `Segmentos: ${filtrosExportacao.segmento.join(', ')}` : null,
                            filtrosExportacao.regioes_administrativas?.length ? `Regiões: ${filtrosExportacao.regioes_administrativas.length}` : null,
                            filtrosExportacao.habilidades?.length ? `Habilidades: ${filtrosExportacao.habilidades.join(', ')}` : null,
                        ].filter(Boolean).join(' | ') || 'Sem filtros adicionais';

        const linhas = candidatos.map((aluno) => `
            <tr>
                <td>${escaparHtml(aluno.pessoa?.nome || '—')}</td>
                <td>${escaparHtml(formatarCpf(aluno.cpf) || '—')}</td>
                <td>${escaparHtml(formatarTelefoneListagem(aluno.pessoa?.telefone))}</td>
                <td>${escaparHtml(aluno.dados_academicos?.[0]?.curso || '—')}</td>
                <td>${escaparHtml(aluno.dados_academicos?.[0]?.unidade || '—')}</td>
            </tr>
        `).join('');
        const titulo = `Candidatos cadastrados - ${new Date().toLocaleDateString('pt-BR')}`;

        janelaImpressao.document.open();
        janelaImpressao.document.write(`<!doctype html>
            <html lang="pt-BR">
                <head>
                    <meta charset="utf-8">
                    <title>${escaparHtml(titulo)}</title>
                    <style>
                        @page { size: landscape; margin: 14mm; }
                        body { color: #212529; font: 12px Arial, sans-serif; }
                        h1 { color: #163f70; font-size: 20px; margin: 0 0 6px; }
                        p { color: #5c6670; margin: 0 0 16px; }
                        table { border-collapse: collapse; width: 100%; }
                        th, td { border: 1px solid #cbd2d9; padding: 8px; text-align: left; }
                        th { background: #edf2f7; color: #163f70; }
                        tr { break-inside: avoid; }
                        footer { color: #6c757d; margin-top: 12px; }
                    </style>
                </head>
                <body>
                    <h1>Candidatos Cadastrados</h1>
                    <p>${escaparHtml(resumoFiltros)} · ${candidatos.length} candidato(s) · Gerado em ${new Date().toLocaleString('pt-BR')}</p>
                    <table>
                        <thead><tr><th>Candidato</th><th>CPF</th><th>Telefone</th><th>Curso</th><th>Unidade</th></tr></thead>
                        <tbody>${linhas || '<tr><td colspan="5">Nenhum candidato encontrado.</td></tr>'}</tbody>
                    </table>
                    <footer>Senac DF · Gestão dos Candidatos</footer>
                    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));<\/script>
                </body>
            </html>`);
        janelaImpressao.document.close();
    } catch (error) {
        janelaImpressao.close();
        toast.error('Não foi possível preparar o PDF com os filtros selecionados.');
    } finally {
        gerandoRelatorioPdf.value = false;
    }
}

function escaparHtml(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, (caractere) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    })[caractere]);
}

async function gerarZipCurriculos({ paginaInicial, paginaFinal } = {}) {
    const inicio = Number(paginaInicial ?? formularioCurriculos.paginaInicial);
    const fim = Number(paginaFinal ?? formularioCurriculos.paginaFinal);
    await executarDownloadZip(inicio, fim, { fecharModalAoConcluir: true });
}

async function executarDownloadZip(inicio, fim, { fecharModalAoConcluir = false } = {}) {
    erroCurriculos.value = validarIntervaloCurriculos(inicio, fim);

    if (erroCurriculos.value) {
        toast.error(erroCurriculos.value);
        return;
    }

    gerandoZip.value = true;

    try {
        const response = await adminService.baixarCurriculosAlunos(parametrosCurriculos(inicio, fim));
        baixarBlobZipCurriculos(response, inicio, fim);

        if (fecharModalAoConcluir) {
            modalCurriculosAberto.value = false;
        }
    } catch (error) {
        erroCurriculos.value = obterMensagemErroDownloadCurriculos(error);
        toast.error(erroCurriculos.value);
    } finally {
        gerandoZip.value = false;
    }
}

function nomeArquivoCurriculo(response, aluno) {
    const disposicao = response.headers?.['content-disposition'] || '';
    const encontrado = disposicao.match(/filename="?([^";]+)"?/i);

    if (encontrado?.[1]) {
        return encontrado[1];
    }

    const nome = (aluno.pessoa?.nome || 'Candidato')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/gi, '_')
        .replace(/^_+|_+$/g, '');

    return `Curriculo_${nome || 'Candidato'}.pdf`;
}

function alternarDetalhes(matricula) {
    alunoExpandido.value = alunoExpandido.value === matricula ? null : matricula;
}

function formatarPretensao(valor) {
    return formatarFaixaPretensaoSalarial(valor, 'Não informado');
}

function formatarDisponibilidade(valor) {
    return formatarDisponibilidadeHorario(valor, 'Não informado');
}

</script>

<style scoped>
.admin-filter-toggle {
    border: 1px solid #163f70;
    color: #163f70;
    background: #fff;
    min-height: 2.375rem;
}

.admin-filter-toggle:hover,
.admin-filter-toggle--open {
    color: #fff;
    background: #163f70;
}

.admin-filter-tray {
    --ses-admin-filter-bg: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    --ses-admin-filter-surface: #ffffff;
    --ses-admin-filter-input-bg: #f8fafc;
    --ses-admin-filter-border: #cbd5e1;
    --ses-admin-filter-heading: #1e3a5f;
    --ses-admin-filter-text: #0f172a;
    --ses-admin-filter-text-soft: #475569;
    --ses-admin-filter-dropdown-bg: #ffffff;
    --ses-admin-filter-option-hover: #e2e8f0;
    --ses-admin-filter-option-selected: #dbeafe;
    --ses-admin-filter-option-selected-text: #1e3a8a;
    --ses-admin-filter-scrollbar-thumb: #cbd5e1;
    --ses-admin-filter-scrollbar-track: #f8fafc;
    border: 1px solid var(--ses-admin-filter-border);
    background: var(--ses-admin-filter-bg);
    overflow: visible;
}

html.dark .admin-filter-tray {
    --ses-admin-filter-bg: linear-gradient(135deg, #102f55 0%, #163f70 100%);
    --ses-admin-filter-surface: #111827;
    --ses-admin-filter-input-bg: #0b1220;
    --ses-admin-filter-border: rgba(148, 163, 184, 0.22);
    --ses-admin-filter-heading: #f8fafc;
    --ses-admin-filter-text: #e5e7eb;
    --ses-admin-filter-text-soft: #cbd5e1;
    --ses-admin-filter-dropdown-bg: #111827;
    --ses-admin-filter-option-hover: #1e3a5f;
    --ses-admin-filter-option-selected: #1d4ed8;
    --ses-admin-filter-option-selected-text: #ffffff;
    --ses-admin-filter-scrollbar-thumb: #475569;
    --ses-admin-filter-scrollbar-track: #0f172a;
    border-color: var(--ses-admin-filter-border);
    background: var(--ses-admin-filter-bg);
}

.admin-filter-tray .form-label {
    color: var(--ses-admin-filter-heading) !important;
}

.admin-filter-tray .form-control,
.admin-filter-tray .form-select,
.admin-filter-tray .filtro-multiselect {
    border-color: var(--ses-admin-filter-border);
    background-color: var(--ses-admin-filter-input-bg);
    color: var(--ses-admin-filter-text);
}

.admin-filter-tray .form-control::placeholder {
    color: var(--ses-admin-filter-text-soft);
    opacity: 1;
}

.admin-filter-tray .form-control:focus,
.admin-filter-tray .form-select:focus,
.admin-filter-tray .filtro-multiselect:focus,
.admin-filter-tray .filtro-multiselect:focus-visible {
    border-color: #60a5fa;
    background-color: var(--ses-admin-filter-input-bg);
    color: var(--ses-admin-filter-text);
    box-shadow: 0 0 0 0.2rem rgba(96, 165, 250, 0.28);
    outline: 0;
}

.admin-filter-tray .form-select option {
    background-color: var(--ses-admin-filter-dropdown-bg);
    color: var(--ses-admin-filter-text);
}

.admin-filter-tray .form-select option:checked {
    background-color: var(--ses-admin-filter-option-selected);
    color: var(--ses-admin-filter-option-selected-text);
}

.admin-filter-tray .form-check-label,
.admin-filter-tray .filtro-multiselect .text-body {
    color: var(--ses-admin-filter-text) !important;
}

.admin-filter-tray .text-secondary,
.admin-filter-tray .filtro-multiselect .text-secondary {
    color: var(--ses-admin-filter-text-soft) !important;
}

.admin-filter-tray .form-check-input {
    border-color: var(--ses-admin-filter-border);
    background-color: var(--ses-admin-filter-input-bg);
}

.admin-filter-tray .form-check-input:hover {
    border-color: #93c5fd;
    box-shadow: 0 0 0 0.12rem rgba(96, 165, 250, 0.18);
}

.admin-filter-tray .form-check-input:focus {
    border-color: #60a5fa;
    box-shadow: 0 0 0 0.2rem rgba(96, 165, 250, 0.28);
}

.admin-filter-tray .form-check-input:checked {
    border-color: #60a5fa;
    background-color: #1d4ed8;
}

.admin-filter-tray .form-check-input:disabled {
    border-color: var(--ses-admin-filter-border);
    background-color: rgba(148, 163, 184, 0.2);
    opacity: 0.72;
}

.admin-filter-grid {
    overflow: visible;
}

.filtro-multiselect {
    position: relative;
    min-height: 2rem;
    justify-content: space-between;
    padding-right: 2.25rem;
    background-image: none;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
}

.filtro-multiselect-seta {
    position: absolute;
    top: 50%;
    right: 0.75rem;
    transform: translateY(-50%);
    pointer-events: none;
    line-height: 1;
    color: var(--ses-admin-filter-text);
}

.filtro-multiselect-dropdown {
    position: absolute;
    z-index: 1060;
    width: calc(100% - var(--bs-gutter-x));
    max-height: 18rem;
    overflow: hidden;
    border-color: var(--ses-admin-filter-border) !important;
    background: var(--ses-admin-filter-dropdown-bg);
    color: var(--ses-admin-filter-text);
}

.filtro-multiselect-dropdown .border-bottom {
    border-color: var(--ses-admin-filter-border) !important;
}

.filtro-multiselect-lista {
    max-height: 12rem;
    overflow-y: auto;
    scrollbar-color: var(--ses-admin-filter-scrollbar-thumb) var(--ses-admin-filter-scrollbar-track);
}

.filtro-multiselect-opcao {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    cursor: pointer;
    color: var(--ses-admin-filter-text);
    transition: background-color 0.16s ease, color 0.16s ease, box-shadow 0.16s ease;
}

.filtro-multiselect-opcao:hover,
.filtro-multiselect-opcao:focus-within {
    background: var(--ses-admin-filter-option-hover);
    color: var(--ses-admin-filter-text);
}

.filtro-multiselect-opcao:has(.form-check-input:checked) {
    background: var(--ses-admin-filter-option-selected);
    color: var(--ses-admin-filter-option-selected-text);
}

.filtro-multiselect-opcao:has(.form-check-input:focus-visible) {
    box-shadow: 0 0 0 0.16rem rgba(96, 165, 250, 0.42);
}

.admin-filter-suggestions {
    z-index: 1060;
    max-height: 220px;
    overflow-y: auto;
    border: 1px solid var(--ses-admin-filter-border);
    border-radius: 0.375rem;
    background: var(--ses-admin-filter-dropdown-bg);
    scrollbar-color: var(--ses-admin-filter-scrollbar-thumb) var(--ses-admin-filter-scrollbar-track);
}

.admin-filter-suggestions .list-group-item {
    border-color: var(--ses-admin-filter-border);
    background: var(--ses-admin-filter-dropdown-bg);
    color: var(--ses-admin-filter-text);
}

.admin-filter-suggestions .list-group-item:hover,
.admin-filter-suggestions .list-group-item:focus,
.admin-filter-suggestions .list-group-item:focus-visible,
.admin-filter-suggestions .list-group-item.active {
    border-color: var(--ses-admin-filter-border);
    background: var(--ses-admin-filter-option-hover);
    color: var(--ses-admin-filter-text);
    outline: 0;
}

.admin-filter-suggestions .list-group-item:active {
    border-color: #1d4ed8;
    background: #1d4ed8;
    color: #ffffff;
}

.admin-candidates-table-wrapper {
    width: 100%;
}

.admin-candidates-table {
    table-layout: fixed;
    min-width: 1480px;
}

.admin-candidates-table th,
.admin-candidates-table td {
    vertical-align: middle;
}

.admin-candidates-col-candidate {
    width: 18%;
}

.admin-candidates-col-cpf {
    width: 10.5rem;
}

.admin-candidates-col-phone {
    width: 12rem;
}

.admin-candidates-col-course {
    width: 19%;
}

.admin-candidates-col-status {
    width: 13rem;
}

.admin-candidates-col-actions {
    width: 31rem;
}

.admin-candidates-cell-candidate,
.admin-candidates-cell-course {
    text-align: left;
}

.admin-candidates-cell-cpf,
.admin-candidates-cell-phone,
.admin-candidates-cell-status,
.admin-candidates-cell-actions {
    text-align: center;
}

.admin-candidates-cell-cpf,
.admin-candidates-cell-phone,
.admin-candidates-cell-status {
    white-space: nowrap;
}

.admin-candidates-cell-cpf,
.admin-candidates-cell-phone {
    font-variant-numeric: tabular-nums;
}

.admin-candidates-status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    max-width: 100%;
    white-space: nowrap;
}

.admin-candidates-actions-header,
.admin-candidates-actions-cell {
    min-width: 31rem;
    white-space: nowrap;
}

.admin-candidates-actions {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    white-space: nowrap;
}

.admin-candidates-action-button {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    height: 2rem;
    padding-top: 0;
    padding-bottom: 0;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
}

.admin-candidates-action-button :deep(.bi),
.admin-candidates-action-button.bi {
    line-height: 1;
}

.admin-candidates-action-button--details {
    width: 8.75rem;
}

.admin-candidates-action-button--hiring {
    width: 9.25rem;
}

.admin-candidates-action-button--access {
    width: 10.25rem;
}

@media (max-width: 1024px) {
    .admin-candidates-table {
        min-width: 1440px;
    }

    .admin-candidates-actions-header,
    .admin-candidates-actions-cell {
        min-width: 30rem;
    }
}
</style>
