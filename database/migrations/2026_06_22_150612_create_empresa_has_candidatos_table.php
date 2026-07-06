<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_has_candidatos', function (Blueprint $table) {

            $table->id();

            $table->string('empresa_cnpj', 14);

            $table->integer('candidatos_matricula');

            $table->timestamps();

            $table->unique([
                'empresa_cnpj',
                'candidatos_matricula'
            ], 'uk_empresa_candidato');

            $table->foreign(
                'empresa_cnpj',
                'fk_empresa_has_candidato_empresa'
            )
            ->references('cnpj')
            ->on('empresa')
            ->cascadeOnDelete();

            $table->foreign(
                'candidatos_matricula',
                'fk_empresa_has_candidato_candidato'
            )
            ->references('matricula')
            ->on('candidato')
            ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_has_candidatos');
    }
};