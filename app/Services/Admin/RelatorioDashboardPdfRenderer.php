<?php

namespace App\Services\Admin;

use Illuminate\Support\Carbon;

class RelatorioDashboardPdfRenderer
{
    private const AZUL = '0 0.2706 0.5294';
    private const TEXTO = '0.1216 0.1608 0.2157';
    private const CINZA = '0.4196 0.4471 0.5020';
    private const BORDA = '0.8588 0.8941 0.9412';
    private const FUNDO = '0.9725 0.9804 0.9922';

    private array $objetos = [];
    private array $paginas = [];
    private string $conteudoAtual = '';
    private float $y = 0;

    public function render(array $dashboard, array $secoes, Carbon $geradoEm): string
    {
        $this->objetos = [];
        $this->paginas = [];
        $this->novaPagina();

        $this->cabecalho($geradoEm);
        $this->resumo($dashboard, $secoes);

        foreach ($secoes as $secao) {
            match ($secao) {
                'perfis_ativos' => $this->perfisAtivos($dashboard['perfisAtivos'] ?? []),
                'contratados' => $this->contratados($dashboard['contratados'] ?? []),
                'acessos_candidatos' => $this->acessosCandidatos($dashboard['acessosCandidatos'] ?? []),
                'empresas_ativas' => $this->empresasAtivas($dashboard['empresasAtivas'] ?? []),
                default => null,
            };
        }

        $this->rodape();
        $this->fecharPaginaAtual();

        return $this->montarPdf();
    }

    private function cabecalho(Carbon $geradoEm): void
    {
        $this->texto('SENAC', 54, 782, 18, 'F2', self::AZUL);
        $this->texto('Relatório Geral — Portal de Oportunidades', 54, 758, 16, 'F2', self::TEXTO);
        $this->texto('Gerado em: ' . $geradoEm->format('d/m/Y') . ' às ' . $geradoEm->format('H:i'), 54, 736, 10.5, 'F1', self::CINZA);
        $this->texto('Período dos dados: conforme regra de cada indicador abaixo.', 54, 720, 10.5, 'F1', self::CINZA);
        $this->linhaHorizontal(704);
        $this->y = 678;
    }

    private function resumo(array $dashboard, array $secoes): void
    {
        $this->tituloSecao('RESUMO');
        $linhas = [
            'perfis_ativos' => ['Perfis Ativos', $dashboard['perfisAtivos']['total'] ?? 0],
            'contratados' => ['Contratados', $dashboard['contratados']['ultimos30Dias'] ?? 0],
            'acessos_candidatos' => ['Acessos de Candidatos', $dashboard['acessosCandidatos']['ultimos30Dias'] ?? 0],
            'empresas_ativas' => ['Empresas Ativas', $dashboard['empresasAtivas']['total'] ?? 0],
        ];

        foreach ($secoes as $secao) {
            if (! isset($linhas[$secao])) {
                continue;
            }
            [$rotulo, $valor] = $linhas[$secao];
            $this->linhaResumo($rotulo, (int) $valor);
        }
        $this->y -= 10;
    }

    private function perfisAtivos(array $dados): void
    {
        $this->tituloSecao('PERFIS ATIVOS');
        $this->linhaTexto('Total atual: ' . $this->numero((int) ($dados['total'] ?? 0)));
        if (($dados['variacaoPercentualVsMesAnterior'] ?? null) !== null) {
            $this->linhaTexto('Variação em relação ao período anterior: ' . $dados['variacaoPercentualVsMesAnterior'] . '%');
            $this->linhaTexto('Período comparado: Mês atual vs mês anterior', 10.5, 'F1', self::CINZA);
        } else {
            $this->linhaTexto($dados['subtitulo'] ?? 'Candidatos disponíveis pelo estado efetivo', 10.5, 'F1', self::CINZA);
        }
    }

    private function contratados(array $dados): void
    {
        $this->tituloSecao('CONTRATADOS');
        $this->linhaTexto('Total: ' . $this->numero((int) ($dados['ultimos30Dias'] ?? 0)));
        $this->linhaTexto('Período: Últimos 30 dias', 10.5, 'F1', self::CINZA);
    }

    private function acessosCandidatos(array $dados): void
    {
        $this->tituloSecao('ACESSOS DE CANDIDATOS');
        $this->linhaTexto('Total: ' . $this->numero((int) ($dados['ultimos30Dias'] ?? 0)));
        $this->linhaTexto('Período: Últimos 30 dias', 10.5, 'F1', self::CINZA);
    }

    private function empresasAtivas(array $dados): void
    {
        $this->tituloSecao('EMPRESAS ATIVAS');
        $this->linhaTexto('Total: ' . $this->numero((int) ($dados['total'] ?? 0)));
        $this->linhaTexto('Total de empresas cadastradas: ' . $this->numero((int) ($dados['deUmTotalDe'] ?? 0)), 10.5, 'F1', self::CINZA);
        $this->linhaTexto('Engajamento: ' . (int) ($dados['engajamentoPercentual'] ?? 0) . '%', 10.5, 'F1', self::CINZA);
    }

    private function tituloSecao(string $titulo): void
    {
        $this->garantirEspaco(44);
        $this->linhaHorizontal($this->y + 12);
        $this->linhaTexto($titulo, 12.5, 'F2', self::AZUL, 54, 18);
    }

    private function linhaResumo(string $rotulo, int $valor): void
    {
        $this->garantirEspaco(22);
        $this->texto($rotulo, 72, $this->y, 11, 'F1', self::TEXTO);
        $this->texto($this->numero($valor), 456, $this->y, 11, 'F2', self::TEXTO);
        $this->y -= 20;
    }

    private function linhaTexto(string $texto, float $tamanho = 11, string $fonte = 'F1', string $cor = self::TEXTO, float $x = 72, float $entrelinha = 16): void
    {
        foreach ($this->quebrarLinha($texto, 88) as $linha) {
            $this->garantirEspaco($entrelinha + 8);
            $this->texto($linha, $x, $this->y, $tamanho, $fonte, $cor);
            $this->y -= $entrelinha;
        }
    }

    private function rodape(): void
    {
        $this->garantirEspaco(30);
        $this->linhaHorizontal($this->y - 2);
        $this->texto('Relatório gerencial com informações agregadas do Dashboard Administrativo.', 54, $this->y - 20, 9.5, 'F1', self::CINZA);
    }

    private function texto(string $texto, float $x, float $y, float $tamanho, string $fonte, string $cor): void
    {
        $this->conteudoAtual .= "BT\n{$cor} rg\n/{$fonte} {$tamanho} Tf\n{$x} {$y} Td\n(" . $this->escaparPdf($texto) . ") Tj\nET\n";
    }

    private function linhaHorizontal(float $y, string $cor = self::BORDA): void
    {
        $this->conteudoAtual .= "q\n{$cor} RG\n0.7 w\n54 {$y} m\n541 {$y} l\nS\nQ\n";
    }

    private function garantirEspaco(float $altura): void
    {
        if ($this->y - $altura < 60) {
            $this->rodapePagina();
            $this->fecharPaginaAtual();
            $this->novaPagina();
            $this->y = 780;
        }
    }

    private function rodapePagina(): void
    {
        $this->texto('SENAC · Portal de Oportunidades', 54, 36, 9, 'F1', self::CINZA);
    }

    private function novaPagina(): void
    {
        $this->conteudoAtual = '';
        $this->retangulo(36, 806, 523, 5, self::AZUL, self::AZUL);
        $this->y = 780;
    }

    private function retangulo(float $x, float $y, float $largura, float $altura, string $preenchimento, string $borda): void
    {
        $this->conteudoAtual .= "q\n{$preenchimento} rg\n{$borda} RG\n{$x} {$y} {$largura} {$altura} re\nB\nQ\n";
    }

    private function fecharPaginaAtual(): void
    {
        if ($this->conteudoAtual !== '') {
            $this->rodapePagina();
            $this->paginas[] = $this->conteudoAtual;
            $this->conteudoAtual = '';
        }
    }

    private function montarPdf(): string
    {
        $fontRegularId = 3;
        $fontBoldId = 4;

        foreach ($this->paginas as $indice => $conteudo) {
            $contentId = 5 + $indice * 2;
            $pageId = 6 + $indice * 2;
            $this->objetos[$contentId] = '<< /Length ' . strlen($conteudo) . " >>\nstream\n{$conteudo}\nendstream";
            $this->objetos[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$fontRegularId} 0 R /F2 {$fontBoldId} 0 R >> >> /Contents {$contentId} 0 R >>";
        }

        $kids = implode(' ', array_map(fn ($i) => (6 + $i * 2) . ' 0 R', array_keys($this->paginas)));
        $this->objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $this->objetos[2] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($this->paginas) . ' >>';
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
        return mb_strlen($linha) <= $limite ? [$linha] : explode("\n", wordwrap($linha, $limite, "\n", false));
    }

    private function escaparPdf(string $texto): string
    {
        $texto = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $texto) ?: $texto;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
    }

    private function numero(int $valor): string
    {
        return number_format($valor, 0, ',', '.');
    }
}
