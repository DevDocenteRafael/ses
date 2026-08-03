<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cursos feitos fora do Senac, cadastrados manualmente pelo candidato.
        Schema::create('cursos_externos', function (Blueprint $table) {
            $table->id();
            $table->string('nome_curso', 100);
            $table->string('instituicao', 100);
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
        Schema::dropIfExists('cursos_externos');
    }
};
