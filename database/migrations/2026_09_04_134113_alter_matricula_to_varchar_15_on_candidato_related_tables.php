<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    private const FK_DEFINITIONS = [
        ['table' => 'convites', 'column' => 'candidatos_matricula', 'constraint' => 'convites_candidatos_matricula_foreign', 'on_delete' => 'NO ACTION', 'on_update' => 'NO ACTION'],
        ['table' => 'cursos_externos', 'column' => 'candidato_matricula', 'constraint' => 'cursos_externos_candidato_matricula_foreign', 'on_delete' => 'CASCADE', 'on_update' => 'NO ACTION'],
        ['table' => 'cursos_senac', 'column' => 'candidato_matricula', 'constraint' => 'cursos_senac_candidato_matricula_foreign', 'on_delete' => 'CASCADE', 'on_update' => 'NO ACTION'],
        ['table' => 'dados_academicos', 'column' => 'candidato_matricula', 'constraint' => 'dados_academicos_candidato_matricula_foreign', 'on_delete' => 'NO ACTION', 'on_update' => 'NO ACTION'],
        ['table' => 'empresa_has_candidatos', 'column' => 'candidatos_matricula', 'constraint' => 'fk_empresa_has_candidato_candidato', 'on_delete' => 'CASCADE', 'on_update' => 'NO ACTION'],
        ['table' => 'experiencias_profissionais', 'column' => 'candidato_matricula', 'constraint' => 'experiencias_profissionais_candidato_matricula_foreign', 'on_delete' => 'CASCADE', 'on_update' => 'NO ACTION'],
        ['table' => 'informacoes_profissionais', 'column' => 'candidato_matricula', 'constraint' => 'informacoes_profissionais_candidato_matricula_foreign', 'on_delete' => 'NO ACTION', 'on_update' => 'NO ACTION'],
        ['table' => 'link_externo', 'column' => 'candidato_matricula', 'constraint' => 'link_externo_candidato_matricula_foreign', 'on_delete' => 'NO ACTION', 'on_update' => 'NO ACTION'],
        ['table' => 'preferencias_de_trabalho', 'column' => 'candidato_matricula', 'constraint' => 'preferencias_de_trabalho_candidato_matricula_foreign', 'on_delete' => 'NO ACTION', 'on_update' => 'NO ACTION'],
        ['table' => 'visualizacoes_perfil', 'column' => 'candidato_matricula', 'constraint' => 'visualizacoes_perfil_candidato_matricula_foreign', 'on_delete' => 'CASCADE', 'on_update' => 'NO ACTION'],
    ];

    private const RELATED_COLUMNS = [
        ['table' => 'convites', 'column' => 'candidatos_matricula'],
        ['table' => 'cursos_externos', 'column' => 'candidato_matricula'],
        ['table' => 'cursos_senac', 'column' => 'candidato_matricula'],
        ['table' => 'dados_academicos', 'column' => 'candidato_matricula'],
        ['table' => 'empresa_has_candidatos', 'column' => 'candidatos_matricula'],
        ['table' => 'experiencias_profissionais', 'column' => 'candidato_matricula'],
        ['table' => 'informacoes_profissionais', 'column' => 'candidato_matricula'],
        ['table' => 'link_externo', 'column' => 'candidato_matricula'],
        ['table' => 'preferencias_de_trabalho', 'column' => 'candidato_matricula'],
        ['table' => 'visualizacoes_perfil', 'column' => 'candidato_matricula'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->dropForeignKeys();
        $this->alterColumnsToVarchar();
        $this->recreateForeignKeys();
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->dropForeignKeys();
        $this->alterColumnsToInt();
        $this->recreateForeignKeys();
    }

    private function dropForeignKeys(): void
    {
        foreach (self::FK_DEFINITIONS as $definition) {
            DB::statement(sprintf(
                'ALTER TABLE %s DROP FOREIGN KEY %s',
                $definition['table'],
                $definition['constraint']
            ));
        }

        DB::statement('ALTER TABLE candidato DROP PRIMARY KEY');
    }

    private function alterColumnsToVarchar(): void
    {
        DB::statement('ALTER TABLE candidato MODIFY matricula VARCHAR(15) NOT NULL');

        foreach (self::RELATED_COLUMNS as $definition) {
            DB::statement(sprintf(
                'ALTER TABLE %s MODIFY %s VARCHAR(15) NOT NULL',
                $definition['table'],
                $definition['column']
            ));
        }

        DB::statement('ALTER TABLE candidato ADD PRIMARY KEY (matricula)');
    }

    private function alterColumnsToInt(): void
    {
        DB::statement('ALTER TABLE candidato MODIFY matricula INT NOT NULL');

        foreach (self::RELATED_COLUMNS as $definition) {
            DB::statement(sprintf(
                'ALTER TABLE %s MODIFY %s INT NOT NULL',
                $definition['table'],
                $definition['column']
            ));
        }

        DB::statement('ALTER TABLE candidato ADD PRIMARY KEY (matricula)');
    }

    private function recreateForeignKeys(): void
    {
        foreach (self::FK_DEFINITIONS as $definition) {
            DB::statement(sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES candidato(matricula) ON DELETE %s ON UPDATE %s',
                $definition['table'],
                $definition['constraint'],
                $definition['column'],
                $definition['on_delete'],
                $definition['on_update']
            ));
        }
    }
};
