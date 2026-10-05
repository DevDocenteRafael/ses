<?php

namespace Database\Seeders;

use App\Support\CatalogoAcademicoSenacDf;
use App\Support\RegioesAdministrativasDf;
use Carbon\Carbon;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Popula o banco com 10.000 candidatos (alunos) fictícios e COMPLETOS.
 *
 * Para cada candidato são criados registros em:
 *   pessoa (com endereço), candidato, link_externo, informacoes_profissionais,
 *   preferencias_de_trabalho, regioes_preferidas_trabalho, dados_academicos,
 *   cursos_senac, cursos_externos e experiencias_profissionais.
 *
 * Como executar:
 *   php artisan db:seed --class=CandidatosFicticiosSeeder
 *
 * Perfis fixos (os 3 primeiros candidatos, criados VAZIOS: só conta básica):
 *   arlinson.santos@ficticio.senac.test
 *   barbara.machado@ficticio.senac.test
 *   ana.biatriz@ficticio.senac.test
 *
 * Login de qualquer candidato gerado:
 *   email: (veja na tabela pessoa, domínio @ficticio.senac.test)
 *   senha: senac123
 *
 * Observações:
 *  - Usa inserts em lote (DB::table) e um único hash de senha reaproveitado,
 *    senão 10 mil bcrypt levariam vários minutos.
 *  - Os dados são determinísticos (faker com seed fixa).
 *  - Não roda duas vezes: se já existirem pessoas do domínio fictício, pula.
 *  - Todos os valores respeitam as validações do PerfilCandidatocontroller
 *    e os catálogos do sistema (regiões, habilidades, tipos/segmentos de curso).
 */
class CandidatosFicticiosSeeder extends Seeder
{
    private const TOTAL = 10000;
    private const LOTE = 500;
    private const DOMINIO = 'ficticio.senac.test';
    private const SENHA = 'senac123';

    /**
     * Perfis com nome definido. São criados primeiro (entram na contagem de TOTAL),
     * com e-mail sem número: nome.sobrenome@ficticio.senac.test
     * São criados VAZIOS: apenas pessoa + candidato (nome, e-mail, telefone, CPF,
     * matrícula e senha). Endereço, links, habilidades, preferências, dados
     * acadêmicos, cursos e experiências ficam em branco para preencher no sistema.
     */
    private const PERFIS_FIXOS = [
        'Arlinson Santos',
        'Bárbara Machado',
        'Ana Biatriz',
    ];

    /** Unidades fictícias do Senac usadas em dados_academicos e cursos_senac. Ajuste à vontade. */
    private const UNIDADES = [
        'Senac Taguatinga', 'Senac Ceilândia', 'Senac Gama', 'Senac Águas Claras',
        'Senac Sobradinho', 'Senac Planaltina', 'Senac Samambaia', 'Senac Guará',
        'Senac Asa Norte', 'Senac Asa Sul', 'Senac Santa Maria', 'Senac Recanto das Emas',
    ];

    /** Segmento acadêmico -> área de atuação profissional (chaves do habilidades.json). */
    private const AREA_POR_SEGMENTO = [
        'tecnologia-da-informacao' => 'Tecnologia da Informação',
        'gestao-e-negocios'        => 'Administração',
        'comercio'                 => 'Administração',
        'comunicacao'              => 'Marketing',
        'design'                   => 'Marketing',
        'moda'                     => 'Marketing',
        'beleza'                   => 'Marketing',
        'educacao'                 => 'Recursos Humanos',
    ];

    private const PREFIXO_TIPO = [
        'livres'        => 'Curso de ',
        'extensao'      => 'Certificação em ',
        'tecnico'       => 'Técnico em ',
        'graduacao'     => 'Graduação em ',
        'pos-graduacao' => 'Pós-graduação em ',
    ];

    private const ASSUNTOS_POR_SEGMENTO = [
        'tecnologia-da-informacao' => ['Desenvolvimento de Sistemas', 'Redes de Computadores', 'Ciência de Dados', 'Segurança da Informação', 'Análise de Sistemas', 'Suporte e Manutenção de Computadores', 'Programação Web'],
        'gestao-e-negocios'        => ['Administração', 'Recursos Humanos', 'Logística', 'Finanças', 'Contabilidade', 'Gestão Comercial', 'Marketing'],
        'educacao'                 => ['Docência da Educação Profissional', 'Educação Inclusiva', 'Metodologias Ativas'],
        'ambiente-e-saude'         => ['Enfermagem', 'Saúde Bucal', 'Meio Ambiente', 'Segurança do Trabalho'],
        'design'                   => ['Design Gráfico', 'Design de Interiores', 'Design Digital'],
        'gastronomia-e-turismo'    => ['Cozinha', 'Panificação', 'Turismo', 'Hospedagem', 'Eventos'],
        'seguranca'                => ['Segurança Patrimonial', 'Segurança do Trabalho'],
        'comercio'                 => ['Vendas', 'Atendimento ao Cliente', 'Comércio Varejista'],
        'comunicacao'              => ['Produção de Conteúdo', 'Fotografia', 'Comunicação Digital'],
        'beleza'                   => ['Cabeleireiro', 'Manicure e Pedicure', 'Maquiagem', 'Estética'],
        'conservacao-e-zeladoria'  => ['Zeladoria', 'Conservação de Ambientes'],
        'idiomas'                  => ['Inglês', 'Espanhol'],
        'moda'                     => ['Costura', 'Modelagem', 'Moda'],
        'producao-de-alimentos'    => ['Boas Práticas de Manipulação', 'Produção de Alimentos'],
    ];

    /** Cursos curtos (nome => carga horária) por área. */
    private const CURSOS_CURTOS = [
        'Tecnologia da Informação' => [
            'Lógica de Programação' => 40, 'JavaScript Básico' => 60, 'Banco de Dados MySQL' => 60,
            'Git e GitHub' => 20, 'Introdução a Redes' => 40, 'Python para Iniciantes' => 60,
            'Segurança Digital' => 30, 'Manutenção de Computadores' => 80, 'Desenvolvimento Web com PHP' => 80,
            'Laravel na Prática' => 60,
        ],
        'Administração' => [
            'Excel Básico' => 30, 'Excel Avançado' => 40, 'Rotinas Administrativas' => 40,
            'Atendimento ao Cliente' => 20, 'Matemática Financeira' => 30, 'Power BI' => 40,
            'Gestão de Documentos' => 30, 'Organização e Métodos' => 30,
        ],
        'Marketing' => [
            'Marketing Digital' => 40, 'Gestão de Redes Sociais' => 30, 'Fotografia com Celular' => 20,
            'Design com Canva' => 20, 'Google Analytics' => 30, 'Copywriting' => 30,
            'SEO na Prática' => 30, 'Edição de Vídeo' => 40,
        ],
        'Recursos Humanos' => [
            'Recrutamento e Seleção' => 40, 'Departamento Pessoal' => 60, 'Treinamento e Desenvolvimento' => 40,
            'Liderança e Gestão de Equipes' => 30, 'Gestão do Tempo' => 20, 'Comunicação Empresarial' => 30,
            'Rotinas de Folha de Pagamento' => 40,
        ],
        'Outra' => [
            'Vendas e Negociação' => 30, 'Atendimento ao Cliente' => 20, 'Controle de Estoque' => 30,
            'Informática Básica' => 40, 'Boas Práticas de Trabalho' => 20, 'Empreendedorismo' => 40,
            'Inglês Instrumental' => 60,
        ],
    ];

    private const INSTITUICOES_EXTERNAS = [
        'Alura', 'Udemy', 'Coursera', 'Fundação Bradesco', 'SENAI', 'SEBRAE', 'Cisco Networking Academy',
        'Microsoft Learn', 'DIO', 'Escola Virtual.Gov', 'Google Skillshop', 'FGV Online', 'Rocketseat',
        'Descomplica', 'IFB',
    ];

    private const CARGOS_POR_AREA = [
        'Tecnologia da Informação' => ['Desenvolvedor Júnior', 'Analista de Suporte', 'Técnico de Informática', 'Analista de Dados', 'Desenvolvedor Front-end', 'Desenvolvedor Back-end', 'Analista de Redes', 'Estagiário de TI'],
        'Administração'            => ['Auxiliar Administrativo', 'Assistente Administrativo', 'Assistente Financeiro', 'Recepcionista', 'Auxiliar de Escritório', 'Analista Financeiro Júnior', 'Estagiário Administrativo'],
        'Marketing'                => ['Social Media', 'Assistente de Marketing', 'Designer Gráfico', 'Analista de Marketing Digital', 'Redator', 'Produtor de Conteúdo', 'Estagiário de Marketing'],
        'Recursos Humanos'         => ['Assistente de RH', 'Analista de Recrutamento', 'Auxiliar de Departamento Pessoal', 'Analista de RH Júnior', 'Assistente de Treinamento', 'Estagiário de RH'],
        'Outra'                    => ['Vendedor', 'Atendente', 'Auxiliar de Estoque', 'Assistente Comercial', 'Operador de Caixa', 'Auxiliar de Logística', 'Estagiário'],
    ];

    private const TIPOS_EXPERIENCIA = ['Estágio', 'CLT', 'Freelancer', 'Voluntário'];

    private const DISPONIBILIDADES = [
        ['Manhã'], ['Tarde'], ['Noite'], ['Integral'],
        ['Manhã', 'Tarde'], ['Tarde', 'Noite'], ['Manhã', 'Tarde', 'Noite'],
    ];

    private Generator $faker;
    private array $catalogoHabilidades = [];
    private array $regioes = [];
    private array $tipos = [];
    private array $cpfsUsados = [];
    private array $telefonesUsados = [];
    private array $matriculasUsadas = [];

    public function run(): void
    {
        DB::disableQueryLog();

        if (DB::table('pessoa')->where('email', 'like', '%@' . self::DOMINIO)->exists()) {
            $this->command?->info('Candidatos fictícios já existem (domínio @' . self::DOMINIO . '), pulando.');
            return;
        }

        $this->faker = FakerFactory::create('pt_BR');
        $this->faker->seed(20260929);

        $this->catalogoHabilidades = json_decode(
            file_get_contents(resource_path('catalogos/habilidades.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $this->regioes = RegioesAdministrativasDf::todas();
        $this->tipos = array_column(CatalogoAcademicoSenacDf::tipos(), 'id');

        // Evita colisão com registros já existentes.
        $this->cpfsUsados       = array_flip(DB::table('candidato')->pluck('cpf')->all());
        $this->telefonesUsados  = array_flip(DB::table('pessoa')->pluck('telefone')->all());
        $this->matriculasUsadas = array_flip(DB::table('candidato')->pluck('matricula')->map(fn ($m) => (string) $m)->all());

        $senhaHash    = Hash::make(self::SENHA); // um único hash reaproveitado (bcrypt é lento)
        $idPessoa     = (int) (DB::table('pessoa')->max('id_pessoa') ?? 0) + 1;
        $sequencia    = 1;
        $criados      = 0;

        $this->command?->info('Gerando ' . self::TOTAL . ' candidatos fictícios...');

        while ($criados < self::TOTAL) {
            $tamanho = min(self::LOTE, self::TOTAL - $criados);

            $lote = [
                'pessoa' => [], 'candidato' => [], 'link_externo' => [], 'informacoes_profissionais' => [],
                'preferencias_de_trabalho' => [], 'regioes_preferidas_trabalho' => [], 'dados_academicos' => [],
                'cursos_senac' => [], 'cursos_externos' => [], 'experiencias_profissionais' => [],
            ];

            for ($i = 0; $i < $tamanho; $i++) {
                $matricula = $this->proximaMatricula($sequencia);
                $indice    = $criados + $i;
                $nomeFixo  = self::PERFIS_FIXOS[$indice] ?? null;
                $this->gerarCandidato($lote, $idPessoa, $matricula, $indice + 1, $senhaHash, $nomeFixo);
                $idPessoa++;
            }

            DB::transaction(function () use ($lote) {
                foreach ($lote as $tabela => $linhas) {
                    if (! empty($linhas)) {
                        DB::table($tabela)->insert($linhas);
                    }
                }
            });

            $criados += $tamanho;

            if ($criados % 1000 === 0) {
                $this->command?->info("  {$criados} / " . self::TOTAL);
            }
        }

        $this->command?->info(self::TOTAL . ' candidatos fictícios criados. Senha de todos: ' . self::SENHA);

        foreach (self::PERFIS_FIXOS as $nome) {
            $this->command?->info('  • ' . $nome . ' → ' . Str::slug($nome, '.') . '@' . self::DOMINIO);
        }
    }

    // ─────────────────────────────────────────────────────────────

    private function gerarCandidato(array &$lote, int $idPessoa, string $matricula, int $numero, string $senhaHash, ?string $nomeFixo = null): void
    {
        $f     = $this->faker;
        $agora = now()->format('Y-m-d H:i:s');

        // Identidade -------------------------------------------------
        $genero = $f->randomElement(['male', 'female']);
        $nome   = $nomeFixo ?? trim($f->firstName($genero) . ' ' . $f->lastName() . ' ' . $f->lastName());
        $slug   = Str::limit(Str::slug($nome, '.'), 60, '');
        $email  = $nomeFixo
            ? "{$slug}@" . self::DOMINIO
            : "{$slug}.{$numero}@" . self::DOMINIO;

        // Perfis fixos: só a conta básica (igual a um cadastro recém-feito).
        // Nenhuma outra tabela é preenchida, para a pessoa completar pelo sistema.
        if ($nomeFixo !== null) {
            $lote['pessoa'][] = [
                'id_pessoa'     => $idPessoa,
                'nome'          => $nome,
                'email'         => $email,
                'telefone'      => $this->telefoneUnico(),
                'senha'         => $senhaHash,
                'data_cadastro' => $agora,
                'created_at'    => $agora,
                'updated_at'    => $agora,
            ];

            $lote['candidato'][] = [
                'matricula'        => $matricula,
                'cpf'              => $this->cpfUnico(),
                'status'           => 1,
                'pessoa_id_pessoa' => $idPessoa,
                'created_at'       => $agora,
                'updated_at'       => $agora,
            ];

            return;
        }

        $regiaoResidencia = $f->randomElement($this->regioes);

        $lote['pessoa'][] = [
            'id_pessoa'            => $idPessoa,
            'nome'                 => Str::limit($nome, 100, ''),
            'email'                => $email,
            'telefone'             => $this->telefoneUnico(),
            'endereco_cep'         => sprintf('%08d', $f->numberBetween(70000000, 73699999)),
            'endereco_logradouro'  => Str::limit($this->logradouro(), 120, ''),
            'endereco_numero'      => (string) $f->numberBetween(1, 999),
            'endereco_complemento' => $f->randomElement(['Casa', 'Apto 101', 'Apto 302', 'Bloco B, Apto 204', 'Lote ' . $f->numberBetween(1, 40), 'Fundos']),
            'endereco_bairro'      => Str::limit($regiaoResidencia['nome'], 80, ''),
            'endereco_cidade'      => 'Brasília',
            'endereco_uf'          => 'DF',
            'senha'                => $senhaHash,
            'data_cadastro'        => Carbon::instance($f->dateTimeBetween('-12 months', '-1 day'))->format('Y-m-d H:i:s'),
            'created_at'           => $agora,
            'updated_at'           => $agora,
        ];

        $lote['candidato'][] = [
            'matricula'        => $matricula,
            'cpf'              => $this->cpfUnico(),
            'status'           => $f->boolean(92) ? 1 : 0,
            'pessoa_id_pessoa' => $idPessoa,
            'created_at'       => $agora,
            'updated_at'       => $agora,
        ];

        // Formação principal define a área de atuação -----------------
        $tipoCurso = $f->randomElement($this->tipos);
        $segmentos = array_column(CatalogoAcademicoSenacDf::segmentos($tipoCurso), 'id');
        $segmento  = $f->randomElement($segmentos);
        $area      = self::AREA_POR_SEGMENTO[$segmento] ?? 'Outra';

        // Links externos ---------------------------------------------
        $usuario = str_replace('.', '-', Str::limit($slug, 30, ''));
        $lote['link_externo'][] = [
            'linkedin'            => "https://www.linkedin.com/in/{$usuario}-{$numero}",
            'portfolio'           => "https://{$usuario}-{$numero}.com.br",
            'github'              => "https://github.com/{$usuario}-{$numero}",
            'candidato_matricula' => $matricula,
            'created_at'          => $agora,
            'updated_at'          => $agora,
        ];

        // Informações profissionais ----------------------------------
        $hard  = $this->catalogoHabilidades['habilidadesPorArea'][$area] ?? $this->catalogoHabilidades['habilidadesPorArea']['Outra'];
        $soft  = $this->catalogoHabilidades['sugestoesSoftSkills'];
        $habilidades = array_values(array_unique([
            ...$f->randomElements($hard, $f->numberBetween(5, 8)),
            ...$f->randomElements($soft, $f->numberBetween(2, 4)),
        ]));
        $porArea = [$area => $habilidades];

        $cargo = $f->randomElement(self::CARGOS_POR_AREA[$area]);

        $lote['informacoes_profissionais'][] = [
            'sobre_mim'            => $this->sobreMim($area, $cargo, $habilidades),
            'cargo_de_interesse'   => Str::limit($cargo, 45, ''),
            'area_de_atuacao'      => Str::limit($area, 45, ''),
            'habilidades'          => json_encode($habilidades, JSON_UNESCAPED_UNICODE),
            'habilidades_por_area' => json_encode($porArea, JSON_UNESCAPED_UNICODE),
            'candidato_matricula'  => $matricula,
            'created_at'           => $agora,
            'updated_at'           => $agora,
        ];

        // Preferências de trabalho -----------------------------------
        $tipoContratacao = $f->randomElement([1, 1, 2, 2, 3, 3, 3]); // bitmask: 1 = CLT, 2 = Estágio
        $aceitaTodas     = $f->boolean(12);

        if ($tipoContratacao === 2) {
            $pretensao = $f->numberBetween(9, 20) * 100;
        } else {
            $pretensao = $f->numberBetween(36, 180) * 50;
        }

        if ($aceitaTodas) {
            $codigosPreferidos = [];
            $regiaoAdministrativa = 'Todas as regiões';
        } else {
            $codigos = [];
            if ($f->boolean(70)) {
                $codigos[] = $regiaoResidencia['codigo'];
            }
            foreach ($f->randomElements(array_column($this->regioes, 'codigo'), $f->numberBetween(1, 3)) as $c) {
                $codigos[] = $c;
            }
            $codigosPreferidos = array_slice(array_values(array_unique($codigos)), 0, 3);
            $regiaoAdministrativa = RegioesAdministrativasDf::nome($codigosPreferidos[0]);
        }

        $lote['preferencias_de_trabalho'][] = [
            'tipo_de_contratacao'        => $tipoContratacao,
            'disponibilidade_de_horario' => json_encode($f->randomElement(self::DISPONIBILIDADES), JSON_UNESCAPED_UNICODE),
            'regiao_administrativa'      => $regiaoAdministrativa,
            'aceita_todas_regioes'       => $aceitaTodas ? 1 : 0,
            'pretensao_salarial'         => number_format($pretensao, 2, '.', ''),
            'candidato_matricula'        => $matricula,
            'created_at'                 => $agora,
            'updated_at'                 => $agora,
        ];

        foreach ($codigosPreferidos as $codigo) {
            $lote['regioes_preferidas_trabalho'][] = [
                'candidato_matricula' => $matricula,
                'codigo_regiao'       => $codigo,
                'created_at'          => $agora,
                'updated_at'          => $agora,
            ];
        }

        // Dados acadêmicos (1 principal + eventualmente 1 complementar)
        $lote['dados_academicos'][] = $this->linhaAcademica($matricula, $tipoCurso, $segmento, $agora);

        if ($f->boolean(30)) {
            $tipo2 = $f->randomElement($this->tipos);
            $seg2  = $f->randomElement(array_column(CatalogoAcademicoSenacDf::segmentos($tipo2), 'id'));
            $lote['dados_academicos'][] = $this->linhaAcademica($matricula, $tipo2, $seg2, $agora);
        }

        // Cursos Senac (1 a 3) ---------------------------------------
        $pool = self::CURSOS_CURTOS[$area];
        foreach ($f->randomElements(array_keys($pool), min(count($pool), $f->numberBetween(1, 3))) as $curso) {
            $lote['cursos_senac'][] = [
                'nome_curso'          => $curso,
                'unidade'             => $f->randomElement(self::UNIDADES),
                'carga_horaria'       => $pool[$curso],
                'concluido_em'        => Carbon::instance($f->dateTimeBetween('-4 years', '-1 month'))->toDateString(),
                'candidato_matricula' => $matricula,
                'created_at'          => $agora,
                'updated_at'          => $agora,
            ];
        }

        // Cursos externos (1 a 2) ------------------------------------
        foreach ($f->randomElements(array_keys($pool), min(count($pool), $f->numberBetween(1, 2))) as $curso) {
            $lote['cursos_externos'][] = [
                'nome_curso'          => $curso,
                'instituicao'         => $f->randomElement(self::INSTITUICOES_EXTERNAS),
                'carga_horaria'       => $f->randomElement([10, 20, 30, 40, 60, 80, 120]),
                'concluido_em'        => Carbon::instance($f->dateTimeBetween('-4 years', '-1 month'))->toDateString(),
                'candidato_matricula' => $matricula,
                'created_at'          => $agora,
                'updated_at'          => $agora,
            ];
        }

        // Experiências profissionais (1 a 3, em ordem cronológica) ---
        $cursor    = now()->subMonths($f->numberBetween(24, 72))->startOfMonth();
        $qtdExp    = $f->numberBetween(1, 3);
        $hoje      = now();

        for ($e = 0; $e < $qtdExp; $e++) {
            $inicio = $cursor->copy();
            $fim    = $inicio->copy()->addMonths($f->numberBetween(4, 20));
            $ehUltima = $e === $qtdExp - 1;

            // A última experiência pode estar em andamento.
            if ($fim->greaterThanOrEqualTo($hoje->copy()->subMonth()) || ($ehUltima && $f->boolean(35))) {
                $fim = null;
            }

            $lote['experiencias_profissionais'][] = [
                'tipo'                => $f->randomElement(self::TIPOS_EXPERIENCIA),
                'cargo'               => Str::limit($f->randomElement(self::CARGOS_POR_AREA[$area]), 100, ''),
                'empresa'             => Str::limit($f->company(), 100, ''),
                'local'               => 'Brasília - DF',
                'data_inicio'         => $inicio->toDateString(),
                'data_fim'            => $fim?->toDateString(),
                'descricao'           => $this->descricaoExperiencia($habilidades),
                'candidato_matricula' => $matricula,
                'created_at'          => $agora,
                'updated_at'          => $agora,
            ];

            if ($fim === null) {
                break;
            }

            $cursor = $fim->copy()->addMonths($f->numberBetween(0, 3));

            if ($cursor->greaterThanOrEqualTo($hoje->copy()->subMonths(2))) {
                break;
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    private function linhaAcademica(string $matricula, string $tipoCurso, string $segmento, string $agora): array
    {
        $f = $this->faker;

        $assuntos = self::ASSUNTOS_POR_SEGMENTO[$segmento] ?? ['Formação Profissional'];
        $curso    = self::PREFIXO_TIPO[$tipoCurso] . $f->randomElement($assuntos);

        return [
            'instituicao'         => 'Senac DF',
            'curso'               => Str::limit($curso, 45, ''),
            'segmento'            => $segmento,   // slug do CatalogoAcademicoSenacDf
            'tipo_curso'          => $tipoCurso,  // slug do CatalogoAcademicoSenacDf
            'unidade'             => $f->randomElement(self::UNIDADES),
            'ano_de_conclusao'    => Carbon::instance($f->dateTimeBetween('-5 years', '+1 year'))->toDateString(),
            'candidato_matricula' => $matricula,
            'created_at'          => $agora,
            'updated_at'          => $agora,
        ];
    }

    private function sobreMim(string $area, string $cargo, array $habilidades): string
    {
        $f  = $this->faker;
        $h1 = $habilidades[0];
        $h2 = $habilidades[1] ?? $habilidades[0];
        $c  = mb_strtolower($cargo);

        $modelos = [
            "Profissional em início de carreira na área de {$area}, com foco em {$h1} e {$h2}. Busco uma oportunidade como {$c} para crescer e aprender.",
            "Apaixonado(a) por {$area}, com experiência prática em {$h1}. Comunicativo(a), organizado(a) e sempre em busca de novos desafios como {$c}.",
            "Egresso(a) do Senac DF com base sólida em {$h1} e {$h2}. Quero aplicar meus conhecimentos em uma vaga de {$c}.",
            "Dedicado(a) e proativo(a), com interesse em {$area}. Tenho facilidade para aprender {$h1} e trabalhar em equipe. Objetivo: atuar como {$c}.",
        ];

        return Str::limit($f->randomElement($modelos), 197, '...');
    }

    private function descricaoExperiencia(array $habilidades): string
    {
        $f  = $this->faker;
        $h1 = $habilidades[0];
        $h2 = $habilidades[1] ?? $habilidades[0];

        return $f->randomElement([
            "Atuei com {$h1} e {$h2}, apoiando a equipe nas rotinas do setor e contribuindo para a melhoria dos processos.",
            "Responsável por atividades de {$h1}, com apoio em {$h2}, atendimento às demandas internas e organização de informações.",
            "Participei de projetos envolvendo {$h1}, acompanhei indicadores e colaborei com {$h2} no dia a dia da equipe.",
        ]);
    }

    private function logradouro(): string
    {
        $f = $this->faker;

        return $f->randomElement([
            'Quadra ' . $f->numberBetween(1, 40) . ' Conjunto ' . chr(64 + $f->numberBetween(1, 13)),
            'QNN ' . $f->numberBetween(1, 40) . ' Conjunto ' . chr(64 + $f->numberBetween(1, 13)),
            'CNB ' . $f->numberBetween(1, 15),
            'Rua ' . $f->numberBetween(1, 60),
            'Avenida ' . $f->lastName(),
        ]);
    }

    private function cpfUnico(): string
    {
        do {
            $cpf = $this->faker->cpf(false); // 11 dígitos, válido
        } while (isset($this->cpfsUsados[$cpf]));

        $this->cpfsUsados[$cpf] = true;

        return $cpf;
    }

    private function telefoneUnico(): string
    {
        do {
            $tel = '619' . $this->faker->numberBetween(10000000, 99999999); // 11 dígitos
        } while (isset($this->telefonesUsados[$tel]));

        $this->telefonesUsados[$tel] = true;

        return $tel;
    }

    /** Matrícula numérica (até 15 dígitos) no formato 26 + 8 dígitos sequenciais. */
    private function proximaMatricula(int &$sequencia): string
    {
        do {
            $matricula = '26' . sprintf('%08d', $sequencia++);
        } while (isset($this->matriculasUsadas[$matricula]));

        $this->matriculasUsadas[$matricula] = true;

        return $matricula;
    }
}
