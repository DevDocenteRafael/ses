<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('candidato_inatividade_campanhas', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30);
            $table->string('status', 30)->default('pendente');
            $table->unsignedBigInteger('administrador_pessoa_id')->nullable();
            $table->unsignedInteger('quantidade_prevista')->default(0);
            $table->unsignedInteger('pendentes')->default(0);
            $table->unsignedInteger('enviados')->default(0);
            $table->unsignedInteger('ignorados')->default(0);
            $table->unsignedInteger('falhas')->default(0);
            $table->timestamp('iniciado_em')->nullable();
            $table->timestamp('finalizado_em')->nullable();
            $table->timestamps();

            $table->foreign('administrador_pessoa_id')
                ->references('id_pessoa')
                ->on('pessoa')
                ->nullOnDelete();
            $table->index(['tipo', 'status']);
        });

        Schema::table('candidato_bloqueio_notificacoes', function (Blueprint $table) {
            $table->string('origem', 30)->default('automatico')->after('status');
            $table->unsignedBigInteger('administrador_pessoa_id')->nullable()->after('origem');
            $table->foreignId('campanha_id')->nullable()->after('administrador_pessoa_id')->constrained('candidato_inatividade_campanhas')->nullOnDelete();

            $table->foreign('administrador_pessoa_id')
                ->references('id_pessoa')
                ->on('pessoa')
                ->nullOnDelete();
            $table->index(['tipo_bloqueio', 'origem', 'status'], 'candidato_bloqueio_notificacoes_tipo_origem_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidato_bloqueio_notificacoes', function (Blueprint $table) {
            $table->dropIndex('candidato_bloqueio_notificacoes_tipo_origem_status_index');
            $table->dropConstrainedForeignId('campanha_id');
            $table->dropForeign(['administrador_pessoa_id']);
            $table->dropColumn(['origem', 'administrador_pessoa_id']);
        });

        Schema::dropIfExists('candidato_inatividade_campanhas');
    }
};
