<?php

namespace Tests\Feature;

use App\Models\Administrativo;
use App\Models\Candidato;
use App\Models\Convite;
use App\Models\CursoSenac;
use App\Models\DadosAcademicos;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Models\ResponsavelContratual;
use App\Models\Vaga;
use App\Support\AreasAtuacaoCatalogo;
use App\Support\CatalogoAcademicoSenacDf;
use App\Support\HabilidadesCatalogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Support\GeneratesMatricula;
use Tests\TestCase;

class AuthorizationCriticalEndpointsTest extends TestCase
{
    use RefreshDatabase;
    use GeneratesMatricula;

    public function test_admin_acessa_endpoint_administrativo(): void
    {
        [$admin, $token] = $this->criarAdministrativoAutenticado();

        $this->withToken($token)
            ->getJson('/api/administrativo')
            ->assertOk();

        $this->withToken($token)
            ->getJson("/api/administrativo/{$admin->pessoa_id_pessoa}")
            ->assertOk();
    }

    public function test_aluno_recebe_403_em_endpoint_administrativo(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();

        $this->withToken($token)
            ->getJson('/api/administrativo/dashboard')
            ->assertForbidden();

        $this->withToken($token)
            ->postJson('/api/administrativo/sincronizar-alunos', [
                'status_ativacao' => true,
            ])
            ->assertForbidden();
    }

    public function test_empresa_recebe_403_em_endpoint_administrativo(): void
    {
        [, $empresa, $token] = $this->criarEmpresaAutenticada();

        $this->withToken($token)
            ->getJson('/api/administrativo/engajamento')
            ->assertForbidden();

        $this->withToken($token)
            ->postJson('/api/administrativo/engajamento', [
                'unidade' => 'Asa Sul',
                'elegibilidade' => true,
                'status' => true,
            ])
            ->assertForbidden();
    }

    public function test_sem_token_recebe_401_em_endpoint_administrativo(): void
    {
        $this->getJson('/api/administrativo')
            ->assertUnauthorized();
    }

    public function test_admin_e_empresa_listam_candidatos_mas_aluno_recebe_403(): void
    {
        [, $tokenAdmin] = $this->criarAdministrativoAutenticado();
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();
        [, , $tokenAluno] = $this->criarCandidatoAutenticado();

        $this->withToken($tokenAdmin)
            ->getJson('/api/candidatos')
            ->assertOk();

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos')
            ->assertOk();

        $this->withToken($tokenAluno)
            ->getJson('/api/candidatos')
            ->assertForbidden();
    }

    public function test_filtro_de_candidatos_rejeita_jovem_aprendiz_por_query_manual(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();

        foreach ([4, 5, 6, 7] as $tipoContratacao) {
            $this->withToken($tokenEmpresa)
                ->getJson("/api/candidatos?tipo_contratacao={$tipoContratacao}")
                ->assertStatus(422)
                ->assertJsonValidationErrors(['tipo_contratacao'])
                ->assertJsonFragment(['O tipo de contratação informado não é permitido. Jovem Aprendiz não é mais uma opção válida.']);
        }
    }

    public function test_empresa_lista_candidatos_paginados_com_dez_por_pagina(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();

        for ($i = 1; $i <= 23; $i++) {
            $this->criarCandidatoParaBusca('Aluno Paginado ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        }

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('total', 23)
            ->assertJsonPath('last_page', 3)
            ->assertJsonCount(10, 'data');

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos?page=2&per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data');

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos?page=3&per_page=10')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_empresa_encontra_candidato_fora_dos_primeiros_dez_apos_filtro(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();

        for ($i = 1; $i <= 10; $i++) {
            $this->criarCandidatoParaBusca('Aluno Sem Match ' . $i, habilidades: ['php']);
        }

        $alvo = $this->criarCandidatoParaBusca('Aluno Encontrado', habilidades: ['laravel-especial']);

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos?habilidades[]=laravel-especial&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('last_page', 1)
            ->assertJsonPath('data.0.matricula', $alvo->matricula);
    }

    public function test_listagem_retorna_telefone_sem_expor_senha_e_busca_por_telefone(): void
    {
        [, $tokenAdmin] = $this->criarAdministrativoAutenticado();
        $alvo = $this->criarCandidatoParaBusca('Aluno Telefone', telefone: '61999999999');
        $this->criarCandidatoParaBusca('Aluno Outro Telefone', telefone: '6133334444');

        $this->withToken($tokenAdmin)
            ->getJson('/api/candidatos?busca=(61)%209%209999-9999')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.matricula', $alvo->matricula)
            ->assertJsonPath('data.0.pessoa.telefone', '61999999999')
            ->assertJsonMissingPath('data.0.pessoa.senha');
    }

    public function test_empresa_lista_catalogo_de_habilidades_padrao_mais_persistidas_unicas_incluindo_personalizadas(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();

        $this->criarCandidatoParaBusca('Aluno Docker A', habilidades: [' Docker ', 'javascript']);
        $this->criarCandidatoParaBusca('Aluno Docker B', habilidades: ['docker', ' JavaScript ']);
        $this->criarCandidatoParaBusca('Aluno Bloqueado Skill', status: false, habilidades: ['Kubernetes']);

        $catalogoEsperado = HabilidadesCatalogo::ordenar(HabilidadesCatalogo::deduplicar([
            ...HabilidadesCatalogo::padrao(),
            ' Docker ',
            'javascript',
            'docker',
            ' JavaScript ',
        ]));

        $resposta = $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos/habilidades')
            ->assertOk()
            ->assertExactJson($catalogoEsperado)
            ->assertJsonCount(HabilidadesCatalogo::totalPadrao() + 1)
            ->assertJsonMissing(['javascript'])
            ->assertJsonMissing([' JavaScript '])
            ->assertJsonMissing(['Kubernetes']);

        $this->assertContains('Docker', $resposta->json());
        $this->assertContains('JavaScript', $resposta->json());
    }

    public function test_empresa_filtra_por_varias_habilidades_com_semantica_and_antes_da_paginacao(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();

        for ($i = 1; $i <= 10; $i++) {
            $this->criarCandidatoParaBusca('Aluno Sem Docker ' . $i, habilidades: ['Laravel']);
        }

        $this->criarCandidatoParaBusca('Aluno Docker Sem Vue', habilidades: ['Docker', 'Laravel']);
        $alvo = $this->criarCandidatoParaBusca('Aluno Docker Vue', habilidades: ['Docker', 'Vue.js', 'Laravel']);

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos?habilidades[]=Docker&habilidades[]=Vue.js&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('last_page', 1)
            ->assertJsonPath('data.0.matricula', $alvo->matricula);
    }

    public function test_empresa_filtra_candidatos_por_regiao_incluindo_quem_aceita_todas(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();

        $taguatinga = $this->criarCandidatoParaBusca('Aluno Taguatinga');
        \App\Models\RegiaoPreferidaTrabalho::query()->create([
            'candidato_matricula' => $taguatinga->matricula,
            'codigo_regiao' => 3,
        ]);

        $todas = $this->criarCandidatoParaBusca('Aluno Todas Regioes');
        \App\Models\PreferenciasDeTrabalho::query()
            ->where('candidato_matricula', $todas->matricula)
            ->update([
                'aceita_todas_regioes' => true,
                'regiao_administrativa' => 'Todas as regiões',
            ]);

        $guara = $this->criarCandidatoParaBusca('Aluno Guara');
        \App\Models\RegiaoPreferidaTrabalho::query()->create([
            'candidato_matricula' => $guara->matricula,
            'codigo_regiao' => 10,
        ]);

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos?regioes_administrativas[]=3&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonFragment(['matricula' => $taguatinga->matricula])
            ->assertJsonFragment(['matricula' => $todas->matricula])
            ->assertJsonMissing(['matricula' => $guara->matricula]);
    }

    public function test_empresa_filtra_por_multiplas_regioes_administrativas(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();

        $taguatinga = $this->criarCandidatoParaBusca('Aluno Multi Taguatinga');
        \App\Models\RegiaoPreferidaTrabalho::query()->create([
            'candidato_matricula' => $taguatinga->matricula,
            'codigo_regiao' => 3,
        ]);

        $guara = $this->criarCandidatoParaBusca('Aluno Multi Guara');
        \App\Models\RegiaoPreferidaTrabalho::query()->create([
            'candidato_matricula' => $guara->matricula,
            'codigo_regiao' => 10,
        ]);

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos?regioes_administrativas[]=3&regioes_administrativas[]=10&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonFragment(['matricula' => $taguatinga->matricula])
            ->assertJsonFragment(['matricula' => $guara->matricula]);
    }

    public function test_empresa_api_retorna_todas_as_regioes_preferidas_e_filtra_por_regioes_posteriores(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();

        $candidato = $this->criarCandidatoParaBusca('Aluno Cinco Regioes');
        
        foreach ([6, 15, 3, 5, 2] as $codigoRegiao) {
            \App\Models\RegiaoPreferidaTrabalho::query()->create([
                'candidato_matricula' => $candidato->matricula,
                'codigo_regiao' => $codigoRegiao,
            ]);
        }

        $this->assertSame(5, \App\Models\RegiaoPreferidaTrabalho::where('candidato_matricula', $candidato->matricula)->count());

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.regioes_preferidas_trabalho.0.codigo', 2)
            ->assertJsonPath('data.0.regioes_preferidas_trabalho.1.codigo', 3)
            ->assertJsonPath('data.0.regioes_preferidas_trabalho.2.codigo', 5)
            ->assertJsonPath('data.0.regioes_preferidas_trabalho.3.codigo', 6)
            ->assertJsonPath('data.0.regioes_preferidas_trabalho.4.codigo', 15)
            ->assertJsonCount(5, 'data.0.regioes_preferidas_trabalho');

        foreach ([6, 15, 3] as $codigoRegiao) {
            $this->withToken($tokenEmpresa)
                ->getJson("/api/candidatos?regioes_administrativas[]={$codigoRegiao}&page=1&per_page=10")
                ->assertOk()
                ->assertJsonFragment(['matricula' => $candidato->matricula]);
        }

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos?regioes_administrativas[]=10&page=1&per_page=10')
            ->assertOk()
            ->assertJsonMissing(['matricula' => $candidato->matricula]);
    }

    public function test_filtro_de_regiao_administrativa_rejeita_codigo_invalido(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos?regioes_administrativas[]=999')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['regioes_administrativas.0']);
    }

    public function test_filtro_atualiza_total_e_last_page_da_paginacao_para_empresa(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();

        for ($i = 1; $i <= 12; $i++) {
            $this->criarCandidatoParaBusca('Aluno Tecnologia ' . $i, areaAtuacao: 'Tecnologia da Informação');
        }

        for ($i = 1; $i <= 5; $i++) {
            $this->criarCandidatoParaBusca('Aluno Comunicação ' . $i, areaAtuacao: 'Comunicação, Arte e Design');
        }

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos?segmento[]=Comunica%C3%A7%C3%A3o%2C%20Arte%20e%20Design&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 5)
            ->assertJsonPath('last_page', 1)
            ->assertJsonCount(5, 'data');
    }

    public function test_empresa_lista_segmentos_como_areas_de_atuacao_canonicas(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos/segmentos-academicos')
            ->assertOk()
            ->assertJsonCount(8)
            ->assertJsonFragment(['id' => 'Tecnologia da Informação', 'nome' => 'Tecnologia da Informação'])
            ->assertJsonFragment(['id' => 'Ciências Exatas e da Terra', 'nome' => 'Ciências Exatas e da Terra'])
            ->assertJsonMissing(['id' => 'moda', 'nome' => 'Moda']);
    }

    public function test_segmento_filtra_area_de_atuacao_antes_da_paginacao(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();

        for ($i = 1; $i <= 12; $i++) {
            $this->criarCandidatoParaBusca('TI ' . $i, areaAtuacao: 'Tecnologia da Informação');
        }

        $this->criarCandidatoParaBusca('Saúde', areaAtuacao: 'Ambiente e Saúde');
        $this->criarCandidatoParaBusca('Comunicação', areaAtuacao: 'Comunicação, Arte e Design');

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos?segmento[]=Tecnologia%20da%20Informa%C3%A7%C3%A3o&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 12)
            ->assertJsonPath('last_page', 2)
            ->assertJsonCount(10, 'data');
    }

    public function test_sem_segmento_nao_restringe_area_de_atuacao(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();

        $ti = $this->criarCandidatoParaBusca('TI Todos', areaAtuacao: 'Tecnologia da Informação');
        $saude = $this->criarCandidatoParaBusca('Saude Todos', areaAtuacao: 'Ambiente e Saúde');
        $semAreaCanonica = $this->criarCandidatoParaBusca('Legado Todos', areaAtuacao: 'Outra');

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonFragment(['matricula' => $ti->matricula])
            ->assertJsonFragment(['matricula' => $saude->matricula])
            ->assertJsonFragment(['matricula' => $semAreaCanonica->matricula]);
    }

    public function test_backend_rejeita_segmento_fora_das_areas_canonicas(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos?segmento[]=qualquer-coisa')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['segmento']);
    }

    public function test_segmento_ra_contratacao_disponibilidade_e_habilidade_tem_semantica_and(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();

        $esperado = $this->criarCandidatoParaBusca(
            'Aluno Todos Filtros',
            disponibilidade: 'Manhã',
            habilidades: ['PHP'],
            areaAtuacao: 'Tecnologia da Informação',
        );
        \App\Models\RegiaoPreferidaTrabalho::query()->create([
            'candidato_matricula' => $esperado->matricula,
            'codigo_regiao' => 1,
        ]);

        $outraRa = $this->criarCandidatoParaBusca(
            'Aluno Outra RA',
            disponibilidade: 'Manhã',
            habilidades: ['PHP'],
            areaAtuacao: 'Tecnologia da Informação',
        );
        $outraRa->preferenciasDeTrabalho()->update(['regiao_administrativa' => 'Guará']);
        \App\Models\RegiaoPreferidaTrabalho::query()->create([
            'candidato_matricula' => $outraRa->matricula,
            'codigo_regiao' => 10,
        ]);

        $habilidadeInativa = $this->criarCandidatoParaBusca(
            'Aluno Habilidade Inativa',
            disponibilidade: 'Manhã',
            habilidades: ['Excel'],
            areaAtuacao: 'Negócios, Finanças e Gestão',
            habilidadesPorArea: ['Tecnologia da Informação' => ['PHP'], 'Negócios, Finanças e Gestão' => ['Excel']],
        );
        $habilidadeInativa->preferenciasDeTrabalho()->update(['regiao_administrativa' => 'Guará']);
        \App\Models\RegiaoPreferidaTrabalho::query()->create([
            'candidato_matricula' => $habilidadeInativa->matricula,
            'codigo_regiao' => 10,
        ]);

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos?segmento[]=Tecnologia%20da%20Informa%C3%A7%C3%A3o&tipo_contratacao=1&disponibilidade=Manh%C3%A3&regioes_administrativas[]=1&habilidades[]=PHP&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonFragment(['matricula' => $esperado->matricula])
            ->assertJsonMissing(['matricula' => $outraRa->matricula])
            ->assertJsonMissing(['matricula' => $habilidadeInativa->matricula]);
    }

    public function test_segmento_multisselecao_usa_or_e_todas_as_oito_areas(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();
        $candidatos = [];

        foreach (AreasAtuacaoCatalogo::areas() as $area) {
            $candidatos[$area] = $this->criarCandidatoParaBusca('Aluno ' . $area, areaAtuacao: $area);

            $this->withToken($tokenEmpresa)
                ->getJson('/api/candidatos?segmento[]=' . urlencode($area) . '&page=1&per_page=10')
                ->assertOk()
                ->assertJsonPath('total', 1)
                ->assertJsonFragment(['matricula' => $candidatos[$area]->matricula]);
        }

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos?segmento[]=Tecnologia%20da%20Informa%C3%A7%C3%A3o&segmento[]=Comunica%C3%A7%C3%A3o%2C%20Arte%20e%20Design&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonFragment(['matricula' => $candidatos['Tecnologia da Informação']->matricula])
            ->assertJsonFragment(['matricula' => $candidatos['Comunicação, Arte e Design']->matricula]);
    }

    public function test_segmento_usa_area_atual_e_nao_habilidades_de_area_antiga(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();
        $candidato = $this->criarCandidatoParaBusca(
            'Aluno Troca Area',
            habilidades: ['Excel'],
            areaAtuacao: 'Tecnologia da Informação',
            habilidadesPorArea: ['Tecnologia da Informação' => ['PHP', 'Vue.js'], 'Negócios, Finanças e Gestão' => ['Excel']],
        );

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos?segmento[]=Tecnologia%20da%20Informa%C3%A7%C3%A3o&page=1&per_page=10')
            ->assertOk()
            ->assertJsonFragment(['matricula' => $candidato->matricula]);

        $candidato->informacoesProfissionais()->update(['area_de_atuacao' => 'Negócios, Finanças e Gestão']);

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos?segmento[]=Tecnologia%20da%20Informa%C3%A7%C3%A3o&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonMissing(['matricula' => $candidato->matricula]);

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos?segmento[]=Neg%C3%B3cios%2C%20Finan%C3%A7as%20e%20Gest%C3%A3o&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonFragment(['matricula' => $candidato->matricula]);
    }

    public function test_empresa_lista_apenas_candidatos_liberados_mesmo_com_paginacao(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();

        for ($i = 1; $i <= 11; $i++) {
            $this->criarCandidatoParaBusca('Aluno Liberado ' . $i, status: true);
        }

        $bloqueado = $this->criarCandidatoParaBusca('Aluno Bloqueado', status: false);

        $this->withToken($tokenEmpresa)
            ->getJson('/api/candidatos?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 11)
            ->assertJsonMissing(['matricula' => $bloqueado->matricula]);
    }

    public function test_admin_lista_candidatos_paginados_com_dez_por_pagina(): void
    {
        [, $tokenAdmin] = $this->criarAdministrativoAutenticado();

        for ($i = 1; $i <= 25; $i++) {
            $this->criarCandidatoParaBusca('Aluno Admin Paginado ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        }

        $this->withToken($tokenAdmin)
            ->getJson('/api/candidatos?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('total', 25)
            ->assertJsonPath('last_page', 3)
            ->assertJsonCount(10, 'data');

        $this->withToken($tokenAdmin)
            ->getJson('/api/candidatos?page=2&per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data');

        $this->withToken($tokenAdmin)
            ->getJson('/api/candidatos?page=3&per_page=10')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_admin_paginacao_respeita_quantidades_de_cinco_dez_e_onze_candidatos(): void
    {
        [, $tokenAdmin] = $this->criarAdministrativoAutenticado();

        for ($i = 1; $i <= 5; $i++) {
            $this->criarCandidatoParaBusca('Aluno Quantidade 5 ' . $i);
        }

        $this->withToken($tokenAdmin)
            ->getJson('/api/candidatos?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('total', 5)
            ->assertJsonCount(5, 'data');

        for ($i = 6; $i <= 10; $i++) {
            $this->criarCandidatoParaBusca('Aluno Quantidade 10 ' . $i);
        }

        $this->withToken($tokenAdmin)
            ->getJson('/api/candidatos?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('total', 10)
            ->assertJsonCount(10, 'data');

        $this->criarCandidatoParaBusca('Aluno Quantidade 11');

        $this->withToken($tokenAdmin)
            ->getJson('/api/candidatos?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('total', 11)
            ->assertJsonPath('last_page', 2)
            ->assertJsonCount(10, 'data');

        $this->withToken($tokenAdmin)
            ->getJson('/api/candidatos?page=2&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 11)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_filtra_candidatos_por_busca_e_status_com_semantica_and(): void
    {
        [, $tokenAdmin] = $this->criarAdministrativoAutenticado();

        $liberado = $this->criarCandidatoParaBusca('João Silva', status: true);
        $bloqueado = $this->criarCandidatoParaBusca('João Santos', status: false);
        $maria = $this->criarCandidatoParaBusca('Maria Silva', status: true);

        $this->withToken($tokenAdmin)
            ->getJson('/api/candidatos?busca=Jo%C3%A3o&status=1')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['matricula' => $liberado->matricula])
            ->assertJsonMissing(['matricula' => $bloqueado->matricula])
            ->assertJsonMissing(['matricula' => $maria->matricula]);
    }

    public function test_admin_filtra_candidatos_apenas_por_status(): void
    {
        [, $tokenAdmin] = $this->criarAdministrativoAutenticado();

        $this->criarCandidatoParaBusca('Aluno Liberado Status', status: true);
        $bloqueado = $this->criarCandidatoParaBusca('Aluno Bloqueado Status', status: false);

        $this->withToken($tokenAdmin)
            ->getJson('/api/candidatos?status=0')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['matricula' => $bloqueado->matricula]);
    }

    public function test_admin_paginacao_preserva_busca_status_e_unidade(): void
    {
        [, $tokenAdmin] = $this->criarAdministrativoAutenticado();

        for ($i = 1; $i <= 12; $i++) {
            $this->criarCandidatoParaBusca('Maria Filtrada ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), status: true);
        }

        for ($i = 1; $i <= 4; $i++) {
            $this->criarCandidatoParaBusca('Maria Outra Unidade ' . $i, status: true);
        }

        DadosAcademicos::query()
            ->whereHas('candidato.pessoa', fn ($query) => $query->where('nome', 'like', 'Maria Outra Unidade%'))
            ->update(['unidade' => 'Taguatinga']);

        $this->criarCandidatoParaBusca('Maria Bloqueada', status: false);

        $this->withToken($tokenAdmin)
            ->getJson('/api/candidatos?busca=Maria&status=1&unidade=Asa%20Sul&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 12)
            ->assertJsonPath('last_page', 2)
            ->assertJsonCount(10, 'data');

        $this->withToken($tokenAdmin)
            ->getJson('/api/candidatos?busca=Maria&status=1&unidade=Asa%20Sul&page=2&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 12)
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing(['status' => false]);
    }

    public function test_aluno_acessa_proprio_candidato_mas_nao_acessa_candidato_de_terceiro(): void
    {
        [, $candidatoA, $tokenA] = $this->criarCandidatoAutenticado();
        [, $candidatoB] = $this->criarCandidatoAutenticado();

        $this->withToken($tokenA)
            ->getJson("/api/candidatos/{$candidatoA->matricula}")
            ->assertOk()
            ->assertJsonPath('matricula', $candidatoA->matricula);

        $this->withToken($tokenA)
            ->getJson("/api/candidatos/{$candidatoB->matricula}")
            ->assertForbidden();
    }

    public function test_empresa_acessa_candidato_ativo_para_busca_de_talentos(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();
        [, $candidato] = $this->criarCandidatoAutenticado();

        $this->withToken($tokenEmpresa)
            ->getJson("/api/candidatos/{$candidato->matricula}")
            ->assertOk()
            ->assertJsonPath('matricula', $candidato->matricula);
    }

    public function test_empresa_nao_acessa_candidato_bloqueado(): void
    {
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();
        [, $candidato] = $this->criarCandidatoAutenticado();

        $candidato->update(['status' => false]);

        $this->withToken($tokenEmpresa)
            ->getJson("/api/candidatos/{$candidato->matricula}")
            ->assertForbidden();
    }

    public function test_admin_lista_empresas_mas_aluno_e_empresa_recebem_403(): void
    {
        [, $tokenAdmin] = $this->criarAdministrativoAutenticado();
        [, , $tokenAluno] = $this->criarCandidatoAutenticado();
        [, , $tokenEmpresa] = $this->criarEmpresaAutenticada();

        $this->withToken($tokenAdmin)
            ->getJson('/api/empresas')
            ->assertOk();

        $this->withToken($tokenAluno)
            ->getJson('/api/empresas')
            ->assertForbidden();

        $this->withToken($tokenEmpresa)
            ->getJson('/api/empresas')
            ->assertForbidden();
    }

    public function test_admin_lista_empresas_paginadas_com_dez_por_pagina(): void
    {
        [, $tokenAdmin] = $this->criarAdministrativoAutenticado();

        for ($i = 1; $i <= 25; $i++) {
            $this->criarEmpresaParaGestao('Empresa Paginada ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'Responsável Empresa ' . $i, true);
        }

        $this->withToken($tokenAdmin)
            ->getJson('/api/empresas?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('total', 25)
            ->assertJsonPath('last_page', 3)
            ->assertJsonCount(10, 'data');

        $this->withToken($tokenAdmin)
            ->getJson('/api/empresas?page=2&per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data');

        $this->withToken($tokenAdmin)
            ->getJson('/api/empresas?page=3&per_page=10')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_admin_filtra_empresas_por_busca_e_status_com_semantica_and(): void
    {
        [, $tokenAdmin] = $this->criarAdministrativoAutenticado();

        $liberada = $this->criarEmpresaParaGestao('Empresa Teste Liberada', 'Responsável Alpha', true);
        $bloqueada = $this->criarEmpresaParaGestao('Empresa Teste Bloqueada', 'Responsável Beta', false);
        $outra = $this->criarEmpresaParaGestao('Outra Empresa', 'Responsável Teste', false);

        $this->withToken($tokenAdmin)
            ->getJson('/api/empresas?busca=Empresa%20Teste&status=0')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['cnpj' => $bloqueada->cnpj])
            ->assertJsonMissing(['cnpj' => $liberada->cnpj])
            ->assertJsonMissing(['cnpj' => $outra->cnpj]);
    }

    public function test_admin_filtra_empresas_apenas_por_status(): void
    {
        [, $tokenAdmin] = $this->criarAdministrativoAutenticado();

        $this->criarEmpresaParaGestao('Empresa Liberada Status', 'Responsável Liberado', true);
        $bloqueada = $this->criarEmpresaParaGestao('Empresa Bloqueada Status', 'Responsável Bloqueado', false);

        $this->withToken($tokenAdmin)
            ->getJson('/api/empresas?status=0')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['cnpj' => $bloqueada->cnpj]);
    }

    public function test_admin_paginacao_de_empresas_respeita_filtros(): void
    {
        [, $tokenAdmin] = $this->criarAdministrativoAutenticado();

        for ($i = 1; $i <= 12; $i++) {
            $this->criarEmpresaParaGestao('Empresa Filtro Ativa ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'Responsável Ativo ' . $i, true);
        }

        for ($i = 1; $i <= 4; $i++) {
            $this->criarEmpresaParaGestao('Empresa Filtro Bloqueada ' . $i, 'Responsável Bloqueado ' . $i, false);
        }

        $this->withToken($tokenAdmin)
            ->getJson('/api/empresas?busca=Filtro&status=1&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 12)
            ->assertJsonPath('last_page', 2)
            ->assertJsonCount(10, 'data');

        $this->withToken($tokenAdmin)
            ->getJson('/api/empresas?busca=Filtro&status=1&page=2&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 12)
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing(['status' => false]);
    }

    public function test_empresa_acessa_propria_empresa_mas_nao_acessa_empresa_de_terceiro(): void
    {
        [, $empresaA, $tokenA] = $this->criarEmpresaAutenticada();
        [, $empresaB] = $this->criarEmpresaAutenticada();

        $this->withToken($tokenA)
            ->getJson("/api/empresas/{$empresaA->cnpj}")
            ->assertOk()
            ->assertJsonPath('cnpj', $empresaA->cnpj);

        $this->withToken($tokenA)
            ->getJson("/api/empresas/{$empresaB->cnpj}")
            ->assertForbidden();
    }

    public function test_admin_acessa_empresa_de_qualquer_cnpj_e_aluno_recebe_403(): void
    {
        [, $tokenAdmin] = $this->criarAdministrativoAutenticado();
        [, , $tokenAluno] = $this->criarCandidatoAutenticado();
        [, $empresa] = $this->criarEmpresaAutenticada();

        $this->withToken($tokenAdmin)
            ->getJson("/api/empresas/{$empresa->cnpj}")
            ->assertOk()
            ->assertJsonPath('cnpj', $empresa->cnpj);

        $this->withToken($tokenAluno)
            ->getJson("/api/empresas/{$empresa->cnpj}")
            ->assertForbidden();
    }

    public function test_empresa_cria_vaga_propria_com_cnpj_do_token(): void
    {
        [, $empresa, $token] = $this->criarEmpresaAutenticada();
        [, $outraEmpresa] = $this->criarEmpresaAutenticada();

        $response = $this->withToken($token)->postJson('/api/vagas', [
            'titulo' => 'Pessoa Desenvolvedora',
            'tipo' => 1,
            'area' => 'Tecnologia',
            'status' => true,
            'data_publicacao' => '2026-09-01',
            'empresa_cnpj' => $outraEmpresa->cnpj,
        ]);

        $response->assertCreated()
            ->assertJsonPath('empresa_cnpj', $empresa->cnpj);

        $this->assertDatabaseHas('vagas', [
            'titulo' => 'Pessoa Desenvolvedora',
            'empresa_cnpj' => $empresa->cnpj,
        ]);
    }

    public function test_empresa_nao_cria_vaga_com_tipo_de_contratacao_antigo(): void
    {
        [, $empresa, $token] = $this->criarEmpresaAutenticada();

        $this->withToken($token)->postJson('/api/vagas', [
            'titulo' => 'Pessoa Desenvolvedora',
            'tipo' => 2,
            'area' => 'Tecnologia',
            'status' => true,
            'data_publicacao' => '2026-09-01',
            'empresa_cnpj' => $empresa->cnpj,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['tipo']);
    }

    public function test_empresa_lista_somente_suas_vagas_e_nao_recebe_vaga_de_outra_empresa(): void
    {
        [, $empresaA, $tokenA] = $this->criarEmpresaAutenticada();
        [, $empresaB] = $this->criarEmpresaAutenticada();

        $vagaA = $this->criarVagaParaEmpresa($empresaA, 'Vaga Empresa A');
        $vagaB = $this->criarVagaParaEmpresa($empresaB, 'Vaga Empresa B');

        $this->withToken($tokenA)
            ->getJson('/api/vagas')
            ->assertOk()
            ->assertJsonFragment(['id_vaga' => $vagaA->id_vaga])
            ->assertJsonMissing(['id_vaga' => $vagaB->id_vaga]);
    }

    public function test_empresa_nao_acessa_vaga_de_outra_empresa_e_admin_mantem_acesso(): void
    {
        [, $tokenAdmin] = $this->criarAdministrativoAutenticado();
        [, $empresaA, $tokenA] = $this->criarEmpresaAutenticada();
        [, $empresaB] = $this->criarEmpresaAutenticada();

        $vagaA = $this->criarVagaParaEmpresa($empresaA, 'Vaga Empresa A');
        $vagaB = $this->criarVagaParaEmpresa($empresaB, 'Vaga Empresa B');

        $this->withToken($tokenA)
            ->getJson("/api/vagas/{$vagaA->id_vaga}")
            ->assertOk();

        $this->withToken($tokenA)
            ->getJson("/api/vagas/{$vagaB->id_vaga}")
            ->assertForbidden();

        $this->withToken($tokenAdmin)
            ->getJson('/api/vagas')
            ->assertOk()
            ->assertJsonFragment(['id_vaga' => $vagaA->id_vaga])
            ->assertJsonFragment(['id_vaga' => $vagaB->id_vaga]);

        $this->withToken($tokenAdmin)
            ->getJson("/api/vagas/{$vagaB->id_vaga}")
            ->assertOk();
    }

    public function test_aluno_nao_pode_criar_vaga(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();

        $this->withToken($token)->postJson('/api/vagas', [
            'titulo' => 'Pessoa Desenvolvedora',
            'tipo' => 1,
            'area' => 'Tecnologia',
            'status' => true,
            'data_publicacao' => '2026-09-01',
        ])->assertForbidden();
    }

    public function test_empresa_a_nao_pode_editar_vaga_da_empresa_b(): void
    {
        [, , $tokenEmpresaA] = $this->criarEmpresaAutenticada();
        [, $empresaB] = $this->criarEmpresaAutenticada();

        $vaga = Vaga::query()->create([
            'titulo' => 'Vaga Empresa B',
            'tipo' => 1,
            'area' => 'Financeiro',
            'status' => true,
            'data_publicacao' => '2026-09-01',
            'empresa_cnpj' => $empresaB->cnpj,
        ]);

        $this->withToken($tokenEmpresaA)
            ->putJson("/api/vagas/{$vaga->id_vaga}", [
                'titulo' => 'Tentativa indevida',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('vagas', [
            'id_vaga' => $vaga->id_vaga,
            'titulo' => 'Vaga Empresa B',
        ]);
    }

    public function test_empresa_a_nao_pode_excluir_vaga_da_empresa_b(): void
    {
        [, , $tokenEmpresaA] = $this->criarEmpresaAutenticada();
        [, $empresaB] = $this->criarEmpresaAutenticada();

        $vaga = Vaga::query()->create([
            'titulo' => 'Vaga Empresa B',
            'tipo' => 1,
            'area' => 'Financeiro',
            'status' => true,
            'data_publicacao' => '2026-09-01',
            'empresa_cnpj' => $empresaB->cnpj,
        ]);

        $this->withToken($tokenEmpresaA)
            ->deleteJson("/api/vagas/{$vaga->id_vaga}")
            ->assertForbidden();

        $this->assertDatabaseHas('vagas', [
            'id_vaga' => $vaga->id_vaga,
        ]);
    }

    public function test_empresa_cria_convite_proprio(): void
    {
        [, $empresa, $tokenEmpresa] = $this->criarEmpresaAutenticada();
        [, $candidato] = $this->criarCandidatoAutenticado();

        $vaga = Vaga::query()->create([
            'titulo' => 'Vaga Convite',
            'tipo' => 1,
            'area' => 'Tecnologia',
            'status' => true,
            'data_publicacao' => '2026-09-01',
            'empresa_cnpj' => $empresa->cnpj,
        ]);

        $response = $this->withToken($tokenEmpresa)->postJson('/api/convites', [
            'descricao' => 'Convite para processo seletivo',
            'empresa_cnpj' => '99999999999999',
            'candidatos_matricula' => $candidato->matricula,
            'vagas_id_vaga' => $vaga->id_vaga,
        ]);

        $response->assertCreated()
            ->assertJsonPath('empresa_cnpj', $empresa->cnpj);

        $this->assertDatabaseHas('convites', [
            'empresa_cnpj' => $empresa->cnpj,
            'candidatos_matricula' => $candidato->matricula,
            'vagas_id_vaga' => $vaga->id_vaga,
        ]);
    }

    public function test_empresa_ve_somente_convites_proprios(): void
    {
        [, $empresaA, $tokenA] = $this->criarEmpresaAutenticada();
        [, $empresaB] = $this->criarEmpresaAutenticada();
        [, $candidato] = $this->criarCandidatoAutenticado();

        $conviteA = $this->criarConviteParaEmpresaECandidato($empresaA, $candidato, 'Convite A');
        $conviteB = $this->criarConviteParaEmpresaECandidato($empresaB, $candidato, 'Convite B');

        $this->withToken($tokenA)
            ->getJson('/api/convites')
            ->assertOk()
            ->assertJsonFragment(['id' => $conviteA->id])
            ->assertJsonMissing(['id' => $conviteB->id]);

        $this->withToken($tokenA)
            ->getJson("/api/convites/{$conviteB->id}")
            ->assertForbidden();
    }

    public function test_aluno_ve_somente_convites_proprios(): void
    {
        [, $empresa] = $this->criarEmpresaAutenticada();
        [, $candidatoA, $tokenA] = $this->criarCandidatoAutenticado();
        [, $candidatoB] = $this->criarCandidatoAutenticado();

        $conviteA = $this->criarConviteParaEmpresaECandidato($empresa, $candidatoA, 'Convite Aluno A');
        $conviteB = $this->criarConviteParaEmpresaECandidato($empresa, $candidatoB, 'Convite Aluno B');

        $this->withToken($tokenA)
            ->getJson('/api/convites')
            ->assertOk()
            ->assertJsonFragment(['id' => $conviteA->id])
            ->assertJsonMissing(['id' => $conviteB->id]);

        $this->withToken($tokenA)
            ->getJson("/api/convites/{$conviteA->id}")
            ->assertOk();

        $this->withToken($tokenA)
            ->getJson("/api/convites/{$conviteB->id}")
            ->assertForbidden();
    }

    public function test_admin_ve_todos_os_convites(): void
    {
        [, $tokenAdmin] = $this->criarAdministrativoAutenticado();
        [, $empresaA] = $this->criarEmpresaAutenticada();
        [, $empresaB] = $this->criarEmpresaAutenticada();
        [, $candidatoA] = $this->criarCandidatoAutenticado();
        [, $candidatoB] = $this->criarCandidatoAutenticado();

        $conviteA = $this->criarConviteParaEmpresaECandidato($empresaA, $candidatoA, 'Convite A');
        $conviteB = $this->criarConviteParaEmpresaECandidato($empresaB, $candidatoB, 'Convite B');

        $this->withToken($tokenAdmin)
            ->getJson('/api/convites')
            ->assertOk()
            ->assertJsonFragment(['id' => $conviteA->id])
            ->assertJsonFragment(['id' => $conviteB->id]);

        $this->withToken($tokenAdmin)
            ->getJson("/api/convites/{$conviteB->id}")
            ->assertOk();
    }

    public function test_aluno_nao_pode_criar_convite(): void
    {
        [, $candidato, $tokenAluno] = $this->criarCandidatoAutenticado();
        [, $empresa] = $this->criarEmpresaAutenticada();

        $vaga = Vaga::query()->create([
            'titulo' => 'Vaga Convite',
            'tipo' => 1,
            'area' => 'Tecnologia',
            'status' => true,
            'data_publicacao' => '2026-09-01',
            'empresa_cnpj' => $empresa->cnpj,
        ]);

        $this->withToken($tokenAluno)->postJson('/api/convites', [
            'descricao' => 'Tentativa indevida',
            'candidatos_matricula' => $candidato->matricula,
            'vagas_id_vaga' => $vaga->id_vaga,
        ])->assertForbidden();
    }

    public function test_empresa_a_nao_pode_excluir_convite_da_empresa_b(): void
    {
        [, , $tokenEmpresaA] = $this->criarEmpresaAutenticada();
        [, $empresaB] = $this->criarEmpresaAutenticada();
        [, $candidato] = $this->criarCandidatoAutenticado();

        $vaga = Vaga::query()->create([
            'titulo' => 'Vaga Empresa B',
            'tipo' => 1,
            'area' => 'Tecnologia',
            'status' => true,
            'data_publicacao' => '2026-09-01',
            'empresa_cnpj' => $empresaB->cnpj,
        ]);

        $convite = Convite::query()->create([
            'descricao' => 'Convite Empresa B',
            'data_envio' => now(),
            'status' => Convite::STATUS_PENDENTE,
            'empresa_cnpj' => $empresaB->cnpj,
            'candidatos_matricula' => $candidato->matricula,
            'vagas_id_vaga' => $vaga->id_vaga,
        ]);

        $this->withToken($tokenEmpresaA)
            ->deleteJson("/api/convites/{$convite->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('convites', [
            'id' => $convite->id,
        ]);
    }

    public function test_candidato_remove_recurso_proprio(): void
    {
        [, $candidato, $token] = $this->criarCandidatoAutenticado();

        $dadoAcademico = DadosAcademicos::query()->create([
            'instituicao' => 'Senac',
            'curso' => 'ADS',
            'segmento' => 'Tecnologia',
            'tipo_curso' => 'Tecnico',
            'unidade' => 'Asa Sul',
            'ano_de_conclusao' => '2026-12-01',
            'candidato_matricula' => $candidato->matricula,
        ]);

        $cursoSenac = CursoSenac::query()->create([
            'nome_curso' => 'Excel',
            'unidade' => 'Taguatinga',
            'carga_horaria' => 40,
            'concluido_em' => '2026-08-01',
            'candidato_matricula' => $candidato->matricula,
        ]);

        $this->withToken($token)
            ->deleteJson("/api/academico/{$dadoAcademico->id}")
            ->assertOk();

        $this->withToken($token)
            ->deleteJson("/api/cursos-senac/{$cursoSenac->id}")
            ->assertOk();
    }

    public function test_candidato_nao_remove_recurso_de_outro_candidato(): void
    {
        [, $candidatoA, $tokenA] = $this->criarCandidatoAutenticado();
        [, $candidatoB] = $this->criarCandidatoAutenticado();

        $dadoAcademico = DadosAcademicos::query()->create([
            'instituicao' => 'Senac',
            'curso' => 'ADS',
            'segmento' => 'Tecnologia',
            'tipo_curso' => 'Tecnico',
            'unidade' => 'Asa Sul',
            'ano_de_conclusao' => '2026-12-01',
            'candidato_matricula' => $candidatoB->matricula,
        ]);

        $cursoSenac = CursoSenac::query()->create([
            'nome_curso' => 'Excel',
            'unidade' => 'Taguatinga',
            'carga_horaria' => 40,
            'concluido_em' => '2026-08-01',
            'candidato_matricula' => $candidatoB->matricula,
        ]);

        $this->withToken($tokenA)
            ->deleteJson("/api/academico/{$dadoAcademico->id}")
            ->assertForbidden();

        $this->withToken($tokenA)
            ->deleteJson("/api/cursos-senac/{$cursoSenac->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('dados_academicos', [
            'id' => $dadoAcademico->id,
            'candidato_matricula' => $candidatoB->matricula,
        ]);

        $this->assertDatabaseHas('cursos_senac', [
            'id' => $cursoSenac->id,
            'candidato_matricula' => $candidatoB->matricula,
        ]);
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

    private function criarCandidatoParaBusca(
        string $nome,
        bool $status = true,
        string $segmento = 'tecnologia-da-informacao',
        string $tipoCurso = 'tecnico',
        string $disponibilidade = 'Manhã',
        array $habilidades = ['php'],
        ?string $areaAtuacao = null,
        ?array $habilidadesPorArea = null,
        int $tipoContratacao = 1,
        bool $aceitaTodasRegioes = false,
        ?string $telefone = null
    ): Candidato {
        $pessoa = Pessoa::query()->create([
            'nome' => $nome,
            'email' => Str::slug($nome) . Str::random(6) . '@teste.com',
            'telefone' => $telefone ?? (string) random_int(10000000000, 99999999999),
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);

        $candidato = Candidato::query()->create([
            'matricula' => $this->gerarMatricula(),
            'cpf' => (string) random_int(10000000000, 99999999999),
            'status' => $status,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);

        DadosAcademicos::query()->create([
            'instituicao' => 'Senac',
            'curso' => 'Curso Teste',
            'segmento' => $segmento,
            'tipo_curso' => $tipoCurso,
            'unidade' => 'Asa Sul',
            'ano_de_conclusao' => now(),
            'candidato_matricula' => $candidato->matricula,
        ]);

        \App\Models\PreferenciasDeTrabalho::query()->create([
            'tipo_de_contratacao' => $tipoContratacao,
            'disponibilidade_de_horario' => [$disponibilidade],
            'regiao_administrativa' => 'Plano Piloto',
            'aceita_todas_regioes' => $aceitaTodasRegioes,
            'pretensao_salarial' => 2500,
            'candidato_matricula' => $candidato->matricula,
        ]);

        $areaAtuacao ??= CatalogoAcademicoSenacDf::valoresLegadosSegmento($segmento)[1] ?? $segmento;

        \App\Models\InformacoesProfissionais::query()->create([
            'sobre_mim' => 'Perfil de teste',
            'cargo_de_interesse' => 'Desenvolvedor',
            'area_de_atuacao' => $areaAtuacao,
            'habilidades' => $habilidades,
            'habilidades_por_area' => $habilidadesPorArea,
            'candidato_matricula' => $candidato->matricula,
        ]);

        return $candidato;
    }

    private function criarEmpresaAutenticada(): array
    {
        $pessoaEmpresa = $this->criarPessoa('empresa');
        $pessoaResponsavel = $this->criarPessoa('responsavel');

        $responsavel = ResponsavelContratual::query()->create([
            'pessoa_id_pessoa' => $pessoaResponsavel->id_pessoa,
        ]);

        $empresa = Empresa::query()->create([
            'cnpj' => (string) random_int(10000000000000, 99999999999999),
            'razao_social' => 'Empresa ' . Str::random(5),
            'atividade_economica' => 'Tecnologia',
            'status' => true,
            'pessoa_id_pessoa' => $pessoaEmpresa->id_pessoa,
            'responsavel_contratual_id_responsavel_contratual' => $responsavel->id_responsavel_contratual,
        ]);

        return [$pessoaEmpresa, $empresa, $this->gerarTokenParaPessoa($pessoaEmpresa)];
    }

    private function criarEmpresaParaGestao(string $razaoSocial, string $responsavelNome, bool $status): Empresa
    {
        $pessoaEmpresa = Pessoa::query()->create([
            'nome' => $razaoSocial,
            'email' => Str::slug($razaoSocial) . Str::random(6) . '@teste.com',
            'telefone' => (string) random_int(10000000000, 99999999999),
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);

        $pessoaResponsavel = Pessoa::query()->create([
            'nome' => $responsavelNome,
            'email' => Str::slug($responsavelNome) . Str::random(6) . '@teste.com',
            'telefone' => (string) random_int(10000000000, 99999999999),
            'senha' => bcrypt('123456'),
            'data_cadastro' => now(),
        ]);

        $responsavel = ResponsavelContratual::query()->create([
            'pessoa_id_pessoa' => $pessoaResponsavel->id_pessoa,
        ]);

        return Empresa::query()->create([
            'cnpj' => (string) random_int(10000000000000, 99999999999999),
            'razao_social' => $razaoSocial,
            'atividade_economica' => 'Tecnologia',
            'status' => $status,
            'pessoa_id_pessoa' => $pessoaEmpresa->id_pessoa,
            'responsavel_contratual_id_responsavel_contratual' => $responsavel->id_responsavel_contratual,
        ]);
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

    private function gerarTokenParaPessoa(Pessoa $pessoa): string
    {
        $token = 'teste-token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return $token;
    }

    private function criarVagaParaEmpresa(Empresa $empresa, string $titulo): Vaga
    {
        return Vaga::query()->create([
            'titulo' => $titulo,
            'tipo' => 1,
            'area' => 'Tecnologia',
            'status' => true,
            'data_publicacao' => '2026-09-01',
            'empresa_cnpj' => $empresa->cnpj,
        ]);
    }

    private function criarConviteParaEmpresaECandidato(Empresa $empresa, Candidato $candidato, string $descricao): Convite
    {
        $vaga = $this->criarVagaParaEmpresa($empresa, 'Vaga ' . Str::random(5));

        return Convite::query()->create([
            'descricao' => $descricao,
            'data_envio' => now(),
            'status' => Convite::STATUS_PENDENTE,
            'empresa_cnpj' => $empresa->cnpj,
            'candidatos_matricula' => $candidato->matricula,
            'vagas_id_vaga' => $vaga->id_vaga,
        ]);
    }
}
