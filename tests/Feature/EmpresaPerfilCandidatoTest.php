<?php

namespace Tests\Feature;

use App\Models\Candidato;
use App\Models\DadosAcademicos;
use App\Models\Empresa;
use App\Models\InformacoesProfissionais;
use App\Models\Pessoa;
use App\Models\PreferenciasDeTrabalho;
use App\Models\ResponsavelContratual;
use App\Models\SituacaoAcademica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Support\GeneratesMatricula;
use Tests\TestCase;

class EmpresaPerfilCandidatoTest extends TestCase
{
    use RefreshDatabase;
    use GeneratesMatricula;

    public function test_empresa_autenticada_pode_visualizar_candidato_ativo_com_endereco_estruturado(): void
    {
        [, $token] = $this->criarEmpresaAutenticada();
        [$pessoaCandidato, $candidato] = $this->criarCandidatoAtivo();

        InformacoesProfissionais::query()->create([
            'sobre_mim' => 'Perfil profissional resumido.',
            'cargo_de_interesse' => 'Desenvolvedor PHP',
            'area_de_atuacao' => 'Tecnologia e Games',
            'habilidades' => ['PHP', 'SQL', 'Excel'],
            'habilidades_por_area' => [
                'Tecnologia e Games' => ['PHP', 'SQL'],
                'Gestão Comércio e Negócio' => ['Excel'],
            ],
            'candidato_matricula' => $candidato->matricula,
        ]);

        PreferenciasDeTrabalho::query()->create([
            'tipo_de_contratacao' => 3,
            'disponibilidade_de_horario' => ['Manhã'],
            'regiao_administrativa' => 'Brasília',
            'aceita_todas_regioes' => false,
            'pretensao_salarial' => 2500,
            'candidato_matricula' => $candidato->matricula,
        ]);

        DadosAcademicos::query()->create([
            'instituicao' => 'Senac DF',
            'curso' => 'Técnico em Desenvolvimento de Sistemas',
            'tipo_curso' => 'tecnico',
            'unidade' => 'Taguatinga',
            'ano_de_conclusao' => now()->toDateString(),
            'candidato_matricula' => $candidato->matricula,
        ]);

        $response = $this->withToken($token)->getJson("/api/candidatos/{$candidato->matricula}");

        $response->assertOk()
            ->assertJsonPath('matricula', (string) $candidato->matricula)
            ->assertJsonPath('pessoa.nome', $pessoaCandidato->nome)
            ->assertJsonPath('pessoa.email', $pessoaCandidato->email)
            ->assertJsonPath('dados_academicos.0.curso', 'Técnico em Desenvolvimento de Sistemas')
            ->assertJsonPath('dados_academicos.0.instituicao', 'Senac DF')
            ->assertJsonPath('dados_academicos.0.tipo_curso', 'tecnico')
            ->assertJsonPath('dados_academicos.0.ano_de_conclusao', now()->startOfDay()->toISOString())
            ->assertJsonPath('informacoes_profissionais.area_de_atuacao', 'Tecnologia e Games')
            ->assertJsonPath('informacoes_profissionais.habilidades_por_area.Tecnologia e Games.0', 'PHP')
            ->assertJsonPath('pessoa.endereco.cep', '72620207')
            ->assertJsonPath('pessoa.endereco.logradouro', 'Quadra 301 Conjunto 7')
            ->assertJsonPath('pessoa.endereco.numero', '09')
            ->assertJsonPath('pessoa.endereco.complemento', '22')
            ->assertJsonPath('pessoa.endereco.bairro', 'Recanto das Emas')
            ->assertJsonPath('pessoa.endereco.cidade', 'Brasília')
            ->assertJsonPath('pessoa.endereco.uf', 'DF')
            ->assertJsonMissingPath('pessoa.endereco_cep')
            ->assertJsonMissingPath('pessoa.endereco_logradouro')
            ->assertJsonMissingPath('pessoa.endereco_numero')
            ->assertJsonMissingPath('pessoa.endereco_complemento')
            ->assertJsonMissingPath('pessoa.senha')
            ->assertJsonPath('cpf', null)
            ->assertJsonPath('convites', [])
            ->assertJsonPath('empresas', []);
    }

    public function test_empresa_visualiza_situacao_academica_atual_sem_expor_dados_pessoais(): void
    {
        [, $token] = $this->criarEmpresaAutenticada();
        [, $candidato] = $this->criarCandidatoAtivo();

        SituacaoAcademica::query()->create([
            'candidato_matricula' => $candidato->matricula,
            'nivel_escolaridade' => 'ensino_superior',
            'situacao_academica' => 'em_andamento',
            'curso' => 'Administração',
            'instituicao' => 'Universidade de Brasília',
            'data_inicio' => '2024-02-01',
            'previsao_conclusao' => '2027-12-01',
            'atual' => true,
        ]);

        $this->withToken($token)
            ->getJson("/api/candidatos/{$candidato->matricula}")
            ->assertOk()
            ->assertJsonPath('situacao_academica_atual.nivel_escolaridade', 'ensino_superior')
            ->assertJsonPath('situacao_academica_atual.situacao_academica', 'em_andamento')
            ->assertJsonPath('situacao_academica_atual.curso', 'Administração')
            ->assertJsonPath('situacao_academica_atual.instituicao', 'Universidade de Brasília')
            ->assertJsonPath('situacao_academica_atual.previsao_conclusao', '2027-12-01T00:00:00.000000Z')
            ->assertJsonPath('cpf', null)
            ->assertJsonMissingPath('situacao_academica_atual.cpf');

        $this->withToken($token)
            ->getJson('/api/candidatos?per_page=10')
            ->assertOk()
            ->assertJsonMissingPath('data.0.situacao_academica_atual');
    }

    public function test_candidato_salva_edita_e_preserva_historico_da_situacao_academica(): void
    {
        [$pessoa, $candidato] = $this->criarCandidatoAtivo();
        $token = 'candidato-token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());
        $endpoint = "/api/candidatos/{$candidato->matricula}/perfil/situacao-academica";
        $situacaoAnterior = SituacaoAcademica::query()->create([
            'candidato_matricula' => $candidato->matricula,
            'nivel_escolaridade' => 'ensino_superior',
            'nivel_escolaridade_concluido' => 'ensino_medio',
            'situacao_academica' => 'em_andamento',
            'curso' => 'Administração',
            'instituicao' => 'Universidade de Brasília',
            'data_inicio' => now()->subYear()->toDateString(),
            'previsao_conclusao' => now()->addYear()->toDateString(),
            'atual' => true,
        ]);

        $payload = [
            'nivel_escolaridade' => 'ensino_superior',
            'situacao_academica' => 'em_andamento',
            'curso' => 'Administração',
            'instituicao' => 'Universidade de Brasília',
            'data_inicio' => now()->subYear()->toDateString(),
            'previsao_conclusao' => now()->addYear()->toDateString(),
        ];

        $this->withToken($token)->putJson($endpoint, $payload)
            ->assertOk()
            ->assertJsonPath('situacao_academica', 'em_andamento')
            ->assertJsonPath('curso', 'Administração');

        $this->withToken($token)->putJson($endpoint, $payload)->assertOk();
        $this->assertDatabaseCount('situacoes_academicas', 1);
        $this->assertDatabaseHas('situacoes_academicas', [
            'id' => $situacaoAnterior->id,
            'nivel_escolaridade_concluido' => 'ensino_medio',
            'atual' => true,
        ]);

        $payload['situacao_academica'] = 'nao_estudando';
        $payload['curso'] = 'Administração';
        $payload['instituicao'] = 'Universidade de Brasília';
        $this->withToken($token)->putJson($endpoint, $payload)
            ->assertOk()
            ->assertJsonPath('situacao_academica', 'nao_estudando')
            ->assertJsonPath('curso', null)
            ->assertJsonPath('previsao_conclusao', null);

        $this->assertDatabaseCount('situacoes_academicas', 2);
        $this->assertDatabaseHas('situacoes_academicas', [
            'candidato_matricula' => $candidato->matricula,
            'situacao_academica' => 'em_andamento',
            'atual' => false,
        ]);
        $this->assertDatabaseHas('situacoes_academicas', [
            'candidato_matricula' => $candidato->matricula,
            'situacao_academica' => 'nao_estudando',
            'curso' => null,
            'atual' => true,
        ]);

        $this->withToken($token)
            ->getJson("/api/candidatos/{$candidato->matricula}")
            ->assertOk()
            ->assertJsonPath('situacao_academica_atual.situacao_academica', 'nao_estudando')
            ->assertJsonPath('situacao_academica_atual.curso', null);
    }

    public function test_candidato_pode_registrar_formacao_concluida_com_dados_parciais(): void
    {
        [$pessoa, $candidato] = $this->criarCandidatoAtivo();
        $token = 'candidato-token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        $this->withToken($token)
            ->putJson("/api/candidatos/{$candidato->matricula}/perfil/situacao-academica", [
                'nivel_escolaridade' => 'ensino_tecnico',
                'situacao_academica' => 'concluido',
                'curso' => 'Técnico em Informática',
                'instituicao' => 'Senac DF',
                'data_conclusao' => now()->subYear()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('situacao_academica', 'concluido')
            ->assertJsonPath('curso', 'Técnico em Informática')
            ->assertJsonPath('data_inicio', null)
            ->assertJsonPath('previsao_conclusao', null);
    }

    public function test_candidato_nao_pode_editar_situacao_academica_de_outra_pessoa(): void
    {
        [$pessoa] = $this->criarCandidatoAtivo();
        [, $alvo] = $this->criarCandidatoAtivo();
        $token = 'candidato-token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        $this->withToken($token)
            ->putJson("/api/candidatos/{$alvo->matricula}/perfil/situacao-academica", [
                'nivel_escolaridade' => 'ensino_medio',
                'situacao_academica' => 'nao_estudando',
            ])
            ->assertForbidden();
    }

    public function test_empresa_autenticada_pode_visualizar_candidato_sem_endereco(): void
    {
        [, $token] = $this->criarEmpresaAutenticada();
        [, $candidato] = $this->criarCandidatoAtivo(endereco: false);

        $this->withToken($token)
            ->getJson("/api/candidatos/{$candidato->matricula}")
            ->assertOk()
            ->assertJsonPath('pessoa.endereco', [
                'cep' => null,
                'logradouro' => null,
                'numero' => null,
                'complemento' => null,
                'bairro' => null,
                'cidade' => null,
                'uf' => null,
            ])
            ->assertJsonMissingPath('pessoa.endereco_cep')
            ->assertJsonMissingPath('pessoa.senha');
    }

    public function test_empresa_nao_pode_visualizar_candidato_inativo(): void
    {
        [, $token] = $this->criarEmpresaAutenticada();
        [, $candidato] = $this->criarCandidatoAtivo(false);

        $this->withToken($token)
            ->getJson("/api/candidatos/{$candidato->matricula}")
            ->assertForbidden();
    }

    public function test_candidato_inexistente_retorna_404_para_empresa(): void
    {
        [, $token] = $this->criarEmpresaAutenticada();

        $this->withToken($token)
            ->getJson('/api/candidatos/999999999')
            ->assertNotFound();
    }

    private function criarEmpresaAutenticada(): array
    {
        $pessoa = Pessoa::query()->create([
            'nome' => 'Empresa Teste',
            'email' => 'empresa' . Str::random(8) . '@teste.com',
            'telefone' => '61' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);

        $responsavel = ResponsavelContratual::query()->create([
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);

        $empresa = Empresa::query()->create([
            'cnpj' => (string) random_int(10000000000000, 99999999999999),
            'razao_social' => 'Empresa Teste LTDA',
            'atividade_economica' => 'Tecnologia',
            'status' => true,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
            'responsavel_contratual_id_responsavel_contratual' => $responsavel->id_responsavel_contratual,
        ]);

        $token = 'empresa-token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return [$empresa, $token];
    }

    private function criarCandidatoAtivo(bool $ativo = true, bool $endereco = true): array
    {
        $dadosPessoa = [
            'nome' => 'Candidato Teste',
            'email' => 'candidato' . Str::random(8) . '@teste.com',
            'telefone' => '61' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ];

        if ($endereco) {
            $dadosPessoa = array_merge($dadosPessoa, [
            'endereco_cep' => '72620207',
            'endereco_logradouro' => 'Quadra 301 Conjunto 7',
            'endereco_numero' => '09',
            'endereco_complemento' => '22',
            'endereco_bairro' => 'Recanto das Emas',
            'endereco_cidade' => 'Brasília',
            'endereco_uf' => 'DF',
            ]);
        }

        $pessoa = Pessoa::query()->create($dadosPessoa);

        $candidato = Candidato::query()->create([
            'matricula' => $this->gerarMatricula(),
            'cpf' => (string) random_int(10000000000, 99999999999),
            'status' => $ativo,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);

        return [$pessoa, $candidato];
    }
}
