<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\StaffController;
use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\LeaveController;

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

    });

});
