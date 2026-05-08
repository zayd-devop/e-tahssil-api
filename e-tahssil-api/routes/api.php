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

Route::post('/hearing-minutes/import', [HearingMinuteController::class, 'importExcel']);
Route::get('/hearing-minutes', [HearingMinuteController::class, 'index']);
Route::get('/hearing-minutes/print/{id}', [HearingMinuteController::class, 'printSingle']);
Route::post('/hearing-minutes/print-merged', [HearingMinuteController::class, 'printMerged']);
Route::post('/general-register/import', [HearingMinuteController::class, 'importGeneralRegister']);
});
