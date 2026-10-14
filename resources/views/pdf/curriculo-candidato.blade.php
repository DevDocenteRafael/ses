<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $curriculo['nome'] }}</title>
    <style>
        @page { size: A4; margin: 8.5mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #263238; font-size: 9.6px; line-height: 1.38; margin: 0; background: #FFFFFF; }
        .page { width: 100%; }
        .layout { width: 100%; border-collapse: separate; border-spacing: 0; table-layout: fixed; }
        .sidebar { width: 32%; background: #E8EFF5; padding: 11px 10px; vertical-align: top; word-wrap: break-word; overflow-wrap: anywhere; }
        .gutter { width: 2.5%; background: #FFFFFF; }
        .main { width: 65.5%; background: #FFFFFF; padding: 0; vertical-align: top; word-wrap: break-word; overflow-wrap: anywhere; }
        h1 { color: #17324D; font-size: 20px; margin: 0 0 8px; text-transform: uppercase; letter-spacing: .35px; border-bottom: 1px solid #D6E0E8; padding-bottom: 8px; line-height: 1.16; word-wrap: break-word; overflow-wrap: anywhere; }
        h2 { color: #174C7A; font-size: 10.8px; border-bottom: 1px solid #D6E0E8; padding-bottom: 3px; margin: 11px 0 5px; text-transform: uppercase; page-break-after: avoid; word-wrap: break-word; overflow-wrap: anywhere; }
        .sidebar h2 { color: #17324D; font-size: 9px; margin: 0 0 6px; padding-top: 9px; page-break-after: avoid; }
        .sidebar h2:first-child { padding-top: 0; }
        p { margin: 0 0 3px; word-wrap: break-word; overflow-wrap: anywhere; }
        ul { margin: 0; padding-left: 12px; }
        li { margin-bottom: 2px; word-wrap: break-word; overflow-wrap: anywhere; }
        .item { margin-bottom: 7px; page-break-inside: avoid; }
        .titulo { font-weight: bold; color: #263238; }
        .empresa { color: #64748B; }
        .muted { color: #64748B; }
        .label { color: #17324D; font-weight: bold; font-size: 8.6px; }
        .divisor { border-bottom: 1px solid #D6E0E8; padding-bottom: 6px; }
        .lateral-item { margin-bottom: 6px; word-wrap: break-word; overflow-wrap: anywhere; }
    </style>
</head>
<body>
    <div class="page">
        <table class="layout">
            <tr>
                <td class="sidebar">
                    @if($curriculo['contato'])
                        <h2>Identificação e contato</h2>
                        @foreach($curriculo['contato'] as $rotulo => $valor)
                            @if(!empty($valor))<div class="lateral-item"><span class="label">{{ $rotulo }}</span><br>{{ $valor }}</div>@endif
                        @endforeach
                    @endif

                    @if($curriculo['habilidades'])
                        <h2>Competências</h2>
                        <ul>
                            @foreach(array_filter($curriculo['habilidades']) as $habilidade)
                                <li>{{ $habilidade }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if($curriculo['outras_informacoes'])
                        <h2>Outras informações</h2>
                        @foreach($curriculo['outras_informacoes'] as $rotulo => $valor)
                            @if(!empty($valor))<div class="lateral-item"><span class="label">{{ $rotulo }}</span><br>{{ $valor }}</div>@endif
                        @endforeach
                    @endif
                </td>
                <td class="gutter"></td>
                <td class="main">
                    <h1>{{ $curriculo['nome'] }}</h1>

    @if($curriculo['objetivo_profissional'])
        <h2>Objetivo profissional</h2>
        <p>{{ $curriculo['objetivo_profissional'] }}</p>
    @endif

    @if($curriculo['resumo_profissional'])
        <h2>Resumo profissional</h2>
        @foreach(preg_split('/\R+/', $curriculo['resumo_profissional']) as $paragrafo)
            @if(trim($paragrafo) !== '')<p>{{ $paragrafo }}</p>@endif
        @endforeach
    @endif

    @if($curriculo['formacao_academica'])
        <h2>Formação Acadêmica</h2>
        @foreach($curriculo['formacao_academica'] as $formacao)
            <div class="item">
                @if(!empty($formacao['curso']))<p class="titulo">{{ $formacao['curso'] }}</p>@endif
                @foreach(['escolaridade' => 'Escolaridade', 'instituicao' => 'Instituição', 'situacao' => 'Situação', 'conclusao' => 'Conclusão'] as $campo => $rotulo)
                    @if(!empty($formacao[$campo]))<p class="muted">{{ $rotulo }}: {{ $formacao[$campo] }}</p>@endif
                @endforeach
            </div>
        @endforeach
    @endif

    @if($curriculo['formacao_senac'])
        <h2>Formação profissional – Senac</h2>
        @foreach($curriculo['formacao_senac'] as $curso)
            <div class="item">
                @if(!empty($curso['curso']))<p class="titulo">{{ $curso['curso'] }}</p>@endif
                @foreach(['unidade' => 'Unidade Senac', 'conclusao' => 'Conclusão', 'competencias' => 'Principais competências'] as $campo => $rotulo)
                    @if(!empty($curso[$campo]))<p class="muted">{{ $rotulo }}: {{ $curso[$campo] }}</p>@endif
                @endforeach
            </div>
        @endforeach
    @endif

    @if($curriculo['experiencias'])
        <h2>Experiência Profissional</h2>
        @foreach($curriculo['experiencias'] as $experiencia)
            <div class="item divisor">
                @if(!empty($experiencia['cargo']))<p class="titulo">{{ $experiencia['cargo'] }}</p>@endif
                @if(!empty($experiencia['empresa']))<p class="empresa">{{ $experiencia['empresa'] }}</p>@endif
                @if(!empty($experiencia['periodo']))<p class="muted">{{ $experiencia['periodo'] }}</p>@endif
                @php($metaExperiencia = implode(' | ', array_filter([$experiencia['tipo'] ?? null, $experiencia['local'] ?? null])))
                @if($metaExperiencia)<p class="muted">{{ $metaExperiencia }}</p>@endif
                @if(!empty($experiencia['descricao']))<p>{{ $experiencia['descricao'] }}</p>@endif
            </div>
        @endforeach
    @endif

    @if($curriculo['cursos_complementares'])
        <h2>Cursos complementares e certificações</h2>
        @foreach($curriculo['cursos_complementares'] as $curso)
            <div class="item">
                @if(!empty($curso['curso']))<p class="titulo">{{ $curso['curso'] }}</p>@endif
                @foreach(['instituicao' => 'Instituição', 'conclusao' => 'Conclusão', 'carga_horaria' => 'Carga horária'] as $campo => $rotulo)
                    @if(!empty($curso[$campo]))<p class="muted">{{ $rotulo }}: {{ $curso[$campo] }}</p>@endif
                @endforeach
            </div>
        @endforeach
    @endif

                </td>
            </tr>
        </table>
    </div>
</body>
</html>
