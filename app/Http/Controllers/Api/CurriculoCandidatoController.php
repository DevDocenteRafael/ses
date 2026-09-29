<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Candidatos\CandidatoQueryService;
use App\Services\Curriculo\CurriculoCandidatoBuilder;
use App\Services\Curriculo\CurriculoLoteZipService;
use App\Services\Curriculo\CurriculoPdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function zip(Request $request, CurriculoLoteZipService $service): BinaryFileResponse
    {
        $admin = $this->garantirAdministrativo($request);

        try {
            $arquivo = $service->gerar($request, $admin);

            return response()->download($arquivo['path'], $arquivo['name'], [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            if (isset($arquivo['path'])) {
                File::delete($arquivo['path']);
            }

            throw $e;
        }
    }

    public function zipEmpresa(
        Request $request,
        CurriculoLoteZipService $service,
        CandidatoQueryService $queryService
    ): BinaryFileResponse {
        $empresa = $this->pessoaAutenticada($request);

        if (! $empresa || $empresa->tipo() !== 'empresa') {
            abort(403, 'Apenas empresas podem baixar currículos de candidatos.');
        }

        $query = $queryService->construir($request, $empresa);
        if (! $query->exists()) {
            abort(422, 'Nenhum candidato disponível corresponde aos filtros informados.');
        }

        try {
            $arquivo = $service->gerar($request, $empresa);

            return response()->download($arquivo['path'], $arquivo['name'], [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            if (isset($arquivo['path'])) {
                File::delete($arquivo['path']);
            }

            throw $e;
        }
    }
}
