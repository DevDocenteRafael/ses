<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alunos_migrados', function (Blueprint $table) {

            $table->id('id_aluno');

            $table->boolean('status_ativacao');

            $table->dateTime('ultima_sincronizacao')->nullable();

            $table->unsignedBigInteger('administrativo_pessoa_id_pessoa');

            $table->timestamps();

            $table->foreign(
                'administrativo_pessoa_id_pessoa',
                'fk_aluno_migrado_adm'
            )
            ->references('pessoa_id_pessoa')
            ->on('administrativo')
            ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alunos_migrados');
    }
};