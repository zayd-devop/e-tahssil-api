<?php

use App\Http\Controllers\FraisStatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Routes pour الباقي بدون تحصيل
// 1. Toujours mettre les routes spécifiques EN PREMIER
Route::post('outstanding-debts/import', [App\Http\Controllers\OutstandingDebtController::class, 'import']);
// 2. Mettre la route Ressource (qui contient les paramètres dynamiques comme {id}) EN DERNIER
Route::apiResource('outstanding-debts', App\Http\Controllers\OutstandingDebtController::class);


// Routes pour  تصفية الصوائر
Route::post('/frais-stats', [FraisStatController::class, 'store']);
Route::get('/frais-stats', [FraisStatController::class, 'index']);
