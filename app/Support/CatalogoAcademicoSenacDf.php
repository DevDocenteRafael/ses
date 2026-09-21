<?php

namespace App\Support;

/**
 * Fonte central local para a taxonomia acadêmica usada no Portal da Empresa.
 *
 * Não consulta SIG e não faz scraping em runtime. A lista inicial foi mantida
 * localmente a partir da estrutura pública institucional do Senac-DF usada como
 * referência de modalidades/tipos de oferta e segmentos/eixos de atuação.
 * Futuramente esta classe pode passar a ler uma tabela sincronizada por rotina
 * institucional sem exigir alterações no frontend de Buscar Talentos.
 */
class CatalogoAcademicoSenacDf
{
    private const TIPOS = [
        'livres' => 'Cursos Livres / Formação Continuada',
        'extensao' => 'Extensão / Certificação',
        'tecnico' => 'Cursos Técnicos',
        'graduacao' => 'Graduação',
        'pos-graduacao' => 'Pós-graduação',
    ];

    private const SEGMENTOS = [
        'ambiente-e-saude' => 'Ambiente e Saúde',
        'beleza' => 'Beleza',
        'comercio' => 'Comércio',
        'comunicacao' => 'Comunicação',
        'conservacao-e-zeladoria' => 'Conservação e Zeladoria',
        'design' => 'Design',
        'educacao' => 'Educação',
        'gastronomia-e-turismo' => 'Gastronomia e Turismo',
        'gestao-e-negocios' => 'Gestão e Negócios',
        'idiomas' => 'Idiomas',
        'moda' => 'Moda',
        'producao-de-alimentos' => 'Produção de Alimentos',
        'seguranca' => 'Segurança',
        'tecnologia-da-informacao' => 'Tecnologia da Informação',
    ];

    private const SEGMENTOS_POR_TIPO = [
        'livres' => [
            'ambiente-e-saude',
            'beleza',
            'comercio',
            'comunicacao',
            'conservacao-e-zeladoria',
            'design',
            'educacao',
            'gastronomia-e-turismo',
            'gestao-e-negocios',
            'idiomas',
            'moda',
            'producao-de-alimentos',
            'seguranca',
            'tecnologia-da-informacao',
        ],
        'extensao' => [
            'gestao-e-negocios',
            'tecnologia-da-informacao',
        ],
        'tecnico' => [
            'ambiente-e-saude',
            'design',
            'gastronomia-e-turismo',
            'gestao-e-negocios',
            'seguranca',
            'tecnologia-da-informacao',
        ],
        'graduacao' => [
            'gestao-e-negocios',
            'tecnologia-da-informacao',
        ],
        'pos-graduacao' => [
            'educacao',
            'gestao-e-negocios',
            'tecnologia-da-informacao',
        ],
    ];

    public static function tipos(): array
    {
        return collect(self::TIPOS)
            ->map(fn (string $nome, string $id): array => ['id' => $id, 'nome' => $nome])
            ->values()
            ->all();
    }

    public static function segmentos(?string $tipoCurso = null): array
    {
        $ids = $tipoCurso && array_key_exists($tipoCurso, self::SEGMENTOS_POR_TIPO)
            ? self::SEGMENTOS_POR_TIPO[$tipoCurso]
            : array_keys(self::SEGMENTOS);

        return collect($ids)
            ->filter(fn (string $id): bool => array_key_exists($id, self::SEGMENTOS))
            ->map(fn (string $id): array => ['id' => $id, 'nome' => self::SEGMENTOS[$id]])
            ->values()
            ->all();
    }

    public static function tipoExiste(?string $tipoCurso): bool
    {
        return is_string($tipoCurso) && array_key_exists($tipoCurso, self::TIPOS);
    }

    public static function segmentoExiste(?string $segmento): bool
    {
        return is_string($segmento) && array_key_exists($segmento, self::SEGMENTOS);
    }

    public static function segmentoPertenceAoTipo(?string $segmento, ?string $tipoCurso): bool
    {
        if (! self::tipoExiste($tipoCurso) || ! self::segmentoExiste($segmento)) {
            return false;
        }

        return in_array($segmento, self::SEGMENTOS_POR_TIPO[$tipoCurso], true);
    }

    public static function valoresLegadosSegmento(string $segmento): array
    {
        $mapa = [
            'tecnologia-da-informacao' => ['Tecnologia da Informação', 'Tecnologia e Economia Criativa', 'tecnologia-e-games', 'Tecnologia'],
            'gastronomia-e-turismo' => ['Gastronomia e Turismo'],
            'gestao-e-negocios' => ['Gestão e Negócios', 'Administração'],
            'moda' => ['Moda', 'moda-e-costura'],
        ];

        return array_values(array_unique([$segmento, self::SEGMENTOS[$segmento] ?? $segmento, ...($mapa[$segmento] ?? [])]));
    }

    public static function valoresLegadosTipo(string $tipoCurso): array
    {
        $mapa = [
            'tecnico' => ['tecnico', 'Técnico', 'Tecnico'],
            'livres' => ['livres', 'livre', 'Cursos Livres'],
            'extensao' => ['extensao', 'extensão', 'Certificação em TI'],
            'graduacao' => ['graduacao', 'graduação', 'Graduação'],
            'pos-graduacao' => ['pos-graduacao', 'pós-graduação', 'Pós-graduação'],
        ];

        return $mapa[$tipoCurso] ?? [$tipoCurso];
    }
}
