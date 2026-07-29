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
use App\Http\Controllers\API\ActivityLogController;
use App\Http\Controllers\API\AttachmentController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\GuestRsvpController;
use App\Http\Controllers\API\MeetingController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\PayrollRunController;
use App\Http\Controllers\API\PermissionController;
use App\Http\Controllers\API\SalaryAssignmentController;
use App\Http\Controllers\API\StaffInvitationController;
use App\Http\Controllers\API\ProjectController;
use App\Http\Controllers\API\TaskChecklistController;
use App\Http\Controllers\API\TaskCommentController;
use App\Http\Controllers\API\TaskController;
use App\Http\Controllers\API\TaskTimeEntryController;
use App\Http\Controllers\API\WorkspaceUserController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
| Public invite endpoints. External guests have no account, so the unguessable
| token is the only credential. Throttled because these are unauthenticated and
| the token is the sole barrier to brute force.
*/
Route::middleware('throttle:20,1')->group(function () {
    Route::get('/meetings/invite/{token}', [GuestRsvpController::class, 'show']);
    Route::post('/meetings/invite/{token}/respond', [GuestRsvpController::class, 'respond']);
});



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
        /*
        | These modules predate the policy layer and their controllers only
        | check tenancy, so permission is enforced here with can: middleware
        | against the gates registered in AppServiceProvider. Approving a
        | request is a separate permission from editing it.
        */
        Route::get('/attendance', [AttendanceController::class, 'index'])->middleware('can:attendance.view');
        Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])->middleware('can:attendance.create');
        Route::post('/attendance/{attendance}/clock-out', [AttendanceController::class, 'clockOut'])->middleware('can:attendance.create');
        Route::get('/leave/types', [LeaveController::class, 'types'])->middleware('can:leave.view');
        Route::get('/leave/requests', [LeaveController::class, 'index'])->middleware('can:leave.view');
        Route::post('/leave/requests', [LeaveController::class, 'store'])->middleware('can:leave.create');
        Route::patch('/leave/requests/{leaveRequest}/status', [LeaveController::class, 'updateStatus'])->middleware('can:leave.approve');
        // Salary slips are read-only: they are produced by generating a payroll
        // run from salary assignments, not by posting figures directly.
        Route::get('/payroll', [PayrollController::class, 'index'])->middleware('can:payroll.view');
        Route::get('/payroll/{slip}', [PayrollController::class, 'show'])->middleware('can:payroll.view');

        // An employee's salary and its revision history.
        Route::get('/staff/{staff}/salary', [SalaryAssignmentController::class, 'current'])->middleware('can:payroll.view');
        Route::get('/staff/{staff}/salary/history', [SalaryAssignmentController::class, 'index'])->middleware('can:payroll.view');
        Route::post('/staff/{staff}/salary', [SalaryAssignmentController::class, 'store'])->middleware('can:payroll.create');
        Route::patch('/staff/{staff}/salary/end', [SalaryAssignmentController::class, 'end'])->middleware('can:payroll.edit');

        Route::get('/payroll-runs', [PayrollRunController::class, 'index'])->middleware('can:payroll.view');
        Route::get('/payroll-runs/{run}', [PayrollRunController::class, 'show'])->middleware('can:payroll.view');
        Route::post('/payroll-runs', [PayrollRunController::class, 'store'])->middleware('can:payroll.generate');
        Route::post('/payroll-runs/{run}/regenerate', [PayrollRunController::class, 'regenerate'])->middleware('can:payroll.generate');
        Route::delete('/payroll-runs/{run}', [PayrollRunController::class, 'destroy'])->middleware('can:payroll.delete');
        Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('can:expenses.view');
        Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('can:expenses.create');
        Route::patch('/expenses/{expense}/status', [ExpenseController::class, 'updateStatus'])->middleware('can:expenses.approve');
        Route::get('/recruitment/openings', [PhaseFourController::class, 'openings'])->middleware('can:recruitment.view');
        Route::post('/recruitment/openings', [PhaseFourController::class, 'storeOpening'])->middleware('can:recruitment.create');
        Route::get('/recruitment/candidates', [PhaseFourController::class, 'candidates'])->middleware('can:recruitment.view');
        Route::post('/recruitment/candidates', [PhaseFourController::class, 'storeCandidate'])->middleware('can:recruitment.create');
        Route::get('/performance/goals', [PhaseFourController::class, 'goals'])->middleware('can:performance.view');
        Route::post('/performance/goals', [PhaseFourController::class, 'storeGoal'])->middleware('can:performance.create');
        Route::get('/performance/reviews', [PhaseFourController::class, 'reviews'])->middleware('can:performance.view');
        Route::get('/assets', [PhaseFiveController::class, 'assets'])->middleware('can:assets.view');
        Route::post('/assets', [PhaseFiveController::class, 'storeAsset'])->middleware('can:assets.create');
        Route::get('/announcements', [PhaseFiveController::class, 'announcements'])->middleware('can:announcements.view');
        Route::post('/announcements', [PhaseFiveController::class, 'storeAnnouncement'])->middleware('can:announcements.create');
        Route::get('/reports/summary', [PhaseFiveController::class, 'report'])->middleware('can:reports.view');

        Route::post('/staff/{staff}/invite', [StaffInvitationController::class, 'store']);
        Route::delete('/staff/{staff}/invite', [StaffInvitationController::class, 'destroy']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('/activity', [ActivityLogController::class, 'index']);
        Route::get('/workspace/users', [WorkspaceUserController::class, 'index']);

        Route::get('/attachments', [AttachmentController::class, 'index']);
        Route::post('/attachments', [AttachmentController::class, 'store']);
        Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
        Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy']);

        Route::apiResource('projects', ProjectController::class);
        Route::get('/projects/{project}/tasks', [TaskController::class, 'indexForProject']);
        Route::post('/projects/{project}/tasks', [TaskController::class, 'storeForProject']);
        Route::apiResource('meetings', MeetingController::class);
        Route::patch('/meetings/{meeting}/reschedule', [MeetingController::class, 'reschedule']);
        Route::patch('/meetings/{meeting}/cancel', [MeetingController::class, 'cancel']);
        Route::post('/meetings/{meeting}/duplicate', [MeetingController::class, 'duplicate']);
        Route::post('/meetings/{meeting}/invite', [MeetingController::class, 'invite']);
        Route::post('/meetings/{meeting}/respond', [MeetingController::class, 'respond']);
        Route::patch('/meetings/{meeting}/attendance', [MeetingController::class, 'attendance']);

        Route::get('/tasks', [TaskController::class, 'index']);
        Route::get('/tasks/{task}', [TaskController::class, 'show']);
        Route::put('/tasks/{task}', [TaskController::class, 'update']);
        Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus']);
        Route::patch('/tasks/{task}/archive', [TaskController::class, 'archive']);
        Route::patch('/tasks/{task}/restore', [TaskController::class, 'restore']);
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);

        Route::get('/tasks/{task}/comments', [TaskCommentController::class, 'index']);
        Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store']);
        Route::put('/comments/{comment}', [TaskCommentController::class, 'update']);
        Route::delete('/comments/{comment}', [TaskCommentController::class, 'destroy']);

        Route::get('/tasks/{task}/checklist', [TaskChecklistController::class, 'index']);
        Route::post('/tasks/{task}/checklist', [TaskChecklistController::class, 'store']);
        Route::patch('/tasks/{task}/checklist/reorder', [TaskChecklistController::class, 'reorder']);
        Route::patch('/checklist-items/{item}', [TaskChecklistController::class, 'update']);
        Route::delete('/checklist-items/{item}', [TaskChecklistController::class, 'destroy']);

        Route::get('/time-entries/running', [TaskTimeEntryController::class, 'running']);
        Route::get('/tasks/{task}/time-entries', [TaskTimeEntryController::class, 'index']);
        Route::post('/tasks/{task}/time-entries', [TaskTimeEntryController::class, 'store']);
        Route::post('/tasks/{task}/timer/start', [TaskTimeEntryController::class, 'start']);
        Route::post('/tasks/{task}/timer/stop', [TaskTimeEntryController::class, 'stop']);
        Route::delete('/time-entries/{entry}', [TaskTimeEntryController::class, 'destroy']);

    });

});
