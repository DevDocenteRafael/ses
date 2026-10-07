<?php

namespace Tests\Feature;

use App\Models\Administrativo;
use App\Models\Candidato;
use App\Models\CursoExterno;
use App\Models\DadosAcademicos;
use App\Models\Empresa;
use App\Models\ExperienciaProfissional;
use App\Models\InformacoesProfissionais;
use App\Models\Pessoa;
use App\Models\PreferenciasDeTrabalho;
use App\Models\RegiaoPreferidaTrabalho;
use App\Models\ResponsavelContratual;
use App\Services\Curriculo\CurriculoCandidatoBuilder;
use App\Services\Curriculo\CurriculoPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Support\GeneratesMatricula;
use Tests\TestCase;
use ZipArchive;

class AdminCurriculoCandidatoTest extends TestCase
{
    use RefreshDatabase;
    use GeneratesMatricula;

    public function test_admin_autorizado_baixa_curriculo_em_pdf(): void
    {
        [, $token] = $this->criarAdminAutenticado();
        $candidato = $this->criarCandidatoCompleto();

        $response = $this->withToken($token)->get("/api/administrativo/candidatos/{$candidato->matricula}/curriculo");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment; filename="Curriculo_Joao_da_Silva.pdf"', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(2000, strlen($response->getContent()));
        $this->assertStringContainsString('/Type /Page', $response->getContent());
        $this->assertStringContainsString('João da Silva', $this->normalizarPdfParaTeste($response->getContent()));
        $this->assertStringNotContainsString('@page', $response->getContent());
        $this->assertStringNotContainsString('font-family', $response->getContent());
        $this->assertStringNotContainsString('.item', $response->getContent());
        $this->assertStringNotContainsString('<style>', $response->getContent());
        $this->assertStringNotContainsString('<body>', $response->getContent());
    }

    public function test_sem_autenticacao_recebe_401(): void
    {
        $candidato = $this->criarCandidatoCompleto();

        $this->getJson("/api/administrativo/candidatos/{$candidato->matricula}/curriculo")
            ->assertUnauthorized();
    }

    public function test_usuario_nao_admin_recebe_403(): void
    {
        [, $tokenEmpresa] = $this->criarEmpresaAutenticada();
        $candidato = $this->criarCandidatoCompleto();

        $this->withToken($tokenEmpresa)
            ->getJson("/api/administrativo/candidatos/{$candidato->matricula}/curriculo")
            ->assertForbidden();
    }

    public function test_candidato_inexistente_retorna_404(): void
    {
        [, $token] = $this->criarAdminAutenticado();

        $this->withToken($token)
            ->getJson('/api/administrativo/candidatos/999999999/curriculo')
            ->assertNotFound();
    }

    public function test_candidato_com_dados_incompletos_ainda_gera_pdf(): void
    {
        [, $token] = $this->criarAdminAutenticado();
        $candidato = $this->criarCandidatoBasico();

        $this->withToken($token)
            ->get("/api/administrativo/candidatos/{$candidato->matricula}/curriculo")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_aluno_autenticado_baixa_proprio_curriculo_em_pdf(): void
    {
        $candidato = $this->criarCandidatoCompleto();
        $token = $this->token($candidato->pessoa);

        $response = $this->withToken($token)->get('/api/me/curriculo');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment; filename="Curriculo_Joao_da_Silva.pdf"', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('João da Silva', $this->normalizarPdfParaTeste($response->getContent()));
    }

    public function test_curriculo_do_aluno_exige_autenticacao(): void
    {
        $this->getJson('/api/me/curriculo')->assertUnauthorized();
    }

    public function test_aluno_nao_baixa_curriculo_de_outro_candidato_por_endpoint_com_matricula(): void
    {
        $candidatoA = $this->criarCandidatoBasico('Aluno A');
        $candidatoB = $this->criarCandidatoBasico('Aluno B');
        $token = $this->token($candidatoA->pessoa);

        $this->withToken($token)
            ->getJson("/api/candidatos/{$candidatoB->matricula}/curriculo")
            ->assertForbidden();
    }

    public function test_curriculo_do_aluno_usa_dados_persistidos_e_nao_atualiza_ultima_atividade(): void
    {
        $candidato = $this->criarCandidatoCompleto();
        $token = $this->token($candidato->pessoa);
        $ultimaAtividade = now()->subDays(10)->startOfSecond();
        $candidato->forceFill(['ultima_atividade_em' => $ultimaAtividade])->save();
        $candidato->informacoesProfissionais->update(['sobre_mim' => 'Resumo persistido atualizado com Gestão e Brasília.']);

        $response = $this->withToken($token)->get('/api/me/curriculo');

        $response->assertOk();
        $conteudo = $this->normalizarPdfParaTeste($response->getContent());
        $this->assertStringContainsString('Resumo persistido atualizado com Gestão e Brasília.', $conteudo);
        $this->assertSame($ultimaAtividade->toDateTimeString(), $candidato->fresh()->ultima_atividade_em?->toDateTimeString());
    }

    public function test_curriculo_do_aluno_com_perfil_incompleto_gera_pdf_valido(): void
    {
        $candidato = $this->criarCandidatoBasico('João Gonçalves');
        DadosAcademicos::query()->create([
            'instituicao' => 'Senac DF',
            'curso' => 'Administração',
            'unidade' => 'Brasília',
            'ano_de_conclusao' => '2026-12-01',
            'candidato_matricula' => $candidato->matricula,
        ]);

        $response = $this->withToken($this->token($candidato->pessoa))->get('/api/me/curriculo');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $conteudo = $this->normalizarPdfParaTeste($response->getContent());
        $this->assertStringContainsString('João Gonçalves', $conteudo);
        $this->assertStringContainsString('Administração', $conteudo);
    }

    public function test_builder_inclui_somente_habilidades_da_area_atual(): void
    {
        $candidato = $this->criarCandidatoCompleto();

        $curriculo = app(CurriculoCandidatoBuilder::class)->montar($candidato->matricula);

        $this->assertSame(['PHP', 'Laravel', 'Docker Personalizado'], $curriculo['habilidades']);
        $this->assertNotContains('Excel', $curriculo['habilidades']);
    }

    public function test_builder_inclui_formacao_cursos_e_ordena_experiencias_mais_recentes(): void
    {
        $candidato = $this->criarCandidatoCompleto();

        ExperienciaProfissional::query()->create([
            'tipo' => 'Jovem Aprendiz',
            'cargo' => 'Experiência Antiga',
            'empresa' => 'Empresa ABC',
            'local' => 'Brasília - DF',
            'data_inicio' => '2024-01-01',
            'data_fim' => '2024-12-31',
            'descricao' => 'Atendimento administrativo.',
            'candidato_matricula' => $candidato->matricula,
        ]);

        $curriculo = app(CurriculoCandidatoBuilder::class)->montar($candidato->matricula);

        $this->assertSame('Técnico em Desenvolvimento de Sistemas', $curriculo['formacao'][0]['curso']);
        $this->assertSame('Laravel Avançado', $curriculo['cursos_complementares'][0]['curso']);
        $this->assertSame('Analista de Sistemas', $curriculo['experiencias'][0]['cargo']);
        $this->assertSame('Experiência Antiga', $curriculo['experiencias'][1]['cargo']);
    }

    public function test_dados_dinamicos_ficam_escapados_no_html_controlado_pela_aplicacao(): void
    {
        $candidato = $this->criarCandidatoBasico('Arlinson <script>alert(1)</script> Santos');
        InformacoesProfissionais::query()->create([
            'sobre_mim' => 'Experiência com <script>malicioso</script> & gestão',
            'area_de_atuacao' => 'Administração',
            'habilidades' => ['Excel <b>avançado</b>'],
            'candidato_matricula' => $candidato->matricula,
        ]);

        $curriculo = app(CurriculoCandidatoBuilder::class)->montar($candidato->matricula);
        $html = app(CurriculoPdfRenderer::class)->renderHtml($curriculo);

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;malicioso&lt;/script&gt; &amp; gestão', $html);
        $this->assertStringNotContainsString('Experiência com <script>malicioso</script>', $html);
    }

    public function test_admin_baixa_zip_da_pagina_atual_respeitando_filtros_e_ordem(): void
    {
        [, $token] = $this->criarAdminAutenticado();

        for ($i = 1; $i <= 25; $i++) {
            $candidato = $this->criarCandidatoBasico(sprintf('Ativo %02d', $i));
            $candidato->forceFill(['matricula' => str_pad((string) $i, 15, '0', STR_PAD_LEFT)])->save();
            DadosAcademicos::query()->create([
                'instituicao' => 'Senac DF',
                'curso' => 'Curso Teste',
                'unidade' => 'Taguatinga',
                'ano_de_conclusao' => '2026-12-01',
                'candidato_matricula' => $candidato->matricula,
            ]);
        }

        $bloqueado = $this->criarCandidatoBasico('Ativo Bloqueado');
        $bloqueado->update(['status' => false]);

        $response = $this->withToken($token)->post('/api/administrativo/candidatos/curriculos/zip', [
            'pagina_inicial' => 2,
            'pagina_final' => 2,
            'status' => true,
            'unidade' => 'Taguatinga',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/zip', $response->headers->get('content-type'));
        $this->assertStringContainsString('Curriculos_Pagina_2.zip', $response->headers->get('content-disposition'));

        $nomes = $this->nomesNoZip($response->getFile()->getPathname());

        $this->assertCount(10, $nomes);
        $nomesNormalizados = array_map(fn ($nome) => preg_replace('/_+/', '_', $nome), $nomes);
        $this->assertContains('Curriculo_Ativo_11.pdf', $nomesNormalizados, implode(', ', $nomesNormalizados));
        $this->assertContains('Curriculo_Ativo_20.pdf', $nomesNormalizados, implode(', ', $nomesNormalizados));
        $this->assertNotContains('Curriculo_Ativo_Bloqueado.pdf', $nomes);
    }

    public function test_zip_intervalo_inclui_ultima_pagina_e_trata_nomes_duplicados(): void
    {
        [, $token] = $this->criarAdminAutenticado();

        for ($i = 1; $i <= 13; $i++) {
            $this->criarCandidatoBasico($i <= 2 ? 'João Repetido' : sprintf('Candidato %02d', $i));
        }

        $response = $this->withToken($token)->post('/api/administrativo/candidatos/curriculos/zip', [
            'pagina_inicial' => 1,
            'pagina_final' => 2,
        ]);

        $response->assertOk();
        $nomes = $this->nomesNoZip($response->getFile()->getPathname());

        $this->assertCount(13, $nomes);
        $this->assertContains('Curriculo_Joao_Repetido.pdf', $nomes);
        $this->assertContains('Curriculo_Joao_Repetido_2.pdf', $nomes);
    }

    public function test_zip_valida_intervalo_limite_e_autorizacao(): void
    {
        [, $tokenAdmin] = $this->criarAdminAutenticado();
        [, $tokenEmpresa] = $this->criarEmpresaAutenticada();
        $this->criarCandidatoBasico();

        $this->postJson('/api/administrativo/candidatos/curriculos/zip', [
            'pagina_inicial' => 1,
            'pagina_final' => 1,
        ])->assertUnauthorized();

        $this->withToken($tokenEmpresa)->postJson('/api/administrativo/candidatos/curriculos/zip', [
            'pagina_inicial' => 1,
            'pagina_final' => 1,
        ])->assertForbidden();

        $this->withToken($tokenAdmin)->postJson('/api/administrativo/candidatos/curriculos/zip', [
            'pagina_inicial' => 5,
            'pagina_final' => 3,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('pagina_inicial');

        $this->withToken($tokenAdmin)->postJson('/api/administrativo/candidatos/curriculos/zip', [
            'pagina_inicial' => 1.5,
            'pagina_final' => 3,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('pagina_inicial');

        $this->withToken($tokenAdmin)->postJson('/api/administrativo/candidatos/curriculos/zip', [
            'pagina_inicial' => 1,
            'pagina_final' => 11,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('pagina_final');
    }

    public function test_empresa_baixa_zip_respeitando_pagina_atual_filtros_e_ordem(): void
    {
        [, $token] = $this->criarEmpresaAutenticada();

        for ($i = 1; $i <= 25; $i++) {
            $candidato = $this->criarCandidatoBasico(sprintf('Empresa Manha Taguatinga %02d', $i));
            $candidato->forceFill(['matricula' => str_pad((string) $i, 15, '0', STR_PAD_LEFT)])->save();
            $this->adicionarDadosBuscaEmpresa($candidato, disponibilidade: ['Manhã'], regiaoCodigo: 3, habilidade: 'Excel');
        }

        $foraDoFiltro = $this->criarCandidatoBasico('Empresa Tarde Taguatinga');
        $foraDoFiltro->forceFill(['matricula' => str_pad('99', 15, '0', STR_PAD_LEFT)])->save();
        $this->adicionarDadosBuscaEmpresa($foraDoFiltro, disponibilidade: ['Tarde'], regiaoCodigo: 3, habilidade: 'Excel');

        $response = $this->withToken($token)->postJson('/api/candidatos/curriculos/zip', [
            'pagina_inicial' => 2,
            'pagina_final' => 2,
            'disponibilidade' => 'Manhã',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Curriculos_Pagina_2.zip', $response->headers->get('content-disposition'));

        $nomes = array_map(fn ($nome) => preg_replace('/_+/', '_', $nome), $this->nomesNoZip($response->getFile()->getPathname()));

        $this->assertCount(10, $nomes);
        $this->assertContains('Curriculo_Empresa_Manha_Taguatinga_11.pdf', $nomes, implode(', ', $nomes));
        $this->assertContains('Curriculo_Empresa_Manha_Taguatinga_20.pdf', $nomes, implode(', ', $nomes));
        $this->assertNotContains('Curriculo_Empresa_Tarde_Taguatinga.pdf', $nomes);
    }

    public function test_empresa_intervalo_inclui_ultima_pagina_e_quantidade_real(): void
    {
        [, $token] = $this->criarEmpresaAutenticada();

        for ($i = 1; $i <= 25; $i++) {
            $candidato = $this->criarCandidatoBasico(sprintf('Empresa Ultima %02d', $i));
            $candidato->forceFill(['matricula' => str_pad((string) $i, 15, '0', STR_PAD_LEFT)])->save();
            $this->adicionarDadosBuscaEmpresa($candidato, disponibilidade: ['Manhã'], regiaoCodigo: 3, habilidade: 'Excel');
        }

        $response = $this->withToken($token)->post('/api/candidatos/curriculos/zip', [
            'pagina_inicial' => 2,
            'pagina_final' => 3,
            'disponibilidade' => 'Manhã',
        ]);

        $response->assertOk();
        $nomes = $this->nomesNoZip($response->getFile()->getPathname());

        $this->assertCount(15, $nomes);
    }

    public function test_zip_empresa_valida_autorizacao_intervalo_limite_e_lista_vazia(): void
    {
        [, $tokenEmpresa] = $this->criarEmpresaAutenticada();
        [$candidatoAluno] = [$this->criarCandidatoBasico('Aluno Logado')];
        $tokenAluno = $this->token($candidatoAluno->pessoa);
        $this->criarCandidatoBasico();

        $this->postJson('/api/candidatos/curriculos/zip', [
            'pagina_inicial' => 1,
            'pagina_final' => 1,
        ])->assertUnauthorized();

        $this->withToken($tokenAluno)->postJson('/api/candidatos/curriculos/zip', [
            'pagina_inicial' => 1,
            'pagina_final' => 1,
        ])->assertForbidden();

        $this->withToken($tokenEmpresa)->postJson('/api/candidatos/curriculos/zip', [
            'pagina_inicial' => 0,
            'pagina_final' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('pagina_inicial');

        $this->withToken($tokenEmpresa)->postJson('/api/candidatos/curriculos/zip', [
            'pagina_inicial' => 5,
            'pagina_final' => 2,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('pagina_inicial');

        $this->withToken($tokenEmpresa)->postJson('/api/candidatos/curriculos/zip', [
            'pagina_inicial' => 1,
            'pagina_final' => 11,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('pagina_final');

        $this->withToken($tokenEmpresa)->postJson('/api/candidatos/curriculos/zip', [
            'pagina_inicial' => 1,
            'pagina_final' => 1,
            'disponibilidade' => 'Inexistente',
        ])->assertUnprocessable();
    }

    private function criarCandidatoCompleto(): Candidato
    {
        $candidato = $this->criarCandidatoBasico('João da Silva');

        DadosAcademicos::query()->create([
            'instituicao' => 'Senac DF',
            'curso' => 'Técnico em Desenvolvimento de Sistemas',
            'segmento' => 'tecnologia-da-informacao',
            'tipo_curso' => 'tecnico',
            'unidade' => 'Taguatinga',
            'ano_de_conclusao' => '2026-12-01',
            'candidato_matricula' => $candidato->matricula,
        ]);

        InformacoesProfissionais::query()->create([
            'sobre_mim' => 'Profissional com experiência em sistemas web.',
            'cargo_de_interesse' => 'Desenvolvedor PHP',
            'area_de_atuacao' => 'Tecnologia da Informação',
            'habilidades' => ['Legado'],
            'habilidades_por_area' => [
                'Tecnologia da Informação' => ['PHP', 'Laravel', 'Docker Personalizado'],
                'Administração' => ['Excel'],
            ],
            'candidato_matricula' => $candidato->matricula,
        ]);

        PreferenciasDeTrabalho::query()->create([
            'tipo_de_contratacao' => 3,
            'disponibilidade_de_horario' => ['Manhã', 'Noite'],
            'regiao_administrativa' => 'Brasília',
            'aceita_todas_regioes' => false,
            'pretensao_salarial' => 3500,
            'candidato_matricula' => $candidato->matricula,
        ]);

        ExperienciaProfissional::query()->create([
            'tipo' => 'CLT',
            'cargo' => 'Analista de Sistemas',
            'empresa' => 'Empresa XYZ',
            'local' => 'Brasília',
            'data_inicio' => '2025-01-01',
            'descricao' => 'Desenvolvimento de aplicações Laravel.',
            'candidato_matricula' => $candidato->matricula,
        ]);

        CursoExterno::query()->create([
            'nome_curso' => 'Laravel Avançado',
            'instituicao' => 'Escola Online',
            'carga_horaria' => 40,
            'concluido_em' => '2026-08-01',
            'candidato_matricula' => $candidato->matricula,
        ]);

        return $candidato;
    }

    private function adicionarDadosBuscaEmpresa(Candidato $candidato, array $disponibilidade, int $regiaoCodigo, string $habilidade): void
    {
        DadosAcademicos::query()->create([
            'instituicao' => 'Senac DF',
            'curso' => 'Assistente Administrativo',
            'segmento' => 'gestao-e-negocios',
            'tipo_curso' => 'tecnico',
            'unidade' => 'Taguatinga',
            'ano_de_conclusao' => '2026-12-01',
            'candidato_matricula' => $candidato->matricula,
        ]);

        InformacoesProfissionais::query()->create([
            'sobre_mim' => 'Perfil para busca de talentos.',
            'area_de_atuacao' => 'Administração',
            'habilidades' => [$habilidade],
            'habilidades_por_area' => ['Administração' => [$habilidade]],
            'candidato_matricula' => $candidato->matricula,
        ]);

        PreferenciasDeTrabalho::query()->create([
            'tipo_de_contratacao' => 3,
            'disponibilidade_de_horario' => $disponibilidade,
            'regiao_administrativa' => 'Taguatinga',
            'aceita_todas_regioes' => false,
            'candidato_matricula' => $candidato->matricula,
        ]);

        RegiaoPreferidaTrabalho::query()->create([
            'candidato_matricula' => $candidato->matricula,
            'codigo_regiao' => $regiaoCodigo,
        ]);
    }

    private function criarCandidatoBasico(string $nome = 'Candidato Básico'): Candidato
    {
        $pessoa = Pessoa::query()->create([
            'nome' => $nome,
            'email' => Str::slug($nome) . Str::random(6) . '@teste.com',
            'telefone' => (string) random_int(10000000000, 99999999999),
            'endereco_cidade' => 'Brasília',
            'endereco_uf' => 'DF',
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);

        return Candidato::query()->create([
            'matricula' => $this->gerarMatricula(),
            'cpf' => (string) random_int(10000000000, 99999999999),
            'status' => true,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);
    }

    private function criarAdminAutenticado(): array
    {
        $pessoa = $this->criarPessoa('admin');
        $admin = Administrativo::query()->create(['pessoa_id_pessoa' => $pessoa->id_pessoa]);

        return [$admin, $this->token($pessoa)];
    }

    private function criarEmpresaAutenticada(): array
    {
        $pessoa = $this->criarPessoa('empresa');
        $responsavelPessoa = $this->criarPessoa('responsavel');
        $responsavel = ResponsavelContratual::query()->create(['pessoa_id_pessoa' => $responsavelPessoa->id_pessoa]);
        $empresa = Empresa::query()->create([
            'cnpj' => (string) random_int(10000000000000, 99999999999999),
            'razao_social' => 'Empresa Teste',
            'atividade_economica' => 'Tecnologia',
            'status' => true,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
            'responsavel_contratual_id_responsavel_contratual' => $responsavel->id_responsavel_contratual,
        ]);

        return [$empresa, $this->token($pessoa)];
    }

    private function criarPessoa(string $prefixo): Pessoa
    {
        return Pessoa::query()->create([
            'nome' => ucfirst($prefixo) . ' Teste',
            'email' => $prefixo . Str::random(8) . '@teste.com',
            'telefone' => (string) random_int(10000000000, 99999999999),
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);
    }

    private function token(Pessoa $pessoa): string
    {
        $token = 'curriculo-token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return $token;
    }

    private function normalizarPdfParaTeste(string $pdf): string
    {
        return iconv('Windows-1252', 'UTF-8//IGNORE', $pdf) ?: $pdf;
    }

    private function nomesNoZip(string $caminho): array
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($caminho));

        $nomes = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nomes[] = $zip->getNameIndex($i);
            $this->assertStringStartsWith('%PDF-', $zip->getFromIndex($i));
        }

        $zip->close();
        sort($nomes);

        return $nomes;
    }
}
