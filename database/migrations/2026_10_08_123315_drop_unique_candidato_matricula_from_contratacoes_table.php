<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contratacoes', function (Blueprint $table) {
            if ($this->indiceExiste('contratacoes', 'contratacoes_candidato_matricula_unique')) {
                $table->dropUnique('contratacoes_candidato_matricula_unique');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contratacoes', function (Blueprint $table) {
            if (! $this->indiceExiste('contratacoes', 'contratacoes_candidato_matricula_unique')) {
                $table->unique('candidato_matricula', 'contratacoes_candidato_matricula_unique');
            }
        });
    }

    private function indiceExiste(string $tabela, string $indice): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$tabela}')"))
                ->contains(fn ($item) => ($item->name ?? null) === $indice);
        }

        return collect(DB::select('SHOW INDEX FROM ' . $tabela . ' WHERE Key_name = ?', [$indice]))->isNotEmpty();
    }
};
