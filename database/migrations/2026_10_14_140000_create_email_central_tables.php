<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_modelos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 80)->unique();
            $table->string('nome', 120);
            $table->string('tipo_notificacao', 80)->nullable();
            $table->text('descricao')->nullable();
            $table->string('assunto_padrao', 200)->nullable();
            $table->longText('conteudo_html')->nullable();
            $table->longText('conteudo_rascunho')->nullable();
            $table->string('status_configuracao', 40)->default('ativo');
            $table->unsignedBigInteger('administrador_pessoa_id')->nullable();
            $table->integer('versao_atual')->default(1);
            $table->timestamp('ultima_alteracao_em')->nullable();
            $table->timestamp('publicado_em')->nullable();
            $table->json('variaveis_permitidas')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('email_modelos_versoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_modelo_id')->constrained('email_modelos')->cascadeOnDelete();
            $table->integer('versao');
            $table->string('assunto_anterior', 200)->nullable();
            $table->string('assunto_novo', 200)->nullable();
            $table->longText('conteudo_html');
            $table->string('acao', 50)->default('publicado');
            $table->unsignedBigInteger('administrador_pessoa_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['email_modelo_id', 'versao'], 'email_modelos_versoes_unique');
            $table->index(['email_modelo_id', 'created_at'], 'email_modelos_versoes_modelo_data_idx');
        });

        Schema::create('email_envios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_modelo_id')->nullable()->constrained('email_modelos')->nullOnDelete();
            $table->foreignId('email_modelo_versao_id')->nullable()->constrained('email_modelos_versoes')->nullOnDelete();
            $table->string('candidato_matricula', 15)->nullable();
            $table->string('destinatario_email', 255);
            $table->string('tipo_mensagem', 80)->nullable();
            $table->string('assunto', 200);
            $table->string('origem', 30)->default('manual');
            $table->string('status', 30)->default('pendente');
            $table->string('administrador_nome', 120)->nullable();
            $table->unsignedBigInteger('administrador_pessoa_id')->nullable();
            $table->text('motivo_falha')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'email_envios_status_data_idx');
            $table->index(['destinatario_email', 'created_at'], 'email_envios_destinatario_data_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_envios');
        Schema::dropIfExists('email_modelos_versoes');
        Schema::dropIfExists('email_modelos');
    }
};
