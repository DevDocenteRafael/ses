<?php

namespace Tests\Feature;

use App\Models\Candidato;
use App\Models\Pessoa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class PerfilCandidatoDadosPessoaisTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidato_pode_atualizar_nome_e_telefone_proprios(): void
    {
        [$pessoa, $candidato, $token] = $this->criarCandidatoAutenticado();

        $response = $this->withToken($token)->putJson("/api/candidatos/{$candidato->matricula}", [
            'nome' => 'Barbara Souza',
            'telefone' => '61987654321',
        ]);

        $response->assertOk()
            ->assertJsonPath('pessoa.nome', 'Barbara Souza')
            ->assertJsonPath('pessoa.telefone', '61987654321');

        $this->assertDatabaseHas('pessoa', [
            'id_pessoa' => $pessoa->id_pessoa,
            'nome' => 'Barbara Souza',
            'telefone' => '61987654321',
        ]);
    }

    public function test_candidato_nao_pode_atualizar_dados_pessoais_de_terceiro(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();
        [, $outroCandidato] = $this->criarCandidatoAutenticado();

        $response = $this->withToken($token)->putJson("/api/candidatos/{$outroCandidato->matricula}", [
            'nome' => 'Nome Indevido',
            'telefone' => '61987654321',
        ]);

        $response->assertForbidden();
    }

    private function criarCandidatoAutenticado(): array
    {
        $telefone = '61' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);

        $pessoa = Pessoa::query()->create([
            'nome' => 'Barbara',
            'email' => 'aluno' . Str::random(8) . '@teste.com',
            'telefone' => $telefone,
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);

        $candidato = Candidato::query()->create([
            'matricula' => random_int(100000, 999999),
            'cpf' => (string) random_int(10000000000, 99999999999),
            'status' => true,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);

        $token = 'teste-token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return [$pessoa, $candidato, $token];
    }
}
