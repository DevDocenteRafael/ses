<?php

namespace Database\Seeders;

use App\Models\Candidato;
use App\Models\Pessoa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * TEMPORÁRIO: cria um candidato (aluno) de teste para permitir login
 * e validação manual do painel do aluno (Dashboard, Meu Perfil,
 * Convites), sem precisar passar pelo cadastro público.
 *
 * Remover este seeder (e a chamada em DatabaseSeeder) quando o
 * cadastro de candidato for implementado de verdade.
 *
 * Credenciais de login:
 *   email: aluno@teste.com
 *   senha: senac123
 *
 * Nota: Pessoa::$incrementing = false, então o id_pessoa precisa ser
 * informado manualmente. A matrícula do candidato é usada como o
 * próprio id_pessoa (mesmo padrão do CandidatoController@store).
 */
class CandidatoTesteSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'aluno@teste.com';

        if (Pessoa::where('email', $email)->exists()) {
            $this->command?->info('Candidato de teste já existe, pulando.');
            return;
        }

        $matricula = (int) (Pessoa::max('id_pessoa') ?? 0) + 1;

        $pessoa = Pessoa::create([
            'id_pessoa'     => $matricula,
            'nome'          => 'Lucas Silva',
            'email'         => $email,
            'telefone'      => '61999990003',
            'senha'         => Hash::make('senac123'),
            'data_cadastro' => now(),
        ]);

        Candidato::create([
            'matricula'        => $matricula,
            'cpf'              => '12345678900',
            'status'           => true,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);

        $this->command?->info("Candidato de teste criado: {$email} / senac123");
    }
}