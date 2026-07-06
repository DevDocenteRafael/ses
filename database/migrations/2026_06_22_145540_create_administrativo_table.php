<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrativo', function (Blueprint $table) {

            // Mesmo tipo de pessoa.id_pessoa
            $table->unsignedBigInteger('pessoa_id_pessoa')->primary();

            $table->timestamps();

            $table->foreign(
                'pessoa_id_pessoa',
                'fk_administrativo_pessoa'
            )
            ->references('id_pessoa')
            ->on('pessoa')
            ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administrativo');
    }
};