<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona `status` (liberado/bloqueado) à tabela `empresa`, no mesmo
 * padrão já usado em `candidato.status`. Necessário para a tela
 * "Gestão de Empresas" (FR35), que controla o acesso de parceiros
 * corporativos com um simples liberar/bloquear — substituindo o
 * rascunho anterior de "aprovação de cadastro", que era mantido só em
 * memória no front (sem persistência real).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->boolean('status')->default(true)->after('atividade_economica');
        });
    }

    public function down(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
