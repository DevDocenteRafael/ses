<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Curriculo\CurriculoCandidatoBuilder;
use App\Services\Curriculo\CurriculoPdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CurriculoCandidatoController extends Controller
{
    public function show(
        Request $request,
        string $matricula,
        CurriculoCandidatoBuilder $builder,
        CurriculoPdfRenderer $renderer
    ): Response {
        $this->garantirAdministrativo($request);

        $curriculo = $builder->montar($matricula);
        $pdf = $renderer->render($curriculo);
        $arquivo = $builder->nomeArquivo($curriculo);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $arquivo . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
