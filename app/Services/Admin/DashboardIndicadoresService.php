<?php

namespace App\Services\Admin;

use App\Models\BuscaTalento;
use App\Models\Candidato;
use App\Models\Contratacao;
use App\Models\Empresa;
use App\Models\VisualizacaoPerfil;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardIndicadoresService
{
    public function __construct(private readonly \App\Services\Candidatos\CandidatoStatusService $statusService) {}

    public function obter(): array
    {
        $agora = Carbon::now('America/Sao_Paulo');
        $inicioUltimos30Dias = $agora->copy()->subDays(30);

        $totalCandidatos = $this->statusService->aplicarEscopoDisponiveis(Candidato::query())->count();
        $variacaoPerfis = null;

        $contratadosUltimos30Dias = Contratacao::query()
            ->where('contratado_em', '>=', $inicioUltimos30Dias->toDateString())
            ->count();

        $cursosMaisContratados = Contratacao::query()
            ->leftJoin('dados_academicos', function ($join) {
                $join->on('dados_academicos.candidato_matricula', '=', 'contratacoes.candidato_matricula')
                    ->whereRaw('dados_academicos.id = (SELECT MIN(academico.id) FROM dados_academicos as academico WHERE academico.candidato_matricula = contratacoes.candidato_matricula)');
            })
            ->where('contratacoes.contratado_em', '>=', $inicioUltimos30Dias->toDateString())
            ->selectRaw("COALESCE(NULLIF(dados_academicos.curso, ''), 'Não informado') as curso")
            ->selectRaw('COUNT(DISTINCT contratacoes.id) as total')
            ->groupBy('curso')
            ->orderByDesc('total')
            ->orderBy('curso')
            ->limit(10)
            ->get();

        $acessosUltimos30Dias = VisualizacaoPerfil::where('visualizado_em', '>=', $inicioUltimos30Dias)->count();

        $totalEmpresas = Empresa::count();
        $empresasAtivas = Empresa::where('status', true)->count();
        $empresasComVagaAtiva = Empresa::where('status', true)
            ->whereHas('vagas', fn ($q) => $q->where('status', true))
            ->count();
        $engajamentoEmpresas = $empresasAtivas > 0
            ? round(($empresasComVagaAtiva / $empresasAtivas) * 100)
            : 0;

        $acessosPorSegmento = VisualizacaoPerfil::query()
            ->join('candidato', 'visualizacoes_perfil.candidato_matricula', '=', 'candidato.matricula')
            ->join('dados_academicos', 'dados_academicos.candidato_matricula', '=', 'candidato.matricula')
            ->selectRaw('dados_academicos.segmento as segmento, count(*) as total')
            ->groupBy('dados_academicos.segmento')
            ->orderByDesc('total')
            ->get();

        $candidatosPorCurso = Candidato::query()
            ->join('dados_academicos', 'dados_academicos.candidato_matricula', '=', 'candidato.matricula')
            ->whereRaw('dados_academicos.id = (SELECT MIN(academico.id) FROM dados_academicos as academico WHERE academico.candidato_matricula = candidato.matricula)')
            ->whereNotNull('dados_academicos.curso')
            ->where('dados_academicos.curso', '<>', '')
            ->select('dados_academicos.curso')
            ->selectRaw('COUNT(DISTINCT candidato.matricula) as total')
            ->groupBy('dados_academicos.curso')
            ->orderByDesc('total')
            ->orderBy('dados_academicos.curso')
            ->limit(10)
            ->get();

        $formatoMesVisualizacao = $this->expressaoMes('visualizado_em');
        $formatoMesBusca = $this->expressaoMes('buscado_em');

        $visualizacoesPorMes = VisualizacaoPerfil::query()
            ->selectRaw("{$formatoMesVisualizacao} as mes, count(*) as total")
            ->where('visualizado_em', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();

        $buscasPorMes = BuscaTalento::query()
            ->selectRaw("{$formatoMesBusca} as mes, count(*) as total")
            ->where('buscado_em', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();

        $rotulosFiltro = [
            'segmento' => 'Segmento',
            'tipo_curso' => 'Tipo de Curso',
            'disponibilidade' => 'Disponibilidade',
            'tipo_contratacao' => 'Contratação',
            'habilidades' => 'Habilidade',
        ];
        $contagem = [];
        foreach (BuscaTalento::orderByDesc('buscado_em')->limit(500)->get() as $busca) {
            foreach ((array) $busca->filtros as $filtro => $valor) {
                foreach ((array) $valor as $valorUnico) {
                    if ($valorUnico === null || $valorUnico === '') {
                        continue;
                    }
                    $chave = $filtro . '|' . $valorUnico;
                    $contagem[$chave] ??= [
                        'filtro' => $rotulosFiltro[$filtro] ?? $filtro,
                        'valor' => (string) $valorUnico,
                        'totalBuscas' => 0,
                        'ultimaPesquisa' => $busca->buscado_em,
                    ];
                    $contagem[$chave]['totalBuscas']++;
                }
            }
        }
        $filtrosMaisAcessados = collect($contagem)
            ->sortByDesc('totalBuscas')
            ->take(10)
            ->values()
            ->map(fn ($item) => [
                'filtro' => $item['filtro'],
                'valor' => $item['valor'],
                'totalBuscas' => $item['totalBuscas'],
                'ultimaPesquisa' => $item['ultimaPesquisa']->diffForHumans(),
            ]);

        return [
            'perfisAtivos' => [
                'total' => $totalCandidatos,
                'variacaoPercentualVsMesAnterior' => $variacaoPerfis,
                'periodoComparado' => null,
                'subtitulo' => 'Candidatos disponíveis pelo estado efetivo',
            ],
            'contratados' => [
                'ultimos30Dias' => $contratadosUltimos30Dias,
                'periodo' => 'Últimos 30 dias',
            ],
            'cursosMaisContratados' => $cursosMaisContratados,
            'acessosCandidatos' => [
                'ultimos30Dias' => $acessosUltimos30Dias,
                'periodo' => 'Últimos 30 dias',
            ],
            'empresasAtivas' => [
                'total' => $empresasAtivas,
                'deUmTotalDe' => $totalEmpresas,
                'engajamentoPercentual' => $engajamentoEmpresas,
            ],
            'acessosPorSegmento' => $acessosPorSegmento,
            'visualizacoesPorMes' => $visualizacoesPorMes,
            'buscasPorMes' => $buscasPorMes,
            'filtrosMaisAcessados' => $filtrosMaisAcessados,
            'candidatosPorCurso' => $candidatosPorCurso,
        ];
    }

    private function expressaoMes(string $coluna): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$coluna})"
            : "DATE_FORMAT({$coluna}, '%Y-%m')";
    }
}
