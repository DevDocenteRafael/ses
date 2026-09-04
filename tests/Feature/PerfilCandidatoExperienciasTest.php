<?php

namespace Tests\Feature;

use App\Models\Candidato;
use App\Models\Pessoa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Support\GeneratesMatricula;
use Tests\TestCase;

class PerfilCandidatoExperienciasTest extends TestCase
{
    use RefreshDatabase;
    use GeneratesMatricula;

    public function test_experiencia_atual_sem_data_fim_e_aceita(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();

        $response = $this->withToken($token)->postJson("/api/candidatos/{$candidato->matricula}/perfil/experiencias", [
            'tipo' => 'CLT',
            'cargo' => 'Desenvolvedor Front-end',
            'empresa' => 'Tech Solutions',
            'local' => 'Brasília, DF',
            'data_inicio' => '2026-01-01',
            'data_fim' => null,
            'descricao' => 'Atuação com Vue.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data_fim', null);

        $this->assertDatabaseHas('experiencias_profissionais', [
            'candidato_matricula' => $candidato->matricula,
            'cargo' => 'Desenvolvedor Front-end',
            'data_fim' => null,
        ]);

        $this->withToken($token)
            ->getJson("/api/candidatos/{$candidato->matricula}")
            ->assertOk()
            ->assertJsonPath('experiencias_profissionais.0.cargo', 'Desenvolvedor Front-end');
    }

    public function test_experiencia_finalizada_com_data_fim_e_aceita(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();

        $response = $this->withToken($token)->postJson("/api/candidatos/{$candidato->matricula}/perfil/experiencias", [
            'tipo' => 'CLT',
            'cargo' => 'Analista',
            'empresa' => 'Empresa X',
            'data_inicio' => '2025-01-01',
            'data_fim' => '2025-12-01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data_fim', '2025-12-01T00:00:00.000000Z');
    }

    public function test_experiencia_rejeita_data_fim_anterior_a_data_inicio(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();

        $response = $this->withToken($token)->postJson("/api/candidatos/{$candidato->matricula}/perfil/experiencias", [
            'tipo' => 'CLT',
            'cargo' => 'Analista',
            'empresa' => 'Empresa X',
            'data_inicio' => '2025-12-01',
            'data_fim' => '2025-01-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data_fim']);
    }

    private function criarCandidatoAutenticado(): array
    {
        $pessoa = Pessoa::query()->create([
            'nome' => 'Aluno Teste',
            'email' => 'aluno' . Str::random(8) . '@teste.com',
            'telefone' => (string) random_int(10000000000, 99999999999),
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);

        $pessoa = Pessoa::query()->where('email', $pessoa->email)->firstOrFail();

        $candidato = Candidato::query()->create([
            'matricula' => $this->gerarMatricula(),
            'cpf' => (string) random_int(10000000000, 99999999999),
            'status' => true,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);

        $token = 'teste-token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return [$pessoa, $candidato, $token];
    }
}
