<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('preferencias_de_trabalho', function (Blueprint $table) {
            $table->decimal('pretensao_salarial', 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('preferencias_de_trabalho', function (Blueprint $table) {
            $table->integer('pretensao_salarial')->nullable()->change();
        });
    }
};
