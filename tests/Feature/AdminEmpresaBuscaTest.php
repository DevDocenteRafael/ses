<?php

namespace Tests\Feature;

use App\Models\Administrativo;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Models\ResponsavelContratual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminEmpresaBuscaTest extends TestCase
{
    use RefreshDatabase;

    public function test_busca_encontra_cnpj_que_contem_sequencia_309(): void
    {
        $token = $this->tokenAdministrativo();
        $empresa = $this->criarEmpresa('12309000000155', 'Empresa Sequencia CNPJ');

        $this->withToken($token)
            ->getJson('/api/empresas?busca=309')
            ->assertOk()
            ->assertJsonFragment(['cnpj' => $empresa->cnpj]);
    }

    public function test_busca_nao_encontra_cnpj_com_digitos_espalhados_13000009000155(): void
    {
        $token = $this->tokenAdministrativo();
        $empresa = $this->criarEmpresa('13000009000155', 'Empresa Sem Sequencia CNPJ');

        $this->withToken($token)
            ->getJson('/api/empresas?busca=309')
            ->assertOk()
            ->assertJsonMissing(['cnpj' => $empresa->cnpj]);
    }

    public function test_busca_encontra_cnpj_que_comeca_com_309(): void
    {
        $token = $this->tokenAdministrativo();
        $empresa = $this->criarEmpresa('30912345000100', 'Empresa CNPJ Inicia Sequencia');

        $this->withToken($token)
            ->getJson('/api/empresas?busca=309')
            ->assertOk()
            ->assertJsonFragment(['cnpj' => $empresa->cnpj]);
    }

    public function test_busca_nao_encontra_cnpj_com_digitos_espalhados_90300129000100(): void
    {
        $token = $this->tokenAdministrativo();
        $empresa = $this->criarEmpresa('90300129000100', 'Empresa CNPJ Digitos Espalhados');

        $this->withToken($token)
            ->getJson('/api/empresas?busca=309')
            ->assertOk()
            ->assertJsonMissing(['cnpj' => $empresa->cnpj]);
    }

    public function test_busca_encontra_cnpj_completo_sem_mascara(): void
    {
        $token = $this->tokenAdministrativo();
        $empresa = $this->criarEmpresa('90000000000003', 'Empresa CNPJ Sem Mascara');

        $this->withToken($token)
            ->getJson('/api/empresas?busca=90000000000003')
            ->assertOk()
            ->assertJsonFragment(['cnpj' => $empresa->cnpj]);
    }

    public function test_busca_encontra_cnpj_completo_com_mascara(): void
    {
        $token = $this->tokenAdministrativo();
        $empresa = $this->criarEmpresa('90000000000003', 'Empresa CNPJ Com Mascara');

        $this->withToken($token)
            ->getJson('/api/empresas?busca=90.000.000%2F0000-03')
            ->assertOk()
            ->assertJsonFragment(['cnpj' => $empresa->cnpj]);
    }

    public function test_busca_encontra_sequencia_309_no_nome(): void
    {
        $token = $this->tokenAdministrativo();
        $empresa = $this->criarEmpresa('13000009000155', 'Comércio e Serviços 0309');

        $this->withToken($token)
            ->getJson('/api/empresas?busca=309')
            ->assertOk()
            ->assertJsonFragment(['cnpj' => $empresa->cnpj]);
    }

    public function test_busca_nao_encontra_nome_com_digitos_espalhados(): void
    {
        $token = $this->tokenAdministrativo();
        $empresa = $this->criarEmpresa('13000009000155', 'Comércio e Serviços 0039');

        $this->withToken($token)
            ->getJson('/api/empresas?busca=309')
            ->assertOk()
            ->assertJsonMissing(['cnpj' => $empresa->cnpj]);
    }

    public function test_busca_por_nome_continua_funcionando_por_substring_case_insensitive(): void
    {
        $token = $this->tokenAdministrativo();
        $empresa = $this->criarEmpresa('22345678000199', 'Comércio e Serviços 0013');

        $this->withToken($token)
            ->getJson('/api/empresas?busca=COMÉRCIO')
            ->assertOk()
            ->assertJsonFragment(['cnpj' => $empresa->cnpj]);
    }

    public function test_digitacao_progressiva_refina_resultados_por_substring_continua(): void
    {
        $token = $this->tokenAdministrativo();
        $empresa30 = $this->criarEmpresa('30123456000100', 'Empresa Parcial 30');
        $empresa309 = $this->criarEmpresa('30912345000100', 'Empresa Parcial Sequencia');
        $empresa3090 = $this->criarEmpresa('30901234000100', 'Empresa Parcial Sequencia Completa');

        $this->assertResultadosContem($token, '3', [$empresa30, $empresa309, $empresa3090]);
        $this->assertResultadosContem($token, '30', [$empresa30, $empresa309, $empresa3090]);
        $this->assertResultadosContem($token, '309', [$empresa309, $empresa3090], [$empresa30]);
        $this->assertResultadosContem($token, '3090', [$empresa3090], [$empresa30, $empresa309]);
    }

    private function assertResultadosContem(string $token, string $busca, array $presentes, array $ausentes = []): void
    {
        $response = $this->withToken($token)->getJson('/api/empresas?busca=' . urlencode($busca));

        $response->assertOk();

        foreach ($presentes as $empresa) {
            $response->assertJsonFragment(['cnpj' => $empresa->cnpj]);
        }

        foreach ($ausentes as $empresa) {
            $response->assertJsonMissing(['cnpj' => $empresa->cnpj]);
        }
    }

    private function tokenAdministrativo(): string
    {
        $pessoa = $this->criarPessoa('admin');

        Administrativo::query()->create([
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);

        return $this->gerarTokenParaPessoa($pessoa);
    }

    private function criarEmpresa(string $cnpj, string $razaoSocial): Empresa
    {
        $pessoaEmpresa = $this->criarPessoa('empresa');
        $pessoaResponsavel = $this->criarPessoa('responsavel');

        $responsavel = ResponsavelContratual::query()->create([
            'pessoa_id_pessoa' => $pessoaResponsavel->id_pessoa,
        ]);

        return Empresa::query()->create([
            'cnpj' => $cnpj,
            'razao_social' => $razaoSocial,
            'atividade_economica' => 'Tecnologia',
            'status' => true,
            'pessoa_id_pessoa' => $pessoaEmpresa->id_pessoa,
            'responsavel_contratual_id_responsavel_contratual' => $responsavel->id_responsavel_contratual,
        ]);
    }

    private function criarPessoa(string $prefixo): Pessoa
    {
        return Pessoa::query()->create([
            'nome' => ucfirst($prefixo) . ' Teste ' . Str::random(6),
            'email' => $prefixo . Str::random(10) . '@teste.com',
            'telefone' => (string) random_int(10000000000, 99999999999),
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);
    }

    private function gerarTokenParaPessoa(Pessoa $pessoa): string
    {
        $token = 'teste-token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return $token;
    }
}
