<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $preferencias = DB::table('preferencias_de_trabalho')
            ->select('id', 'disponibilidade_de_horario')
            ->get();

        Schema::table('preferencias_de_trabalho', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                $table->text('disponibilidade_de_horario_json')->nullable()->after('tipo_de_contratacao');
            } else {
                $table->json('disponibilidade_de_horario_json')->nullable()->after('tipo_de_contratacao');
            }
        });

        foreach ($preferencias as $preferencia) {
            DB::table('preferencias_de_trabalho')
                ->where('id', $preferencia->id)
                ->update([
                    'disponibilidade_de_horario_json' => json_encode(
                        $this->normalizarParaLista($preferencia->disponibilidade_de_horario),
                        JSON_UNESCAPED_UNICODE
                    ),
                ]);
        }

        Schema::table('preferencias_de_trabalho', function (Blueprint $table) {
            $table->dropColumn('disponibilidade_de_horario');
        });

        Schema::table('preferencias_de_trabalho', function (Blueprint $table) {
            $table->renameColumn('disponibilidade_de_horario_json', 'disponibilidade_de_horario');
        });
    }

    public function down(): void
    {
        $preferencias = DB::table('preferencias_de_trabalho')
            ->select('id', 'disponibilidade_de_horario')
            ->get();

        Schema::table('preferencias_de_trabalho', function (Blueprint $table) {
            $table->string('disponibilidade_de_horario_legado', 30)->nullable()->after('tipo_de_contratacao');
        });

        foreach ($preferencias as $preferencia) {
            DB::table('preferencias_de_trabalho')
                ->where('id', $preferencia->id)
                ->update([
                    'disponibilidade_de_horario_legado' => $this->normalizarParaLista($preferencia->disponibilidade_de_horario)[0] ?? null,
                ]);
        }

        Schema::table('preferencias_de_trabalho', function (Blueprint $table) {
            $table->dropColumn('disponibilidade_de_horario');
        });

        Schema::table('preferencias_de_trabalho', function (Blueprint $table) {
            $table->renameColumn('disponibilidade_de_horario_legado', 'disponibilidade_de_horario');
        });
    }

    private function normalizarParaLista(mixed $valor): array
    {
        if ($valor === null || $valor === '') {
            return [];
        }

        $decodificado = is_string($valor) ? json_decode($valor, true) : null;
        $itens = is_array($decodificado) ? $decodificado : [$valor];
        $permitidos = ['Manhã', 'Tarde', 'Noite', 'Integral'];

        return array_values(array_intersect($permitidos, array_unique(array_filter(array_map(
            fn ($item) => trim((string) $item),
            $itens
        )))));
    }
};
