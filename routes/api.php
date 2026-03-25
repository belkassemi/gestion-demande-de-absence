<?php

use App\Http\Controllers\AbsenceRequestController;
use App\Http\Controllers\AbsenceTypeController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ChefServiceController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DirecteurController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| API Routes  –  /api/...
|--------------------------------------------------------------------------
*/

// Public
Route::post('login', [AuthController::class, 'login']);

// Protected
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('logout', [AuthController::class, 'logout']);

    // Notifications
    Route::get('notifications',                          [NotificationController::class, 'index']);
    Route::post('notifications/{id}/read',               [NotificationController::class, 'markRead']);
    Route::post('notifications/read-all',                [NotificationController::class, 'markAllRead']);

    //Profile
    Route::get('profile',                  [ProfileController::class, 'show']);
    Route::put('profile',                  [ProfileController::class, 'update']);
    Route::post('profile/change-password', [ProfileController::class, 'changePassword']);

    //Absence Types
    Route::get('absence-types', [AbsenceTypeController::class, 'index']);

    //Absence Requests (employee-scoped)
    Route::get('absence-requests',              [AbsenceRequestController::class, 'index']);
    Route::post('absence-requests',             [AbsenceRequestController::class, 'store']);
    Route::get('absence-requests/{absenceRequest}',    [AbsenceRequestController::class, 'show']);
    Route::put('absence-requests/{absenceRequest}',    [AbsenceRequestController::class, 'update']);
    Route::delete('absence-requests/{absenceRequest}', [AbsenceRequestController::class, 'destroy']);
    Route::post('absence-requests/{absenceRequest}/cancel',  [AbsenceRequestController::class, 'cancel'])
        ->name('absence-requests.cancel');
    Route::get('absence-requests/my-stats', [AbsenceRequestController::class, 'myStats'])
        ->name('absence-requests.my-stats');

    // Documents 
    Route::get('documents',                 [DocumentController::class, 'index']);
    Route::post('documents',                [DocumentController::class, 'store']);
    Route::get('documents/{document}',      [DocumentController::class, 'show']);
    Route::delete('documents/{document}',   [DocumentController::class, 'destroy']);
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])
        ->name('documents.download');

    // Approvals (read-only for reference) 
    Route::get('approvals',           [ApprovalController::class, 'index']);
    Route::get('approvals/{approval}', [ApprovalController::class, 'show']);

    //  Chef Service Routes
    Route::middleware('role:chef_service')->prefix('chef-service')->group(function () {
        Route::get('pending-requests',         [ChefServiceController::class, 'pendingRequests']);
        Route::get('requests/{absenceRequest}', [ChefServiceController::class, 'showRequest']);
        Route::post('requests/{absenceRequest}/review', [ChefServiceController::class, 'review']);
        Route::get('team-calendar',            [ChefServiceController::class, 'teamCalendar']);
        Route::get('team-history',             [ChefServiceController::class, 'teamHistory']);
        Route::get('reports/export',           [ChefServiceController::class, 'exportReport']);
    });

    // Directeur Routes 
    Route::middleware('role:directeur')->prefix('directeur')->group(function () {
        Route::get('pending-requests',          [DirecteurController::class, 'pendingRequests']);
        Route::get('requests/{absenceRequest}',  [DirecteurController::class, 'showRequest']);
        Route::post('requests/{absenceRequest}/review', [DirecteurController::class, 'review']);
        Route::get('dashboard',                 [DirecteurController::class, 'dashboard']);
        Route::get('statistics',                [DirecteurController::class, 'statistics']);
        Route::get('reports/export',            [DirecteurController::class, 'exportReport']);
        Route::get('calendar',                  [DirecteurController::class, 'allDepartmentsCalendar']);
    });

    // Admin Routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Users
        Route::get('users',          [UserController::class, 'index']);
        Route::post('users',         [UserController::class, 'store']);
        Route::get('users/{user}',   [UserController::class, 'show']);
        Route::put('users/{user}',   [UserController::class, 'update']);
        Route::delete('users/{user}',[UserController::class, 'destroy']);

        // Dashboard & Global Stats
        Route::get('dashboard',       [AdminController::class, 'dashboard']);
        Route::get('statistics',      [AdminController::class, 'statistics']);
        Route::get('all-requests',    [AdminController::class, 'allRequests']);
        Route::get('all-users-stats', [AdminController::class, 'allUsersStats']);
        Route::get('reports/export',  [AdminController::class, 'exportReport']);
        Route::get('calendar',        [AdminController::class, 'allCalendar']);

        // Departments & Services
        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('services', ServiceController::class);
        Route::get('departments/{department}/statistics', [DepartmentController::class, 'statistics']);

        // Absence Types (write)
        Route::post('absence-types',         [AbsenceTypeController::class, 'store']);
        Route::put('absence-types/{absenceType}',   [AbsenceTypeController::class, 'update']);
        Route::delete('absence-types/{absenceType}',[AbsenceTypeController::class, 'destroy']);

        // Audit Logs
        Route::get('audit-logs',              [AuditLogController::class, 'index']);
        Route::get('audit-logs/history',      [AuditLogController::class, 'history']);
        Route::get('audit-logs/{auditLog}',   [AuditLogController::class, 'show']);
    });
});
