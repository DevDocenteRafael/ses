<?php

namespace App\Support;

use Illuminate\Support\Str;

class HabilidadesCatalogo
{
    public static function padrao(): array
    {
        $catalogo = self::catalogo();

        return self::deduplicar([
            ...array_merge(...array_values($catalogo['habilidadesPorArea'] ?? [])),
            ...($catalogo['sugestoesSoftSkills'] ?? []),
        ]);
    }

    public static function totalPadrao(): int
    {
        return count(self::padrao());
    }

    public static function deduplicar(array $habilidades): array
    {
        $mapa = [];

        foreach ($habilidades as $habilidade) {
            $rotulo = self::normalizarRotulo($habilidade);
            $chave = self::normalizarChave($rotulo);

            if ($rotulo !== '' && ! isset($mapa[$chave])) {
                $mapa[$chave] = $rotulo;
            }
        }

        return array_values($mapa);
    }

    public static function ordenar(array $habilidades): array
    {
        uasort($habilidades, fn (string $a, string $b) => strnatcasecmp($a, $b));

        return array_values($habilidades);
    }

    public static function normalizarRotulo(mixed $habilidade): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) $habilidade)) ?: '';
    }

    public static function normalizarChave(mixed $habilidade): string
    {
        return Str::of(self::normalizarRotulo($habilidade))
            ->ascii()
            ->lower()
            ->toString();
    }

    private static function catalogo(): array
    {
        $conteudo = file_get_contents(resource_path('catalogos/habilidades.json'));

        return json_decode($conteudo ?: '{}', true, flags: JSON_THROW_ON_ERROR);
    }
}
