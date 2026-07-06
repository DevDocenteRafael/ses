<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historico_de_engajamento', function (Blueprint $table) {

            $table->id();

            $table->integer('convites_enviados');
            $table->integer('contratacoes');

            $table->string('empresa_cnpj', 14);

            $table->timestamps();

            $table->foreign(
                'empresa_cnpj',
                'fk_historico_empresa'
            )
            ->references('cnpj')
            ->on('empresa')
            ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historico_de_engajamento');
    }
};