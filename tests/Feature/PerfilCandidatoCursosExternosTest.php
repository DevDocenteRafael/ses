<?php

namespace Tests\Feature;

use App\Models\Candidato;
use App\Models\Pessoa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class PerfilCandidatoCursosExternosTest extends TestCase
{
    use RefreshDatabase;

    public function test_curso_externo_persistido_retorna_no_show_do_candidato(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();

        $salvar = $this->withToken($token)->postJson("/api/candidatos/{$candidato->matricula}/perfil/cursos-externos", [
            'nome_curso' => 'Inglês Básico',
            'instituicao' => 'Senac',
            'carga_horaria' => 120,
            'concluido_em' => '2023-01-05',
        ]);

        $salvar->assertCreated()
            ->assertJsonPath('nome_curso', 'Inglês Básico')
            ->assertJsonPath('instituicao', 'Senac')
            ->assertJsonPath('carga_horaria', 120)
            ->assertJsonPath('candidato_matricula', $candidato->matricula);

        $this->assertDatabaseHas('cursos_externos', [
            'nome_curso' => 'Inglês Básico',
            'instituicao' => 'Senac',
            'carga_horaria' => 120,
            'candidato_matricula' => $candidato->matricula,
        ]);

        $recarregar = $this->withToken($token)->getJson("/api/candidatos/{$candidato->matricula}");

        $recarregar->assertOk()
            ->assertJsonCount(1, 'cursos_externos')
            ->assertJsonPath('cursos_externos.0.nome_curso', 'Inglês Básico')
            ->assertJsonPath('cursos_externos.0.instituicao', 'Senac')
            ->assertJsonPath('cursos_externos.0.carga_horaria', 120)
            ->assertJsonPath('cursos_externos.0.candidato_matricula', $candidato->matricula);
    }

    public function test_exclusao_de_curso_externo_nao_reaparece_apos_recarregar(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();

        $primeiro = $this->withToken($token)->postJson("/api/candidatos/{$candidato->matricula}/perfil/cursos-externos", [
            'nome_curso' => 'Inglês Básico',
            'instituicao' => 'Senac',
            'carga_horaria' => 120,
            'concluido_em' => '2023-01-05',
        ])->assertCreated();

        $segundo = $this->withToken($token)->postJson("/api/candidatos/{$candidato->matricula}/perfil/cursos-externos", [
            'nome_curso' => 'Excel Avançado',
            'instituicao' => 'Senac',
            'carga_horaria' => 40,
            'concluido_em' => '2024-06-10',
        ])->assertCreated();

        $cursoId = $segundo->json('id');

        $this->withToken($token)
            ->deleteJson("/api/candidatos/{$candidato->matricula}/perfil/cursos-externos/{$cursoId}")
            ->assertOk();

        $this->assertDatabaseMissing('cursos_externos', [
            'id' => $cursoId,
            'candidato_matricula' => $candidato->matricula,
        ]);

        $recarregar = $this->withToken($token)->getJson("/api/candidatos/{$candidato->matricula}");

        $recarregar->assertOk()
            ->assertJsonCount(1, 'cursos_externos')
            ->assertJsonPath('cursos_externos.0.nome_curso', 'Inglês Básico');
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
