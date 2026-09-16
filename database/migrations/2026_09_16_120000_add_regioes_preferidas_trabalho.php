<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preferencias_de_trabalho', function (Blueprint $table) {
            $table->boolean('aceita_todas_regioes')->nullable()->after('regiao_administrativa');
        });

        Schema::create('regioes_preferidas_trabalho', function (Blueprint $table) {
            $table->id();
            $table->string('candidato_matricula', 15);
            $table->unsignedTinyInteger('codigo_regiao');
            $table->timestamps();

            $table->unique(['candidato_matricula', 'codigo_regiao'], 'regioes_pref_trab_candidato_codigo_unique');
            $table->foreign('candidato_matricula', 'regioes_pref_trab_candidato_foreign')
                ->references('matricula')
                ->on('candidato')
                ->cascadeOnDelete()
                ->noActionOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regioes_preferidas_trabalho');

        Schema::table('preferencias_de_trabalho', function (Blueprint $table) {
            $table->dropColumn('aceita_todas_regioes');
        });
    }
};
