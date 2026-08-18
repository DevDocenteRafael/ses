<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            // Caso a coluna id_pessoa ainda exista, renomeia ou ajusta
            if (Schema::hasColumn('empresa', 'id_pessoa')) {
                $table->renameColumn('id_pessoa', 'pessoa_id_pessoa');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            if (Schema::hasColumn('empresa', 'pessoa_id_pessoa')) {
                $table->renameColumn('pessoa_id_pessoa', 'id_pessoa');
            }
        });
    }
};