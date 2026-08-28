<?php

namespace Tests\Feature;

use App\Models\Candidato;
use App\Models\Pessoa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class PerfilCandidatoHabilidadesTest extends TestCase
{
    use RefreshDatabase;

    public function test_habilidades_persistem_e_retornao_apos_recarregar_perfil(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();

        $payload = [
            'sobre_mim' => 'Perfil de teste',
            'cargo_de_interesse' => 'Desenvolvedor',
            'area_de_atuacao' => 'Tecnologia da Informação',
            'habilidades' => ['Vue.js', 'Laravel', 'Comunicação'],
        ];

        $salvar = $this->withToken($token)->postJson("/api/candidatos/{$candidato->matricula}/perfil/profissional", $payload);

        $salvar->assertCreated()
            ->assertJsonPath('habilidades.0', 'Vue.js')
            ->assertJsonPath('habilidades.1', 'Laravel')
            ->assertJsonPath('habilidades.2', 'Comunicação');

        $this->assertDatabaseHas('informacoes_profissionais', [
            'candidato_matricula' => $candidato->matricula,
            'area_de_atuacao' => 'Tecnologia da Informação',
        ]);

        $recarregar = $this->withToken($token)->getJson("/api/candidatos/{$candidato->matricula}");

        $recarregar->assertOk()
            ->assertJsonPath('informacoes_profissionais.habilidades.0', 'Vue.js')
            ->assertJsonPath('informacoes_profissionais.habilidades.1', 'Laravel')
            ->assertJsonPath('informacoes_profissionais.habilidades.2', 'Comunicação');
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
