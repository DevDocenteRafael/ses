<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('informacoes_profissionais', function (Blueprint $table) {
            $table->id();
            $table->string('sobre_mim', 200)->nullable();
            $table->string('cargo_de_interesse', 45)->nullable();
            $table->string('area_de_atuacao', 45);
            $table->integer('habilidades_tags')->nullable();
            $table->integer('candidato_matricula');
            $table->timestamps();

            $table->foreign('candidato_matricula')
                  ->references('matricula')
                  ->on('candidato')
                  ->onDelete('no action')
                  ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('informacoes_profissionais');
    }
};