<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidato_reativacao_questionarios', function (Blueprint $table) {
            $table->id();
            $table->string('candidato_matricula', 15);
            $table->timestamp('bloqueado_em')->nullable();
            $table->unsignedBigInteger('bloqueio_notificacao_id')->nullable();
            $table->string('resposta_1', 50);
            $table->string('resposta_2', 80)->nullable();
            $table->string('resposta_3', 80)->nullable();
            $table->json('resposta_4')->nullable();
            $table->string('resposta_5', 80)->nullable();
            $table->string('resposta_6', 80)->nullable();
            $table->string('resposta_7', 80)->nullable();
            $table->string('resposta_8', 80)->nullable();
            $table->string('resultado', 50);
            $table->json('orientacoes')->nullable();
            $table->timestamp('concluido_em');
            $table->timestamps();

            $table->foreign('candidato_matricula', 'crq_candidato_fk')->references('matricula')->on('candidato')->cascadeOnDelete();
            $table->foreign('bloqueio_notificacao_id', 'crq_bloqueio_fk')->references('id')->on('candidato_bloqueio_notificacoes')->nullOnDelete();
            $table->unique(['candidato_matricula', 'bloqueado_em'], 'candidato_questionario_reativacao_evento_unique');
            $table->index(['candidato_matricula', 'resultado'], 'crq_candidato_resultado_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidato_reativacao_questionarios');
    }
};
