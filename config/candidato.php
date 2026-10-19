<?php

return [
    'inatividade_habilitada' => env('CANDIDATO_INATIVIDADE_HABILITADA', true),
    'inatividade_segundos' => (int) env('CANDIDATO_INATIVIDADE_SEGUNDOS', 30), // 3 meses são 7889400 segundos
    'notificacoes_manuais_habilitadas' => env('NOTIFICACOES_MANUAIS_HABILITADAS', false),
    'notificacao_inativos_em_massa_habilitada' => env('NOTIFICACAO_INATIVOS_EM_MASSA_HABILITADA', false),
    'notificacao_inativos_intervalo_minimo_minutos' => (int) env('NOTIFICACAO_INATIVOS_INTERVALO_MINIMO_MINUTOS', 60),
    'notificacao_inativos_lote' => (int) env('NOTIFICACAO_INATIVOS_LOTE', 100),
    'notificacao_inativos_limite_teste' => env('NOTIFICACAO_INATIVOS_LIMITE_TESTE'),
    'notificacao_inativos_travada_minutos' => (int) env('NOTIFICACAO_INATIVOS_TRAVADA_MINUTOS', 30),
];
