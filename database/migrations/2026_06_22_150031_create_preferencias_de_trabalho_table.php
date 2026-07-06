<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preferencias_de_trabalho', function (Blueprint $table) {
            $table->id();
            $table->integer('tipo_de_contratacao')->nullable();
            $table->time('disponibilidade_de_horario');
            $table->string('regiao_administrativa', 100);
            $table->integer('pretensao_salarial')->nullable();
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
        Schema::dropIfExists('preferencias_de_trabalho');
    }
};