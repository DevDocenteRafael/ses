<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('situacoes_academicas', function (Blueprint $table) {
            $table->id();
            $table->integer('candidato_matricula');
            $table->string('nivel_escolaridade', 30);
            $table->string('nivel_escolaridade_concluido', 30)->nullable();
            $table->string('situacao_academica', 30);
            $table->string('curso', 100)->nullable();
            $table->string('instituicao', 100)->nullable();
            $table->date('data_inicio')->nullable();
            $table->date('previsao_conclusao')->nullable();
            $table->date('data_conclusao')->nullable();
            $table->string('descricao_situacao', 300)->nullable();
            $table->boolean('atual')->default(true);
            $table->timestamps();

            $table->foreign('candidato_matricula')
                ->references('matricula')
                ->on('candidato')
                ->onDelete('no action')
                ->onUpdate('no action');
            $table->index(['candidato_matricula', 'atual']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('situacoes_academicas');
    }
};
