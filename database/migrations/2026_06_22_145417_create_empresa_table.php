<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa', function (Blueprint $table) {
            $table->id();

            $table->string('cnpj', 14)->unique();
            $table->string('razao_social', 45);
            $table->string('atividade_economica', 45);

            // FK para pessoa.id_pessoa
            $table->unsignedBigInteger('id_pessoa');

            // FK para responsavel_contratual.id_responsavel_contratual
            $table->unsignedBigInteger('responsavel_contratual_id_responsavel_contratual');

            $table->timestamps();

            $table->foreign(
                'id_pessoa',
                'fk_empresa_pessoa'
            )
            ->references('id_pessoa')
            ->on('pessoa')
            ->cascadeOnDelete();

            $table->foreign(
                'responsavel_contratual_id_responsavel_contratual',
                'fk_empresa_resp_contratual'
            )
            ->references('id_responsavel_contratual')
            ->on('responsavel_contratual')
            ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa');
    }
};