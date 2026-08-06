<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dados_academicos', function (Blueprint $table) {
            // Eixo tecnológico do Senac (ex.: "Tecnologia e Economia Criativa").
            $table->string('segmento', 60)->nullable()->after('curso');
            // Tipo de curso: livre, tecnico, graduacao, pos-graduacao, extensao...
            $table->string('tipo_curso', 30)->nullable()->after('segmento');
        });
    }

    public function down(): void
    {
        Schema::table('dados_academicos', function (Blueprint $table) {
            $table->dropColumn(['segmento', 'tipo_curso']);
        });
    }
};
