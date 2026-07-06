<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidato', function (Blueprint $table) {

            $table->integer('matricula')->primary();

            $table->string('cpf', 14)->unique();

            $table->tinyInteger('status');

            // Mesmo tipo de pessoa.id_pessoa
            $table->unsignedBigInteger('pessoa_id_pessoa');

            $table->timestamps();

            $table->foreign(
                'pessoa_id_pessoa',
                'fk_candidato_pessoa'
            )
            ->references('id_pessoa')
            ->on('pessoa')
            ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidato');
    }
};