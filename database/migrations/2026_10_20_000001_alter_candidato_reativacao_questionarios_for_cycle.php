<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function columnExists(string $column): bool
    {
        return Schema::hasColumn('candidato_reativacao_questionarios', $column);
    }

    private function indexExists(string $indexName): bool
    {
        return Schema::hasIndex('candidato_reativacao_questionarios', $indexName);
    }

    public function up(): void
    {
        if (! $this->columnExists('pergunta_numero')) {
            Schema::table('candidato_reativacao_questionarios', function (Blueprint $table) {
                $table->unsignedTinyInteger('pergunta_numero')->nullable()->after('bloqueado_em');
            });
        }

        if (! $this->columnExists('ciclo_inicio_em')) {
            Schema::table('candidato_reativacao_questionarios', function (Blueprint $table) {
                $table->timestamp('ciclo_inicio_em')->nullable()->after('pergunta_numero');
            });
        }

        if ($this->indexExists('candidato_questionario_reativacao_evento_unique')) {
            Schema::table('candidato_reativacao_questionarios', function (Blueprint $table) {
                $table->dropUnique('candidato_questionario_reativacao_evento_unique');
            });
        }

        if (! $this->indexExists('crq_ciclo_pergunta_unique')) {
            Schema::table('candidato_reativacao_questionarios', function (Blueprint $table) {
                $table->unique(['candidato_matricula', 'ciclo_inicio_em', 'pergunta_numero'], 'crq_ciclo_pergunta_unique');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('crq_ciclo_pergunta_unique')) {
            Schema::table('candidato_reativacao_questionarios', function (Blueprint $table) {
                $table->dropUnique('crq_ciclo_pergunta_unique');
            });
        }

        if ($this->columnExists('pergunta_numero')) {
            Schema::table('candidato_reativacao_questionarios', function (Blueprint $table) {
                $table->dropColumn('pergunta_numero');
            });
        }

        if ($this->columnExists('ciclo_inicio_em')) {
            Schema::table('candidato_reativacao_questionarios', function (Blueprint $table) {
                $table->dropColumn('ciclo_inicio_em');
            });
        }

        if (! $this->indexExists('candidato_questionario_reativacao_evento_unique')) {
            Schema::table('candidato_reativacao_questionarios', function (Blueprint $table) {
                $table->unique(['candidato_matricula', 'bloqueado_em'], 'candidato_questionario_reativacao_evento_unique');
            });
        }
    }
};
