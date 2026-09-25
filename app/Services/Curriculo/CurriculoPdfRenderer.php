<?php

namespace App\Services\Curriculo;

class CurriculoPdfRenderer
{
    public function render(array $curriculo): string
    {
        $html = view('pdf.curriculo-candidato', ['curriculo' => $curriculo])->render();

        return $this->htmlParaPdfMinimo($html);
    }

    private function htmlParaPdfMinimo(string $html): string
    {
        $texto = html_entity_decode(strip_tags(str_replace(['</p>', '</h1>', '</h2>', '</li>', '<br>', '<br/>', '<br />'], "\n", $html)), ENT_QUOTES, 'UTF-8');
        $linhas = preg_split('/\R+/', $texto) ?: [];
        $linhas = array_values(array_filter(array_map(fn ($linha) => trim(preg_replace('/\s+/', ' ', $linha)), $linhas)));

        $objetos = [];
        $paginas = [];
        $linhaAtual = 0;
        $paginaLinhas = [];
        foreach ($linhas as $linha) {
            foreach ($this->quebrarLinha($linha, 92) as $parte) {
                $paginaLinhas[] = $parte;
                $linhaAtual++;
                if ($linhaAtual >= 46) {
                    $paginas[] = $paginaLinhas;
                    $paginaLinhas = [];
                    $linhaAtual = 0;
                }
            }
        }
        if ($paginaLinhas) {
            $paginas[] = $paginaLinhas;
        }
        if (! $paginas) {
            $paginas[] = ['Currículo'];
        }

        $fontId = 3 + count($paginas) * 2;
        foreach ($paginas as $indice => $linhasPagina) {
            $conteudo = "BT\n/F1 11 Tf\n50 790 Td\n14 TL\n";
            foreach ($linhasPagina as $linha) {
                $conteudo .= '(' . $this->escaparPdf($linha) . ") Tj\nT*\n";
            }
            $conteudo .= 'ET';

            $contentId = 4 + $indice * 2;
            $pageId = 5 + $indice * 2;
            $objetos[$contentId] = "<< /Length " . strlen($conteudo) . " >>\nstream\n{$conteudo}\nendstream";
            $objetos[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$fontId} 0 R >> >> /Contents {$contentId} 0 R >>";
        }

        $kids = implode(' ', array_map(fn ($i) => (5 + $i * 2) . ' 0 R', array_keys($paginas)));
        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objetos[2] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($paginas) . ' >>';
        $objetos[$fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        ksort($objetos);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objetos as $id => $objeto) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$objeto}\nendobj\n";
        }

        $xref = strlen($pdf);
        $max = max(array_keys($objetos));
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
        $texto = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $texto) ?: $texto;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
    }
}
