<?php

namespace App\Support;

use Illuminate\Support\Str;

class AreasAtuacaoCatalogo
{
    public const AREAS = [
        'Tecnologia da Informação',
        'Ambiente e Saúde',
        'Negócios, Finanças e Gestão',
        'Direito e Políticas Públicas',
        'Engenharia e Indústria',
        'Educação, Humanas e Sociais',
        'Comunicação, Arte e Design',
        'Ciências Exatas e da Terra',
    ];

    private const LEGADOS = [
        'Tecnologia da Informação' => ['TI', 'Tecnologia', 'Tecnologia e Economia Criativa', 'tecnologia-e-games'],
        'Ambiente e Saúde' => ['Saúde'],
        'Negócios, Finanças e Gestão' => ['Administração', 'Gestão', 'Gestão e Negócios', 'Marketing', 'Recursos Humanos'],
        'Educação, Humanas e Sociais' => ['Educação'],
        'Comunicação, Arte e Design' => ['Comunicação', 'Design'],
    ];

    public static function areas(): array
    {
        return self::AREAS;
    }

    public static function opcoes(): array
    {
        return array_map(fn (string $area): array => ['id' => $area, 'nome' => $area], self::AREAS);
    }

    public static function existe(?string $area): bool
    {
        return is_string($area) && in_array($area, self::AREAS, true);
    }

    public static function validarTodas(array $areas): bool
    {
        return collect($areas)->every(fn ($area): bool => self::existe($area));
    }

    public static function valoresCompativeis(string $area): array
    {
        return array_values(array_unique([$area, ...(self::LEGADOS[$area] ?? [])]));
    }

    public static function normalizarChave(mixed $area): string
    {
        return Str::of((string) $area)
            ->trim()
            ->replaceMatches('/\s+/u', ' ')
            ->ascii()
            ->lower()
            ->toString();
    }
}
