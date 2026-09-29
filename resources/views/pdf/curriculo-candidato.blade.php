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
    @if($curriculo['area_atuacao'])
        <p class="area">{{ $curriculo['area_atuacao'] }}</p>
    @endif

    @if($curriculo['contato'])
        <div class="contato">
            @foreach($curriculo['contato'] as $rotulo => $valor)
                @if(!empty($valor))<span><strong>{{ $rotulo }}:</strong> {{ $valor }}</span>@if(!$loop->last)<span> &nbsp;|&nbsp; </span>@endif @endif
            @endforeach
        </div>
    @endif

    @if($curriculo['objetivo'])
        <h2>Informações Profissionais</h2>
        @foreach($curriculo['objetivo'] as $rotulo => $valor)
            @if(!empty($valor))
                <p class="label">{{ $rotulo }}</p>
                <p>{{ $valor }}</p>
            @endif
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
        <p>{{ implode(' • ', array_filter($curriculo['habilidades'])) }}</p>
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
