<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Popula a gestão administrativa com empresas de demonstração.
 *
 * As empresas usam dados claramente fictícios, não possuem vagas nem
 * candidatos associados e podem ser semeadas novamente sem duplicação.
 */
class EmpresasFicticiasSeeder extends Seeder
{
    private const TOTAL = 3000;
    private const LOTE = 250;
    private const DOMINIO = 'empresas.senac.test';

    private const RAZOES_SOCIAIS = [
        'Tecnologia e Sistemas', 'Soluções Digitais', 'Comércio e Serviços',
        'Consultoria Empresarial', 'Logística Integrada', 'Gestão e Tecnologia',
        'Inovação e Desenvolvimento', 'Serviços Profissionais', 'Indústria e Comércio',
        'Marketing e Comunicação',
    ];

    private const ATIVIDADES = [
        'Tecnologia da Informação', 'Comércio varejista', 'Consultoria empresarial',
        'Serviços administrativos', 'Logística', 'Desenvolvimento de Software',
        'Marketing e publicidade', 'Recursos Humanos', 'Serviços educacionais',
        'Construção civil',
    ];

    private const NOMES = [
        'Ana Souza', 'Bruno Oliveira', 'Carla Santos', 'Diego Costa',
        'Elisa Pereira', 'Felipe Rodrigues', 'Gabriela Almeida', 'Hugo Lima',
        'Isabela Gomes', 'João Carvalho', 'Larissa Ribeiro', 'Marcos Ferreira',
    ];

    public function run(): void
    {
        $sequencialDisponivel = $this->proximoSequencialDisponivel();

        if ($sequencialDisponivel >= self::TOTAL) {
            $this->command?->info('Empresas fictícias já existem, pulando.');

            return;
        }

        $empresasExistentes = DB::table('empresa')
            ->whereIn('pessoa_id_pessoa', DB::table('pessoa')
                ->select('id_pessoa')
                ->where('email', 'like', '%@' . self::DOMINIO))
            ->count();

        // A tabela empresa recebe seu status em migration posterior ao schema base.
        $temStatus = DB::getSchemaBuilder()->hasColumn('empresa', 'status');
        $senha = Hash::make('senac123');
        $agora = now();
        $lotePessoas = [];
        $loteResponsaveis = [];
        $loteEmpresas = [];
        $criados = 0;
        $numeroInicial = $sequencialDisponivel;

        for ($numero = $numeroInicial; $numero < self::TOTAL; $numero++) {
            $sequencial = $numero + 1;
            $idPessoaEmpresa = $this->proximoIdPessoa++;
            $idPessoaResponsavel = $this->proximoIdPessoa++;
            $idResponsavel = $this->proximoIdResponsavel++;
            $cnpj = '90000000' . str_pad((string) $sequencial, 6, '0', STR_PAD_LEFT);
            $nomeEmpresa = self::RAZOES_SOCIAIS[$numero % count(self::RAZOES_SOCIAIS)] . ' ' . str_pad((string) $sequencial, 4, '0', STR_PAD_LEFT);
            $slug = str_pad((string) $sequencial, 4, '0', STR_PAD_LEFT);

            $lotePessoas[] = [
                'id_pessoa' => $idPessoaEmpresa,
                'nome' => $nomeEmpresa,
                'email' => "rh{$slug}@" . self::DOMINIO,
                'telefone' => '61' . str_pad((string) (10000000 + $sequencial * 2), 9, '0', STR_PAD_LEFT),
                'senha' => $senha,
                'data_cadastro' => $agora,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
            $lotePessoas[] = [
                'id_pessoa' => $idPessoaResponsavel,
                'nome' => self::NOMES[$numero % count(self::NOMES)],
                'email' => "responsavel{$slug}@" . self::DOMINIO,
                'telefone' => '61' . str_pad((string) (10000001 + $sequencial * 2), 9, '0', STR_PAD_LEFT),
                'senha' => $senha,
                'data_cadastro' => $agora,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
            $loteResponsaveis[] = [
                'id_responsavel_contratual' => $idResponsavel,
                'pessoa_id_pessoa' => $idPessoaResponsavel,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];

            $empresa = [
                'cnpj' => $cnpj,
                'razao_social' => $nomeEmpresa,
                'atividade_economica' => self::ATIVIDADES[$numero % count(self::ATIVIDADES)],
                'pessoa_id_pessoa' => $idPessoaEmpresa,
                'responsavel_contratual_id_responsavel_contratual' => $idResponsavel,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];

            if ($temStatus) {
                $empresa['status'] = $sequencial % 10 !== 0;
            }

            $loteEmpresas[] = $empresa;

            if (count($loteEmpresas) === self::LOTE) {
                $this->inserirLote($lotePessoas, $loteResponsaveis, $loteEmpresas);
                $criados += count($loteEmpresas);
                $lotePessoas = $loteResponsaveis = $loteEmpresas = [];
            }
        }

        if ($loteEmpresas !== []) {
            $this->inserirLote($lotePessoas, $loteResponsaveis, $loteEmpresas);
            $criados += count($loteEmpresas);
        }

        $this->command?->info("{$criados} empresas fictícias criadas.");
        $this->command?->info('Acesso de demonstração: senha senac123; e-mails rhNNNN@' . self::DOMINIO);
    }

    private int $proximoIdPessoa;
    private int $proximoIdResponsavel;

    private function proximoSequencialDisponivel(): int
    {
        $ultimoCnpj = DB::table('empresa')
            ->where('cnpj', 'like', '90000000%')
            ->max('cnpj');

        return max(0, (int) substr((string) $ultimoCnpj, 8));
    }

    private function inserirLote(array $pessoas, array $responsaveis, array $empresas): void
    {
        DB::transaction(function () use ($pessoas, $responsaveis, $empresas): void {
            DB::table('pessoa')->insert($pessoas);
            DB::table('responsavel_contratual')->insert($responsaveis);
            DB::table('empresa')->insert($empresas);
        });
    }

    public function __construct()
    {
        $this->proximoIdPessoa = (int) DB::table('pessoa')->max('id_pessoa') + 1;
        $this->proximoIdResponsavel = (int) DB::table('responsavel_contratual')->max('id_responsavel_contratual') + 1;
    }
}
