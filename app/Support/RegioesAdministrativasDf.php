<?php

namespace App\Support;

final class RegioesAdministrativasDf
{
    private const REGIOES = [
        1 => 'Plano Piloto',
        2 => 'Gama',
        3 => 'Taguatinga',
        4 => 'Brazlândia',
        5 => 'Sobradinho',
        6 => 'Planaltina',
        7 => 'Paranoá',
        8 => 'Núcleo Bandeirante',
        9 => 'Ceilândia',
        10 => 'Guará',
        11 => 'Cruzeiro',
        12 => 'Samambaia',
        13 => 'Santa Maria',
        14 => 'São Sebastião',
        15 => 'Recanto das Emas',
        16 => 'Lago Sul',
        17 => 'Riacho Fundo',
        18 => 'Lago Norte',
        19 => 'Candangolândia',
        20 => 'Águas Claras',
        21 => 'Riacho Fundo II',
        22 => 'Sudoeste/Octogonal',
        23 => 'Varjão',
        24 => 'Park Way',
        25 => 'SCIA / Estrutural',
        26 => 'Sobradinho II',
        27 => 'Jardim Botânico',
        28 => 'Itapoã',
        29 => 'SIA (Setor de Indústria e Abastecimento)',
        30 => 'Vicente Pires',
        31 => 'Fercal',
        32 => 'Sol Nascente / Pôr do Sol',
        33 => 'Arniqueira',
        34 => 'Arapoanga',
        35 => 'Água Quente',
        36 => '26 de Setembro',
        37 => 'Ponte Alta',
    ];

    public static function nomes(): array
    {
        return array_values(self::REGIOES);
    }

    public static function codigos(): array
    {
        return array_keys(self::REGIOES);
    }

    public static function nome(int $codigo): ?string
    {
        return self::REGIOES[$codigo] ?? null;
    }

    public static function codigoPorNome(?string $nome): ?int
    {
        if ($nome === null) {
            return null;
        }

        $codigo = array_search($nome, self::REGIOES, true);

        return $codigo === false ? null : (int) $codigo;
    }

    public static function todas(): array
    {
        return array_map(
            fn (int $codigo, string $nome): array => ['codigo' => $codigo, 'nome' => $nome],
            array_keys(self::REGIOES),
            self::REGIOES
        );
    }
}
