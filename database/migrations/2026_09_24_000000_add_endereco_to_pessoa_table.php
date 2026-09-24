<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pessoa', function (Blueprint $table) {
            $table->string('endereco', 255)->nullable()->after('telefone');
        });
    }

    public function down(): void
    {
        Schema::table('pessoa', function (Blueprint $table) {
            $table->dropColumn('endereco');
        });
    }
};
