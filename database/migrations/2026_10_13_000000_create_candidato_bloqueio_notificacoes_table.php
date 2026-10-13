<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidato_bloqueio_notificacoes', function (Blueprint $table) {
            $table->id();
            $table->string('candidato_matricula', 15);
            $table->string('tipo_bloqueio', 50);
            $table->timestamp('bloqueado_em');
            $table->timestamp('tentativa_envio_em')->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->string('status', 30)->default('pendente');
            $table->text('motivo_publico')->nullable();
            $table->text('erro')->nullable();
            $table->timestamps();

            $table->foreign('candidato_matricula')
                ->references('matricula')
                ->on('candidato')
                ->cascadeOnDelete();
            $table->unique(['candidato_matricula', 'tipo_bloqueio', 'bloqueado_em'], 'candidato_bloqueio_notificacao_evento_unique');
            $table->index(['candidato_matricula', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidato_bloqueio_notificacoes');
    }
};
