<?php

namespace App\Support;

use Illuminate\Support\Str;

class AreasAtuacaoCatalogo
{
    public const AREAS = [
        'Beleza e Cuidado Pessoal',
        'Gastronomia e Turismo',
        'Gestão Comércio e Negócio',
        'Moda',
        'Saúde',
        'Segurança',
        'Tecnologia e Games',
        'Economia Criativa',
    ];

    private const LEGADOS = [
        'Beleza e Cuidado Pessoal' => ['beleza', 'Beleza'],
        'Gastronomia e Turismo' => ['gastronomia-e-turismo'],
        'Gestão Comércio e Negócio' => ['gestao-e-negocios', 'Gestão e Negócios', 'Administração', 'Gestão'],
        'Moda' => ['moda', 'moda-e-costura'],
        'Saúde' => [],
        'Segurança' => ['seguranca'],
        'Tecnologia e Games' => ['tecnologia-e-games'],
        'Economia Criativa' => [],
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
