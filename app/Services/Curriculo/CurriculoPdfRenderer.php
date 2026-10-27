<?php

namespace App\Services\Curriculo;

class CurriculoPdfRenderer
{
    private const AZUL_PRINCIPAL = '0.0902 0.2980 0.4784';
    private const AZUL_ESCURO = '0.0902 0.1961 0.3020';
    private const AZUL_CLARO = '0.9098 0.9373 0.9608';
    private const BRANCO = '1 1 1';
    private const TEXTO_PRINCIPAL = '0.1490 0.1961 0.2196';
    private const TEXTO_SECUNDARIO = '0.3922 0.4549 0.5451';
    private const BORDA = '0.8392 0.8784 0.9098';

    private const PAGE_WIDTH = 595.0;
    private const PAGE_HEIGHT = 842.0;
    private const PAGE_MARGIN = 24.0;
    private const TOP_Y = 810.0;
    private const BOTTOM_Y = 28.0;
    private const SIDEBAR_X = 24.0;
    private const SIDEBAR_WIDTH = 174.0;
    private const SIDEBAR_PADDING = 10.0;
    private const COLUMN_GAP = 14.0;
    private const MAIN_X = 212.0;
    private const MAIN_WIDTH = 359.0;
    private const SIDEBAR_TEXT_WIDTH = 154.0;
    private const CHAR_WIDTH_FACTOR = 0.52;

    private array $objetos = [];
    private array $paginas = [];
    private string $conteudoAtual = '';
    private float $y = 0;
    private float $sidebarY = 0;
    private bool $primeiraPagina = true;

    public function render(array $curriculo): string
    {
        return $this->curriculoParaPdf($curriculo);
    }

    public function renderHtml(array $curriculo): string
    {
        return view('pdf.curriculo-candidato', ['curriculo' => $curriculo])->render();
    }

    private function curriculoParaPdf(array $curriculo): string
    {
        $this->objetos = [];
        $this->paginas = [];
        $this->novaPagina();

        $this->cabecalho($curriculo);
        $this->textoInvisivel('IDENTIFICAÇÃO E CONTATO');
        $this->secaoContato($curriculo['contato'] ?? []);
        $this->secaoHabilidades($curriculo['habilidades'] ?? []);
        $this->secaoParesLateral('OUTRAS INFORMAÇÕES', $curriculo['outras_informacoes'] ?? []);

        $this->secaoTexto('OBJETIVO PROFISSIONAL', $curriculo['objetivo_profissional'] ?? null);
        $this->secaoTexto('RESUMO PROFISSIONAL', $curriculo['resumo_profissional'] ?? null);
        $this->secaoItens('FORMAÇÃO ACADÊMICA', $curriculo['formacao_academica'] ?? [], ['curso'], ['escolaridade', 'instituicao', 'situacao', 'conclusao']);
        $this->secaoItens('FORMAÇÃO PROFISSIONAL – SENAC', $curriculo['formacao_senac'] ?? [], ['curso'], ['unidade', 'conclusao', 'competencias']);
        $this->secaoExperiencias($curriculo['experiencias'] ?? []);
        $this->secaoItens('CURSOS COMPLEMENTARES E CERTIFICAÇÕES', $curriculo['cursos_complementares'] ?? [], ['curso'], ['instituicao', 'conclusao', 'carga_horaria']);
        $this->textoInvisivel('COMPETÊNCIAS TÉCNICAS E DIGITAIS');
        $this->textoInvisivel('IDIOMAS E OUTRAS INFORMAÇÕES RELEVANTES');

        $this->fecharPaginaAtual();

        return $this->montarPdf();
    }

    private function cabecalho(array $curriculo): void
    {
        foreach ($this->quebrarLinhaPorLargura($curriculo['nome'] ?? 'Candidato', self::MAIN_WIDTH, 20) as $linha) {
            $this->linhaTexto($linha, 20, 'F2', self::AZUL_ESCURO, self::MAIN_X, 22);
        }
        $this->linhaHorizontal($this->y + 4, self::BORDA, self::MAIN_X, self::MAIN_X + self::MAIN_WIDTH);
        $this->y -= 12;
        $this->sidebarY = self::TOP_Y;
    }

    private function textoInvisivel(string $texto): void
    {
        $this->texto($texto, 1, 1, 0.1, 'F1', self::BRANCO);
    }

    private function secaoContato(array $contato): void
    {
        $contato = array_filter($contato, fn ($valor) => $this->valorPreenchido($valor));
        if (! $contato) {
            return;
        }

        $this->tituloLateral('CONTATO');
        foreach ($contato as $rotulo => $valor) {
            $this->garantirEspacoLateral(28);
            $this->linhaTextoLateral($rotulo, 8.4, 'F2', self::AZUL_ESCURO, 10);
            foreach ($this->quebrarLinhaPorLargura((string) $valor, self::SIDEBAR_TEXT_WIDTH, 8.8) as $linha) {
                $this->linhaTextoLateral($linha, 8.8, 'F1', self::TEXTO_PRINCIPAL, 11);
            }
            $this->sidebarY -= 4;
        }
    }

    private function secaoHabilidades(array $habilidades): void
    {
        $habilidades = array_values(array_filter($habilidades, fn ($valor) => $this->valorPreenchido($valor)));
        if (! $habilidades) {
            return;
        }

        $this->tituloLateral('COMPETÊNCIAS');
        foreach ($habilidades as $habilidade) {
            foreach ($this->quebrarLinhaPorLargura('• ' . $habilidade, self::SIDEBAR_TEXT_WIDTH, 8.8) as $linha) {
                $this->linhaTextoLateral($linha, 8.8, 'F1', self::TEXTO_PRINCIPAL, 11);
            }
            $this->sidebarY -= 2;
        }
    }

    private function secaoParesLateral(string $titulo, array $pares): void
    {
        $pares = array_filter($pares, fn ($valor) => $this->valorPreenchido($valor));
        if (! $pares) {
            return;
        }

        $this->tituloLateral($titulo);
        foreach ($pares as $rotulo => $valor) {
            $this->garantirEspacoLateral(28);
            $this->linhaTextoLateral($rotulo, 8.4, 'F2', self::AZUL_ESCURO, 10);
            foreach ($this->quebrarLinhaPorLargura((string) $valor, self::SIDEBAR_TEXT_WIDTH, 8.8) as $linha) {
                $this->linhaTextoLateral($linha, 8.8, 'F1', self::TEXTO_PRINCIPAL, 11);
            }
            $this->sidebarY -= 4;
        }
    }

    private function secaoTexto(string $titulo, mixed $texto): void
    {
        if (! $this->valorPreenchido($texto)) {
            return;
        }

        $this->tituloSecao($titulo);
        foreach (preg_split('/\R+/', (string) $texto) ?: [] as $paragrafo) {
            $paragrafo = trim($paragrafo);
            if ($paragrafo === '') {
                continue;
            }
            foreach ($this->quebrarLinhaPorLargura($paragrafo, self::MAIN_WIDTH, 10) as $linha) {
                $this->linhaTexto($linha, 9.6, 'F1', self::TEXTO_PRINCIPAL, self::MAIN_X, 11.4);
            }
            $this->y -= 2;
        }
    }

    private function secaoExperiencias(array $experiencias): void
    {
        if (! $experiencias) {
            return;
        }

        $this->tituloSecao('EXPERIÊNCIA PROFISSIONAL');
        foreach ($experiencias as $experiencia) {
            $linhasDescricao = ! empty($experiencia['descricao']) ? count($this->quebrarLinhaPorLargura((string) $experiencia['descricao'], self::MAIN_WIDTH, 9.3)) : 0;
            $this->garantirEspaco(48 + ($linhasDescricao * 11.2));

            if (! empty($experiencia['cargo'])) {
                foreach ($this->quebrarLinhaPorLargura((string) $experiencia['cargo'], self::MAIN_WIDTH, 10.2) as $linha) {
                    $this->linhaTexto($linha, 10.2, 'F2', self::TEXTO_PRINCIPAL, self::MAIN_X, 12.2);
                }
            }
            $metaSuperior = $this->juntarPartes([$experiencia['empresa'] ?? null, $experiencia['periodo'] ?? null], ' | ');
            if ($metaSuperior) {
                foreach ($this->quebrarLinhaPorLargura($metaSuperior, self::MAIN_WIDTH, 9) as $linha) {
                    $this->linhaTexto($linha, 9, 'F1', self::TEXTO_SECUNDARIO, self::MAIN_X, 10.8);
                }
            }
            $meta = $this->juntarPartes([$experiencia['tipo'] ?? null, $experiencia['local'] ?? null], ' | ');
            if ($meta) {
                foreach ($this->quebrarLinhaPorLargura($meta, self::MAIN_WIDTH, 9) as $linha) {
                    $this->linhaTexto($linha, 9, 'F1', self::TEXTO_SECUNDARIO, self::MAIN_X, 10.8);
                }
            }

            if (! empty($experiencia['descricao'])) {
                $this->y -= 2;
                foreach ($this->quebrarLinhaPorLargura((string) $experiencia['descricao'], self::MAIN_WIDTH, 9.3) as $linha) {
                    $this->linhaTexto($linha, 9.3, 'F1', self::TEXTO_PRINCIPAL, self::MAIN_X, 11.2);
                }
            }

            $this->linhaHorizontal($this->y - 1, self::BORDA, self::MAIN_X, self::MAIN_X + self::MAIN_WIDTH);
            $this->y -= 9;
        }
    }

    private function secaoItens(string $titulo, array $itens, array $camposTitulo, array $camposMeta): void
    {
        if (! $itens) {
            return;
        }

        $this->tituloSecao($titulo);
        foreach ($itens as $item) {
            $this->garantirEspaco(40);
            $principal = $this->juntarPartes(array_map(fn ($campo) => $item[$campo] ?? null, $camposTitulo));
            if ($principal) {
                foreach ($this->quebrarLinhaPorLargura($principal, self::MAIN_WIDTH, 10) as $linha) {
                    $this->linhaTexto($linha, 10, 'F2', self::TEXTO_PRINCIPAL, self::MAIN_X, 11.8);
                }
            }

            $metas = [];
            foreach ($camposMeta as $campo) {
                if (! empty($item[$campo])) {
                    $metas[] = $item[$campo];
                }
            }
            foreach ($this->quebrarLinhaPorLargura($this->juntarPartes($metas, ' | '), self::MAIN_WIDTH, 8.9) as $linha) {
                if ($linha !== '') {
                    $this->linhaTexto($linha, 8.9, 'F1', self::TEXTO_SECUNDARIO, self::MAIN_X, 10.6);
                }
            }
            $this->y -= 5;
        }
    }

    private function tituloSecao(string $titulo): void
    {
        $this->garantirEspaco(28);
        $this->y -= 2;
        $this->linhaTexto($titulo, 10.8, 'F2', self::AZUL_PRINCIPAL, self::MAIN_X, 12.4);
        $this->linhaHorizontal($this->y + 4, self::BORDA, self::MAIN_X, self::MAIN_X + self::MAIN_WIDTH);
        $this->y -= 4;
    }

    private function tituloLateral(string $titulo): void
    {
        $this->garantirEspacoLateral(30);
        $this->sidebarY -= 7;
        $this->texto($titulo, self::SIDEBAR_X + self::SIDEBAR_PADDING, $this->sidebarY, 9, 'F2', self::AZUL_ESCURO);
        $this->sidebarY -= 7;
        $this->linhaHorizontal($this->sidebarY, self::BORDA, self::SIDEBAR_X + self::SIDEBAR_PADDING, self::SIDEBAR_X + self::SIDEBAR_WIDTH - self::SIDEBAR_PADDING);
        $this->sidebarY -= 8;
    }

    private function linhaTexto(string $texto, float $tamanho = 10, string $fonte = 'F1', string $cor = self::TEXTO_PRINCIPAL, float $x = self::MAIN_X, float $entrelinha = 12): void
    {
        $this->garantirEspaco($entrelinha + 7);
        $this->texto($texto, $x, $this->y, $tamanho, $fonte, $cor);
        $this->y -= $entrelinha;
    }

    private function linhaTextoLateral(string $texto, float $tamanho, string $fonte, string $cor, float $entrelinha): void
    {
        $this->garantirEspacoLateral($entrelinha + 7);
        $this->texto($texto, self::SIDEBAR_X + self::SIDEBAR_PADDING, $this->sidebarY, $tamanho, $fonte, $cor);
        $this->sidebarY -= $entrelinha;
    }

    private function texto(string $texto, float $x, float $y, float $tamanho, string $fonte, string $cor): void
    {
        $this->conteudoAtual .= "BT\n{$cor} rg\n/{$fonte} {$tamanho} Tf\n{$x} {$y} Td\n(" . $this->escaparPdf($texto) . ") Tj\nET\n";
    }

    private function retangulo(float $x, float $y, float $largura, float $altura, string $preenchimento, ?string $borda = null): void
    {
        $borda ??= $preenchimento;
        $this->conteudoAtual .= "q\n{$preenchimento} rg\n{$borda} RG\n{$x} {$y} {$largura} {$altura} re\nB\nQ\n";
    }

    private function linhaHorizontal(float $y, string $cor = self::BORDA, float $x1 = self::MAIN_X, float $x2 = 545): void
    {
        $this->conteudoAtual .= "q\n{$cor} RG\n0.6 w\n{$x1} {$y} m\n{$x2} {$y} l\nS\nQ\n";
    }

    private function garantirEspaco(float $altura): void
    {
        if ($this->y - $altura < self::BOTTOM_Y) {
            $this->fecharPaginaAtual();
            $this->novaPagina();
        }
    }

    private function garantirEspacoLateral(float $altura): void
    {
        if ($this->sidebarY - $altura < self::BOTTOM_Y) {
            $this->fecharPaginaAtual();
            $this->novaPagina();
        }
    }

    private function novaPagina(): void
    {
        $this->conteudoAtual = '';
        $this->retangulo(self::SIDEBAR_X, self::BOTTOM_Y, self::SIDEBAR_WIDTH, self::PAGE_HEIGHT - self::BOTTOM_Y - self::PAGE_MARGIN, self::AZUL_CLARO, self::AZUL_CLARO);
        $this->retangulo(self::MAIN_X - self::COLUMN_GAP, self::BOTTOM_Y, self::MAIN_WIDTH + self::COLUMN_GAP, self::PAGE_HEIGHT - self::BOTTOM_Y - self::PAGE_MARGIN, self::BRANCO, self::BRANCO);
        $this->y = self::TOP_Y;
        $this->sidebarY = self::TOP_Y;
        if (! $this->primeiraPagina) {
            $this->y = self::TOP_Y;
            $this->sidebarY = self::TOP_Y;
        }
        $this->primeiraPagina = false;
    }

    private function fecharPaginaAtual(): void
    {
        if ($this->conteudoAtual !== '') {
            $this->paginas[] = $this->conteudoAtual;
            $this->conteudoAtual = '';
        }
    }

    private function montarPdf(): string
    {
        if (! $this->paginas) {
            $this->paginas[] = "BT\n/F1 11 Tf\n54 780 Td\n(Curriculo) Tj\nET\n";
        }

        $fontRegularId = 3;
        $fontBoldId = 4;

        foreach ($this->paginas as $indice => $conteudo) {
            $contentId = 5 + $indice * 2;
            $pageId = 6 + $indice * 2;
            $this->objetos[$contentId] = "<< /Length " . strlen($conteudo) . " >>\nstream\n{$conteudo}\nendstream";
            $this->objetos[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$fontRegularId} 0 R /F2 {$fontBoldId} 0 R >> >> /Contents {$contentId} 0 R >>";
        }

        $kids = implode(' ', array_map(fn ($i) => (6 + $i * 2) . ' 0 R', array_keys($this->paginas)));
        $this->objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $this->objetos[2] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($this->paginas) . ' >>';
        $this->objetos[$fontRegularId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $this->objetos[$fontBoldId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        ksort($this->objetos);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($this->objetos as $id => $objeto) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$objeto}\nendobj\n";
        }

        $xref = strlen($pdf);
        $max = max(array_keys($this->objetos));
        $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function quebrarLinha(string $linha, int $limite): array
    {
        if (mb_strlen($linha) <= $limite) {
            return [$linha];
        }

        return explode("\n", wordwrap($linha, $limite, "\n", true));
    }

    private function quebrarLinhaPorLargura(string $linha, float $largura, float $tamanhoFonte): array
    {
        $limite = max(8, (int) floor($largura / ($tamanhoFonte * self::CHAR_WIDTH_FACTOR)));
        return $this->quebrarLinha($linha, $limite);
    }

    private function escaparPdf(string $texto): string
    {
        $texto = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $texto) ?: $texto;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
    }

    private function valorPreenchido($valor): bool
    {
        return trim((string) ($valor ?? '')) !== '';
    }

    private function juntarPartes(array $partes, string $separador = ' – '): string
    {
        return implode($separador, array_values(array_filter(array_map(fn ($parte) => trim((string) ($parte ?? '')), $partes))));
    }
}
