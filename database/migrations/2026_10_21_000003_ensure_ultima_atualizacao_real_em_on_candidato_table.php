<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A coluna foi adicionada editando a migration 2026_10_06_142933, que já tinha sido executada
     * em vários bancos; nesses, o migrate não a reexecuta e a coluna nunca é criada (erro 500 ao
     * reativar pelo questionário). Esta migration cria a coluna apenas onde ela falta.
     * Sem preenchimento de dados: o código já usa ultima_atividade_em quando o valor é NULL.
     */
    public function up(): void
    {
        if (Schema::hasColumn('candidato', 'ultima_atualizacao_real_em')) {
            return;
        }

        Schema::table('candidato', function (Blueprint $table) {
            $table->timestamp('ultima_atualizacao_real_em')->nullable()->after('ultima_atividade_em')->index();
        });
    }

    public function down(): void
    {
        // Não remove a coluna: em bancos novos ela pertence à migration 2026_10_06_142933.
    }
};
