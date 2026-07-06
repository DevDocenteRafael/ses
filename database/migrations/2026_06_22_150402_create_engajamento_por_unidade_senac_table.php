<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engajamento_por_unidade_senac', function (Blueprint $table) {

            $table->id();

            $table->string('unidade', 100);
            $table->boolean('elegibilidade')->default(false);
            $table->boolean('status')->default(false);

            $table->unsignedBigInteger('administrativo_pessoa_id_pessoa');

            $table->foreign(
                'administrativo_pessoa_id_pessoa',
                'fk_engajamento_adm'
            )
            ->references('pessoa_id_pessoa')
            ->on('administrativo')
            ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engajamento_por_unidade_senac');
    }
};