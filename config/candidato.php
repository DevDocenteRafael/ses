<?php

return [
    'inatividade_habilitada' => env('CANDIDATO_INATIVIDADE_HABILITADA', true),
    // Regra normal: candidato inativo após 3 meses de calendário sem atividade.
    'inatividade_meses' => 3,
    // Prazo em segundos só vale com CANDIDATO_INATIVIDADE_MODO_TESTE=true e APP_ENV local/testing;
    // CANDIDATO_INATIVIDADE_SEGUNDOS sozinho (ex.: .env antigo com 30) é ignorado.
    'inatividade_modo_teste' => filter_var(env('CANDIDATO_INATIVIDADE_MODO_TESTE', false), FILTER_VALIDATE_BOOL),
    'inatividade_segundos' => max(1, (int) env('CANDIDATO_INATIVIDADE_SEGUNDOS', 30)),
    'perguntas_modo_teste' => filter_var(env('PERGUNTAS_MODO_TESTE', false), FILTER_VALIDATE_BOOL),
    'perguntas_intervalo_teste_segundos' => max(1, (int) env('PERGUNTAS_INTERVALO_TESTE_SEGUNDOS', 30)),
    'notificacoes_manuais_habilitadas' => env('NOTIFICACOES_MANUAIS_HABILITADAS', false),
    'notificacao_inativos_em_massa_habilitada' => env('NOTIFICACAO_INATIVOS_EM_MASSA_HABILITADA', false),
    'notificacao_inativos_intervalo_minimo_minutos' => (int) env('NOTIFICACAO_INATIVOS_INTERVALO_MINIMO_MINUTOS', 60),
    'notificacao_inativos_lote' => (int) env('NOTIFICACAO_INATIVOS_LOTE', 100),
    'notificacao_inativos_limite_teste' => env('NOTIFICACAO_INATIVOS_LIMITE_TESTE'),
    'notificacao_inativos_travada_minutos' => (int) env('NOTIFICACAO_INATIVOS_TRAVADA_MINUTOS', 30),
];
