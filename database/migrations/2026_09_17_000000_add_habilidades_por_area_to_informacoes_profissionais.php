<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('informacoes_profissionais', 'habilidades_por_area')) {
            return;
        }

        Schema::table('informacoes_profissionais', function (Blueprint $table) {
            $table->text('habilidades_por_area')->nullable()->after('habilidades');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('informacoes_profissionais', 'habilidades_por_area')) {
            return;
        }

        Schema::table('informacoes_profissionais', function (Blueprint $table) {
            $table->dropColumn('habilidades_por_area');
        });
    }
};
