<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminEmpresaBuscaInputHelperTest extends TestCase
{
    public function test_input_do_autocomplete_aplica_mascara_progressiva_sem_limitar_nome_por_maxlength(): void
    {
        $arquivo = file_get_contents(base_path('resources/js/modules/admin/views/GestaoAlunosView.vue'));
        $inicioInputEmpresa = strpos($arquivo, 'id="empresa-contratante"');
        $inputEmpresa = substr($arquivo, $inicioInputEmpresa, 700);

        $this->assertStringContainsString('@input="onBuscaEmpresaContratanteInput"', $arquivo);
        $this->assertStringContainsString('mascararBuscaCnpj(valorDigitado)', $arquivo);
        $this->assertStringNotContainsString('maxlength="18"', $inputEmpresa);
        $this->assertStringNotContainsString('maxlength="14"', $inputEmpresa);
    }

    public function test_input_separa_valor_visual_valor_de_busca_e_selecao(): void
    {
        $arquivo = file_get_contents(base_path('resources/js/modules/admin/views/GestaoAlunosView.vue'));

        $this->assertStringContainsString('const empresaContratanteDisplay = ref', $arquivo);
        $this->assertStringContainsString('const empresaContratanteSearch = ref', $arquivo);
        $this->assertStringContainsString('const empresaContratanteSelecionada = ref', $arquivo);
        $this->assertStringContainsString('empresaContratanteDisplay.value = valorVisual', $arquivo);
        $this->assertStringContainsString('empresaContratanteSearch.value = valorBusca', $arquivo);
        $this->assertStringContainsString("empresaContratanteSelecionada.value = '';", $arquivo);
    }

    public function test_busca_do_autocomplete_envia_documento_normalizado_ao_backend(): void
    {
        $arquivo = file_get_contents(base_path('resources/js/modules/admin/views/GestaoAlunosView.vue'));

        $this->assertStringContainsString('const valorBusca = normalizarBuscaDocumentoOuTexto(valorVisual)', $arquivo);
        $this->assertStringContainsString('carregarEmpresasParaContratacao(valorBusca)', $arquivo);
        $this->assertStringContainsString('busca: termo', $arquivo);
    }

    public function test_limpar_empresa_remove_termo_selecao_e_resultados(): void
    {
        $arquivo = file_get_contents(base_path('resources/js/modules/admin/views/GestaoAlunosView.vue'));

        $this->assertStringContainsString("empresaContratanteSelecionada.value = '';", $arquivo);
        $this->assertStringContainsString("empresaContratanteDisplay.value = '';", $arquivo);
        $this->assertStringContainsString("empresaContratanteSearch.value = '';", $arquivo);
        $this->assertStringContainsString('empresasEncontradas.value = [];', $arquivo);
    }

    public function test_input_nao_usa_watch_para_alterar_o_proprio_valor_e_disparar_busca(): void
    {
        $arquivo = file_get_contents(base_path('resources/js/modules/admin/views/GestaoAlunosView.vue'));

        $this->assertStringNotContainsString('watch(empresaContratanteDisplay', $arquivo);
        $this->assertStringNotContainsString('watch(buscaEmpresaContratante', $arquivo);
        $this->assertStringContainsString(':value="empresaContratanteDisplay"', $arquivo);
    }
}
