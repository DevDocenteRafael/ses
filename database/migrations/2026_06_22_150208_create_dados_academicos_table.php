<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dados_academicos', function (Blueprint $table) {
            $table->id();
            $table->string('instituicao', 100);
            $table->string('curso', 45);
            $table->string('unidade', 45);
            $table->date('ano_de_conclusao');
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
        Schema::dropIfExists('dados_academicos');
    }
};