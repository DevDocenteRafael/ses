<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Pessoa;
use App\Models\ResponsavelContratual;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Cria uma empresa de teste para permitir login e validação manual
 * do painel da empresa (Dashboard, Buscar Talentos, Favoritos,
 * Convites, Minhas Vagas) sem precisar passar pelo cadastro público
 * (que ainda não existe).
 *
 * Credenciais de login:
 *   email: rh@techsolutions.com.br
 *   senha: senac123
 *
 * Nota: Pessoa::$incrementing = false, então o id_pessoa precisa ser
 * informado manualmente na criação (o Eloquent não busca o
 * autoincrement do banco sozinho nesse caso).
 */
class EmpresaTesteSeeder extends Seeder
{
    public function run(): void
    {
        $cnpj = '12345678000199';

        if (Empresa::where('cnpj', $cnpj)->exists()) {
            $this->command?->info('Empresa de teste já existe, pulando.');
            return;
        }

        $idPessoaEmpresa = $this->proximoIdPessoa();

        // Pessoa da empresa (quem efetivamente faz login no painel)
        $pessoaEmpresa = Pessoa::create([
            'id_pessoa'     => $idPessoaEmpresa,
            'nome'          => 'Tech Solutions DF',
            'email'         => 'rh@techsolutions.com.br',
            'telefone'      => '61999990000',
            'senha'         => Hash::make('senac123'),
            'data_cadastro' => now(),
        ]);

        // Pessoa do responsável contratual
        $pessoaResponsavel = Pessoa::create([
            'id_pessoa'     => $idPessoaEmpresa + 1,
            'nome'          => 'Roberto Santos',
            'email'         => 'roberto.santos@techsolutions.com.br',
            'telefone'      => '61999990001',
            'senha'         => Hash::make('senac123'),
            'data_cadastro' => now(),
        ]);

        $responsavel = ResponsavelContratual::create([
            'pessoa_id_pessoa' => $pessoaResponsavel->id_pessoa,
        ]);

        Empresa::create([
            'cnpj'                                            => $cnpj,
            'razao_social'                                    => 'Tech Solutions DF',
            'atividade_economica'                             => 'Desenvolvimento de Software',
            'pessoa_id_pessoa'                                => $pessoaEmpresa->id_pessoa,
            'responsavel_contratual_id_responsavel_contratual' => $responsavel->id_responsavel_contratual,
        ]);

        $this->command?->info('Empresa de teste criada: rh@techsolutions.com.br / senac123');
    }

    /**
     * Pessoa.id_pessoa não é autoincrement no Eloquent (incrementing =
     * false), então calculamos o próximo ID livre manualmente.
     */
    private function proximoIdPessoa(): int
    {
        return (int) (Pessoa::max('id_pessoa') ?? 0) + 1;
    }
}