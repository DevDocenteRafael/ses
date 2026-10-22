<template>
    <div>
        <topbar titulo="Indicações de Candidatos" subtitulo="Indique candidatos do Senac para empresas parceiras e acompanhe os retornos" />

        <div class="container-fluid p-4">
            <ul class="nav nav-pills indicacoes-abas mb-3" role="tablist">
                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link" :class="{ active: aba === 'nova' }" role="tab" :aria-selected="aba === 'nova'" @click="aba = 'nova'">
                        <i class="bi bi-send-plus me-1" aria-hidden="true"></i> Nova indicação
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link" :class="{ active: aba === 'historico' }" role="tab" :aria-selected="aba === 'historico'" @click="abrirHistorico">
                        <i class="bi bi-clock-history me-1" aria-hidden="true"></i> Histórico de indicações
                    </button>
                </li>
            </ul>

            <!-- ======================= NOVA INDICAÇÃO ======================= -->
            <div v-show="aba === 'nova'">
                <div class="row g-3">
                    <!-- Candidatos -->
                    <div class="col-12 col-xl-7">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                    <h2 class="h6 fw-bold indicacoes-secao-titulo mb-0">1. Candidatos elegíveis</h2>
                                    <span class="badge rounded-pill bg-primary" data-testid="contador-candidatos">{{ candidatosSelecionados.size }} selecionado(s)</span>
                                </div>
                                <p class="small indicacoes-texto-suave mb-3">
                                    Somente candidatos ativos aparecem aqui: inativos, bloqueados e contratados não podem ser indicados.
                                </p>

                                <form class="row g-2 mb-3" role="search" @submit.prevent="buscarCandidatos(1)">
                                    <div class="col-12 col-md-5">
                                        <input v-model.trim="filtrosCandidatos.busca" type="search" class="form-control" placeholder="Nome, CPF ou matrícula" aria-label="Pesquisar candidato por nome, CPF ou matrícula" maxlength="100" autocomplete="off">
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <select v-model="filtrosCandidatos.segmento" class="form-select" aria-label="Filtrar por segmento">
                                            <option value="">Segmento</option>
                                            <option v-for="s in segmentos" :key="s.id" :value="s.id">{{ s.nome }}</option>
                                        </select>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <select v-model="filtrosCandidatos.habilidade" class="form-select" aria-label="Filtrar por habilidade">
                                            <option value="">Habilidade</option>
                                            <option v-for="h in habilidades" :key="h" :value="h">{{ h }}</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-1 d-grid">
                                        <button type="submit" class="btn btn-primary" aria-label="Buscar candidatos"><i class="bi bi-search" aria-hidden="true"></i></button>
                                    </div>
                                </form>

                                <loading v-if="carregandoCandidatos && !candidatos.length" mensagem="Carregando candidatos..." />
                                <div v-else-if="erroCandidatos" class="alert alert-danger small">{{ erroCandidatos }}</div>
                                <div v-else-if="!candidatos.length" class="text-center py-4 indicacoes-texto-suave" role="status" data-testid="candidatos-vazio">
                                    <i class="bi bi-person-x fs-3 d-block mb-2" aria-hidden="true"></i>
                                    <template v-if="candidatosEncontradosIndisponiveis.length">
                                        <span class="d-block mb-2">Candidato encontrado, mas indisponível para indicação:</span>
                                        <ul class="list-unstyled small mb-0">
                                            <li v-for="(c, i) in candidatosEncontradosIndisponiveis" :key="i">
                                                <span class="fw-semibold">{{ c.nome }}</span> — {{ c.estado_efetivo_rotulo }}
                                            </li>
                                        </ul>
                                    </template>
                                    <template v-else-if="buscaTemElegiveisForaDosFiltros">
                                        Há candidatos elegíveis com esse termo, mas nenhum corresponde ao segmento ou à habilidade selecionados.
                                    </template>
                                    <template v-else-if="diagnosticoBusca">
                                        Nenhum candidato cadastrado corresponde a esse nome, CPF ou matrícula.
                                    </template>
                                    <template v-else>
                                        Nenhum candidato elegível encontrado com esses filtros.
                                    </template>
                                </div>
                                <template v-else>
                                    <div class="form-check mb-2">
                                        <input id="selecionar-pagina-candidatos" class="form-check-input" type="checkbox" :checked="paginaCandidatosSelecionada" @change="alternarPaginaCandidatos($event.target.checked)">
                                        <label class="form-check-label small" for="selecionar-pagina-candidatos">Selecionar todos desta página</label>
                                    </div>
                                    <ul class="list-group indicacoes-lista mb-2" :class="{ 'opacity-50': carregandoCandidatos }">
                                        <li v-for="c in candidatos" :key="c.matricula" class="list-group-item">
                                            <div class="form-check d-flex gap-3 align-items-start mb-0">
                                                <input :id="`cand-${c.matricula}`" class="form-check-input mt-1 flex-shrink-0" type="checkbox" :checked="candidatosSelecionados.has(String(c.matricula))" @change="alternarCandidato(c, $event.target.checked)">
                                                <label class="form-check-label flex-grow-1 min-w-0" :for="`cand-${c.matricula}`">
                                                    <span class="d-flex align-items-center flex-wrap gap-2">
                                                        <span class="fw-semibold">{{ c.pessoa?.nome }}</span>
                                                        <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle fw-normal">{{ c.estado_efetivo_rotulo || 'Ativo' }}</span>
                                                    </span>
                                                    <span class="d-block small indicacoes-texto-suave">
                                                        {{ resumoAcademico(c) }}
                                                    </span>
                                                    <span v-if="habilidadesDe(c).length" class="d-flex flex-wrap gap-1 mt-1">
                                                        <span v-for="h in habilidadesDe(c).slice(0, 4)" :key="h" class="badge bg-primary-subtle text-primary-emphasis fw-normal">{{ h }}</span>
                                                        <span v-if="habilidadesDe(c).length > 4" class="badge bg-secondary-subtle text-secondary-emphasis fw-normal">+{{ habilidadesDe(c).length - 4 }}</span>
                                                    </span>
                                                    <span class="d-block small indicacoes-texto-suave mt-1">
                                                        <i class="bi bi-clock me-1" aria-hidden="true"></i>{{ formatarDisponibilidadeHorario(c.preferencias_de_trabalho?.disponibilidade_de_horario) }}
                                                    </span>
                                                </label>
                                            </div>
                                        </li>
                                    </ul>
                                    <base-pagination
                                        :current-page="paginacaoCandidatos.current_page"
                                        :last-page="paginacaoCandidatos.last_page"
                                        :per-page="paginacaoCandidatos.per_page"
                                        :total="paginacaoCandidatos.total"
                                        :from="paginacaoCandidatos.from"
                                        :to="paginacaoCandidatos.to"
                                        :loading="carregandoCandidatos"
                                        item-label="candidatos"
                                        aria-label="Paginação de candidatos elegíveis"
                                        @change="buscarCandidatos"
                                    />
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Empresas -->
                    <div class="col-12 col-xl-5">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                    <h2 class="h6 fw-bold indicacoes-secao-titulo mb-0">2. Empresas destinatárias</h2>
                                    <span class="badge rounded-pill bg-primary" data-testid="contador-empresas">{{ empresasSelecionadas.size }} selecionada(s)</span>
                                </div>
                                <p class="small indicacoes-texto-suave mb-3">Somente empresas com acesso ativo ao portal podem receber indicações.</p>

                                <form class="input-group mb-3" role="search" @submit.prevent="buscarEmpresas(1)">
                                    <input v-model.trim="filtroEmpresas" type="search" class="form-control" placeholder="Razão social ou CNPJ" aria-label="Pesquisar empresa por razão social ou CNPJ">
                                    <button type="submit" class="btn btn-primary" aria-label="Buscar empresas"><i class="bi bi-search" aria-hidden="true"></i></button>
                                </form>

                                <loading v-if="carregandoEmpresas && !empresas.length" mensagem="Carregando empresas..." />
                                <div v-else-if="erroEmpresas" class="alert alert-danger small">{{ erroEmpresas }}</div>
                                <div v-else-if="!empresas.length" class="text-center py-4 indicacoes-texto-suave">
                                    <i class="bi bi-building-x fs-3 d-block mb-2" aria-hidden="true"></i>
                                    Nenhuma empresa ativa encontrada.
                                </div>
                                <template v-else>
                                    <ul class="list-group indicacoes-lista mb-2" :class="{ 'opacity-50': carregandoEmpresas }">
                                        <li v-for="e in empresas" :key="e.cnpj" class="list-group-item">
                                            <div class="form-check d-flex gap-3 align-items-start mb-0">
                                                <input :id="`emp-${e.cnpj}`" class="form-check-input mt-1 flex-shrink-0" type="checkbox" :checked="empresasSelecionadas.has(String(e.cnpj))" @change="alternarEmpresa(e, $event.target.checked)">
                                                <label class="form-check-label flex-grow-1 min-w-0" :for="`emp-${e.cnpj}`">
                                                    <span class="d-flex align-items-center flex-wrap gap-2">
                                                        <span class="fw-semibold">{{ e.razao_social }}</span>
                                                        <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle fw-normal">Ativa</span>
                                                    </span>
                                                    <span class="d-block small indicacoes-texto-suave">CNPJ {{ formatarCnpj(e.cnpj) }}</span>
                                                </label>
                                            </div>
                                        </li>
                                    </ul>
                                    <base-pagination
                                        :current-page="paginacaoEmpresas.current_page"
                                        :last-page="paginacaoEmpresas.last_page"
                                        :per-page="paginacaoEmpresas.per_page"
                                        :total="paginacaoEmpresas.total"
                                        :from="paginacaoEmpresas.from"
                                        :to="paginacaoEmpresas.to"
                                        :loading="carregandoEmpresas"
                                        item-label="empresas"
                                        aria-label="Paginação de empresas"
                                        @change="buscarEmpresas"
                                    />
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resumo da seleção -->
                <div class="card border-0 shadow-sm mt-3 indicacoes-resumo">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                            <div>
                                <h2 class="h6 fw-bold indicacoes-secao-titulo mb-1">3. Revisar e confirmar</h2>
                                <p class="mb-0" data-testid="resumo-total">
                                    <strong>{{ candidatosSelecionados.size }}</strong> candidato(s) ×
                                    <strong>{{ empresasSelecionadas.size }}</strong> empresa(s) =
                                    <strong>{{ totalIndicacoes }}</strong> {{ totalIndicacoes === 1 ? 'indicação' : 'indicações' }}
                                </p>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-outline-secondary ses-btn-relatorio" :disabled="!candidatosSelecionados.size && !empresasSelecionadas.size" @click="limparSelecao">
                                    Limpar seleção
                                </button>
                                <button type="button" class="btn btn-primary" :disabled="!totalIndicacoes" @click="abrirConfirmacao">
                                    <i class="bi bi-send-check me-1" aria-hidden="true"></i> Revisar e confirmar
                                </button>
                            </div>
                        </div>

                        <div v-if="candidatosSelecionados.size || empresasSelecionadas.size" class="row g-3 mt-1">
                            <div class="col-12 col-md-6">
                                <p class="small fw-semibold mb-1">Candidatos escolhidos</p>
                                <div class="d-flex flex-wrap gap-1">
                                    <span v-for="c in candidatosSelecionados.values()" :key="c.matricula" class="badge rounded-pill bg-primary-subtle text-primary-emphasis indicacoes-chip">
                                        {{ c.nome }}
                                        <button type="button" class="btn-close btn-close-sm ms-1" :aria-label="`Remover ${c.nome}`" @click="removerCandidato(c.matricula)"></button>
                                    </span>
                                    <span v-if="!candidatosSelecionados.size" class="small indicacoes-texto-suave">Nenhum candidato.</span>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <p class="small fw-semibold mb-1">Empresas escolhidas</p>
                                <div class="d-flex flex-wrap gap-1">
                                    <span v-for="e in empresasSelecionadas.values()" :key="e.cnpj" class="badge rounded-pill bg-primary-subtle text-primary-emphasis indicacoes-chip">
                                        {{ e.razao_social }}
                                        <button type="button" class="btn-close btn-close-sm ms-1" :aria-label="`Remover ${e.razao_social}`" @click="removerEmpresa(e.cnpj)"></button>
                                    </span>
                                    <span v-if="!empresasSelecionadas.size" class="small indicacoes-texto-suave">Nenhuma empresa.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================= HISTÓRICO ======================= -->
            <div v-show="aba === 'historico'">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 fw-bold indicacoes-secao-titulo mb-3">Histórico de indicações</h2>
                        <form class="row g-2 mb-3" @submit.prevent="buscarHistorico(1)">
                            <div class="col-12 col-md-3">
                                <input v-model.trim="filtrosHistorico.candidato" type="search" class="form-control" placeholder="Candidato (nome ou CPF)" aria-label="Filtrar por candidato">
                            </div>
                            <div class="col-12 col-md-3">
                                <input v-model.trim="filtrosHistorico.empresa" type="search" class="form-control" placeholder="Empresa (razão social ou CNPJ)" aria-label="Filtrar por empresa">
                            </div>
                            <div class="col-6 col-md-2">
                                <select v-model="filtrosHistorico.status" class="form-select" aria-label="Filtrar por status">
                                    <option value="">Todos os status</option>
                                    <option v-for="(rotulo, valor) in ROTULOS_STATUS" :key="valor" :value="valor">{{ rotulo }}</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <input v-model="filtrosHistorico.de" type="date" class="form-control" aria-label="Indicadas a partir de">
                            </div>
                            <div class="col-6 col-md-2">
                                <input v-model="filtrosHistorico.ate" type="date" class="form-control" aria-label="Indicadas até">
                            </div>
                            <div class="col-6 col-md-12 d-flex gap-2 justify-content-md-end">
                                <button type="button" class="btn btn-outline-secondary ses-btn-relatorio" @click="limparFiltrosHistorico">Limpar</button>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1" aria-hidden="true"></i>Filtrar</button>
                            </div>
                        </form>

                        <loading v-if="carregandoHistorico && !historico.length" mensagem="Carregando indicações..." />
                        <div v-else-if="erroHistorico" class="alert alert-danger">{{ erroHistorico }}</div>
                        <div v-else-if="!historico.length" class="text-center py-5 indicacoes-texto-suave">
                            <i class="bi bi-inbox fs-2 d-block mb-2" aria-hidden="true"></i>
                            Nenhuma indicação encontrada.
                        </div>
                        <template v-else>
                            <div class="table-responsive">
                                <table class="table align-middle mb-2" :class="{ 'opacity-50': carregandoHistorico }">
                                    <thead>
                                        <tr>
                                            <th scope="col">Candidato</th>
                                            <th scope="col">Empresa</th>
                                            <th scope="col" class="d-none d-lg-table-cell">Responsável</th>
                                            <th scope="col">Indicada em</th>
                                            <th scope="col">Status</th>
                                            <th scope="col" class="d-none d-xl-table-cell">Observação</th>
                                            <th scope="col" class="text-end">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="i in historico" :key="i.id">
                                            <td>
                                                <span class="fw-semibold d-block">{{ i.candidato?.nome || '—' }}</span>
                                                <small v-if="i.candidato?.estado_efetivo && i.candidato.estado_efetivo !== 'ATIVO'" class="text-danger-emphasis">{{ i.candidato.estado_efetivo_rotulo }}</small>
                                            </td>
                                            <td>{{ i.empresa?.razao_social }}</td>
                                            <td class="d-none d-lg-table-cell">{{ i.indicado_por || '—' }}</td>
                                            <td class="text-nowrap">{{ formatarData(i.indicado_em) }}</td>
                                            <td><span class="badge" :class="classeStatus(i.status)">{{ i.status_rotulo }}</span></td>
                                            <td class="d-none d-xl-table-cell indicacoes-observacao">{{ i.observacao || '—' }}</td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-primary indicacoes-btn-contorno" @click="abrirDetalhes(i.id)">Detalhes</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <base-pagination
                                :current-page="paginacaoHistorico.current_page"
                                :last-page="paginacaoHistorico.last_page"
                                :per-page="paginacaoHistorico.per_page"
                                :total="paginacaoHistorico.total"
                                :from="paginacaoHistorico.from"
                                :to="paginacaoHistorico.to"
                                :loading="carregandoHistorico"
                                item-label="indicações"
                                aria-label="Paginação do histórico de indicações"
                                @change="buscarHistorico"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmação (modal personalizado, sem caixas nativas) -->
        <app-modal :show="confirmando" titulo="Confirmar indicações Senac" @fechar="fecharConfirmacao">
            <div data-testid="modal-confirmar-indicacoes">
                <ul class="list-unstyled mb-3">
                    <li><strong>{{ candidatosSelecionados.size }}</strong> {{ candidatosSelecionados.size === 1 ? 'candidato selecionado' : 'candidatos selecionados' }}.</li>
                    <li><strong>{{ empresasSelecionadas.size }}</strong> {{ empresasSelecionadas.size === 1 ? 'empresa selecionada' : 'empresas selecionadas' }}.</li>
                    <li class="mt-1 fs-5">Total: <strong data-testid="total-confirmacao">{{ totalIndicacoes }}</strong> {{ totalIndicacoes === 1 ? 'indicação' : 'indicações' }}.</li>
                </ul>
                <label for="observacao-indicacao" class="form-label fw-semibold">Observação do Senac <span class="fw-normal indicacoes-texto-suave">(opcional)</span></label>
                <textarea id="observacao-indicacao" v-model="observacao" class="form-control" rows="3" maxlength="1000" placeholder="Ex.: candidatos com ótimo desempenho no curso." :disabled="enviando"></textarea>
                <div class="form-text text-end">{{ observacao.length }}/1000</div>

                <div v-if="erroEnvio" class="alert alert-danger mt-3 mb-0" role="alert">{{ erroEnvio }}</div>

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="button" class="btn btn-outline-secondary ses-btn-relatorio" :disabled="enviando" @click="fecharConfirmacao">Cancelar</button>
                    <button type="button" class="btn btn-primary" :disabled="enviando" @click="confirmarIndicacoes">
                        <span v-if="enviando" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                        {{ enviando ? 'Enviando...' : 'Confirmar indicações' }}
                    </button>
                </div>
            </div>
        </app-modal>

        <!-- Detalhes de uma indicação -->
        <app-modal :show="detalhesAbertos" titulo="Detalhes da indicação" @fechar="detalhesAbertos = false">
            <loading v-if="carregandoDetalhes" mensagem="Carregando detalhes..." />
            <div v-else-if="erroDetalhes" class="alert alert-danger">{{ erroDetalhes }}</div>
            <div v-else-if="detalhes" data-testid="detalhes-indicacao">
                <dl class="row small mb-3">
                    <dt class="col-5">Candidato</dt><dd class="col-7">{{ detalhes.candidato?.nome }} <span class="d-block indicacoes-texto-suave">{{ detalhes.candidato?.estado_efetivo_rotulo }}</span></dd>
                    <dt class="col-5">Empresa</dt><dd class="col-7">{{ detalhes.empresa?.razao_social }}</dd>
                    <dt class="col-5">Responsável</dt><dd class="col-7">{{ detalhes.indicado_por || '—' }}</dd>
                    <dt class="col-5">Indicada em</dt><dd class="col-7">{{ formatarData(detalhes.indicado_em) }}</dd>
                    <dt class="col-5">Status atual</dt><dd class="col-7"><span class="badge" :class="classeStatus(detalhes.status)">{{ detalhes.status_rotulo }}</span></dd>
                    <dt class="col-5">Observação</dt><dd class="col-7">{{ detalhes.observacao || '—' }}</dd>
                </dl>
                <h3 class="h6 fw-bold">Linha do tempo</h3>
                <ol class="list-group list-group-numbered">
                    <li v-for="(e, idx) in detalhes.eventos" :key="idx" class="list-group-item small">
                        <span v-if="e.tipo === 'CRIADA'">Indicação criada como <strong>{{ e.status_novo_rotulo }}</strong></span>
                        <span v-else>Status alterado de <strong>{{ e.status_anterior_rotulo }}</strong> para <strong>{{ e.status_novo_rotulo }}</strong></span>
                        <span class="d-block indicacoes-texto-suave">{{ formatarData(e.em) }} · {{ e.responsavel || '—' }}</span>
                    </li>
                </ol>
            </div>
        </app-modal>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import topbar from '../../../components/common/header.vue';
import loading from '../../../components/common/loading.vue';
import AppModal from '../../../components/common/modal.vue';
import BasePagination from '../../../components/common/BasePagination.vue';
import adminService from '../../../services/adminServices';
import { useToast } from '../../../composables/useToast';
import { formatarDisponibilidadeHorario } from '../../../utils/listasPtBr';
import { normalizarBuscaDocumentoOuTexto } from '../../../utils/documentos';

const ROTULOS_STATUS = {
    NOVA: 'Nova indicação',
    EM_ANALISE: 'Em análise',
    TENHO_INTERESSE: 'Tenho interesse',
    SEM_INTERESSE: 'Sem interesse',
};
const paginacaoVazia = () => ({ current_page: 1, last_page: 1, per_page: 10, total: 0, from: null, to: null });

const toast = useToast();
const aba = ref('nova');

// Seleções guardadas por chave para sobreviver à troca de página e de filtro.
const candidatosSelecionados = reactive(new Map());
const empresasSelecionadas = reactive(new Map());
const totalIndicacoes = computed(() => candidatosSelecionados.size * empresasSelecionadas.size);

const candidatos = ref([]);
const paginacaoCandidatos = ref(paginacaoVazia());
const carregandoCandidatos = ref(false);
const erroCandidatos = ref('');
const filtrosCandidatos = reactive({ busca: '', segmento: '', habilidade: '' });
// Explica uma lista vazia: termo inexistente x candidatos encontrados porém indisponíveis.
const diagnosticoBusca = ref(null);
const segmentos = ref([]);
const habilidades = ref([]);

const empresas = ref([]);
const paginacaoEmpresas = ref(paginacaoVazia());
const carregandoEmpresas = ref(false);
const erroEmpresas = ref('');
const filtroEmpresas = ref('');

const confirmando = ref(false);
const enviando = ref(false);
const observacao = ref('');
const erroEnvio = ref('');

const historico = ref([]);
const paginacaoHistorico = ref(paginacaoVazia());
const carregandoHistorico = ref(false);
const erroHistorico = ref('');
const historicoCarregado = ref(false);
const filtrosHistorico = reactive({ candidato: '', empresa: '', status: '', de: '', ate: '' });

const detalhesAbertos = ref(false);
const carregandoDetalhes = ref(false);
const erroDetalhes = ref('');
const detalhes = ref(null);

const paginaCandidatosSelecionada = computed(() => candidatos.value.length > 0
    && candidatos.value.every((c) => candidatosSelecionados.has(String(c.matricula))));

function mensagemDeErro(error, padrao) {
    const status = error?.response?.status;
    const mensagem = error?.response?.data?.message;
    if (!error?.response) return 'Sem conexão com o servidor. Verifique sua internet e tente novamente.';
    if (status === 422 && typeof mensagem === 'string' && mensagem) return mensagem;
    if (status === 403) return 'Você não tem permissão para esta operação.';
    return padrao;
}

// Cada listagem só aplica a resposta da requisição mais recente: uma busca antiga que
// demore mais não pode sobrescrever o resultado de uma busca nova (condição de corrida).
const ultimaRequisicao = { candidatos: 0, empresas: 0, historico: 0 };

// Pesquisa enquanto digita (com pausa) e recarrega a lista completa quando o campo é limpo.
let temporizadorBuscaCandidatos = null;
watch(() => filtrosCandidatos.busca, () => {
    clearTimeout(temporizadorBuscaCandidatos);
    temporizadorBuscaCandidatos = setTimeout(() => buscarCandidatos(1), 400);
});
onBeforeUnmount(() => clearTimeout(temporizadorBuscaCandidatos));

const candidatosEncontradosIndisponiveis = computed(() => (diagnosticoBusca.value?.candidatos || [])
    .filter((c) => c.estado_efetivo !== 'ATIVO'));
const buscaTemElegiveisForaDosFiltros = computed(() => (diagnosticoBusca.value?.candidatos || [])
    .some((c) => c.estado_efetivo === 'ATIVO'));

async function buscarCandidatos(pagina = 1) {
    const requisicao = ++ultimaRequisicao.candidatos;
    clearTimeout(temporizadorBuscaCandidatos);
    carregandoCandidatos.value = true;
    erroCandidatos.value = '';
    try {
        const params = { status: 'ativo', page: pagina, per_page: 10 };
        const busca = normalizarBuscaDocumentoOuTexto(filtrosCandidatos.busca);
        if (busca) params.busca = busca;
        if (filtrosCandidatos.segmento) params.segmento = filtrosCandidatos.segmento;
        if (filtrosCandidatos.habilidade) params.habilidades = [filtrosCandidatos.habilidade];
        const { data } = await adminService.listarAlunos(params);
        if (requisicao !== ultimaRequisicao.candidatos) return;
        candidatos.value = data.data || [];
        paginacaoCandidatos.value = { ...paginacaoVazia(), ...data };

        diagnosticoBusca.value = null;
        if (!candidatos.value.length && busca) {
            try {
                const { data: diagnostico } = await adminService.diagnosticarBuscaCandidatos(busca);
                if (requisicao !== ultimaRequisicao.candidatos) return;
                diagnosticoBusca.value = diagnostico;
            } catch {
                // O diagnóstico só detalha a mensagem de lista vazia; sem ele, vale a mensagem padrão.
            }
        }
    } catch (error) {
        if (requisicao !== ultimaRequisicao.candidatos) return;
        erroCandidatos.value = mensagemDeErro(error, 'Não foi possível carregar os candidatos. Tente novamente.');
    } finally {
        if (requisicao === ultimaRequisicao.candidatos) carregandoCandidatos.value = false;
    }
}

async function buscarEmpresas(pagina = 1) {
    const requisicao = ++ultimaRequisicao.empresas;
    carregandoEmpresas.value = true;
    erroEmpresas.value = '';
    try {
        const params = { status: 1, page: pagina, per_page: 10 };
        if (filtroEmpresas.value) params.busca = filtroEmpresas.value;
        const { data } = await adminService.listarEmpresas(params);
        if (requisicao !== ultimaRequisicao.empresas) return;
        empresas.value = data.data || [];
        paginacaoEmpresas.value = { ...paginacaoVazia(), ...data };
    } catch (error) {
        if (requisicao !== ultimaRequisicao.empresas) return;
        erroEmpresas.value = mensagemDeErro(error, 'Não foi possível carregar as empresas. Tente novamente.');
    } finally {
        if (requisicao === ultimaRequisicao.empresas) carregandoEmpresas.value = false;
    }
}

function alternarCandidato(c, marcado) {
    const chave = String(c.matricula);
    if (marcado) candidatosSelecionados.set(chave, { matricula: chave, nome: c.pessoa?.nome || chave });
    else candidatosSelecionados.delete(chave);
}

function alternarPaginaCandidatos(marcado) {
    candidatos.value.forEach((c) => alternarCandidato(c, marcado));
}

function alternarEmpresa(e, marcado) {
    const chave = String(e.cnpj);
    if (marcado) empresasSelecionadas.set(chave, { cnpj: chave, razao_social: e.razao_social });
    else empresasSelecionadas.delete(chave);
}

function removerCandidato(matricula) {
    candidatosSelecionados.delete(matricula);
}

function removerEmpresa(cnpj) {
    empresasSelecionadas.delete(cnpj);
}

function limparSelecao() {
    candidatosSelecionados.clear();
    empresasSelecionadas.clear();
}

function abrirConfirmacao() {
    if (!totalIndicacoes.value) return;
    erroEnvio.value = '';
    confirmando.value = true;
}

function fecharConfirmacao() {
    if (enviando.value) return;
    confirmando.value = false;
}

async function confirmarIndicacoes() {
    if (enviando.value) return; // evita clique duplo
    enviando.value = true;
    erroEnvio.value = '';
    try {
        const { data } = await adminService.criarIndicacoes({
            candidatos: [...candidatosSelecionados.keys()],
            empresas: [...empresasSelecionadas.keys()],
            observacao: observacao.value.trim() || null,
        });

        // Só depois da confirmação do servidor: limpa seleção, avisa e atualiza as listagens.
        if (data.criadas > 0) toast.success(data.message);
        else toast.warning(data.message);
        if (data.criadas > 0 && data.ignoradas?.length) {
            toast.warning(`${data.ignoradas.length} par(es) já tinham indicação vigente e foram ignorados.`);
        }
        confirmando.value = false;
        observacao.value = '';
        limparSelecao();
        historicoCarregado.value = false;
        await buscarCandidatos(paginacaoCandidatos.value.current_page || 1);
    } catch (error) {
        // Mantém o modal e as seleções; nada de sucesso falso.
        erroEnvio.value = mensagemDeErro(error, 'Não foi possível registrar as indicações por uma falha no servidor. Suas seleções foram mantidas; tente novamente.');
    } finally {
        enviando.value = false;
    }
}

function abrirHistorico() {
    aba.value = 'historico';
    if (!historicoCarregado.value) buscarHistorico(1);
}

async function buscarHistorico(pagina = 1) {
    const requisicao = ++ultimaRequisicao.historico;
    carregandoHistorico.value = true;
    erroHistorico.value = '';
    try {
        const params = { page: pagina, per_page: 10 };
        Object.entries(filtrosHistorico).forEach(([chave, valor]) => { if (valor) params[chave] = valor; });
        const { data } = await adminService.listarIndicacoes(params);
        if (requisicao !== ultimaRequisicao.historico) return;
        historico.value = data.data || [];
        paginacaoHistorico.value = { ...paginacaoVazia(), ...data };
        historicoCarregado.value = true;
    } catch (error) {
        if (requisicao !== ultimaRequisicao.historico) return;
        erroHistorico.value = mensagemDeErro(error, 'Não foi possível carregar o histórico. Tente novamente.');
    } finally {
        if (requisicao === ultimaRequisicao.historico) carregandoHistorico.value = false;
    }
}

function limparFiltrosHistorico() {
    Object.keys(filtrosHistorico).forEach((chave) => { filtrosHistorico[chave] = ''; });
    buscarHistorico(1);
}

async function abrirDetalhes(id) {
    detalhesAbertos.value = true;
    carregandoDetalhes.value = true;
    erroDetalhes.value = '';
    detalhes.value = null;
    try {
        const { data } = await adminService.verIndicacao(id);
        detalhes.value = data;
    } catch (error) {
        erroDetalhes.value = mensagemDeErro(error, 'Não foi possível carregar os detalhes da indicação.');
    } finally {
        carregandoDetalhes.value = false;
    }
}

function resumoAcademico(c) {
    const academico = (c.dados_academicos || [])[0];
    const partes = [academico?.curso, academico?.segmento || c.informacoes_profissionais?.area_de_atuacao].filter(Boolean);
    return partes.length ? partes.join(' · ') : 'Formação não informada';
}

function habilidadesDe(c) {
    const lista = c.informacoes_profissionais?.habilidades;
    return Array.isArray(lista) ? lista.filter(Boolean) : [];
}

function classeStatus(status) {
    return {
        NOVA: 'bg-primary-subtle text-primary-emphasis border border-primary-subtle',
        EM_ANALISE: 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        TENHO_INTERESSE: 'bg-success-subtle text-success-emphasis border border-success-subtle',
        SEM_INTERESSE: 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle',
    }[status] || 'bg-secondary-subtle text-secondary-emphasis';
}

function formatarData(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatarCnpj(cnpj) {
    const n = String(cnpj || '').replace(/\D/g, '');
    return n.length === 14 ? n.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5') : cnpj;
}

onMounted(async () => {
    buscarCandidatos(1);
    buscarEmpresas(1);
    try {
        const [seg, hab] = await Promise.all([adminService.listarSegmentosAcademicos(), adminService.listarHabilidadesCandidatos()]);
        segmentos.value = Array.isArray(seg.data) ? seg.data : [];
        habilidades.value = (Array.isArray(hab.data) ? hab.data : []).slice().sort((a, b) => a.localeCompare(b, 'pt-BR'));
    } catch {
        // Filtros auxiliares são opcionais; a busca por nome continua funcionando.
    }
});
</script>

<style scoped>
.indicacoes-lista {
    max-height: 30rem;
    overflow-y: auto;
}

.indicacoes-chip {
    display: inline-flex;
    align-items: center;
    font-weight: 500;
    padding: 0.4em 0.6em;
}

.indicacoes-chip .btn-close {
    width: 0.55em;
    height: 0.55em;
}

.indicacoes-observacao {
    max-width: 16rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Texto auxiliar com contraste AA nos dois temas (o cinza padrão do Bootstrap fica abaixo no claro). */
.indicacoes-texto-suave {
    color: #475569;
}

html.dark .indicacoes-texto-suave {
    color: var(--ses-muted);
}

html.dark .indicacoes-chip .btn-close {
    filter: invert(1) grayscale(1) brightness(2);
}

/* Títulos de seção e botões de contorno: no escuro, o azul-marinho padrão fica ilegível. */
.indicacoes-secao-titulo {
    color: var(--ses-primary);
}

html.dark .indicacoes-secao-titulo {
    color: var(--bs-link-color);
}

html.dark .indicacoes-btn-contorno {
    --bs-btn-color: var(--bs-link-color);
    --bs-btn-border-color: var(--bs-link-color);
    --bs-btn-hover-color: #0f172a;
    --bs-btn-hover-bg: var(--bs-link-color);
    --bs-btn-hover-border-color: var(--bs-link-color);
    --bs-btn-active-color: #0f172a;
    --bs-btn-active-bg: var(--bs-link-hover-color);
    --bs-btn-active-border-color: var(--bs-link-hover-color);
}

/* Aba ativa no azul institucional (o nav-pills do Bootstrap traz o #0d6efd fixo). */
.indicacoes-abas {
    --bs-nav-pills-link-active-bg: var(--ses-primary);
    --bs-nav-pills-link-active-color: #ffffff;
    --bs-nav-link-color: var(--ses-primary);
    --bs-nav-link-hover-color: var(--ses-primary-hover);
}

html.dark .indicacoes-abas {
    --bs-nav-link-color: var(--bs-link-color);
    --bs-nav-link-hover-color: var(--bs-link-hover-color);
}

.indicacoes-abas .nav-link:not(.active):hover {
    background-color: rgba(var(--ses-primary-rgb), 0.08);
}

html.dark .indicacoes-abas .nav-link:not(.active):hover {
    background-color: rgba(125, 183, 255, 0.12);
}

.indicacoes-abas .nav-link.active:hover,
.indicacoes-abas .nav-link.active:active {
    background-color: var(--ses-primary-hover);
}

.indicacoes-abas .nav-link:not(.active):active {
    background-color: rgba(var(--ses-primary-rgb), 0.16);
}

html.dark .indicacoes-abas .nav-link:not(.active):active {
    background-color: rgba(125, 183, 255, 0.2);
}

.indicacoes-abas .nav-link:focus-visible {
    box-shadow: 0 0 0 0.25rem rgba(var(--ses-primary-rgb), 0.35);
}

html.dark .indicacoes-abas .nav-link:focus-visible {
    box-shadow: 0 0 0 0.25rem rgba(125, 183, 255, 0.5);
}
</style>
