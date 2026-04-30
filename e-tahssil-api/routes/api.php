<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// 1. Toujours mettre les routes spécifiques EN PREMIER
Route::post('outstanding-debts/import', [App\Http\Controllers\OutstandingDebtController::class, 'import']);

// 2. Mettre la route Ressource (qui contient les paramètres dynamiques comme {id}) EN DERNIER
Route::apiResource('outstanding-debts', App\Http\Controllers\OutstandingDebtController::class);
