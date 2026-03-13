<?php

use App\Http\Controllers\AbsenceRequestController;
use App\Http\Controllers\AbsenceTypeController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| API Routes  –  /api/...
|--------------------------------------------------------------------------
*/

// ── Public Routes ─────────────────────────────────────────────────────────────
Route::post('login', [AuthController::class, 'login']);

// ── Protected Routes ──────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    
    Route::post('logout', [AuthController::class, 'logout']);

    // Admin only route to create new users (manager, employee, hr)
    Route::middleware('role:admin')->post('users', [UserController::class, 'store']);

    // ── Departments ──────────────────────────────────────────────────────────────
    Route::apiResource('departments', DepartmentController::class);
    Route::get('departments/{department}/statistics', [DepartmentController::class, 'statistics'])
        ->name('departments.statistics');

    // ── Absence Types ─────────────────────────────────────────────────────────────
    Route::apiResource('absence-types', AbsenceTypeController::class);

    // ── Absence Requests ──────────────────────────────────────────────────────────
    Route::apiResource('absence-requests', AbsenceRequestController::class);
    Route::post('absence-requests/{absenceRequest}/cancel',  [AbsenceRequestController::class, 'cancel'])
        ->name('absence-requests.cancel');
    Route::post('absence-requests/{absenceRequest}/approve', [AbsenceRequestController::class, 'approve'])
        ->name('absence-requests.approve');
    Route::post('absence-requests/{absenceRequest}/reject',  [AbsenceRequestController::class, 'reject'])
        ->name('absence-requests.reject');

    // ── Approvals ─────────────────────────────────────────────────────────────────
    Route::apiResource('approvals', ApprovalController::class)->except(['update', 'destroy']);
    Route::post('approvals/{approval}/approve', [ApprovalController::class, 'approve'])
        ->name('approvals.approve');
    Route::post('approvals/{approval}/reject',  [ApprovalController::class, 'reject'])
        ->name('approvals.reject');

    // ── Documents ─────────────────────────────────────────────────────────────────
    Route::apiResource('documents', DocumentController::class)->except(['update']);
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])
        ->name('documents.download');

    // ── Audit Logs ────────────────────────────────────────────────────────────────
    Route::get('audit-logs',         [AuditLogController::class, 'index'])  ->name('audit-logs.index');
    Route::get('audit-logs/history', [AuditLogController::class, 'history'])->name('audit-logs.history');
    Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
});
