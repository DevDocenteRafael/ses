<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $curriculo['nome'] }}</title>
    <style>
        @page { size: A4; margin: 20mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 11px; line-height: 1.45; }
        h1 { color: #004587; font-size: 25px; margin: 0 0 3px; text-transform: uppercase; letter-spacing: .4px; }
        .area { color: #4b5563; font-size: 14px; margin: 0 0 14px; }
        h2 { color: #004587; font-size: 12.5px; border-bottom: 1px solid #dbeafe; padding-bottom: 4px; margin: 16px 0 8px; text-transform: uppercase; }
        p { margin: 0 0 4px; }
        ul { margin: 0; padding-left: 18px; }
        li { margin-bottom: 3px; }
        .contato { background: #f8fbff; border: 1px solid #dbeafe; padding: 8px 10px; margin-bottom: 12px; }
        .item { margin-bottom: 11px; page-break-inside: avoid; }
        .titulo { font-weight: bold; color: #111827; text-transform: uppercase; }
        .empresa { font-weight: bold; color: #004587; }
        .muted { color: #6b7280; }
        .label { color: #6b7280; font-weight: bold; font-size: 10px; }
        .divisor { border-bottom: 1px solid #eef2f7; padding-bottom: 8px; }
    </style>
</head>
<body>
    <h1>{{ $curriculo['nome'] }}</h1>
    @if($curriculo['contato'])
        <h2>Identificação e contato</h2>
        <div class="contato">
            @foreach($curriculo['contato'] as $rotulo => $valor)
                @if(!empty($valor))<p><strong>{{ $rotulo }}:</strong> {{ $valor }}</p>@endif
            @endforeach
        </div>
    @endif

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

    @if($curriculo['habilidades'])
        <h2>Competências técnicas e digitais</h2>
        <p>{{ implode(' • ', array_filter($curriculo['habilidades'])) }}</p>
    @endif

    @if($curriculo['outras_informacoes'])
        <h2>Idiomas e outras informações relevantes</h2>
        @foreach($curriculo['outras_informacoes'] as $rotulo => $valor)
            <p><strong>{{ $rotulo }}:</strong> {{ $valor }}</p>
        @endforeach
    @endif
</body>
</html>
