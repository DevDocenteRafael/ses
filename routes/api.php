<?php

use Illuminate\Support\Facades\Route;
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

// ── Candidatos ───────────────────────────────────────────────────
Route::apiResource('candidatos', CandidatoController::class)->parameters([
    'candidatos' => 'matricula',
]);

// Perfil do candidato
Route::prefix('candidatos/{matricula}/perfil')->group(function () {
    Route::post('links',        [PerfilCandidatoController::class, 'storeLink']);
    Route::post('profissional', [PerfilCandidatoController::class, 'storeInfoProfissional']);
    Route::post('preferencias', [PerfilCandidatoController::class, 'storePreferencias']);
    Route::post('academico',    [PerfilCandidatoController::class, 'storeDadosAcademicos']);
});
Route::delete('academico/{id}', [PerfilCandidatoController::class, 'destroyDadosAcademicos']);

// ── Empresas ─────────────────────────────────────────────────────
Route::apiResource('empresas', EmpresaController::class)->parameters([
    'empresas' => 'cnpj',
]);

// ── Vagas ────────────────────────────────────────────────────────
Route::apiResource('vagas', VagaController::class);

// ── Convites ─────────────────────────────────────────────────────
Route::apiResource('convites', ConviteController::class);

// ── Administrativo ───────────────────────────────────────────────
Route::prefix('administrativo')->group(function () {
    Route::get('/',                          [AdministrativoController::class, 'index']);
    Route::get('{pessoaId}',                 [AdministrativoController::class, 'show']);
    Route::post('sincronizar-alunos',        [AdministrativoController::class, 'sincronizarAlunos']);
    Route::get('engajamento',                [AdministrativoController::class, 'listarEngajamento']);
    Route::post('engajamento',               [AdministrativoController::class, 'storeEngajamento']);
    Route::put('engajamento/{unidade}',      [AdministrativoController::class, 'updateEngajamento']);
});
