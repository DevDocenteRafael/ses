<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada registro guarda uma única pergunta do ciclo; resposta_1 só é
     * preenchida quando a pergunta respondida é a de número 1.
     */
    public function up(): void
    {
        Schema::table('candidato_reativacao_questionarios', function (Blueprint $table) {
            $table->string('resposta_1', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Restaurar NOT NULL exigiria inventar respostas para as perguntas 2 a 8.
        if (DB::table('candidato_reativacao_questionarios')->whereNull('resposta_1')->exists()) {
            return;
        }

        Schema::table('candidato_reativacao_questionarios', function (Blueprint $table) {
            $table->string('resposta_1', 50)->nullable(false)->change();
        });
    }
};
