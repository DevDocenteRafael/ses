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
        Schema::table('candidato', function (Blueprint $table) {
            $table->timestamp('ultima_atividade_em')->nullable()->after('status')->index();
        });

        DB::table('candidato')->update(['ultima_atividade_em' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidato', function (Blueprint $table) {
            $table->dropIndex(['ultima_atividade_em']);
            $table->dropColumn('ultima_atividade_em');
        });
    }
};
