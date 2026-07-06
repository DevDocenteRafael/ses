<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_externo', function (Blueprint $table) {
            $table->id();
            $table->string('linkedin', 100)->nullable();
            $table->string('portfolio', 100)->nullable();
            $table->string('github', 100)->nullable();
            $table->integer('candidato_matricula');
            $table->timestamps();

            $table->foreign('candidato_matricula')
                  ->references('matricula')
                  ->on('candidato')
                  ->onDelete('no action')
                  ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_externo');
    }
};