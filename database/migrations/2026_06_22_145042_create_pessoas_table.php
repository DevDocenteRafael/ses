<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pessoa', function (Blueprint $table) {

            $table->id('id_pessoa');

            $table->string('nome', 100);

            $table->string('email', 100)
                  ->unique();

            $table->string('telefone', 11)
                  ->unique();

            $table->string('senha', 255);

            $table->dateTime('data_cadastro');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pessoa');
    }
};