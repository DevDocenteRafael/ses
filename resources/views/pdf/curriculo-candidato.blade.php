<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Currículo - {{ $curriculo['nome'] }}</title>
    <style>
        @page { size: A4; margin: 24mm 20mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 12px; line-height: 1.45; }
        h1 { color: #0d6efd; font-size: 24px; margin: 0 0 4px; text-transform: uppercase; }
        .area { color: #4b5563; font-size: 14px; margin-bottom: 16px; }
        h2 { color: #0d6efd; font-size: 13px; border-bottom: 1px solid #dbeafe; padding-bottom: 4px; margin: 18px 0 8px; text-transform: uppercase; }
        p { margin: 0 0 4px; }
        ul { margin: 0; padding-left: 18px; }
        li { margin-bottom: 3px; }
        .item { margin-bottom: 10px; page-break-inside: avoid; }
        .titulo { font-weight: bold; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <h1>{{ $curriculo['nome'] }}</h1>
    @if($curriculo['area_atuacao'])
        <p class="area">{{ $curriculo['area_atuacao'] }}</p>
    @endif

    @if($curriculo['contato'])
        <h2>Informações de Contato</h2>
        @foreach($curriculo['contato'] as $rotulo => $valor)
            <p><strong>{{ $rotulo }}:</strong> {{ $valor }}</p>
        @endforeach
    @endif

    @if($curriculo['objetivo'])
        <h2>Objetivo / Informações Profissionais</h2>
        @foreach($curriculo['objetivo'] as $rotulo => $valor)
            <p><strong>{{ $rotulo }}:</strong> {{ $valor }}</p>
        @endforeach
    @endif

    @if($curriculo['formacao'])
        <h2>Formação Acadêmica</h2>
        @foreach($curriculo['formacao'] as $formacao)
            <div class="item">
                @if(!empty($formacao['curso']))<p class="titulo">{{ $formacao['curso'] }}</p>@endif
                @foreach(['instituicao' => 'Instituição', 'tipo' => 'Tipo', 'segmento' => 'Segmento', 'unidade' => 'Unidade', 'conclusao' => 'Conclusão'] as $campo => $rotulo)
                    @if(!empty($formacao[$campo]))<p class="muted">{{ $rotulo }}: {{ $formacao[$campo] }}</p>@endif
                @endforeach
            </div>
        @endforeach
    @endif

    @if($curriculo['habilidades'])
        <h2>Habilidades Técnicas</h2>
        <ul>
            @foreach($curriculo['habilidades'] as $habilidade)
                <li>{{ $habilidade }}</li>
            @endforeach
        </ul>
    @endif

    @if($curriculo['experiencias'])
        <h2>Experiências Profissionais</h2>
        @foreach($curriculo['experiencias'] as $experiencia)
            <div class="item">
                @if(!empty($experiencia['cargo']))<p class="titulo">{{ $experiencia['cargo'] }}</p>@endif
                @foreach(['empresa' => 'Empresa', 'periodo' => 'Período', 'tipo' => 'Tipo', 'local' => 'Local', 'descricao' => 'Descrição'] as $campo => $rotulo)
                    @if(!empty($experiencia[$campo]))<p>{{ $rotulo }}: {{ $experiencia[$campo] }}</p>@endif
                @endforeach
            </div>
        @endforeach
    @endif

    @if($curriculo['cursos_complementares'])
        <h2>Cursos Complementares</h2>
        @foreach($curriculo['cursos_complementares'] as $curso)
            <div class="item">
                @if(!empty($curso['curso']))<p class="titulo">{{ $curso['curso'] }}</p>@endif
                @foreach(['instituicao' => 'Instituição', 'unidade' => 'Unidade', 'carga_horaria' => 'Carga horária', 'conclusao' => 'Conclusão'] as $campo => $rotulo)
                    @if(!empty($curso[$campo]))<p class="muted">{{ $rotulo }}: {{ $curso[$campo] }}</p>@endif
                @endforeach
            </div>
        @endforeach
    @endif

    @if($curriculo['links'])
        <h2>Links Profissionais</h2>
        @foreach($curriculo['links'] as $rotulo => $valor)
            <p><strong>{{ $rotulo }}:</strong> {{ $valor }}</p>
        @endforeach
    @endif
</body>
</html>
