<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProcedureController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FraisStatController;
use App\Http\Controllers\HearingMinuteController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//Login Route
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
// Routes pour الباقي بدون تحصيل
// 1. Toujours mettre les routes spécifiques EN PREMIER
Route::post('outstanding-debts/import', [App\Http\Controllers\OutstandingDebtController::class, 'import']);
// 2. Mettre la route Ressource (qui contient les paramètres dynamiques comme {id}) EN DERNIER
Route::apiResource('outstanding-debts', App\Http\Controllers\OutstandingDebtController::class);
// Routes pour  تصفية الصوائر
Route::post('/frais-stats', [FraisStatController::class, 'store']);
Route::get('/frais-stats', [FraisStatController::class, 'index']);

// Routes pour اجراء يوجه
Route::get('/procedures', [ProcedureController::class, 'index']);
Route::put('/procedures/{id}', [ProcedureController::class, 'update']);
Route::post('/procedures/import', [ProcedureController::class, 'import']);
Route::get('/procedures/{id}/print', [ProcedureController::class, 'print']);
Route::post('/generate-dispatch', [DocumentController::class, 'generateDispatchDocument']);
Route::get('/correspondences/archive', [DocumentController::class, 'getArchive']);
Route::get('/user/letters-count', [DocumentController::class, 'getUserLettersCount']);
Route::post('/generate-document', [DocumentController::class, 'generate']);
Route::get('/folders', [DocumentController::class, 'getFolders']);
Route::get('/hearing-minutes', [HearingMinuteController::class, 'index']);
Route::post('/hearing-minutes/import', [HearingMinuteController::class, 'import']);
});
