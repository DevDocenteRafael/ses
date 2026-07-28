<?php

namespace Database\Seeders;

use App\Models\Administrativo;
use App\Models\Pessoa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * TEMPORÁRIO: cria um usuário administrativo para permitir login e
 * validação manual do painel administrativo, sem precisar de uma
 * tela de cadastro (que ainda não existe para esse perfil).
 *
 * Remover este seeder (e a chamada em DatabaseSeeder) quando o
 * cadastro de administrativo for implementado de verdade.
 *
 * Credenciais de login:
 *   email: admin@senac.df.br
 *   senha: senac123
 */
class AdministrativoTesteSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'admin@senac.df.br';

        if (Pessoa::where('email', $email)->exists()) {
            $this->command?->info('Administrativo de teste já existe, pulando.');
            return;
        }

        $idPessoa = (int) (Pessoa::max('id_pessoa') ?? 0) + 1;

        $pessoa = Pessoa::create([
            'id_pessoa'     => $idPessoa,
            'nome'          => 'Administrador SENAC DF',
            'email'         => $email,
            'telefone'      => '61999990002',
            'senha'         => Hash::make('senac123'),
            'data_cadastro' => now(),
        ]);

        Administrativo::create([
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);

        $this->command?->info("Administrativo de teste criado: {$email} / senac123");
    }
}
