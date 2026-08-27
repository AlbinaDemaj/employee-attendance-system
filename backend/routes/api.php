<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessSettingsController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\ManagerController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SuperAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'ok' => true,
    'app' => config('app.name'),
    'time' => now()->toDateTimeString(),
]));

Route::post('/login', [AuthController::class, 'login']);
Route::get('/meta', [AuthController::class, 'meta']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

    // --- punonjësi ----------------------------------------------------------
    Route::get('/attendance/today', [AttendanceController::class, 'today']);
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
    Route::post('/attendance/reason', [AttendanceController::class, 'reportReason']);
    Route::get('/attendance/history', [AttendanceController::class, 'history']);

    Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
    Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
    Route::delete('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'destroy']);

    // --- menaxheri + admini i biznesit --------------------------------------
    Route::middleware('role:manager,admin')->group(function () {
        Route::get('/manager/dashboard', [ManagerController::class, 'dashboard']);
        Route::get('/manager/monthly-report', [ManagerController::class, 'monthlyReport']);
        Route::get('/manager/employees/{user}', [ManagerController::class, 'employeeDetail']);
        Route::post('/manager/employees/{user}/override', [ManagerController::class, 'overrideDay']);
        Route::post('/leave-requests/{leaveRequest}/decide', [LeaveRequestController::class, 'decide']);
    });

    // --- admini i biznesit --------------------------------------------------
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/users', [AdminController::class, 'index']);
        Route::post('/admin/users', [AdminController::class, 'store']);
        Route::put('/admin/users/{user}', [AdminController::class, 'update']);
        Route::delete('/admin/users/{user}', [AdminController::class, 'destroy']);

        Route::get('/business/settings', [BusinessSettingsController::class, 'show']);
        Route::put('/business/settings', [BusinessSettingsController::class, 'update']);
        Route::post('/business/networks', [BusinessSettingsController::class, 'storeNetwork']);
        Route::put('/business/networks/{network}', [BusinessSettingsController::class, 'updateNetwork']);
        Route::delete('/business/networks/{network}', [BusinessSettingsController::class, 'destroyNetwork']);
    });

    // --- pronari i produktit ------------------------------------------------
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/super/businesses', [SuperAdminController::class, 'index']);
        Route::post('/super/businesses', [SuperAdminController::class, 'store']);
        Route::put('/super/businesses/{business}', [SuperAdminController::class, 'update']);
        Route::delete('/super/businesses/{business}', [SuperAdminController::class, 'destroy']);
        Route::get('/super/businesses/{business}/users', [SuperAdminController::class, 'users']);
        Route::post('/super/businesses/{business}/reset-password', [SuperAdminController::class, 'resetAdminPassword']);
    });
});
