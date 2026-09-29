<?php

namespace App\Services\Curriculo;

class CurriculoPdfRenderer
{
    private const AZUL_SISTEMA = '0 0.2706 0.5294';
    private const TEXTO_PRINCIPAL = '0.1216 0.1608 0.2157';
    private const TEXTO_SECUNDARIO = '0.4196 0.4471 0.5020';
    private const BORDA = '0.8588 0.8941 0.9412';

    private array $objetos = [];
    private array $paginas = [];
    private string $conteudoAtual = '';
    private float $y = 0;

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

        $this->texto($curriculo['nome'] ?? 'Candidato', 54, 780, 24, 'F2', self::AZUL_SISTEMA);
        if (! empty($curriculo['area_atuacao'])) {
            $this->texto($curriculo['area_atuacao'], 54, 758, 13, 'F1', self::TEXTO_SECUNDARIO);
        }

        $this->y = 732;
        $this->blocoContato($curriculo['contato'] ?? []);
        $this->secaoPares('INFORMAÇÕES PROFISSIONAIS', $curriculo['objetivo'] ?? []);
        $this->secaoItens('FORMAÇÃO ACADÊMICA', $curriculo['formacao'] ?? [], ['curso'], ['instituicao', 'tipo', 'segmento', 'unidade', 'conclusao']);
        $this->secaoHabilidades($curriculo['habilidades'] ?? []);
        $this->secaoExperiencias($curriculo['experiencias'] ?? []);
        $this->secaoItens('CURSOS COMPLEMENTARES', $curriculo['cursos_complementares'] ?? [], ['curso'], ['instituicao', 'unidade', 'carga_horaria', 'conclusao']);
        $this->secaoPares('LINKS PROFISSIONAIS', $curriculo['links'] ?? []);

        $this->fecharPaginaAtual();

        return $this->montarPdf();
    }

    private function blocoContato(array $contato): void
    {
        if (! $contato) {
            return;
        }

        $partes = [];
        foreach ($contato as $rotulo => $valor) {
            if ($this->valorPreenchido($valor)) {
                $partes[] = $rotulo . ': ' . $valor;
            }
        }

        if (! $partes) {
            return;
        }

        $this->garantirEspaco(44);
        $this->retangulo(54, $this->y - 24, 487, 33, '0.9725 0.9804 0.9922', self::BORDA);
        foreach ($this->quebrarLinha(implode('   |   ', $partes), 86) as $linha) {
            $this->linhaTexto($linha, 10.5, 'F1', self::TEXTO_PRINCIPAL, 66, 13);
        }
        $this->y -= 10;
    }

    private function secaoPares(string $titulo, array $pares): void
    {
        $pares = array_filter($pares, fn ($valor) => $this->valorPreenchido($valor));
        if (! $pares) {
            return;
        }

        $this->tituloSecao($titulo);
        foreach ($pares as $rotulo => $valor) {
            $this->garantirEspaco(34);
            $this->linhaTexto($rotulo, 9.5, 'F2', self::TEXTO_SECUNDARIO, 54, 12);
            foreach ($this->quebrarLinha((string) $valor, 92) as $linha) {
                $this->linhaTexto($linha, 11, 'F1', self::TEXTO_PRINCIPAL, 54, 13);
            }
            $this->y -= 4;
        }
    }

    private function secaoHabilidades(array $habilidades): void
    {
        $habilidades = array_values(array_filter($habilidades, fn ($valor) => $this->valorPreenchido($valor)));
        if (! $habilidades) {
            return;
        }

        $this->tituloSecao('HABILIDADES TÉCNICAS');
        foreach ($this->quebrarLinha(implode('  •  ', $habilidades), 92) as $linha) {
            $this->linhaTexto($linha, 11, 'F1', self::TEXTO_PRINCIPAL, 54, 14);
        }
        $this->y -= 6;
    }

    private function secaoExperiencias(array $experiencias): void
    {
        if (! $experiencias) {
            return;
        }

        $this->tituloSecao('EXPERIÊNCIA PROFISSIONAL');
        foreach ($experiencias as $experiencia) {
            $linhasDescricao = ! empty($experiencia['descricao']) ? count($this->quebrarLinha((string) $experiencia['descricao'], 92)) : 0;
            $this->garantirEspaco(58 + ($linhasDescricao * 13));

            if (! empty($experiencia['cargo'])) {
                $this->linhaTexto(mb_strtoupper($experiencia['cargo'], 'UTF-8'), 11.5, 'F2', self::TEXTO_PRINCIPAL, 54, 14);
            }
            if (! empty($experiencia['empresa'])) {
                $this->linhaTexto($experiencia['empresa'], 11, 'F2', self::AZUL_SISTEMA, 54, 14);
            }

            $linhaPeriodo = $this->juntarPartes([$experiencia['periodo'] ?? null]);
            if ($linhaPeriodo) {
                $this->linhaTexto($linhaPeriodo, 10, 'F1', self::TEXTO_SECUNDARIO, 54, 12);
            }

            $meta = $this->juntarPartes([$experiencia['tipo'] ?? null, $experiencia['local'] ?? null], ' | ');
            if ($meta) {
                $this->linhaTexto($meta, 10, 'F1', self::TEXTO_SECUNDARIO, 54, 12);
            }

            if (! empty($experiencia['descricao'])) {
                $this->y -= 2;
                foreach ($this->quebrarLinha((string) $experiencia['descricao'], 92) as $linha) {
                    $this->linhaTexto($linha, 10.5, 'F1', self::TEXTO_PRINCIPAL, 54, 13);
                }
            }

            $this->linhaHorizontal($this->y - 3);
            $this->y -= 14;
        }
    }

    private function secaoItens(string $titulo, array $itens, array $camposTitulo, array $camposMeta): void
    {
        if (! $itens) {
            return;
        }

        $this->tituloSecao($titulo);
        foreach ($itens as $item) {
            $this->garantirEspaco(46);
            $principal = $this->juntarPartes(array_map(fn ($campo) => $item[$campo] ?? null, $camposTitulo));
            if ($principal) {
                $this->linhaTexto($principal, 11, 'F2', self::TEXTO_PRINCIPAL, 54, 14);
            }

            $metas = [];
            foreach ($camposMeta as $campo) {
                if (! empty($item[$campo])) {
                    $metas[] = $item[$campo];
                }
            }
            foreach ($this->quebrarLinha($this->juntarPartes($metas, ' | '), 92) as $linha) {
                if ($linha !== '') {
                    $this->linhaTexto($linha, 10, 'F1', self::TEXTO_SECUNDARIO, 54, 12);
                }
            }
            $this->y -= 7;
        }
    }

    private function tituloSecao(string $titulo): void
    {
        $this->garantirEspaco(35);
        $this->y -= 4;
        $this->linhaTexto($titulo, 12.5, 'F2', self::AZUL_SISTEMA, 54, 15);
        $this->linhaHorizontal($this->y + 5, self::BORDA);
        $this->y -= 6;
    }

    private function linhaTexto(string $texto, float $tamanho = 11, string $fonte = 'F1', string $cor = self::TEXTO_PRINCIPAL, float $x = 54, float $entrelinha = 14): void
    {
        $this->garantirEspaco($entrelinha + 8);
        $this->texto($texto, $x, $this->y, $tamanho, $fonte, $cor);
        $this->y -= $entrelinha;
    }

    private function texto(string $texto, float $x, float $y, float $tamanho, string $fonte, string $cor): void
    {
        $this->conteudoAtual .= "BT\n{$cor} rg\n/{$fonte} {$tamanho} Tf\n{$x} {$y} Td\n(" . $this->escaparPdf($texto) . ") Tj\nET\n";
    }

    private function retangulo(float $x, float $y, float $largura, float $altura, string $preenchimento, string $borda): void
    {
        $this->conteudoAtual .= "q\n{$preenchimento} rg\n{$borda} RG\n{$x} {$y} {$largura} {$altura} re\nB\nQ\n";
    }

    private function linhaHorizontal(float $y, string $cor = self::BORDA): void
    {
        $this->conteudoAtual .= "q\n{$cor} RG\n0.6 w\n54 {$y} m\n541 {$y} l\nS\nQ\n";
    }

    private function garantirEspaco(float $altura): void
    {
        if ($this->y - $altura < 52) {
            $this->fecharPaginaAtual();
            $this->novaPagina();
        }
    }

    private function novaPagina(): void
    {
        $this->conteudoAtual = '';
        $this->y = 780;
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

        return explode("\n", wordwrap($linha, $limite, "\n", false));
    }

    private function escaparPdf(string $texto): string
    {
        $texto = iconv('UTF-8', 'Windows-1252//IGNORE', $texto) ?: $texto;
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
