<?php

namespace Tests\Feature;

use App\Models\Administrativo;
use App\Models\Candidato;
use App\Models\DadosAcademicos;
use App\Models\Pessoa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CadastroCandidatoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_consegue_cadastrar_candidato_com_pessoa_relacionada_e_senha_hash(): void
    {
        $token = $this->criarAdminAutenticado();

        $payload = $this->payloadValido();

        $response = $this->withToken($token)->postJson('/api/candidatos', $payload);

        $response->assertCreated()
            ->assertJsonPath('matricula', $payload['matricula'])
            ->assertJsonPath('cpf', preg_replace('/\D+/', '', $payload['cpf']))
            ->assertJsonPath('pessoa.nome', $payload['nome'])
            ->assertJsonPath('status', $payload['status'])
            ->assertJsonPath('dados_academicos.0.curso', $payload['curso'])
            ->assertJsonPath('dados_academicos.0.unidade', $payload['unidade']);

        $this->assertDatabaseHas('pessoa', [
            'email' => $payload['email'],
            'nome' => $payload['nome'],
            'telefone' => $payload['telefone'],
        ]);

        $this->assertDatabaseHas('candidato', [
            'matricula' => $payload['matricula'],
            'cpf' => preg_replace('/\D+/', '', $payload['cpf']),
            'status' => $payload['status'],
        ]);

        $this->assertDatabaseHas('dados_academicos', [
            'candidato_matricula' => $payload['matricula'],
            'curso' => $payload['curso'],
            'unidade' => $payload['unidade'],
            'instituicao' => 'Senac DF',
        ]);

        $pessoa = Pessoa::where('email', $payload['email'])->firstOrFail();
        $this->assertTrue(Hash::check($payload['senha'], $pessoa->senha));
    }

    public function test_cpf_duplicado_e_rejeitado(): void
    {
        $token = $this->criarAdminAutenticado();
        [$pessoa] = $this->criarCandidatoExistente();

        $payload = $this->payloadValido([
            'email' => 'novo' . Str::random(4) . '@teste.com',
            'telefone' => (string) random_int(10000000000, 99999999999),
            'cpf' => '123.456.789-01',
        ]);

        $candidatoExistente = Candidato::query()->where('pessoa_id_pessoa', $pessoa->id_pessoa)->firstOrFail();

        $candidatoExistente->update([
            'cpf' => preg_replace('/\D+/', '', $payload['cpf']),
        ]);

        $this->withToken($token)
            ->postJson('/api/candidatos', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cpf']);
    }

    public function test_matricula_duplicada_e_rejeitada(): void
    {
        $token = $this->criarAdminAutenticado();
        [, $candidato] = $this->criarCandidatoExistente();

        $payload = $this->payloadValido([
            'matricula' => $candidato->matricula,
            'email' => 'novo' . Str::random(4) . '@teste.com',
            'telefone' => (string) random_int(10000000000, 99999999999),
        ]);

        $this->withToken($token)
            ->postJson('/api/candidatos', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['matricula']);
    }

    public function test_email_duplicado_e_rejeitado(): void
    {
        $token = $this->criarAdminAutenticado();
        [$pessoa] = $this->criarCandidatoExistente();

        $payload = $this->payloadValido([
            'email' => $pessoa->email,
            'telefone' => (string) random_int(10000000000, 99999999999),
            'matricula' => random_int(100000, 999999),
        ]);

        $this->withToken($token)
            ->postJson('/api/candidatos', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_usuario_nao_admin_nao_pode_cadastrar_candidato(): void
    {
        [, , $token] = $this->criarCandidatoAutenticado();

        $this->withToken($token)
            ->postJson('/api/candidatos', $this->payloadValido())
            ->assertStatus(403);
    }

    public function test_cadastro_invalido_nao_deixa_registros_parciais(): void
    {
        $token = $this->criarAdminAutenticado();

        $payload = $this->payloadValido([
            'telefone' => '123',
        ]);

        $this->withToken($token)
            ->postJson('/api/candidatos', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['telefone']);

        $this->assertDatabaseMissing('pessoa', [
            'email' => $payload['email'],
        ]);

        $this->assertDatabaseMissing('candidato', [
            'matricula' => $payload['matricula'],
        ]);
    }

    public function test_candidato_criado_consegue_fazer_login(): void
    {
        $token = $this->criarAdminAutenticado();
        $payload = $this->payloadValido();

        $this->withToken($token)->postJson('/api/candidatos', $payload)->assertCreated();

        $this->postJson('/api/auth/login', [
            'email' => $payload['email'],
            'senha' => $payload['senha'],
        ])->assertOk()
            ->assertJsonPath('tipo', 'candidato')
            ->assertJsonPath('pessoa.email', $payload['email']);
    }

    private function payloadValido(array $overrides = []): array
    {
        return array_merge([
            'nome' => 'Candidato Admin',
            'email' => 'candidato' . Str::random(6) . '@teste.com',
            'telefone' => (string) random_int(10000000000, 99999999999),
            'matricula' => random_int(100000, 999999),
            'cpf' => '123.456.789-01',
            'curso' => 'Técnico em Informática',
            'unidade' => 'Taguatinga',
            'senha' => '123456',
            'status' => true,
        ], $overrides);
    }

    private function criarAdminAutenticado(): string
    {
        $pessoa = Pessoa::query()->create([
            'nome' => 'Admin Teste',
            'email' => 'admin' . Str::random(8) . '@teste.com',
            'telefone' => (string) random_int(10000000000, 99999999999),
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);

        $pessoa = Pessoa::query()->where('email', $pessoa->email)->firstOrFail();

        Administrativo::query()->create([
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);

        $token = 'admin-token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return $token;
    }

    private function criarCandidatoExistente(): array
    {
        $pessoa = Pessoa::query()->create([
            'nome' => 'Candidato Existente',
            'email' => 'existente' . Str::random(8) . '@teste.com',
            'telefone' => (string) random_int(10000000000, 99999999999),
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);

        $pessoa = Pessoa::query()->where('email', $pessoa->email)->firstOrFail();

        $candidato = Candidato::query()->create([
            'matricula' => random_int(100000, 999999),
            'cpf' => '12345678901',
            'status' => true,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);

        return [$pessoa, $candidato];
    }

    private function criarCandidatoAutenticado(): array
    {
        [$pessoa, $candidato] = $this->criarCandidatoExistente();

        $token = 'candidato-token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return [$pessoa, $candidato, $token];
    }
}
