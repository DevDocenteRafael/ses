<?php

namespace App\Services\Curriculo;

use App\Models\Candidato;
use App\Models\Pessoa;
use App\Services\Candidatos\CandidatoQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

class CurriculoLoteZipService
{
    public function __construct(
        private readonly CandidatoQueryService $queryService,
        private readonly CurriculoCandidatoBuilder $builder,
        private readonly CurriculoPdfRenderer $renderer,
    ) {}

    public function gerar(Request $request, Pessoa $admin): array
    {
        $paginaInicial = $this->paginaInteira($request->input('pagina_inicial'), 'pagina_inicial', 'A página inicial deve ser um número inteiro maior ou igual a 1.');
        $paginaFinal = $this->paginaInteira($request->input('pagina_final'), 'pagina_final', 'A página final deve ser um número inteiro maior ou igual a 1.');

        if ($paginaInicial > $paginaFinal) {
            throw ValidationException::withMessages(['pagina_inicial' => 'A página inicial deve ser menor ou igual à página final.']);
        }

        $maxPaginas = max(1, (int) config('curriculo.lote.max_paginas', 10));
        $totalPaginasSolicitadas = ($paginaFinal - $paginaInicial) + 1;

        if ($totalPaginasSolicitadas > $maxPaginas) {
            throw ValidationException::withMessages(['pagina_final' => "Você pode gerar até {$maxPaginas} páginas por arquivo."]);
        }

        $porPagina = max(1, (int) config('curriculo.lote.por_pagina', 10));
        $query = $this->queryService->construir($request, $admin);
        $total = (clone $query)->count();

        if ($total === 0) {
            throw ValidationException::withMessages(['pagina_inicial' => 'Nenhum candidato encontrado para gerar currículos.']);
        }

        $ultimaPagina = (int) ceil($total / $porPagina);
        if ($paginaFinal > $ultimaPagina) {
            throw ValidationException::withMessages(['pagina_final' => 'A página final não pode ser maior que a última página disponível.']);
        }

        $candidatos = (clone $query)
            ->skip(($paginaInicial - 1) * $porPagina)
            ->take($totalPaginasSolicitadas * $porPagina)
            ->get();

        if ($candidatos->isEmpty()) {
            throw ValidationException::withMessages(['pagina_inicial' => 'Nenhum candidato encontrado para gerar currículos.']);
        }

        $diretorio = storage_path('app/private/curriculos-lote');
        File::ensureDirectoryExists($diretorio);
        $nomeZip = $this->nomeZip($paginaInicial, $paginaFinal);
        $caminhoZip = $diretorio . DIRECTORY_SEPARATOR . uniqid('curriculos_', true) . '.zip';
        $zip = new ZipArchive();
        $nomesUsados = [];

        try {
            if ($zip->open($caminhoZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Não foi possível criar o arquivo ZIP temporário.');
            }

            foreach ($candidatos as $candidato) {
                /** @var Candidato $candidato */
                $curriculo = $this->builder->montar((string) $candidato->matricula);
                $pdf = $this->renderer->render($curriculo);
                $nomePdf = $this->nomePdfUnico($this->builder->nomeArquivo($curriculo), $nomesUsados);

                if (! $zip->addFromString($nomePdf, $pdf)) {
                    throw new RuntimeException('Não foi possível adicionar um currículo ao ZIP.');
                }
            }

            $zip->close();

            return [
                'path' => $caminhoZip,
                'name' => $nomeZip,
                'count' => $candidatos->count(),
            ];
        } catch (\Throwable $e) {
            if ($zip->status !== ZipArchive::ER_OK) {
                $zip->close();
            }

            File::delete($caminhoZip);
            Log::error('Falha ao gerar ZIP de currículos.', ['exception' => $e]);
            throw $e;
        }
    }

    private function paginaInteira(mixed $valor, string $campo, string $mensagem): int
    {
        if (! is_scalar($valor) || filter_var($valor, FILTER_VALIDATE_INT) === false || (int) $valor < 1) {
            throw ValidationException::withMessages([$campo => $mensagem]);
        }

        return (int) $valor;
    }

    private function nomeZip(int $paginaInicial, int $paginaFinal): string
    {
        if ($paginaInicial === $paginaFinal) {
            return "Curriculos_Pagina_{$paginaInicial}.zip";
        }

        return "Curriculos_Paginas_{$paginaInicial}_a_{$paginaFinal}.zip";
    }

    private function nomePdfUnico(string $nomeOriginal, array &$nomesUsados): string
    {
        $base = preg_replace('/\.pdf$/i', '', $nomeOriginal) ?: 'Curriculo_Candidato';
        $nome = $base . '.pdf';
        $contador = 2;

        while (isset($nomesUsados[mb_strtolower($nome)])) {
            $nome = $base . '_' . $contador . '.pdf';
            $contador++;
        }

        $nomesUsados[mb_strtolower($nome)] = true;

        return $nome;
    }
}
