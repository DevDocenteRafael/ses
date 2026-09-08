<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Pessoa;
use App\Models\ResponsavelContratual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthEmpresaCnpjTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_empresa_retorna_cnpj_real_da_empresa_autenticada(): void
    {
        [$pessoa, $empresa] = $this->criarEmpresaComPessoa('empresa-login@teste.com');

        $this->postJson('/api/auth/login', [
            'email' => $pessoa->email,
            'senha' => '123456',
        ])->assertOk()
            ->assertJsonPath('tipo', 'empresa')
            ->assertJsonPath('pessoa.id_pessoa', $pessoa->id_pessoa)
            ->assertJsonPath('pessoa.cnpj', $empresa->cnpj)
            ->assertJsonMissingPath('pessoa.senha');
    }

    public function test_auth_me_empresa_retorna_cnpj_real_da_empresa_autenticada(): void
    {
        [$pessoa, $empresa] = $this->criarEmpresaComPessoa('empresa-me@teste.com');
        $token = 'empresa-token-' . Str::random(10);

        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('tipo', 'empresa')
            ->assertJsonPath('pessoa.id_pessoa', $pessoa->id_pessoa)
            ->assertJsonPath('pessoa.cnpj', $empresa->cnpj)
            ->assertJsonMissingPath('pessoa.senha');
    }

    private function criarEmpresaComPessoa(string $email): array
    {
        $pessoaEmpresa = Pessoa::query()->create([
            'nome' => 'Empresa Teste',
            'email' => $email,
            'telefone' => (string) random_int(10000000000, 99999999999),
            'senha' => Hash::make('123456'),
            'data_cadastro' => now(),
        ]);

        $pessoaResponsavel = Pessoa::query()->create([
            'nome' => 'Responsavel Teste',
            'email' => 'responsavel' . Str::random(8) . '@teste.com',
            'telefone' => (string) random_int(10000000000, 99999999999),
            'senha' => Hash::make('123456'),
            'data_cadastro' => now(),
        ]);

        $responsavel = ResponsavelContratual::query()->create([
            'pessoa_id_pessoa' => $pessoaResponsavel->id_pessoa,
        ]);

        $empresa = Empresa::query()->create([
            'cnpj' => (string) random_int(10000000000000, 99999999999999),
            'razao_social' => 'Empresa ' . Str::random(5),
            'atividade_economica' => 'Tecnologia',
            'status' => true,
            'pessoa_id_pessoa' => $pessoaEmpresa->id_pessoa,
            'responsavel_contratual_id_responsavel_contratual' => $responsavel->id_responsavel_contratual,
        ]);

        return [$pessoaEmpresa, $empresa];
    }
}
