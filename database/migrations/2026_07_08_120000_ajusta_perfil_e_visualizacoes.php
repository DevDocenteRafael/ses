<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // habilidades_tags (integer) nunca comportou uma lista de tags de fato.
        // Passa a ser armazenado como texto (JSON), decodificado no model.
        Schema::table('informacoes_profissionais', function (Blueprint $table) {
            $table->dropColumn('habilidades_tags');
        });
        Schema::table('informacoes_profissionais', function (Blueprint $table) {
            $table->text('habilidades')->nullable()->after('area_de_atuacao');
        });

        // disponibilidade_de_horario era `time`, mas a UI trabalha com rótulos
        // (Manhã / Tarde-Noite / Integral), não horários exatos.
        Schema::table('preferencias_de_trabalho', function (Blueprint $table) {
            $table->dropColumn('disponibilidade_de_horario');
        });
        Schema::table('preferencias_de_trabalho', function (Blueprint $table) {
            $table->string('disponibilidade_de_horario', 30)->nullable()->after('tipo_de_contratacao');
        });

        // Registro de visualizações do perfil do candidato (dashboard / FR9).
        Schema::create('visualizacoes_perfil', function (Blueprint $table) {
            $table->id();
            $table->integer('candidato_matricula');
            $table->string('empresa_cnpj', 18)->nullable();
            $table->dateTime('visualizado_em');
            $table->timestamps();

            $table->foreign('candidato_matricula')
                  ->references('matricula')
                  ->on('candidato')
                  ->cascadeOnDelete();

            $table->foreign('empresa_cnpj')
                  ->references('cnpj')
                  ->on('empresa')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visualizacoes_perfil');

        Schema::table('preferencias_de_trabalho', function (Blueprint $table) {
            $table->dropColumn('disponibilidade_de_horario');
        });
        Schema::table('preferencias_de_trabalho', function (Blueprint $table) {
            $table->time('disponibilidade_de_horario')->nullable(false)->default('08:00:00');
        });

        Schema::table('informacoes_profissionais', function (Blueprint $table) {
            $table->dropColumn('habilidades');
        });
        Schema::table('informacoes_profissionais', function (Blueprint $table) {
            $table->integer('habilidades_tags')->nullable();
        });
    }
};
