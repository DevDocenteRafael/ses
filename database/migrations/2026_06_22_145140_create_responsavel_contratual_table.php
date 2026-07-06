<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responsavel_contratual', function (Blueprint $table) {

            $table->id('id_responsavel_contratual');

            $table->unsignedBigInteger('pessoa_id_pessoa');

            $table->timestamps();

            $table->foreign(
                'pessoa_id_pessoa',
                'fk_resp_contratual_pessoa'
            )
            ->references('id_pessoa')
            ->on('pessoa')
            ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responsavel_contratual');
    }
};