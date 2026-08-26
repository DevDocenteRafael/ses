<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CandidatoController;
use App\Http\Controllers\Api\EmpresaController;
use App\Http\Controllers\Api\VagaController;
use App\Http\Controllers\Api\ConviteController;
use App\Http\Controllers\Api\AdministrativoController;
use App\Http\Controllers\Api\PerfilCandidatoController;

/*
|--------------------------------------------------------------------------
| API Routes - Sistema de Empregabilidade SENAC DF
|--------------------------------------------------------------------------
*/

// ── Autenticação (públicas) ──────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
});

// ── Demais rotas exigem token válido ─────────────────────────────
Route::post('candidatos', [CandidatoController::class, 'store']);

Route::middleware('auth.token')->group(function () {

    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('candidatos', [CandidatoController::class, 'store']);

    // Candidatos
    Route::apiResource('candidatos', CandidatoController::class)
        ->except(['store'])
        ->parameters(['candidatos' => 'matricula']);
    Route::get('candidatos/{matricula}/dashboard', [CandidatoController::class, 'dashboard']);

    // Perfil do candidato
    Route::prefix('candidatos/{matricula}/perfil')->group(function () {
        Route::post('links',        [PerfilCandidatoController::class, 'storeLink']);
        Route::post('profissional', [PerfilCandidatoController::class, 'storeInfoProfissional']);
        Route::post('preferencias', [PerfilCandidatoController::class, 'storePreferencias']);
        Route::post('academico',    [PerfilCandidatoController::class, 'storeDadosAcademicos']);

        Route::post('cursos-senac',    [PerfilCandidatoController::class, 'storeCursoSenac']);

        Route::post('cursos-externos',      [PerfilCandidatoController::class, 'storeCursoExterno']);
        Route::delete('cursos-externos/{id}', [PerfilCandidatoController::class, 'destroyCursoExterno']);

        Route::post('experiencias',      [PerfilCandidatoController::class, 'storeExperiencia']);
        Route::put('experiencias/{id}',  [PerfilCandidatoController::class, 'updateExperiencia']);
        Route::delete('experiencias/{id}', [PerfilCandidatoController::class, 'destroyExperiencia']);
    });
    Route::delete('academico/{id}',    [PerfilCandidatoController::class, 'destroyDadosAcademicos']);
    Route::delete('cursos-senac/{id}', [PerfilCandidatoController::class, 'destroyCursoSenac']);

    // Empresas
    Route::get('empresas/favoritos', [EmpresaController::class, 'favoritos']);
    Route::post('empresas/favoritos/{matricula}', [EmpresaController::class, 'favoritar']);
    Route::delete('empresas/favoritos/{matricula}', [EmpresaController::class, 'desfavoritar']);

    Route::apiResource('empresas', EmpresaController::class)
        ->except(['store'])
        ->parameters(['empresas' => 'cnpj']);

    // Vagas
    Route::apiResource('vagas', VagaController::class);

    // Convites
    Route::apiResource('convites', ConviteController::class);

    // Administrativo
    Route::prefix('administrativo')->group(function () {
        Route::get('dashboard',                  [AdministrativoController::class, 'dashboard']);
        Route::get('/',                          [AdministrativoController::class, 'index']);
        Route::post('sincronizar-alunos',        [AdministrativoController::class, 'sincronizarAlunos']);
        Route::get('engajamento',                [AdministrativoController::class, 'listarEngajamento']);
        Route::post('engajamento',               [AdministrativoController::class, 'storeEngajamento']);
        Route::put('engajamento/{unidade}',      [AdministrativoController::class, 'updateEngajamento']);
        // Precisa ficar por último: sendo um curinga, {pessoaId} casaria com
        // qualquer um dos caminhos estáticos acima (ex: "engajamento") se
        // viesse antes deles, tornando essas rotas inalcançáveis.
        Route::get('{pessoaId}',                 [AdministrativoController::class, 'show']);
    });
});
