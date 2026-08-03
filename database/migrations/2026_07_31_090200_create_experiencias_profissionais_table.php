<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiencias_profissionais', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30); // Estágio, CLT, Freelancer, Voluntário...
            $table->string('cargo', 100);
            $table->string('empresa', 100);
            $table->string('local', 100)->nullable();
            $table->date('data_inicio');
            $table->date('data_fim')->nullable(); // null = experiência atual
            $table->text('descricao')->nullable();
            $table->integer('candidato_matricula');
            $table->timestamps();

            $table->foreign('candidato_matricula')
                  ->references('matricula')
                  ->on('candidato')
                  ->onDelete('cascade')
                  ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiencias_profissionais');
    }
};
