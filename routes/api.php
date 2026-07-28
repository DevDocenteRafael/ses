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

// ── Criação de conta (públicas) ───────────────────────────────────
// Precisam ficar fora do grupo autenticado: um usuário novo ainda não
// tem token para se cadastrar. `->except(['store'])` abaixo remove o
// POST duplicado do apiResource protegido.
Route::post('candidatos', [CandidatoController::class, 'store']);
Route::post('empresas',   [EmpresaController::class, 'store']);

// ── Demais rotas exigem token válido ─────────────────────────────
Route::middleware('auth.token')->group(function () {

    Route::get('auth/me', [AuthController::class, 'me']);

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
    });
    Route::delete('academico/{id}', [PerfilCandidatoController::class, 'destroyDadosAcademicos']);

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
        Route::get('/',                          [AdministrativoController::class, 'index']);
        Route::get('{pessoaId}',                 [AdministrativoController::class, 'show']);
        Route::post('sincronizar-alunos',        [AdministrativoController::class, 'sincronizarAlunos']);
        Route::get('engajamento',                [AdministrativoController::class, 'listarEngajamento']);
        Route::post('engajamento',               [AdministrativoController::class, 'storeEngajamento']);
        Route::put('engajamento/{unidade}',      [AdministrativoController::class, 'updateEngajamento']);
    });
});
