<?php

namespace Tests\Feature;

use App\Models\Candidato;
use App\Models\DadosAcademicos;
use App\Models\Empresa;
use App\Models\InformacoesProfissionais;
use App\Models\Pessoa;
use App\Models\PreferenciasDeTrabalho;
use App\Models\ResponsavelContratual;
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
            'area_de_atuacao' => 'Tecnologia da Informação',
            'habilidades' => ['PHP', 'SQL', 'Excel'],
            'habilidades_por_area' => [
                'Tecnologia da Informação' => ['PHP', 'SQL'],
                'Administração' => ['Excel'],
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
            'unidade' => 'Taguatinga',
            'ano_de_conclusao' => now()->toDateString(),
            'candidato_matricula' => $candidato->matricula,
        ]);

        $response = $this->withToken($token)->getJson("/api/candidatos/{$candidato->matricula}");

        $response->assertOk()
            ->assertJsonPath('matricula', (string) $candidato->matricula)
            ->assertJsonPath('pessoa.nome', $pessoaCandidato->nome)
            ->assertJsonPath('pessoa.email', $pessoaCandidato->email)
            ->assertJsonPath('informacoes_profissionais.area_de_atuacao', 'Tecnologia da Informação')
            ->assertJsonPath('informacoes_profissionais.habilidades_por_area.Tecnologia da Informação.0', 'PHP')
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
