<?php

namespace App\Support;

class SituacaoAcademicaOpcoes
{
    public const SITUACOES = [
        'em_andamento' => 'Em andamento',
        'concluido' => 'Concluído',
        'interrompido' => 'Interrompido',
        'nao_estudando' => 'Não estou estudando atualmente',
        'outra' => 'Outra situação',
    ];

    public const NIVEIS = [
        'ensino_fundamental' => 'Ensino fundamental',
        'ensino_medio' => 'Ensino médio',
        'ensino_tecnico' => 'Ensino técnico',
        'ensino_superior' => 'Ensino superior',
        'pos_graduacao' => 'Pós-graduação',
        'outra' => 'Outra',
    ];

    public static function rotuloSituacao(?string $situacao): ?string
    {
        return self::SITUACOES[$situacao] ?? null;
    }

    public static function rotuloNivel(?string $nivel): ?string
    {
        return self::NIVEIS[$nivel] ?? null;
    }

}
