<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tabela `vagas` foi criada referenciando `empresa_id` (a coluna
 * autoincrement interna da empresa), mas o restante da aplicação
 * (Vaga::$fillable, Vaga::empresa(), VagaController@store) sempre
 * trabalhou com `empresa_cnpj` — assim como `convites` e
 * `empresa_has_candidatos` já fazem. Esta migration corrige a
 * divergência recriando a tabela (sem perda de dados: 0 registros
 * em produção/homologação até o momento desta correção).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('vagas');

        Schema::create('vagas', function (Blueprint $table) {
            $table->id('id_vaga');
            $table->string('titulo', 100);
            $table->integer('tipo');
            $table->string('area', 45);
            $table->tinyInteger('status');
            $table->date('data_publicacao');
            $table->string('empresa_cnpj', 18);
            $table->timestamps();

            $table->foreign('empresa_cnpj')
                  ->references('cnpj')
                  ->on('empresa')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vagas');

        Schema::create('vagas', function (Blueprint $table) {
            $table->id('id_vaga');
            $table->string('titulo', 100);
            $table->integer('tipo');
            $table->string('area', 45);
            $table->tinyInteger('status');
            $table->date('data_publicacao');
            $table->foreignId('empresa_id')->constrained('empresa');
            $table->timestamps();
        });
    }
};
