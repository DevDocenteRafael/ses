<?php

namespace Database\Seeders;

use App\Models\BuscaTalento;
use App\Models\Candidato;
use App\Models\DadosAcademicos;
use App\Models\Empresa;
use App\Models\VisualizacaoPerfil;
use Illuminate\Database\Seeder;

/**
 * TEMPORÁRIO: popula dados de exemplo para o Relatório Geral (FR38) não
 * ficar vazio em um banco recém-criado — segmento/tipo de curso do
 * candidato de teste, e um histórico simulado de visualizações e buscas
 * dos últimos meses.
 *
 * Remover quando houver uso real da plataforma gerando esses dados.
 */
class RelatorioDemoSeeder extends Seeder
{
    public function run(): void
    {
        $candidato = Candidato::first();
        $empresa = Empresa::first();

        if (! $candidato || ! $empresa) {
            $this->command?->info('Sem candidato/empresa de teste ainda — pulando dados de relatório.');
            return;
        }

        // Garante que o candidato de teste tenha um registro acadêmico com
        // segmento/tipo de curso (usados nos filtros e no gráfico de rosca).
        $academico = DadosAcademicos::firstOrCreate(
            ['candidato_matricula' => $candidato->matricula],
            [
                'instituicao'      => 'Senac Distrito Federal',
                'curso'            => 'Técnico em Informática',
                'unidade'          => 'Senac Taguatinga',
                'ano_de_conclusao' => now(),
            ]
        );
        if (! $academico->segmento) {
            $academico->update([
                'segmento'   => 'Tecnologia e Economia Criativa',
                'tipo_curso' => 'tecnico',
            ]);
        }

        if (VisualizacaoPerfil::count() === 0) {
            for ($i = 0; $i < 6; $i++) {
                VisualizacaoPerfil::create([
                    'candidato_matricula' => $candidato->matricula,
                    'empresa_cnpj'        => $empresa->cnpj,
                    'visualizado_em'      => now()->subMonths(5 - $i)->addDays(rand(1, 20)),
                ]);
            }
            $this->command?->info('Visualizações de perfil de exemplo criadas.');
        }

        if (BuscaTalento::count() === 0) {
            $filtrosExemplo = [
                ['segmento' => 'Tecnologia e Economia Criativa'],
                ['tipo_curso' => 'tecnico'],
                ['segmento' => 'Gastronomia e Turismo'],
                ['tipo_curso' => 'livres'],
            ];
            for ($i = 0; $i < 6; $i++) {
                BuscaTalento::create([
                    'empresa_cnpj' => $empresa->cnpj,
                    'filtros'      => $filtrosExemplo[$i % count($filtrosExemplo)],
                    'buscado_em'   => now()->subMonths(5 - $i)->addDays(rand(1, 20)),
                ]);
            }
            $this->command?->info('Buscas de talentos de exemplo criadas.');
        }
    }
}
