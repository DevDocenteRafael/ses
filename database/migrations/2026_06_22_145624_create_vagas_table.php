<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vagas', function (Blueprint $table) {

            $table->id('id_vaga');

            $table->string('titulo', 100);

            $table->integer('tipo');

            $table->string('area', 45);

            $table->tinyInteger('status');

            $table->date('data_publicacao');

            $table->foreignId('empresa_id')
                ->constrained('empresa');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vagas');
    }
};
