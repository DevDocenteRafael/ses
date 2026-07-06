<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convites', function (Blueprint $table) {
            $table->id();
            $table->string('descricao', 150);
            $table->dateTime('data_envio');
            $table->tinyInteger('status');
            $table->string('empresa_cnpj', 18);
            $table->integer('candidatos_matricula');
            $table->unsignedBigInteger('vagas_id_vaga');
            $table->timestamps();

            $table->foreign('empresa_cnpj', 18)
                  ->references('cnpj')
                  ->on('empresa')
                  ->onDelete('no action')
                  ->onUpdate('no action');

            $table->foreign('candidatos_matricula')
                  ->references('matricula')
                  ->on('candidato')
                  ->onDelete('no action')
                  ->onUpdate('no action');

            $table->foreign('vagas_id_vaga')
                  ->references('id_vaga')
                  ->on('vagas')
                  ->onDelete('no action')
                  ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convites');
    }
};