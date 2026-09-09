<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('preferencias_de_trabalho')) {
            return;
        }

        DB::table('preferencias_de_trabalho')
            ->where('tipo_de_contratacao', 4)
            ->update(['tipo_de_contratacao' => null]);

        DB::table('preferencias_de_trabalho')
            ->whereRaw('(tipo_de_contratacao & 4) != 0')
            ->update(['tipo_de_contratacao' => DB::raw('tipo_de_contratacao - 4')]);
    }

    public function down(): void
    {
        // Não é possível restaurar com segurança quais candidatos tinham o bit
        // removido de Jovem Aprendiz sem inventar dados. O rollback mantém os
        // valores atuais de CLT/Estágio preservados.
    }
};
