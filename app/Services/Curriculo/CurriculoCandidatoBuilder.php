<?php

namespace App\Services\Curriculo;

use App\Models\Candidato;
use Illuminate\Support\Collection;

class CurriculoCandidatoBuilder
{
    public function montar(string $matricula): array
    {
        $candidato = Candidato::query()
            ->with([
                'pessoa',
                'informacoesProfissionais',
                'preferenciasDeTrabalho',
                'dadosAcademicos',
                'cursosSenac',
                'cursosExternos',
                'experienciasProfissionais',
                'linkExterno',
            ])
            ->findOrFail($matricula);

        $pessoa = $candidato->pessoa;
        $info = $candidato->informacoesProfissionais;
        $preferencias = $candidato->preferenciasDeTrabalho;

        return [
            'nome' => $this->texto($pessoa?->nome) ?: 'Candidato',
            'area_atuacao' => $this->texto($info?->area_de_atuacao),
            'contato' => array_filter([
                'E-mail' => $this->texto($pessoa?->email),
                'Telefone' => $this->formatarTelefone($pessoa?->telefone),
                'Localidade' => $this->cidadeUf($pessoa?->endereco_cidade, $pessoa?->endereco_uf),
            ]),
            'objetivo' => array_filter([
                'Resumo' => $this->texto($info?->sobre_mim),
                'Cargo de interesse' => $this->texto($info?->cargo_de_interesse),
                'Área de atuação' => $this->texto($info?->area_de_atuacao),
                'Disponibilidade' => $this->formatarDisponibilidade($preferencias?->disponibilidade_de_horario),
                'Pretensão salarial' => $this->formatarPretensao($preferencias?->pretensao_salarial),
            ]),
            'formacao' => $this->formatarFormacao($candidato->dadosAcademicos),
            'habilidades' => $this->habilidadesDaAreaAtual($info),
            'experiencias' => $this->formatarExperiencias($candidato->experienciasProfissionais),
            'cursos_complementares' => $this->formatarCursosComplementares($candidato->cursosExternos, $candidato->cursosSenac),
            'links' => array_filter([
                'LinkedIn' => $this->texto($candidato->linkExterno?->linkedin),
                'GitHub' => $this->texto($candidato->linkExterno?->github),
                'Portfólio' => $this->texto($candidato->linkExterno?->portfolio),
            ]),
        ];
    }

    public function nomeArquivo(array $curriculo): string
    {
        $nome = $this->removerAcentos($curriculo['nome'] ?? 'Candidato') ?: 'Candidato';
        $nome = preg_replace('/[^A-Za-z0-9]+/', '_', $nome) ?: 'Candidato';
        return 'Curriculo_' . trim($nome, '_') . '.pdf';
    }

    private function habilidadesDaAreaAtual($info): array
    {
        if (! $info) {
            return [];
        }

        $areaAtual = $this->normalizar($info->area_de_atuacao);
        $porArea = (array) ($info->habilidades_por_area ?? []);

        foreach ($porArea as $area => $habilidades) {
            if ($this->normalizar($area) === $areaAtual) {
                return $this->deduplicar((array) $habilidades);
            }
        }

        return $this->deduplicar((array) ($info->habilidades ?? []));
    }

    private function formatarFormacao(Collection $dados): array
    {
        return $dados->map(function ($item) {
            return array_filter([
                'curso' => $this->texto($item->curso),
                'instituicao' => $this->texto($item->instituicao),
                'tipo' => $this->texto($item->tipo_curso),
                'segmento' => $this->texto($item->segmento),
                'unidade' => $this->texto($item->unidade),
                'conclusao' => $item->ano_de_conclusao ? $item->ano_de_conclusao->format('d/m/Y') : null,
            ]);
        })->filter()->values()->all();
    }

    private function formatarExperiencias(Collection $experiencias): array
    {
        return $experiencias
            ->sortByDesc(fn ($item) => $item->data_inicio?->timestamp ?? 0)
            ->map(fn ($item) => array_filter([
                'cargo' => $this->texto($item->cargo),
                'empresa' => $this->texto($item->empresa),
                'tipo' => $this->texto($item->tipo),
                'local' => $this->texto($item->local),
                'periodo' => $this->periodo($item->data_inicio, $item->data_fim),
                'descricao' => $this->texto($item->descricao),
            ]))->filter()->values()->all();
    }

    private function formatarCursosComplementares(Collection $externos, Collection $senac): array
    {
        $cursosExternos = $externos->map(fn ($item) => array_filter([
            'curso' => $this->texto($item->nome_curso),
            'instituicao' => $this->texto($item->instituicao),
            'carga_horaria' => $item->carga_horaria ? $item->carga_horaria . 'h' : null,
            'conclusao' => $item->concluido_em ? $item->concluido_em->format('d/m/Y') : null,
        ]));

        $cursosSenac = $senac->map(fn ($item) => array_filter([
            'curso' => $this->texto($item->nome_curso),
            'instituicao' => 'Senac DF',
            'unidade' => $this->texto($item->unidade),
            'carga_horaria' => $item->carga_horaria ? $item->carga_horaria . 'h' : null,
            'conclusao' => $item->concluido_em ? $item->concluido_em->format('d/m/Y') : null,
        ]));

        return $cursosExternos->merge($cursosSenac)->filter()->values()->all();
    }

    private function periodo($inicio, $fim): ?string
    {
        if (! $inicio && ! $fim) return null;
        return ($inicio ? $inicio->format('m/Y') : '') . ' - ' . ($fim ? $fim->format('m/Y') : 'Atual');
    }

    private function cidadeUf($cidade, $uf): ?string
    {
        $cidade = $this->texto($cidade);
        $uf = $this->texto($uf);
        if ($cidade && $uf) return $cidade . ' - ' . $uf;
        return $cidade ?: $uf;
    }

    private function formatarTelefone($telefone): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $telefone);
        if (strlen($digitos) === 11) return sprintf('(%s) %s %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 1), substr($digitos, 3, 4), substr($digitos, 7));
        if (strlen($digitos) === 10) return sprintf('(%s) %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 4), substr($digitos, 6));
        return $this->texto($telefone);
    }

    private function formatarDisponibilidade($valor): ?string
    {
        $lista = array_filter((array) $valor);
        return $lista ? implode(' + ', $lista) : null;
    }

    private function formatarPretensao($valor): ?string
    {
        if ($valor === null || $valor === '') return null;
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }

    private function texto($valor): ?string
    {
        $valor = trim((string) ($valor ?? ''));
        return $valor === '' ? null : $valor;
    }

    private function normalizar($valor): string
    {
        $valor = $this->removerAcentos((string) $valor);
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $valor)));
    }

    private function removerAcentos(string $valor): string
    {
        $mapa = [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ç' => 'C', 'ç' => 'c', 'Ñ' => 'N', 'ñ' => 'n',
        ];

        return strtr($valor, $mapa);
    }

    private function deduplicar(array $valores): array
    {
        $limpos = array_map(fn ($valor) => trim((string) $valor), $valores);
        return array_values(array_unique(array_filter($limpos)));
    }
}
