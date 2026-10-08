<template>
    <div>
        <topbar titulo="Candidatos Contratados" subtitulo="Acompanhe as contratações registradas por empresas e pela administração" />

        <div class="container-fluid p-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <h2 class="h6 fw-bold text-primary mb-0">Histórico de Contratações</h2>
                        <div class="d-flex align-items-stretch flex-wrap gap-2 w-100">
                            <div class="flex-grow-1" style="min-width: 190px;">
                                <div class="input-group position-relative">
                                    <input v-model="filtros.empresa" class="form-control" type="search" placeholder="Pesquisar empresa" aria-label="Pesquisar empresa" autocomplete="off" @focus="abrirSugestoes('empresa')" @input="abrirSugestoes('empresa')" @blur="fecharSugestoes">
                                    <div v-if="filtroAberto === 'empresa' && sugestoes.empresa.length" class="list-group position-absolute w-100 shadow" style="z-index: 1060; top: 100%;">
                                        <button v-for="opcao in sugestoes.empresa" :key="opcao" type="button" class="list-group-item list-group-item-action text-start" @mousedown.prevent="selecionarSugestao('empresa', opcao)">{{ opcao }}</button>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-grow-1" style="min-width: 190px;">
                                <div class="input-group position-relative">
                                    <input v-model="filtros.nome" class="form-control" type="search" placeholder="Pesquisar candidato" aria-label="Pesquisar candidato" autocomplete="off" @focus="abrirSugestoes('nome')" @input="abrirSugestoes('nome')" @blur="fecharSugestoes">
                                    <div v-if="filtroAberto === 'nome' && sugestoes.nome.length" class="list-group position-absolute w-100 shadow" style="z-index: 1060; top: 100%;">
                                        <button v-for="opcao in sugestoes.nome" :key="opcao" type="button" class="list-group-item list-group-item-action text-start" @mousedown.prevent="selecionarSugestao('nome', opcao)">{{ opcao }}</button>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-grow-1" style="min-width: 190px;">
                                <div class="input-group position-relative">
                                    <input v-model="filtros.cpf" class="form-control" type="search" inputmode="numeric" placeholder="Pesquisar CPF" aria-label="Pesquisar CPF" autocomplete="off" @focus="abrirSugestoes('cpf')" @input="abrirSugestoes('cpf')" @blur="fecharSugestoes">
                                    <div v-if="filtroAberto === 'cpf' && sugestoes.cpf.length" class="list-group position-absolute w-100 shadow" style="z-index: 1060; top: 100%;">
                                        <button v-for="opcao in sugestoes.cpf" :key="opcao" type="button" class="list-group-item list-group-item-action text-start" @mousedown.prevent="selecionarSugestao('cpf', opcao)">{{ formatarCpf(opcao) }}</button>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-grow-1" style="min-width: 190px;">
                                <div class="input-group position-relative">
                                    <input v-model="filtros.curso" class="form-control" type="search" placeholder="Pesquisar curso" aria-label="Pesquisar curso" autocomplete="off" @focus="abrirSugestoes('curso')" @input="abrirSugestoes('curso')" @blur="fecharSugestoes">
                                    <div v-if="filtroAberto === 'curso' && sugestoes.curso.length" class="list-group position-absolute w-100 shadow" style="z-index: 1060; top: 100%;">
                                        <button v-for="opcao in sugestoes.curso" :key="opcao" type="button" class="list-group-item list-group-item-action text-start" @mousedown.prevent="selecionarSugestao('curso', opcao)">{{ opcao }}</button>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <button class="btn btn-outline-secondary" type="button" :disabled="carregando || baixandoPdf || !paginacao.total" @click="baixarContratacoesPdf">
                                    <span v-if="baixandoPdf" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    <i v-else class="bi bi-file-earmark-pdf me-1"></i>
                                    {{ baixandoPdf ? 'Preparando PDF...' : 'Relatório de Busca' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="erro" class="alert alert-danger">{{ erro }}</div>
                    <loading v-else-if="carregando && !carregouUmaVez" mensagem="Carregando contratações..." />
                    <template v-else>
                        <base-pagination
                            class="mb-3"
                            :current-page="paginacao.current_page"
                            :last-page="paginacao.last_page"
                            :per-page="paginacao.per_page"
                            :total="paginacao.total"
                            :from="paginacao.from"
                            :to="paginacao.to"
                            :loading="carregando"
                            item-label="contratações"
                            :show-single-page="true"
                            aria-label="Paginação de candidatos contratados"
                            @change="mudarPagina"
                        />

                        <p v-if="!contratacoes.length" class="text-secondary small mb-0">
                            Nenhuma contratação encontrada.
                        </p>
                        <div v-else class="table-responsive hired-candidates-table-wrapper">
                            <table class="table align-middle mb-0 hired-candidates-table">
                                <colgroup>
                                    <col class="hired-candidates-table__col-candidate">
                                    <col class="hired-candidates-table__col-cpf">
                                    <col class="hired-candidates-table__col-company">
                                    <col class="hired-candidates-table__col-phone">
                                    <col class="hired-candidates-table__col-date">
                                    <col class="hired-candidates-table__col-actions">
                                </colgroup>
                                <thead>
                                    <tr class="text-secondary small text-uppercase">
                                        <th class="text-start">Candidato</th>
                                        <th class="text-center">CPF</th>
                                        <th class="text-center">Empresa</th>
                                        <th class="text-center">Telefone</th>
                                        <th class="text-center">Contratado em</th>
                                        <th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="registro in contratacoes" :key="registro.id">
                                        <tr class="hired-candidates-table__row">
                                            <td class="hired-candidates-table__candidate text-start">
                                                <p class="fw-semibold mb-0 hired-candidates-table__candidate-name">{{ registro.candidato?.pessoa?.nome || '—' }}</p>
                                                <p class="text-secondary small mb-0 hired-candidates-table__candidate-email">{{ registro.candidato?.pessoa?.email || '—' }}</p>
                                            </td>
                                            <td class="text-center hired-candidates-table__nowrap">{{ formatarCpf(registro.candidato?.cpf) }}</td>
                                            <td class="text-center hired-candidates-table__company">{{ registro.empresa?.razao_social || '—' }}</td>
                                            <td class="text-center hired-candidates-table__nowrap">{{ formatarTelefone(registro.candidato?.pessoa?.telefone) }}</td>
                                            <td class="text-center hired-candidates-table__nowrap">{{ formatarData(registro.contratado_em) }}</td>
                                            <td class="text-center hired-candidates-table__actions-cell">
                                                <div class="action-buttons">
                                                    <button class="btn btn-sm btn-outline-primary hired-candidates-action-button" type="button" @click="alternarDetalhes(registro.id)">
                                                        {{ registroExpandido === registro.id ? 'Ocultar Detalhes' : 'Ver Detalhes' }}
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger hired-candidates-action-button" type="button" @click="abrirModalCancelamento(registro)">
                                                        Cancelar contratação
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="registroExpandido === registro.id" :key="`detalhes-${registro.id}`">
                                            <td colspan="6" class="bg-light-subtle">
                                                <div class="p-3 hired-candidate-details">
                                                    <div class="row g-3">
                                                        <div class="col-12"><small class="text-secondary d-block">Sobre mim</small><span>{{ registro.candidato?.informacoes_profissionais?.sobre_mim || 'Não informado' }}</span></div>
                                                        <div class="col-md-6 col-lg-4"><small class="text-secondary d-block">CPF</small><span>{{ formatarCpf(registro.candidato?.cpf) }}</span></div>
                                                        <div class="col-md-6 col-lg-4"><small class="text-secondary d-block">Cargo de interesse</small><span>{{ registro.candidato?.informacoes_profissionais?.cargo_de_interesse || 'Não informado' }}</span></div>
                                                        <div class="col-md-6 col-lg-4"><small class="text-secondary d-block">Disponibilidade de horário</small><span>{{ formatarLista(registro.candidato?.preferencias_de_trabalho?.disponibilidade_de_horario) }}</span></div>
                                                        <div class="col-md-6 col-lg-4"><small class="text-secondary d-block">Empresa contratante</small><span>{{ registro.empresa?.razao_social || 'Não informado' }}</span></div>
                                                        <div class="col-md-6 col-lg-4"><small class="text-secondary d-block">Contratado em</small><span>{{ formatarData(registro.contratado_em) }}</span></div>
                                                        <div class="col-md-6 col-lg-4"><small class="text-secondary d-block">Situação da contratação</small><span>Contratado</span></div>
                                                        <div class="col-md-6 col-lg-4 d-flex align-items-end justify-content-lg-start">
                                                            <button
                                                                class="btn btn-sm btn-outline-primary"
                                                                type="button"
                                                                :disabled="curriculoGerando === registro.candidato?.matricula"
                                                                @click="baixarCurriculo(registro)"
                                                            >
                                                                <span v-if="curriculoGerando === registro.candidato?.matricula" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                                                <i v-else class="bi bi-file-earmark-arrow-down me-1"></i>
                                                                {{ curriculoGerando === registro.candidato?.matricula ? 'Gerando...' : 'Baixar currículo' }}
                                                            </button>
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
                            :current-page="paginacao.current_page"
                            :last-page="paginacao.last_page"
                            :per-page="paginacao.per_page"
                            :total="paginacao.total"
                            :from="paginacao.from"
                            :to="paginacao.to"
                            :loading="carregando"
                            item-label="contratações"
                            :show-single-page="true"
                            aria-label="Paginação inferior de candidatos contratados"
                            @change="mudarPagina"
                        />
                    </template>
                </div>
            </div>
        </div>

        <div v-if="modalCancelamentoAberto" class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="cancelar-contratacao-titulo">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="cancelar-contratacao-titulo" class="modal-title h5">Cancelar contratação</h3>
                        <button type="button" class="btn-close" aria-label="Fechar" :disabled="cancelandoContratacao" @click="fecharModalCancelamento"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">
                            Deseja cancelar a contratação de <strong>{{ contratacaoParaCancelar?.candidato?.pessoa?.nome || 'candidato' }}</strong>
                            pela empresa <strong>{{ contratacaoParaCancelar?.empresa?.razao_social || 'empresa' }}</strong>?
                        </p>
                        <p class="text-secondary small">Essa ação removerá o candidato da lista de contratados e registrará o cancelamento no histórico.</p>
                        <div class="mb-3">
                            <label for="motivo-cancelamento" class="form-label">Motivo do cancelamento *</label>
                            <select id="motivo-cancelamento" v-model="formCancelamento.motivo_cancelamento" class="form-select" :class="{ 'is-invalid': erroCampoCancelamento }" :disabled="cancelandoContratacao" required>
                                <option value="">Selecione</option>
                                <option v-for="motivo in motivosCancelamento" :key="motivo" :value="motivo">{{ motivo }}</option>
                            </select>
                            <div v-if="erroCampoCancelamento" class="invalid-feedback d-block">{{ erroCampoCancelamento }}</div>
                        </div>
                        <div class="mb-0">
                            <label for="observacao-cancelamento" class="form-label">Observação {{ formCancelamento.motivo_cancelamento === 'Outro motivo' ? '*' : '(opcional)' }}</label>
                            <textarea id="observacao-cancelamento" v-model.trim="formCancelamento.observacao_cancelamento" class="form-control" rows="3" maxlength="1000" :disabled="cancelandoContratacao"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" :disabled="cancelandoContratacao" @click="confirmarCancelamento">
                            <span v-if="cancelandoContratacao" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                            {{ cancelandoContratacao ? 'Cancelando...' : 'Confirmar cancelamento' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="modalCancelamentoAberto" class="modal-backdrop fade show"></div>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import topbar from '../../../components/common/header.vue';
import loading from '../../../components/common/loading.vue';
import BasePagination from '../../../components/common/BasePagination.vue';
import adminService from '../../../services/adminServices';
import { useToast } from '../../../composables/useToast';

const toast = useToast();

const filtros = reactive({ empresa: '', nome: '', cpf: '', curso: '' });
const contratacoes = ref([]);
const carregando = ref(false);
const carregouUmaVez = ref(false);
const baixandoPdf = ref(false);
const curriculoGerando = ref(null);
const erro = ref('');
const registroExpandido = ref(null);
const paginacao = reactive({ current_page: 1, last_page: 1, per_page: 10, total: 0, from: null, to: null });
const filtroAberto = ref('');
const sugestoes = reactive({ empresa: [], nome: [], cpf: [], curso: [] });
const requisicoesSugestoes = { empresa: 0, nome: 0, cpf: 0, curso: 0 };
const temporizadoresSugestoes = {};
const motivosCancelamento = [
    'Contratação registrada por engano',
    'Empresa selecionada incorretamente',
    'Candidato selecionado incorretamente',
    'Outro motivo',
];
const modalCancelamentoAberto = ref(false);
const cancelandoContratacao = ref(false);
const contratacaoParaCancelar = ref(null);
const erroCampoCancelamento = ref('');
const formCancelamento = reactive({ motivo_cancelamento: 'Contratação registrada por engano', observacao_cancelamento: '' });

onMounted(async () => {
    await carregarContratacoes();
    carregouUmaVez.value = true;
});

let temporizadorFiltro = null;
let requisicaoContratacoes = 0;
watch(filtros, () => {
    clearTimeout(temporizadorFiltro);
    temporizadorFiltro = setTimeout(() => carregarContratacoes(1), 300);
});

async function carregarContratacoes(pagina = paginacao.current_page) {
    const requisicao = ++requisicaoContratacoes;
    carregando.value = true;
    erro.value = '';
    try {
        const { data } = await adminService.listarContratacoes({
            page: pagina,
            per_page: 10,
            ...Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor.trim())),
        });
        if (requisicao !== requisicaoContratacoes) return;

        contratacoes.value = data.data || [];
        paginacao.current_page = data.current_page || 1;
        paginacao.last_page = data.last_page || 1;
        paginacao.per_page = Number(data.per_page || 10);
        paginacao.total = Number(data.total || 0);
        paginacao.from = data.from || null;
        paginacao.to = data.to || null;
    } catch (errorResposta) {
        if (requisicao === requisicaoContratacoes) {
            erro.value = 'Não foi possível carregar as contratações. Tente novamente.';
        }
    } finally {
        if (requisicao === requisicaoContratacoes) carregando.value = false;
    }
}

function mudarPagina(pagina) {
    if (pagina < 1 || pagina > paginacao.last_page || pagina === paginacao.current_page || carregando.value) return;
    registroExpandido.value = null;
    carregarContratacoes(pagina);
}

function alternarDetalhes(id) {
    registroExpandido.value = registroExpandido.value === id ? null : id;
}

function abrirModalCancelamento(registro) {
    contratacaoParaCancelar.value = registro;
    formCancelamento.motivo_cancelamento = 'Contratação registrada por engano';
    formCancelamento.observacao_cancelamento = '';
    erroCampoCancelamento.value = '';
    modalCancelamentoAberto.value = true;
}

function fecharModalCancelamento() {
    if (cancelandoContratacao.value) return;
    modalCancelamentoAberto.value = false;
    contratacaoParaCancelar.value = null;
    erroCampoCancelamento.value = '';
    formCancelamento.motivo_cancelamento = 'Contratação registrada por engano';
    formCancelamento.observacao_cancelamento = '';
}

async function confirmarCancelamento() {
    if (cancelandoContratacao.value) return;
    erroCampoCancelamento.value = '';
    if (!formCancelamento.motivo_cancelamento) {
        erroCampoCancelamento.value = 'Informe o motivo do cancelamento.';
        return;
    }
    if (formCancelamento.motivo_cancelamento === 'Outro motivo' && !formCancelamento.observacao_cancelamento.trim()) {
        erroCampoCancelamento.value = 'Informe uma justificativa para outro motivo.';
        return;
    }
    if (!contratacaoParaCancelar.value?.id) return;

    cancelandoContratacao.value = true;
    try {
        const { data } = await adminService.cancelarContratacao(contratacaoParaCancelar.value.id, { ...formCancelamento });
        toast.success(data?.message || 'Contratação cancelada com sucesso.');
        modalCancelamentoAberto.value = false;
        contratacaoParaCancelar.value = null;
        erroCampoCancelamento.value = '';
        formCancelamento.motivo_cancelamento = 'Contratação registrada por engano';
        formCancelamento.observacao_cancelamento = '';
        const pagina = contratacoes.value.length === 1 && paginacao.current_page > 1 ? paginacao.current_page - 1 : paginacao.current_page;
        await carregarContratacoes(pagina);
    } catch (errorResposta) {
        const mensagem = errorResposta.response?.data?.message || '';
        if (errorResposta.response?.status === 409 && mensagem.includes('já foi cancelada')) {
            toast.info('Esta contratação já estava cancelada.');
            modalCancelamentoAberto.value = false;
            contratacaoParaCancelar.value = null;
            await carregarContratacoes(paginacao.current_page);
            return;
        }

        toast.error(mensagem.includes('SQLSTATE') ? 'Não foi possível cancelar a contratação. Tente novamente.' : (mensagem || 'Não foi possível cancelar a contratação. Tente novamente.'));
    } finally {
        cancelandoContratacao.value = false;
    }
}

async function baixarCurriculo(registro) {
    const candidato = registro?.candidato;
    if (!candidato?.matricula) return;

    curriculoGerando.value = candidato.matricula;
    erro.value = '';

    try {
        const response = await adminService.baixarCurriculoAluno(candidato.matricula);
        const blob = new Blob([response.data], { type: 'application/pdf' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = nomeArquivoCurriculo(response, candidato);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (errorResposta) {
        erro.value = 'Não foi possível gerar o currículo deste candidato.';
    } finally {
        curriculoGerando.value = null;
    }
}

function abrirSugestoes(campo) {
    filtroAberto.value = campo;
    const termo = filtros[campo].trim();
    const requisicao = ++requisicoesSugestoes[campo];
    clearTimeout(temporizadoresSugestoes[campo]);

    temporizadoresSugestoes[campo] = setTimeout(async () => {
        try {
            const parametros = {
                page: 1,
                per_page: 10,
                ...(termo ? { [campo]: termo } : {}),
            };
            const { data } = await adminService.listarContratacoes(parametros);
            if (requisicao !== requisicoesSugestoes[campo]) return;

            const registros = data.data || [];
            const valores = registros.map((registro) => {
                if (campo === 'empresa') return registro.empresa?.razao_social;
                if (campo === 'nome') return registro.candidato?.pessoa?.nome;
                if (campo === 'cpf') return registro.candidato?.cpf;
                return (registro.candidato?.dados_academicos || []).map((item) => item.curso);
            }).flat();

            sugestoes[campo] = [...new Set(valores.filter(Boolean))].slice(0, 10);
        } catch (errorResposta) {
            if (requisicao === requisicoesSugestoes[campo]) sugestoes[campo] = [];
        }
    }, 200);
}

function selecionarSugestao(campo, valor) {
    filtros[campo] = valor;
    filtroAberto.value = '';
}

function fecharSugestoes() {
    setTimeout(() => { filtroAberto.value = ''; }, 150);
}

async function baixarContratacoesPdf() {
    const filtrosExportacao = Object.fromEntries(
        Object.entries(filtros).filter(([, valor]) => valor.trim())
    );
    const janelaImpressao = window.open('', '_blank');

    if (!janelaImpressao) {
        erro.value = 'Permita a abertura da janela para preparar o PDF.';
        return;
    }

    baixandoPdf.value = true;
    janelaImpressao.document.write('<!doctype html><html><head><title>Preparando relatório</title></head><body><p style="font:16px Arial;padding:24px">Preparando o relatório de contratações...</p></body></html>');
    janelaImpressao.document.close();

    try {
        const respostaInicial = await adminService.listarContratacoes({ ...filtrosExportacao, page: 1, per_page: 10 });
        const paginacaoExportacao = respostaInicial.data;
        const registros = [...(paginacaoExportacao.data || [])];

        for (let inicio = 2; inicio <= paginacaoExportacao.last_page; inicio += 10) {
            const paginas = Array.from({ length: Math.min(10, paginacaoExportacao.last_page - inicio + 1) }, (_, indice) => inicio + indice);
            const respostas = await Promise.all(paginas.map((pagina) =>
                adminService.listarContratacoes({ ...filtrosExportacao, page: pagina, per_page: 10 })
            ));
            respostas.forEach(({ data }) => registros.push(...(data.data || [])));
        }

        const resumoFiltros = [
            filtrosExportacao.empresa ? `Empresa: ${filtrosExportacao.empresa}` : null,
            filtrosExportacao.nome ? `Candidato: ${filtrosExportacao.nome}` : null,
            filtrosExportacao.cpf ? `CPF: ${formatarCpf(filtrosExportacao.cpf)}` : null,
            filtrosExportacao.curso ? `Curso: ${filtrosExportacao.curso}` : null,
        ].filter(Boolean).join(' | ') || 'Sem filtros adicionais';
        const linhas = registros.map((registro) => {
            const candidato = registro.candidato || {};
            const pessoa = candidato.pessoa || {};
            const endereco = [
                pessoa.endereco_logradouro,
                pessoa.endereco_numero,
                pessoa.endereco_complemento,
                pessoa.endereco_bairro,
                [pessoa.endereco_cidade, pessoa.endereco_uf].filter(Boolean).join('/'),
            ].filter(Boolean).join(', ') || 'Não informado';
            const cursos = (candidato.dados_academicos || []).map((item) => item.curso).filter(Boolean).join(', ') || 'Não informado';

            return `<tr>
                <td>${escaparHtml(pessoa.nome || '—')}</td>
                <td>${escaparHtml(formatarCpf(candidato.cpf))}</td>
                <td>${escaparHtml(pessoa.telefone || 'Não informado')}</td>
                <td>${escaparHtml(pessoa.email || 'Não informado')}</td>
                <td>${escaparHtml(registro.empresa?.razao_social || '—')}</td>
                <td>${escaparHtml(cursos)}</td>
                <td>${escaparHtml(pessoa.endereco_cep || 'Não informado')}</td>
                <td>${escaparHtml(endereco)}</td>
                <td>${escaparHtml(formatarData(registro.contratado_em))}</td>
                <td>${escaparHtml(registro.registrado_por?.nome || (registro.origem === 'empresa' ? 'Empresa' : 'Administração'))}</td>
            </tr>`;
        }).join('');
        const titulo = `Candidatos contratados - ${new Date().toLocaleDateString('pt-BR')}`;

        janelaImpressao.document.open();
        janelaImpressao.document.write(`<!doctype html>
            <html lang="pt-BR">
                <head>
                    <meta charset="utf-8">
                    <title>${escaparHtml(titulo)}</title>
                    <style>
                        @page { size: landscape; margin: 12mm; }
                        body { color: #212529; font: 10px Arial, sans-serif; }
                        h1 { color: #163f70; font-size: 20px; margin: 0 0 6px; }
                        p { color: #5c6670; margin: 0 0 16px; }
                        table { border-collapse: collapse; width: 100%; }
                        th, td { border: 1px solid #cbd2d9; padding: 6px; text-align: left; vertical-align: top; }
                        th { background: #edf2f7; color: #163f70; }
                        tr { break-inside: avoid; }
                        footer { color: #6c757d; margin-top: 12px; }
                    </style>
                </head>
                <body>
                    <h1>Candidatos Contratados</h1>
                    <p>${escaparHtml(resumoFiltros)} · ${registros.length} pessoa(s) contratada(s) · Gerado em ${new Date().toLocaleString('pt-BR')}</p>
                    <table>
                        <thead><tr><th>Candidato</th><th>CPF</th><th>Telefone</th><th>E-mail</th><th>Empresa</th><th>Curso</th><th>CEP</th><th>Endereço</th><th>Contratado em</th><th>Registrado por</th></tr></thead>
                        <tbody>${linhas || '<tr><td colspan="10">Nenhuma contratação encontrada.</td></tr>'}</tbody>
                    </table>
                    <footer>Senac DF · Candidatos Contratados</footer>
                    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));<\/script>
                </body>
            </html>`);
        janelaImpressao.document.close();
    } catch (errorResposta) {
        janelaImpressao.close();
        erro.value = 'Não foi possível preparar o relatório com os filtros selecionados.';
    } finally {
        baixandoPdf.value = false;
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

function formatarCpf(valor) {
    const digitos = String(valor || '').replace(/\D/g, '').padStart(11, '0');
    return digitos.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
}

function formatarCnpj(valor) {
    const digitos = String(valor || '').replace(/\D/g, '').padStart(14, '0');
    return digitos.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
}

function formatarTelefone(valor) {
    const digitos = String(valor || '').replace(/\D/g, '');
    if (digitos.length === 11) return digitos.replace(/(\d{2})(\d)(\d{4})(\d{4})/, '($1) $2 $3-$4');
    if (digitos.length === 10) return digitos.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    return valor || 'Não informado';
}

function formatarLista(valor) {
    if (Array.isArray(valor)) return valor.filter(Boolean).join(', ') || 'Não informado';
    if (valor && typeof valor === 'object') return Object.values(valor).flat().filter(Boolean).join(', ') || 'Não informado';
    return valor || 'Não informado';
}

function formatarData(valor) {
    if (!valor) return '—';
    return new Date(`${String(valor).slice(0, 10)}T00:00:00`).toLocaleDateString('pt-BR');
}

function nomeArquivoCurriculo(response, candidato) {
    const disposicao = response.headers?.['content-disposition'] || '';
    const encontrado = disposicao.match(/filename="?([^";]+)"?/i);

    if (encontrado?.[1]) {
        return encontrado[1];
    }

    const nome = (candidato.pessoa?.nome || 'Candidato')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/gi, '_')
        .replace(/^_+|_+$/g, '');

    return `Curriculo_${nome || 'Candidato'}.pdf`;
}
</script>

<style scoped>
.hired-candidates-table-wrapper {
    overflow-x: auto;
}

.hired-candidates-table {
    min-width: 64rem;
    table-layout: fixed;
}

.hired-candidates-table__col-candidate {
    width: 24%;
}

.hired-candidates-table__col-cpf {
    width: 13%;
}

.hired-candidates-table__col-company {
    width: 21%;
}

.hired-candidates-table__col-phone {
    width: 14%;
}

.hired-candidates-table__col-date {
    width: 12%;
}

.hired-candidates-table__col-actions {
    width: 22rem;
}

.hired-candidates-table th,
.hired-candidates-table td {
    padding: 0.875rem 0.75rem;
    vertical-align: middle;
}

.hired-candidates-table th {
    font-weight: 700;
    letter-spacing: 0.025em;
    white-space: nowrap;
}

.hired-candidates-table__row {
    min-height: 4.5rem;
}

.hired-candidates-table__candidate,
.hired-candidates-table__company {
    overflow-wrap: anywhere;
    word-break: normal;
}

.hired-candidates-table__candidate-name,
.hired-candidates-table__candidate-email {
    line-height: 1.35;
}

.hired-candidates-table__nowrap {
    white-space: nowrap;
}

.hired-candidate-details small {
    margin-bottom: 0.125rem;
}

.hired-candidate-details span {
    overflow-wrap: anywhere;
}

.hired-candidates-table__actions-cell {
    min-width: 22rem;
}

.action-buttons {
    align-items: center;
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    gap: 8px;
    justify-content: center;
    width: 100%;
}

.action-buttons .btn {
    flex: 0 0 auto;
    min-width: 8.75rem;
}

.hired-candidates-action-button {
    align-items: center;
    display: inline-flex;
    justify-content: center;
    min-height: 2rem;
    padding-left: 0.75rem;
    padding-right: 0.75rem;
    white-space: nowrap;
}

@media (max-width: 1199.98px) {
    .hired-candidates-table {
        min-width: 60rem;
    }

    .hired-candidates-table th,
    .hired-candidates-table td {
        padding-left: 0.625rem;
        padding-right: 0.625rem;
    }
}

@media (max-width: 575.98px) {
    .hired-candidates-table-wrapper {
        margin-left: -0.25rem;
        margin-right: -0.25rem;
        padding-left: 0.25rem;
        padding-right: 0.25rem;
    }

    .hired-candidates-table {
        min-width: 56rem;
    }

    .action-buttons {
        flex-wrap: nowrap;
    }
}
</style>
