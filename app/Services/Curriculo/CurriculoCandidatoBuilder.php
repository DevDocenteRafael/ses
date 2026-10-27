<?php

namespace App\Services\Curriculo;

use App\Models\Candidato;
use App\Support\CatalogoAcademicoSenacDf;
use App\Support\FormatadorListaPtBr;
use App\Support\SituacaoAcademicaOpcoes;
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
                'situacaoAcademicaAtual',
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
                'Localidade' => $this->cidadeUf($pessoa?->endereco_cidade, $pessoa?->endereco_uf),
                'Telefone' => $this->formatarTelefone($pessoa?->telefone),
                'E-mail' => $this->texto($pessoa?->email),
                'LinkedIn' => $this->texto($candidato->linkExterno?->linkedin),
                'Portfólio' => $this->texto($candidato->linkExterno?->portfolio),
            ]),
            'objetivo_profissional' => $this->objetivoProfissional($info, $preferencias),
            'resumo_profissional' => $this->texto($info?->sobre_mim),
            'formacao_academica' => $this->formatarFormacaoAcademica(
                $candidato->dadosAcademicos,
                $candidato->situacaoAcademicaAtual
            ),
            'formacao_senac' => $this->formatarFormacaoSenac($candidato->cursosSenac),
            'habilidades' => $this->habilidadesDaAreaAtual($info),
            'experiencias' => $this->formatarExperiencias($candidato->experienciasProfissionais),
            'cursos_complementares' => $this->formatarCursosComplementares($candidato->cursosExternos),
            'outras_informacoes' => array_filter([
                'Disponibilidade de horário' => $this->formatarDisponibilidade($preferencias?->disponibilidade_de_horario),
                'Tipo de contratação desejada' => $this->formatarTipoContratacao($preferencias?->tipo_de_contratacao),
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

    private function objetivoProfissional($info, $preferencias): ?string
    {
        $partes = [];
        if ($area = $this->texto($info?->area_de_atuacao)) {
            $partes[] = 'Atuar na área de ' . $area;
        }
        if ($cargo = $this->texto($info?->cargo_de_interesse)) {
            $partes[] = 'buscando oportunidades como ' . $cargo;
        }
        if ($tipo = $this->formatarTipoContratacao($preferencias?->tipo_de_contratacao)) {
            $partes[] = 'em oportunidades de ' . $tipo;
        }

        return $partes ? implode(', ', $partes) . '.' : null;
    }

    private function formatarFormacaoAcademica(Collection $dados, $situacaoAtual): array
    {
        $formacoes = $dados->map(function ($item) {
            $dataConclusao = $item->ano_de_conclusao;
            $emAndamento = $dataConclusao && $dataConclusao->copy()->startOfDay()->isFuture();

            return array_filter([
                'curso' => $this->texto($item->curso),
                'instituicao' => $this->texto($item->instituicao),
                'escolaridade' => $this->rotuloTipoCurso($item->tipo_curso),
                'situacao' => $dataConclusao ? ($emAndamento ? 'Em andamento' : 'Concluído') : null,
                'conclusao' => $dataConclusao ? $dataConclusao->format('d/m/Y') : null,
            ]);
        })->filter()->values();

        if (! $situacaoAtual) {
            return $formacoes->all();
        }

        $formacaoAtual = array_filter([
            'curso' => $this->texto($situacaoAtual->curso),
            'instituicao' => $this->texto($situacaoAtual->instituicao),
            'escolaridade' => SituacaoAcademicaOpcoes::rotuloNivel($situacaoAtual->nivel_escolaridade),
            'situacao' => SituacaoAcademicaOpcoes::rotuloSituacao($situacaoAtual->situacao_academica),
            'inicio' => $situacaoAtual->data_inicio
                ? 'Início: ' . $situacaoAtual->data_inicio->format('Y')
                : null,
            'previsao_conclusao' => $situacaoAtual->previsao_conclusao
                ? 'Previsão de conclusão: ' . $situacaoAtual->previsao_conclusao->format('Y')
                : null,
            'conclusao' => $situacaoAtual->data_conclusao
                ? 'Conclusão: ' . $situacaoAtual->data_conclusao->format('Y')
                : null,
            'descricao_situacao' => $this->texto($situacaoAtual->descricao_situacao),
        ]);

        $chaveCursoAtual = $this->normalizar($situacaoAtual->curso) . '|' . $this->normalizar($situacaoAtual->instituicao);
        $formacoes = $formacoes->reject(function (array $formacao) use ($chaveCursoAtual) {
            return $chaveCursoAtual !== '|'
                && $this->normalizar($formacao['curso'] ?? null) . '|' . $this->normalizar($formacao['instituicao'] ?? null) === $chaveCursoAtual;
        });

        return $formacoes->prepend($formacaoAtual)->values()->all();
    }

    private function rotuloTipoCurso($tipoCurso): ?string
    {
        $tipo = $this->texto($tipoCurso);

        if ($tipo === null) {
            return null;
        }

        foreach (CatalogoAcademicoSenacDf::tipos() as $opcao) {
            if (mb_strtolower($opcao['id']) === mb_strtolower($tipo)) {
                return $opcao['nome'];
            }
        }

        return $tipo;
    }

    private function formatarFormacaoSenac(Collection $senac): array
    {
        return $senac->map(fn ($item) => array_filter([
            'curso' => $this->texto($item->nome_curso),
            'unidade' => $this->texto($item->unidade),
            'conclusao' => $item->concluido_em ? $item->concluido_em->format('Y') : null,
            'competencias' => null,
        ]))->filter()->values()->all();
    }

    private function formatarExperiencias(Collection $experiencias): array
    {
        return $experiencias
            ->sortByDesc(fn ($item) => [
                $item->data_fim ? 0 : 1,
                $item->data_inicio?->timestamp ?? 0,
            ])
            ->map(fn ($item) => array_filter([
                'cargo' => $this->texto($item->cargo),
                'empresa' => $this->texto($item->empresa),
                'tipo' => $this->texto($item->tipo),
                'local' => $this->texto($item->local),
                'periodo' => $this->periodo($item->data_inicio, $item->data_fim),
                'descricao' => $this->texto($item->descricao),
            ]))->filter()->values()->all();
    }

    private function formatarCursosComplementares(Collection $externos): array
    {
        return $externos->map(fn ($item) => array_filter([
            'curso' => $this->texto($item->nome_curso),
            'instituicao' => $this->texto($item->instituicao),
            'conclusao' => $item->concluido_em ? $item->concluido_em->format('d/m/Y') : null,
            'carga_horaria' => $item->carga_horaria ? $item->carga_horaria . 'h' : null,
        ]))->filter()->values()->all();
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
        return FormatadorListaPtBr::formatar($valor);
    }

    private function formatarPretensao($valor): ?string
    {
        if ($valor === null || $valor === '') return null;
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }

    private function formatarTipoContratacao($valor): ?string
    {
        return match ((string) $valor) {
            '1' => 'Estágio',
            '2' => 'Aprendizagem',
            '3' => 'Emprego',
            default => $this->texto($valor),
        };
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
