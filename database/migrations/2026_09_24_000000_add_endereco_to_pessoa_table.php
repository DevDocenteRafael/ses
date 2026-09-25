<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pessoa', function (Blueprint $table) {
            if (! Schema::hasColumn('pessoa', 'endereco_cep')) {
                $table->string('endereco_cep', 8)->nullable()->after('telefone');
            }

            if (! Schema::hasColumn('pessoa', 'endereco_logradouro')) {
                $table->string('endereco_logradouro', 120)->nullable()->after('endereco_cep');
            }

            if (! Schema::hasColumn('pessoa', 'endereco_numero')) {
                $table->string('endereco_numero', 20)->nullable()->after('endereco_logradouro');
            }

            if (! Schema::hasColumn('pessoa', 'endereco_complemento')) {
                $table->string('endereco_complemento', 80)->nullable()->after('endereco_numero');
            }

            if (! Schema::hasColumn('pessoa', 'endereco_bairro')) {
                $table->string('endereco_bairro', 80)->nullable()->after('endereco_complemento');
            }

            if (! Schema::hasColumn('pessoa', 'endereco_cidade')) {
                $table->string('endereco_cidade', 80)->nullable()->after('endereco_bairro');
            }

            if (! Schema::hasColumn('pessoa', 'endereco_uf')) {
                $table->string('endereco_uf', 2)->nullable()->after('endereco_cidade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pessoa', function (Blueprint $table) {
            $colunas = [
                'endereco_cep',
                'endereco_logradouro',
                'endereco_numero',
                'endereco_complemento',
                'endereco_bairro',
                'endereco_cidade',
                'endereco_uf',
            ];

            foreach ($colunas as $coluna) {
                if (Schema::hasColumn('pessoa', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
