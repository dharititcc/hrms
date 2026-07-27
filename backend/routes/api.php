<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\StaffController;
use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\LeaveController;
use App\Http\Controllers\API\PayrollController;
use App\Http\Controllers\API\ExpenseController;
use App\Http\Controllers\API\PhaseFourController;
use App\Http\Controllers\API\PhaseFiveController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::prefix('auth')->group(function () {

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/me', [AuthController::class, 'me']);
        Route::patch('/profile', [AuthController::class, 'updateProfile']);

        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationNotification'])
            ->middleware('throttle:6,1');

        Route::apiResource('staff', StaffController::class);
        Route::get('/attendance', [AttendanceController::class, 'index']);
        Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn']);
        Route::post('/attendance/{attendance}/clock-out', [AttendanceController::class, 'clockOut']);
        Route::get('/leave/types', [LeaveController::class, 'types']);
        Route::get('/leave/requests', [LeaveController::class, 'index']);
        Route::post('/leave/requests', [LeaveController::class, 'store']);
        Route::patch('/leave/requests/{leaveRequest}/status', [LeaveController::class, 'updateStatus']);
        Route::get('/payroll', [PayrollController::class, 'index']);
        Route::post('/payroll', [PayrollController::class, 'store']);
        Route::get('/expenses', [ExpenseController::class, 'index']);
        Route::post('/expenses', [ExpenseController::class, 'store']);
        Route::patch('/expenses/{expense}/status', [ExpenseController::class, 'updateStatus']);
        Route::get('/recruitment/openings', [PhaseFourController::class, 'openings']);
        Route::post('/recruitment/openings', [PhaseFourController::class, 'storeOpening']);
        Route::get('/recruitment/candidates', [PhaseFourController::class, 'candidates']);
        Route::post('/recruitment/candidates', [PhaseFourController::class, 'storeCandidate']);
        Route::get('/performance/goals', [PhaseFourController::class, 'goals']);
        Route::post('/performance/goals', [PhaseFourController::class, 'storeGoal']);
        Route::get('/performance/reviews', [PhaseFourController::class, 'reviews']);
        Route::get('/assets', [PhaseFiveController::class, 'assets']);
        Route::post('/assets', [PhaseFiveController::class, 'storeAsset']);
        Route::get('/announcements', [PhaseFiveController::class, 'announcements']);
        Route::post('/announcements', [PhaseFiveController::class, 'storeAnnouncement']);
        Route::get('/reports/summary', [PhaseFiveController::class, 'report']);

    });

});
