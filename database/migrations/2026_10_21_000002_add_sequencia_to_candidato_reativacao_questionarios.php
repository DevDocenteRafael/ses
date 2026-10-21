<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABELA = 'candidato_reativacao_questionarios';

    /**
     * Permite registrar mais de uma decisão para a mesma pergunta no ciclo
     * (ex.: candidato que encerrou e depois reconsidera a Pergunta 1) sem
     * sobrescrever a decisão anterior. Registros existentes ficam com sequencia = 1.
     */
    public function up(): void
    {
        if (! Schema::hasColumn(self::TABELA, 'sequencia')) {
            Schema::table(self::TABELA, function (Blueprint $table) {
                $table->unsignedSmallInteger('sequencia')->default(1)->after('pergunta_numero');
            });
        }

        // Cria o índice novo antes de remover o antigo para nunca ficar sem proteção contra duplicidade.
        if (! Schema::hasIndex(self::TABELA, 'crq_ciclo_pergunta_seq_unique')) {
            Schema::table(self::TABELA, function (Blueprint $table) {
                $table->unique(['candidato_matricula', 'ciclo_inicio_em', 'pergunta_numero', 'sequencia'], 'crq_ciclo_pergunta_seq_unique');
            });
        }

        if (Schema::hasIndex(self::TABELA, 'crq_ciclo_pergunta_unique')) {
            Schema::table(self::TABELA, function (Blueprint $table) {
                $table->dropUnique('crq_ciclo_pergunta_unique');
            });
        }
    }

    public function down(): void
    {
        // Voltar ao índice antigo exigiria apagar decisões reconsideradas; nesse caso, mantém a estrutura.
        if (DB::table(self::TABELA)->where('sequencia', '>', 1)->exists()) {
            return;
        }

        if (! Schema::hasIndex(self::TABELA, 'crq_ciclo_pergunta_unique')) {
            Schema::table(self::TABELA, function (Blueprint $table) {
                $table->unique(['candidato_matricula', 'ciclo_inicio_em', 'pergunta_numero'], 'crq_ciclo_pergunta_unique');
            });
        }

        if (Schema::hasIndex(self::TABELA, 'crq_ciclo_pergunta_seq_unique')) {
            Schema::table(self::TABELA, function (Blueprint $table) {
                $table->dropUnique('crq_ciclo_pergunta_seq_unique');
            });
        }

        if (Schema::hasColumn(self::TABELA, 'sequencia')) {
            Schema::table(self::TABELA, function (Blueprint $table) {
                $table->dropColumn('sequencia');
            });
        }
    }
};
