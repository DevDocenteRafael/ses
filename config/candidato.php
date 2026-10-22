<?php

return [
    'inatividade_habilitada' => env('CANDIDATO_INATIVIDADE_HABILITADA', true),
    'inatividade_segundos' => (int) env('CANDIDATO_INATIVIDADE_SEGUNDOS', 7889400), // 3 meses são 7889400 segundos
    'perguntas_modo_teste' => filter_var(env('PERGUNTAS_MODO_TESTE', false), FILTER_VALIDATE_BOOL),
    'perguntas_intervalo_teste_segundos' => max(1, (int) env('PERGUNTAS_INTERVALO_TESTE_SEGUNDOS', 30)),
    'notificacoes_manuais_habilitadas' => env('NOTIFICACOES_MANUAIS_HABILITADAS', false),
    'notificacao_inativos_em_massa_habilitada' => env('NOTIFICACAO_INATIVOS_EM_MASSA_HABILITADA', false),
    'notificacao_inativos_intervalo_minimo_minutos' => (int) env('NOTIFICACAO_INATIVOS_INTERVALO_MINIMO_MINUTOS', 60),
    'notificacao_inativos_lote' => (int) env('NOTIFICACAO_INATIVOS_LOTE', 100),
    'notificacao_inativos_limite_teste' => env('NOTIFICACAO_INATIVOS_LIMITE_TESTE'),
    'notificacao_inativos_travada_minutos' => (int) env('NOTIFICACAO_INATIVOS_TRAVADA_MINUTOS', 30),
];
