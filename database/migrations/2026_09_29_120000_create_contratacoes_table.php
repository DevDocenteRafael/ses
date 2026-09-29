<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratacoes', function (Blueprint $table) {
            $table->id();
            $table->string('candidato_matricula', 15)->unique();
            $table->string('empresa_cnpj', 14);
            $table->foreignId('registrado_por_pessoa_id')->constrained('pessoa', 'id_pessoa');
            $table->string('origem', 20);
            $table->date('contratado_em');
            $table->timestamps();

            $table->foreign('candidato_matricula')
                ->references('matricula')->on('candidato')->cascadeOnDelete();
            $table->foreign('empresa_cnpj')
                ->references('cnpj')->on('empresa')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratacoes');
    }
};
