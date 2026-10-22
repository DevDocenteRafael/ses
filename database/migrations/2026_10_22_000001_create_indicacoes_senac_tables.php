<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indicações Senac: o administrativo indica candidatos para empresas específicas.
     * Apenas cria tabelas novas; nenhuma tabela, coluna ou registro existente é alterado.
     */
    public function up(): void
    {
        Schema::create('indicacoes_senac', function (Blueprint $table) {
            $table->id();
            $table->string('candidato_matricula', 15);
            $table->string('empresa_cnpj', 14);
            $table->foreignId('indicado_por_pessoa_id')->nullable()->constrained('pessoa', 'id_pessoa')->nullOnDelete();
            $table->text('observacao')->nullable();
            $table->string('status', 30)->default('NOVA');
            // 1 = indicação vigente; NULL = encerrada ("Sem interesse"). Como NULL não colide no índice
            // único abaixo, só pode existir UMA indicação vigente por candidato + empresa, sem apagar histórico.
            $table->unsignedTinyInteger('ativa')->nullable()->default(1);
            $table->timestamp('indicado_em');
            $table->timestamps();

            $table->foreign('candidato_matricula', 'ind_senac_candidato_fk')
                ->references('matricula')->on('candidato')->cascadeOnDelete();
            $table->foreign('empresa_cnpj', 'ind_senac_empresa_fk')
                ->references('cnpj')->on('empresa')->restrictOnDelete();

            $table->unique(['candidato_matricula', 'empresa_cnpj', 'ativa'], 'ind_senac_par_ativo_unique');
            $table->index(['empresa_cnpj', 'status'], 'ind_senac_empresa_status_idx');
            $table->index('indicado_em', 'ind_senac_indicado_em_idx');
        });

        // Histórico somente de inserção: cada criação e mudança de status vira um evento.
        Schema::create('indicacao_senac_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicacao_id')->constrained('indicacoes_senac')->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->string('status_anterior', 30)->nullable();
            $table->string('status_novo', 30);
            $table->foreignId('pessoa_id')->nullable()->constrained('pessoa', 'id_pessoa')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicacao_senac_eventos');
        Schema::dropIfExists('indicacoes_senac');
    }
};
