<?php

namespace App\Services\Candidatos;

use App\Models\BuscaTalento;
use App\Models\Candidato;
use App\Models\Pessoa;
use App\Support\CatalogoAcademicoSenacDf;
use App\Support\HabilidadesCatalogo;
use App\Support\RegioesAdministrativasDf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CandidatoQueryService
{
    public function construir(Request $request, Pessoa $solicitante): Builder
    {
        $query = Candidato::query()
            ->whereDoesntHave('contratacao')
            ->with([
                'pessoa:id_pessoa,nome,email,telefone',
                'linkExterno',
                'informacoesProfissionais',
                'preferenciasDeTrabalho',
                'regioesPreferidasTrabalho',
                'dadosAcademicos',
            ]);

        if ($solicitante->tipo() === 'empresa') {
            $query->where('status', true);
        }

        $this->aplicarFiltros($query, $request);

        return $query->orderBy('matricula');
    }

    public function registrarBuscaDeTalentos(Request $request, ?Pessoa $solicitante): void
    {
        if (! $solicitante || $solicitante->tipo() !== 'empresa') {
            return;
        }

        $filtros = array_filter([
            'segmento' => $request->input('segmento'),
            'tipo_curso' => $request->input('tipo_curso'),
            'disponibilidade' => $request->input('disponibilidade'),
            'tipo_contratacao' => $request->input('tipo_contratacao'),
            'regioes_administrativas' => $request->input('regioes_administrativas'),
            'habilidades' => $request->input('habilidades'),
        ]);

        if (! $filtros) {
            return;
        }

        BuscaTalento::create([
            'empresa_cnpj' => $solicitante->empresa?->cnpj,
            'filtros' => $filtros,
            'buscado_em' => now(),
        ]);
    }

    private function aplicarFiltros(Builder $query, Request $request): void
    {
        if ($request->filled('busca')) {
            $termo = trim((string) $request->input('busca'));
            $termoNumerico = preg_replace('/\D+/', '', $termo) ?? '';

            $query->where(function ($q) use ($termo, $termoNumerico) {
                $q->whereHas('pessoa', function ($pessoa) use ($termo) {
                    $pessoa->where('nome', 'like', '%' . $termo . '%');
                });

                if ($termoNumerico !== '') {
                    $q->orWhere('cpf', 'like', '%' . $termoNumerico . '%')
                        ->orWhereHas('pessoa', function ($pessoa) use ($termoNumerico) {
                            $pessoa->where('telefone', 'like', '%' . $termoNumerico . '%');
                        });
                } else {
                    $q->orWhere('cpf', 'like', '%' . $termo . '%');
                }
            });
        }

        if ($request->filled('status')) {
            Validator::make($request->input(), ['status' => ['boolean']])->validate();
            $query->where('status', $request->boolean('status'));
        }

        $unidade = null;
        if ($request->filled('unidade')) {
            Validator::make($request->input(), ['unidade' => ['string', 'max:100']])->validate();
            $unidade = trim((string) $request->input('unidade'));
        }

        $curso = null;
        if ($request->filled('curso')) {
            Validator::make($request->input(), ['curso' => ['string', 'max:100']])->validate();
            $curso = trim((string) $request->input('curso'));
        }

        if ($unidade !== null || $curso !== null) {
            $filtrarDadosAcademicos = function ($academico) use ($unidade, $curso) {
                if ($unidade !== null) {
                    $academico->where('unidade', $unidade);
                }

                if ($curso !== null) {
                    $academico->where('curso', 'like', '%' . $curso . '%');
                }
            };

            $query->whereHas('dadosAcademicos', $filtrarDadosAcademicos)
                ->with(['dadosAcademicos' => $filtrarDadosAcademicos]);
        }

        $tipoCurso = $request->filled('tipo_curso') ? trim((string) $request->input('tipo_curso')) : null;
        $segmento = $request->filled('segmento') ? trim((string) $request->input('segmento')) : null;

        if ($tipoCurso !== null && ! CatalogoAcademicoSenacDf::tipoExiste($tipoCurso)) {
            throw ValidationException::withMessages(['tipo_curso' => 'Tipo de curso inválido.']);
        }

        if ($segmento !== null) {
            if (! CatalogoAcademicoSenacDf::segmentoExiste($segmento)) {
                throw ValidationException::withMessages(['segmento' => 'Segmento inválido.']);
            }

            if ($tipoCurso === null || ! CatalogoAcademicoSenacDf::segmentoPertenceAoTipo($segmento, $tipoCurso)) {
                throw ValidationException::withMessages(['segmento' => 'Segmento não pertence ao tipo de curso informado.']);
            }
        }

        if ($tipoCurso !== null || $segmento !== null) {
            $query->whereHas('dadosAcademicos', function ($q) use ($tipoCurso, $segmento) {
                if ($tipoCurso !== null) {
                    $q->whereIn('tipo_curso', CatalogoAcademicoSenacDf::valoresLegadosTipo($tipoCurso));
                }

                if ($segmento !== null) {
                    $q->whereIn('segmento', CatalogoAcademicoSenacDf::valoresLegadosSegmento($segmento));
                }
            });
        }

        if ($request->filled('disponibilidade')) {
            $query->whereHas('preferenciasDeTrabalho', function ($q) use ($request) {
                $q->whereJsonContains('disponibilidade_de_horario', $request->input('disponibilidade'));
            });
        }

        if ($request->filled('tipo_contratacao')) {
            Validator::make($request->input(), ['tipo_contratacao' => ['integer', 'in:1,2,3']], [
                'tipo_contratacao.in' => 'O tipo de contratação informado não é permitido. Jovem Aprendiz não é mais uma opção válida.',
            ])->validate();

            $mascara = (int) $request->input('tipo_contratacao');
            $query->whereHas('preferenciasDeTrabalho', function ($q) use ($mascara) {
                $q->whereRaw('(tipo_de_contratacao & ?) != 0', [$mascara]);
            });
        }

        if ($request->filled('regioes_administrativas')) {
            Validator::make($request->input(), [
                'regioes_administrativas' => ['array'],
                'regioes_administrativas.*' => ['integer', Rule::in(RegioesAdministrativasDf::codigos())],
            ])->validate();

            $codigosRegioes = array_values(array_unique(array_map('intval', (array) $request->input('regioes_administrativas'))));
            $nomesRegioes = array_filter(array_map(fn (int $codigo) => RegioesAdministrativasDf::nome($codigo), $codigosRegioes));

            $query->where(function ($q) use ($codigosRegioes, $nomesRegioes) {
                $q->whereHas('preferenciasDeTrabalho', function ($preferencias) {
                    $preferencias->where('aceita_todas_regioes', true);
                })
                    ->orWhereHas('regioesPreferidasTrabalho', function ($regioes) use ($codigosRegioes) {
                        $regioes->whereIn('codigo_regiao', $codigosRegioes);
                    })
                    ->orWhereHas('preferenciasDeTrabalho', function ($preferencias) use ($nomesRegioes) {
                        $preferencias->whereIn('regiao_administrativa', $nomesRegioes);
                    });
            });
        }

        if ($request->filled('habilidades')) {
            $habilidades = array_filter((array) $request->input('habilidades'));
            foreach ($habilidades as $habilidade) {
                $this->aplicarFiltroHabilidadeNaAreaAtiva($query, $habilidade);
            }
        }
    }

    private function aplicarFiltroHabilidadeNaAreaAtiva(Builder $query, string $habilidade): void
    {
        $query->whereHas('informacoesProfissionais', function ($q) use ($habilidade) {
            $q->where('habilidades', 'like', '%' . $habilidade . '%')
                ->where(function ($ativo) use ($habilidade) {
                    $ativo->whereNull('habilidades_por_area')
                        ->orWhere('habilidades_por_area', '')
                        ->orWhere('habilidades_por_area', '[]')
                        ->orWhere('habilidades_por_area', '{}')
                        ->orWhere(function ($json) use ($habilidade) {
                            foreach (HabilidadesCatalogo::areas() as $area) {
                                $json->orWhere(function ($areaAtiva) use ($area, $habilidade) {
                                    $areaAtiva->where('area_de_atuacao', $area)
                                        ->whereJsonContains('habilidades_por_area->' . $area, $habilidade);
                                });
                            }
                        });
                });
        });
    }
}
