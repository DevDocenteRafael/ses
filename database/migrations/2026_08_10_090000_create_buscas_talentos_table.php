<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buscas_talentos', function (Blueprint $table) {
            $table->id();
            $table->string('empresa_cnpj', 14)->nullable();
            // Filtros aplicados na busca, ex.: {"segmento":"...","tipo_curso":"..."}
            $table->text('filtros');
            $table->dateTime('buscado_em');
            $table->timestamps();

            $table->foreign('empresa_cnpj')
                  ->references('cnpj')
                  ->on('empresa')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buscas_talentos');
    }
};
