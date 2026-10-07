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
    public const TIMEZONE = 'America/Sao_Paulo';

    public function __construct(private readonly \App\Services\Candidatos\CandidatoStatusService $statusService) {}

    public function resolverPeriodoRelatorio(?string $dataInicial, ?string $dataFinal): array
    {
        $agora = Carbon::now(self::TIMEZONE);

        if ($dataInicial && $dataFinal) {
            $inicio = Carbon::createFromFormat('Y-m-d', $dataInicial, self::TIMEZONE)->startOfDay();
            $fim = Carbon::createFromFormat('Y-m-d', $dataFinal, self::TIMEZONE)->endOfDay();
        } else {
            $inicio = $agora->copy()->startOfMonth()->startOfDay();
            $fim = $agora->copy()->endOfMonth()->endOfDay();
        }

        return [
            'inicio' => $inicio,
            'fim' => $fim,
            'inicio_data' => $inicio->toDateString(),
            'fim_data' => $fim->toDateString(),
            'rotulo' => $inicio->format('d/m/Y') . ' a ' . $fim->format('d/m/Y'),
            'nome_arquivo' => $inicio->format('d-m-Y') . '_a_' . $fim->format('d-m-Y'),
            'timezone' => self::TIMEZONE,
        ];
    }

    public function obter(): array
    {
        $agora = Carbon::now(self::TIMEZONE);
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

    public function obterParaRelatorio(array $periodo): array
    {
        /**
         * Auditoria temporal das métricas do PDF:
         * - Contratados: data de negócio da contratação (`contratado_em`).
         * - Acessos de Candidatos: timestamp real da visualização (`visualizado_em`).
         * - Perfis Ativos: não há histórico de status; portanto a métrica verdadeira é
         *   "candidatos cadastrados no período que estão ativos atualmente" (`created_at` + estado efetivo atual).
         * - Empresas Ativas: não há histórico de status; portanto a métrica verdadeira é
         *   "empresas cadastradas no período que estão ativas atualmente" (`created_at` + `status = true`).
         */
        $inicio = $periodo['inicio'];
        $fim = $periodo['fim'];

        $perfisAtivos = $this->statusService
            ->aplicarEscopoDisponiveis(Candidato::query())
            ->whereBetween('created_at', [$inicio, $fim])
            ->count();

        $contratados = Contratacao::query()
            ->whereBetween('contratado_em', [$periodo['inicio_data'], $periodo['fim_data']])
            ->count();

        $acessos = VisualizacaoPerfil::query()
            ->whereBetween('visualizado_em', [$inicio, $fim])
            ->count();

        $totalEmpresasPeriodo = Empresa::query()
            ->whereBetween('created_at', [$inicio, $fim])
            ->count();

        $empresasAtivasPeriodo = Empresa::query()
            ->where('status', true)
            ->whereBetween('created_at', [$inicio, $fim])
            ->count();

        return [
            'periodoRelatorio' => $periodo,
            'perfisAtivos' => [
                'total' => $perfisAtivos,
                'variacaoPercentualVsMesAnterior' => null,
                'periodoComparado' => null,
                'subtitulo' => 'Candidatos cadastrados no período que estão ativos atualmente; não reconstrói histórico de status.',
            ],
            'contratados' => [
                'ultimos30Dias' => $contratados,
                'total' => $contratados,
                'periodo' => $periodo['rotulo'],
                'colunaTemporal' => 'contratado_em',
            ],
            'acessosCandidatos' => [
                'ultimos30Dias' => $acessos,
                'total' => $acessos,
                'periodo' => $periodo['rotulo'],
                'colunaTemporal' => 'visualizado_em',
            ],
            'empresasAtivas' => [
                'total' => $empresasAtivasPeriodo,
                'deUmTotalDe' => $totalEmpresasPeriodo,
                'engajamentoPercentual' => $totalEmpresasPeriodo > 0 ? round(($empresasAtivasPeriodo / $totalEmpresasPeriodo) * 100) : 0,
                'subtitulo' => 'Empresas cadastradas no período que estão ativas atualmente; não reconstrói histórico de status.',
            ],
        ];
    }

    private function expressaoMes(string $coluna): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$coluna})"
            : "DATE_FORMAT({$coluna}, '%Y-%m')";
    }
}
