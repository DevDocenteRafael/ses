<?php

namespace App\Support;

class FormatadorListaPtBr
{
    public static function formatar(array|string|null $valores): ?string
    {
        $lista = array_values(array_filter(
            array_map(
                fn ($valor) => trim((string) $valor),
                is_array($valores) ? $valores : [$valores]
            ),
            fn ($valor) => $valor !== ''
        ));

        $quantidade = count($lista);

        if ($quantidade === 0) {
            return null;
        }

        if ($quantidade === 1) {
            return $lista[0];
        }

        if ($quantidade === 2) {
            return $lista[0] . ' e ' . $lista[1];
        }

        return implode(', ', array_slice($lista, 0, -1)) . ' e ' . $lista[$quantidade - 1];
    }
}
