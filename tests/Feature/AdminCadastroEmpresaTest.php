<?php

namespace Tests\Feature;

use App\Models\Administrativo;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\Pessoa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\GeneratesMatricula;
use Tests\TestCase;

class AdminCadastroEmpresaTest extends TestCase
{
    use RefreshDatabase;
    use GeneratesMatricula;

    public function test_admin_cadastra_empresa_valida_com_sucesso(): void
    {
        [, $token] = $this->criarAdministrativoAutenticado();

        $response = $this->withToken($token)->postJson('/api/empresas', [
            'razao_social' => 'Empresa Alpha Ltda',
            'cnpj' => '12345678000199',
            'atividade_economica' => 'Tecnologia',
            'telefone' => '61999998888',
            'email' => 'empresa.alpha@teste.com',
            'responsavel_nome' => 'Maria Responsável',
            'responsavel_email' => 'maria.responsavel@teste.com',
            'responsavel_telefone' => '6133334444',
            'senha' => '123456',
            'senha_confirmation' => '123456',
            'status' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('cnpj', '12345678000199')
            ->assertJsonPath('status', true)
            ->assertJsonPath('pessoa.email', 'empresa.alpha@teste.com')
            ->assertJsonPath('responsavel_contratual.pessoa.nome', 'Maria Responsável');

        $this->assertDatabaseHas('empresa', [
            'cnpj' => '12345678000199',
            'razao_social' => 'Empresa Alpha Ltda',
            'atividade_economica' => 'Tecnologia',
            'status' => true,
        ]);

        $empresa = Empresa::query()->with(['pessoa', 'responsavelContratual.pessoa'])->findOrFail('12345678000199');

        $this->assertSame('61999998888', $empresa->pessoa->telefone);
        $this->assertSame('6133334444', $empresa->responsavelContratual->pessoa->telefone);
        $this->assertTrue(Hash::check('123456', $empresa->pessoa->senha));
        $this->assertTrue(Hash::check('123456', $empresa->responsavelContratual->pessoa->senha));
    }

    public function test_admin_cadastra_empresa_bloqueada_quando_checkbox_nao_esta_marcado(): void
    {
        [, $token] = $this->criarAdministrativoAutenticado();

        $this->withToken($token)->postJson('/api/empresas', [
            'razao_social' => 'Empresa Beta Ltda',
            'cnpj' => '22345678000199',
            'atividade_economica' => 'Educação',
            'email' => 'empresa.beta@teste.com',
            'responsavel_nome' => 'João Responsável',
            'senha' => '123456',
            'senha_confirmation' => '123456',
            'status' => false,
        ])->assertCreated()->assertJsonPath('status', false);

        $this->assertDatabaseHas('empresa', [
            'cnpj' => '22345678000199',
            'status' => false,
        ]);
    }

    public function test_nao_permite_cnpj_duplicado(): void
    {
        [, $token] = $this->criarAdministrativoAutenticado();
        $this->criarEmpresaComDados('12345678000199', 'duplicado@teste.com');

        $this->withToken($token)->postJson('/api/empresas', [
            'razao_social' => 'Empresa Duplicada',
            'cnpj' => '12.345.678/0001-99',
            'atividade_economica' => 'Tecnologia',
            'email' => 'nova.empresa@teste.com',
            'responsavel_nome' => 'Responsável',
            'senha' => '123456',
            'senha_confirmation' => '123456',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('cnpj')
            ->assertJsonPath('errors.cnpj.0', 'Este CNPJ já está cadastrado.');
    }

    public function test_nao_permite_email_duplicado(): void
    {
        [, $token] = $this->criarAdministrativoAutenticado();
        $this->criarPessoa('empresa-existente', 'email.existe@teste.com');

        $this->withToken($token)->postJson('/api/empresas', [
            'razao_social' => 'Empresa Email Duplicado',
            'cnpj' => '32345678000199',
            'atividade_economica' => 'Serviços',
            'email' => 'email.existe@teste.com',
            'responsavel_nome' => 'Responsável',
            'senha' => '123456',
            'senha_confirmation' => '123456',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'Este e-mail já está cadastrado.');
    }

    public function test_valida_campos_obrigatorios_e_confirmacao_de_senha(): void
    {
        [, $token] = $this->criarAdministrativoAutenticado();

        $this->withToken($token)->postJson('/api/empresas', [
            'cnpj' => '123',
            'senha' => '123456',
            'senha_confirmation' => '654321',
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'razao_social',
                'atividade_economica',
                'email',
                'responsavel_nome',
                'senha',
            ]);
    }

    public function test_aluno_recebe_403_ao_tentar_cadastrar_empresa(): void
    {
        [, $aluno, $token] = $this->criarCandidatoAutenticado();

        $this->withToken($token)->postJson('/api/empresas', [
            'razao_social' => 'Empresa Indevida',
            'cnpj' => '42345678000199',
            'atividade_economica' => 'Tecnologia',
            'email' => 'indevida@teste.com',
            'responsavel_nome' => 'Responsável',
            'senha' => '123456',
            'senha_confirmation' => '123456',
        ])->assertForbidden();
    }

    public function test_empresa_recebe_403_ao_tentar_cadastrar_empresa(): void
    {
        [, $empresa, $token] = $this->criarEmpresaAutenticada();

        $this->withToken($token)->postJson('/api/empresas', [
            'razao_social' => 'Empresa Indevida',
            'cnpj' => '52345678000199',
            'atividade_economica' => 'Tecnologia',
            'email' => 'indevida2@teste.com',
            'responsavel_nome' => 'Responsável',
            'senha' => '123456',
            'senha_confirmation' => '123456',
        ])->assertForbidden();
    }

    public function test_sem_token_recebe_401_ao_tentar_cadastrar_empresa(): void
    {
        $this->postJson('/api/empresas', [
            'razao_social' => 'Empresa Sem Token',
            'cnpj' => '62345678000199',
            'atividade_economica' => 'Tecnologia',
            'email' => 'sem.token@teste.com',
            'responsavel_nome' => 'Responsável',
            'senha' => '123456',
            'senha_confirmation' => '123456',
        ])->assertUnauthorized();
    }

    private function criarAdministrativoAutenticado(): array
    {
        $pessoa = $this->criarPessoa('admin');

        $administrativo = Administrativo::query()->create([
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);

        return [$administrativo, $this->gerarTokenParaPessoa($pessoa)];
    }

    private function criarCandidatoAutenticado(): array
    {
        $pessoa = $this->criarPessoa('aluno');

        $candidato = Candidato::query()->create([
            'matricula' => $this->gerarMatricula(),
            'cpf' => (string) random_int(10000000000, 99999999999),
            'status' => true,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);

        return [$pessoa, $candidato, $this->gerarTokenParaPessoa($pessoa)];
    }

    private function criarEmpresaAutenticada(): array
    {
        $empresa = $this->criarEmpresaComDados((string) random_int(10000000000000, 99999999999999), 'empresa.' . Str::random(6) . '@teste.com');

        return [$empresa->pessoa, $empresa, $this->gerarTokenParaPessoa($empresa->pessoa)];
    }

    private function criarEmpresaComDados(string $cnpj, string $emailEmpresa): Empresa
    {
        $pessoaEmpresa = $this->criarPessoa('empresa', $emailEmpresa);
        $pessoaResponsavel = $this->criarPessoa('responsavel');

        $responsavel = \App\Models\ResponsavelContratual::query()->create([
            'pessoa_id_pessoa' => $pessoaResponsavel->id_pessoa,
        ]);

        return Empresa::query()->create([
            'cnpj' => $cnpj,
            'razao_social' => 'Empresa ' . Str::random(5),
            'atividade_economica' => 'Tecnologia',
            'status' => true,
            'pessoa_id_pessoa' => $pessoaEmpresa->id_pessoa,
            'responsavel_contratual_id_responsavel_contratual' => $responsavel->id_responsavel_contratual,
        ]);
    }

    private function criarPessoa(string $prefixo, ?string $email = null): Pessoa
    {
        return Pessoa::query()->create([
            'nome' => ucfirst($prefixo) . ' Teste',
            'email' => $email ?? $prefixo . Str::random(8) . '@teste.com',
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
