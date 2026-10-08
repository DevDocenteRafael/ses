<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contratacoes', function (Blueprint $table) {
            $table->string('status', 20)->default('vigente')->after('contratado_em');
            $table->timestamp('cancelado_em')->nullable()->after('status');
            $table->foreignId('cancelado_por_pessoa_id')->nullable()->after('cancelado_em')->constrained('pessoa', 'id_pessoa')->nullOnDelete();
            $table->string('motivo_cancelamento', 100)->nullable()->after('cancelado_por_pessoa_id');
            $table->text('observacao_cancelamento')->nullable()->after('motivo_cancelamento');
            $table->json('historico_alteracoes')->nullable()->after('observacao_cancelamento');
            $table->index(['candidato_matricula', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contratacoes', function (Blueprint $table) {
            $table->dropIndex(['candidato_matricula', 'status']);
            $table->dropConstrainedForeignId('cancelado_por_pessoa_id');
            $table->dropColumn([
                'status',
                'cancelado_em',
                'motivo_cancelamento',
                'observacao_cancelamento',
                'historico_alteracoes',
            ]);
        });
    }
};
