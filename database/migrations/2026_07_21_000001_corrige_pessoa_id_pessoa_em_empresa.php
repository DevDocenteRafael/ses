<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tabela `empresa` foi criada com a coluna `id_pessoa`, mas o
 * restante da aplicação (Empresa::$fillable, Empresa::pessoa(),
 * EmpresaController@store) sempre trabalhou com `pessoa_id_pessoa` —
 * o mesmo padrão já usado em `candidato` e `responsavel_contratual`.
 * Esta migration corrige a divergência recriando a tabela (sem perda
 * de dados: 0 registros até o momento desta correção).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('empresa');

        Schema::create('empresa', function (Blueprint $table) {
            $table->id();
            $table->string('cnpj', 14)->unique();
            $table->string('razao_social', 45);
            $table->string('atividade_economica', 45);

            $table->unsignedBigInteger('pessoa_id_pessoa');
            $table->unsignedBigInteger('responsavel_contratual_id_responsavel_contratual');

            $table->timestamps();

            $table->foreign('pessoa_id_pessoa', 'fk_empresa_pessoa')
                  ->references('id_pessoa')
                  ->on('pessoa')
                  ->cascadeOnDelete();

            $table->foreign('responsavel_contratual_id_responsavel_contratual', 'fk_empresa_resp_contratual')
                  ->references('id_responsavel_contratual')
                  ->on('responsavel_contratual')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa');

        Schema::create('empresa', function (Blueprint $table) {
            $table->id();
            $table->string('cnpj', 14)->unique();
            $table->string('razao_social', 45);
            $table->string('atividade_economica', 45);

            $table->unsignedBigInteger('id_pessoa');
            $table->unsignedBigInteger('responsavel_contratual_id_responsavel_contratual');

            $table->timestamps();

            $table->foreign('id_pessoa', 'fk_empresa_pessoa')
                  ->references('id_pessoa')
                  ->on('pessoa')
                  ->cascadeOnDelete();

            $table->foreign('responsavel_contratual_id_responsavel_contratual', 'fk_empresa_resp_contratual')
                  ->references('id_responsavel_contratual')
                  ->on('responsavel_contratual')
                  ->cascadeOnDelete();
        });
    }
};