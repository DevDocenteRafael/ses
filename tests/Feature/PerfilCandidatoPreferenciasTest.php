<?php

namespace Tests\Feature;

use App\Models\Candidato;
use App\Models\Pessoa;
use App\Models\PreferenciasDeTrabalho;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class PerfilCandidatoPreferenciasTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_aceita_todas_as_opcoes_validas_de_disponibilidade(): void
    {
        foreach (['Manhã', 'Tarde', 'Noite', 'Integral'] as $disponibilidade) {
            [, $candidato, $token] = $this->criarCandidatoAutenticado();

            $response = $this->withToken($token)->postJson("/api/candidatos/{$candidato->matricula}/perfil/preferencias", [
                'tipo_de_contratacao' => 1,
                'disponibilidade_de_horario' => $disponibilidade,
                'regiao_administrativa' => 'Ceilândia',
                'pretensao_salarial' => 2500,
            ]);

            $response->assertCreated()
                ->assertJsonPath('disponibilidade_de_horario', $disponibilidade)
                ->assertJsonPath('pretensao_salarial', 2500);

            $this->assertDatabaseHas('preferencias_de_trabalho', [
                'candidato_matricula' => $candidato->matricula,
                'disponibilidade_de_horario' => $disponibilidade,
                'pretensao_salarial' => 2500,
            ]);
        }
    }

    public function test_api_rejeita_disponibilidade_fora_da_lista_permitida(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();

        foreach (['Tarde/Noite', 'Madrugada'] as $disponibilidade) {
            $response = $this->withToken($token)->postJson("/api/candidatos/{$candidato->matricula}/perfil/preferencias", [
                'tipo_de_contratacao' => 1,
                'disponibilidade_de_horario' => $disponibilidade,
                'regiao_administrativa' => 'Taguatinga',
                'pretensao_salarial' => 1800,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['disponibilidade_de_horario']);
        }
    }

    public function test_api_aceita_pretensao_salarial_inteira_zero_e_nula(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();

        foreach ([2500, 0, null] as $valor) {
            $response = $this->withToken($token)->postJson("/api/candidatos/{$candidato->matricula}/perfil/preferencias", [
                'tipo_de_contratacao' => 1,
                'disponibilidade_de_horario' => 'Manhã',
                'regiao_administrativa' => 'Sobradinho',
                'pretensao_salarial' => $valor,
            ]);

            $response->assertCreated();

            $preferencia = PreferenciasDeTrabalho::where('candidato_matricula', $candidato->matricula)->first();

            $this->assertSame($valor, $preferencia->pretensao_salarial);
        }
    }

    public function test_api_rejeita_pretensao_salarial_negativa_textual_ou_invalida(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();

        foreach ([-1000, 'texto', '12abc'] as $valor) {
            $response = $this->withToken($token)->postJson("/api/candidatos/{$candidato->matricula}/perfil/preferencias", [
                'tipo_de_contratacao' => 1,
                'disponibilidade_de_horario' => 'Tarde',
                'regiao_administrativa' => 'Guará',
                'pretensao_salarial' => $valor,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['pretensao_salarial']);
        }
    }

    public function test_persistencia_de_preferencias_validas_retorna_os_mesmos_dados_na_leitura_posterior(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();

        $payload = [
            'tipo_de_contratacao' => 3,
            'disponibilidade_de_horario' => 'Integral',
            'regiao_administrativa' => 'Plano Piloto',
            'pretensao_salarial' => 3200,
        ];

        $salvar = $this->withToken($token)->postJson("/api/candidatos/{$candidato->matricula}/perfil/preferencias", $payload);

        $salvar->assertCreated();

        $this->assertDatabaseHas('preferencias_de_trabalho', [
            'candidato_matricula' => $candidato->matricula,
            'disponibilidade_de_horario' => 'Integral',
            'pretensao_salarial' => 3200,
        ]);

        $leitura = $this->withToken($token)->getJson("/api/candidatos/{$candidato->matricula}");

        $leitura->assertOk()
            ->assertJsonPath('preferencias_de_trabalho.disponibilidade_de_horario', 'Integral')
            ->assertJsonPath('preferencias_de_trabalho.pretensao_salarial', 3200);
    }

    public function test_pretensao_salarial_decimal_positivo_deve_permanecer_exata_apos_salvar_e_ler(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();

        $valorEnviado = 1741.50;

        $salvar = $this->withToken($token)->postJson("/api/candidatos/{$candidato->matricula}/perfil/preferencias", [
            'tipo_de_contratacao' => 1,
            'disponibilidade_de_horario' => 'Noite',
            'regiao_administrativa' => 'Águas Claras',
            'pretensao_salarial' => $valorEnviado,
        ]);

        $salvar->assertCreated();

        $preferencia = PreferenciasDeTrabalho::where('candidato_matricula', $candidato->matricula)->firstOrFail();

        $this->assertSame($valorEnviado, (float) $preferencia->pretensao_salarial, 'O valor persistido no banco diverge do valor decimal enviado.');

        $leitura = $this->withToken($token)->getJson("/api/candidatos/{$candidato->matricula}");

        $leitura->assertOk()
            ->assertJsonPath('preferencias_de_trabalho.pretensao_salarial', $valorEnviado);
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
