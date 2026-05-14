<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FinancialFeeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProcedureController;
use App\Http\Controllers\Api\ProductionCardController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FraisStatController;
use App\Http\Controllers\HearingMinuteController;
use App\Http\Controllers\JudicialAssistanceController;
use App\Http\Controllers\OutstandingDebtController;
use App\Http\Controllers\SupplementaryFeeController;
use App\Http\Controllers\UserController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//Login Route
Route::post('/login', [AuthController::class, 'login']);


// -----------------------------------------------------
// ROUTES PROTÉGÉES (Nécessitent un Token valide)
// -----------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {

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

    // Obtenir l'utilisateur actuel
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // 🔥 Logout : L'utilisateur est reconnu, on peut détruire son Token
    Route::post('/logout', [AuthController::class, 'logout']);

    // --- Routes pour الباقي بدون تحصيل ---
    // 1. Toujours mettre les routes spécifiques EN PREMIER
    Route::post('outstanding-debts/import', [OutstandingDebtController::class, 'import']);
    // 2. Mettre la route Ressource (qui contient les paramètres dynamiques comme {id}) EN DERNIER
    Route::apiResource('outstanding-debts', OutstandingDebtController::class);

    // --- Routes pour  تصفية الصوائر ---
    Route::post('/frais-stats', [FraisStatController::class, 'store']);
    Route::get('/frais-stats', [FraisStatController::class, 'index']);
    Route::get('supplementary-fees', [SupplementaryFeeController::class, 'index']);
    Route::post('supplementary-fees/import', [SupplementaryFeeController::class, 'import']);


    Route::get('judicial-assistance', [JudicialAssistanceController::class, 'index']);
    Route::post('judicial-assistance/import', [JudicialAssistanceController::class, 'import']);

    // --- Routes pour اجراء يوجه ---
    Route::get('/procedures', [ProcedureController::class, 'index']);
    Route::put('/procedures/{id}', [ProcedureController::class, 'update']);
    Route::post('/procedures/import', [ProcedureController::class, 'import']);
    Route::get('/procedures/{id}/print', [ProcedureController::class, 'print']);
    Route::post('/procedures/print/{id}', [ProcedureController::class, 'print']);

    // --- Routes pour les Documents  توليد الوثائق ---
    Route::post('/generate-document', [DocumentController::class, 'generate']);
    Route::get('/folders', [DocumentController::class, 'getFolders']);

    // --- Routes pour gestion des users
    Route::get('/users', [UserController::class, 'index']); // لجلب الموظفين
    Route::post('/users/import', [UserController::class, 'import']); // لاستيراد Excel
    Route::delete('/users/{id}', [UserController::class, 'destroy']); // للحذف
    Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']); // لإعادة تعيين كلمة السر (وإرجاع كلمة السر الجديدة في الرد: ['new_password' => '...'])
    Route::post('/production-cards', [ProductionCardController::class, 'store']);
    Route::get('/sections-hierarchy', [SectionController::class, 'hierarchy']);
    Route::get('/financial-fees/{type}', [FinancialFeeController::class, 'index']);
    Route::post('/financial-fees', [FinancialFeeController::class, 'store']);
    Route::post('/financial-fees/import', [FinancialFeeController::class, 'import']); // استيراد
    Route::post('/financial-fees/bulk-delete', [FinancialFeeController::class, 'bulkDestroy']); // حذف مجمع
    Route::put('/financial-fees/{id}', [FinancialFeeController::class, 'update']);
    Route::delete('/financial-fees/{id}', [FinancialFeeController::class, 'destroy']);
    Route::get('/financial-fees/download-indar/{id}', [FinancialFeeController::class, 'downloadIndar']);
    Route::post('/financial-fees/bulk-download-indar', [FinancialFeeController::class, 'bulkDownloadIndar']);

    // Nouvelle route pour le dashboard
    Route::get('/dashboard-stats', [DashboardController::class, 'getStats']);
});

