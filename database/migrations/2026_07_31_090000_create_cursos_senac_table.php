<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cursos curtos concluídos no Senac (ex.: Lógica de Programação, 40h).
        // Sincronizado via API do SIG (FR4), assim como dados_academicos —
        // não é editável/removível pelo candidato na tela de perfil.
        Schema::create('cursos_senac', function (Blueprint $table) {
            $table->id();
            $table->string('nome_curso', 100);
            $table->string('unidade', 45);
            $table->unsignedInteger('carga_horaria')->nullable();
            $table->date('concluido_em');
            $table->integer('candidato_matricula');
            $table->timestamps();

            $table->foreign('candidato_matricula')
                  ->references('matricula')
                  ->on('candidato')
                  ->onDelete('cascade')
                  ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cursos_senac');
    }
};
