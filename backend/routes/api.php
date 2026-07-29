<?php

use App\Http\Controllers\API\ActivityLogController;
use App\Http\Controllers\API\AttachmentController;
use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\AttendanceLocationController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\EmployeeController;
use App\Http\Controllers\API\EmployeeInvitationController;
use App\Http\Controllers\API\ExpenseController;
use App\Http\Controllers\API\GuestRsvpController;
use App\Http\Controllers\API\LeaveController;
use App\Http\Controllers\API\MeetingController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\PayrollController;
use App\Http\Controllers\API\PayrollProfileController;
use App\Http\Controllers\API\PayrollRunController;
use App\Http\Controllers\API\PayslipController;
use App\Http\Controllers\API\PermissionController;
use App\Http\Controllers\API\PhaseFiveController;
use App\Http\Controllers\API\PhaseFourController;
use App\Http\Controllers\API\ProjectController;
use App\Http\Controllers\API\SalaryAssignmentController;
use App\Http\Controllers\API\SalaryComponentController;
use App\Http\Controllers\API\SalaryPaymentController;
use App\Http\Controllers\API\SalaryStructureController;
use App\Http\Controllers\API\TaskChecklistController;
use App\Http\Controllers\API\TaskCommentController;
use App\Http\Controllers\API\TaskController;
use App\Http\Controllers\API\TaskTimeEntryController;
use App\Http\Controllers\API\WorkspaceUserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

        Route::apiResource('employees', EmployeeController::class);
        /*
        | These modules predate the policy layer and their controllers only
        | check tenancy, so permission is enforced here with can: middleware
        | against the gates registered in AppServiceProvider. Approving a
        | request is a separate permission from editing it.
        */
        Route::get('/attendance', [AttendanceController::class, 'index'])->middleware('can:attendance.view');
        Route::get('/attendance/today', [AttendanceController::class, 'today'])->middleware('can:attendance.view');
        Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->middleware('can:attendance.create');
        Route::post('/attendance/{attendance}/check-out', [AttendanceController::class, 'checkOut'])->middleware('can:attendance.create');
        Route::patch('/attendance/{attendance}/approve', [AttendanceController::class, 'approve'])->middleware('can:attendance.edit');
        // Correcting somebody's hours, including closing a forgotten check-out.
        Route::patch('/attendance/{attendance}', [AttendanceController::class, 'correct'])->middleware('can:attendance.edit');

        Route::get('/attendance-locations', [AttendanceLocationController::class, 'index'])->middleware('can:attendance.view');
        Route::post('/attendance-locations', [AttendanceLocationController::class, 'store'])->middleware('can:attendance.edit');
        Route::put('/attendance-locations/{location}', [AttendanceLocationController::class, 'update'])->middleware('can:attendance.edit');
        Route::delete('/attendance-locations/{location}', [AttendanceLocationController::class, 'destroy'])->middleware('can:attendance.edit');
        Route::get('/leave/types', [LeaveController::class, 'types'])->middleware('can:leave.view');
        Route::get('/leave/requests', [LeaveController::class, 'index'])->middleware('can:leave.view');
        Route::post('/leave/requests', [LeaveController::class, 'store'])->middleware('can:leave.create');
        Route::patch('/leave/requests/{leaveRequest}/status', [LeaveController::class, 'updateStatus'])->middleware('can:leave.approve');
        // Salary slips are read-only: they are produced by generating a payroll
        // run from salary assignments, not by posting figures directly.
        Route::get('/payroll', [PayrollController::class, 'index'])->middleware('can:payroll.view');
        Route::get('/payroll/{slip}', [PayrollController::class, 'show'])->middleware('can:payroll.view');

        /*
        | Salary templates and the lines that make them up. Assignments point at
        | a structure, so these have to exist before anyone can be paid.
        */
        Route::get('/salary-structures', [SalaryStructureController::class, 'index'])->middleware('can:payroll.view-all');
        Route::get('/salary-structures/{structure}', [SalaryStructureController::class, 'show'])->middleware('can:payroll.view-all');
        Route::post('/salary-structures', [SalaryStructureController::class, 'store'])->middleware('can:payroll.create');
        Route::put('/salary-structures/{structure}', [SalaryStructureController::class, 'update'])->middleware('can:payroll.edit');
        Route::delete('/salary-structures/{structure}', [SalaryStructureController::class, 'destroy'])->middleware('can:payroll.delete');

        Route::get('/salary-components', [SalaryComponentController::class, 'index'])->middleware('can:payroll.view-all');
        Route::post('/salary-components', [SalaryComponentController::class, 'store'])->middleware('can:payroll.create');
        Route::put('/salary-components/{component}', [SalaryComponentController::class, 'update'])->middleware('can:payroll.edit');
        Route::delete('/salary-components/{component}', [SalaryComponentController::class, 'destroy'])->middleware('can:payroll.delete');

        /*
        | Where an employee's pay goes. payroll.view rather than view-all: an
        | employee maintains their own, and RecordScope in the controller is
        | what keeps them out of anybody else's.
        */
        Route::get('/employees/{employee}/payroll-profile', [PayrollProfileController::class, 'show'])->middleware('can:payroll.view');
        Route::put('/employees/{employee}/payroll-profile', [PayrollProfileController::class, 'store'])->middleware('can:payroll.view');
        Route::delete('/employees/{employee}/payroll-profile', [PayrollProfileController::class, 'destroy'])->middleware('can:payroll.edit');

        // An employee's salary and its revision history.
        Route::get('/employees/{employee}/salary', [SalaryAssignmentController::class, 'current'])->middleware('can:payroll.view');
        Route::get('/employees/{employee}/salary/history', [SalaryAssignmentController::class, 'index'])->middleware('can:payroll.view');
        Route::post('/employees/{employee}/salary', [SalaryAssignmentController::class, 'store'])->middleware('can:payroll.create');
        Route::patch('/employees/{employee}/salary/end', [SalaryAssignmentController::class, 'end'])->middleware('can:payroll.edit');

        Route::get('/payroll-runs', [PayrollRunController::class, 'index'])->middleware('can:payroll.view');
        Route::get('/payroll-runs/{run}', [PayrollRunController::class, 'show'])->middleware('can:payroll.view');
        Route::post('/payroll-runs', [PayrollRunController::class, 'store'])->middleware('can:payroll.generate');
        Route::post('/payroll-runs/{run}/regenerate', [PayrollRunController::class, 'regenerate'])->middleware('can:payroll.generate');
        Route::delete('/payroll-runs/{run}', [PayrollRunController::class, 'destroy'])->middleware('can:payroll.delete');

        /*
        | draft -> pending approval -> approved -> paid. Approving is separate
        | from generating so a second pair of eyes can be required, and paying
        | is separate again because it is money leaving the business.
        */
        Route::patch('/payroll-runs/{run}/submit', [PayrollRunController::class, 'submit'])->middleware('can:payroll.edit');
        Route::patch('/payroll-runs/{run}/approve', [PayrollRunController::class, 'approve'])->middleware('can:payroll.approve');
        Route::patch('/payroll-runs/{run}/cancel', [PayrollRunController::class, 'cancel'])->middleware('can:payroll.approve');

        /*
        | Download is the one payroll route an employee reaches: they hold
        | payroll.download but not view-all, so RecordScope in the controller
        | is what limits them to their own payslip.
        */
        Route::get('/salary-slips/{slip}/download', [PayslipController::class, 'download'])
            ->middleware('can:payroll.download')->name('payslips.download');
        Route::post('/salary-slips/{slip}/email', [PayslipController::class, 'email'])->middleware('can:payroll.export');
        Route::post('/payroll-runs/{run}/email', [PayslipController::class, 'emailRun'])->middleware('can:payroll.export');

        Route::get('/salary-slips/{slip}/payments', [SalaryPaymentController::class, 'index'])->middleware('can:payroll.view-all');
        Route::post('/salary-slips/{slip}/payments', [SalaryPaymentController::class, 'store'])->middleware('can:payroll.pay');
        Route::delete('/salary-payments/{payment}', [SalaryPaymentController::class, 'destroy'])->middleware('can:payroll.pay');
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

        Route::post('/employees/{employee}/invite', [EmployeeInvitationController::class, 'store']);
        Route::delete('/employees/{employee}/invite', [EmployeeInvitationController::class, 'destroy']);

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
