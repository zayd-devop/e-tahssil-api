<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProcedureController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/procedures', [ProcedureController::class, 'index']);
Route::put('/procedures/{id}', [ProcedureController::class, 'update']);
Route::post('/procedures/import', [ProcedureController::class, 'import']);
Route::get('/procedures/{id}/print', [ProcedureController::class, 'print']);
