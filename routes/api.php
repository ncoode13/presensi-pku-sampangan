<?php

use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceSubmissionController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmploymentStatusController;
use App\Http\Controllers\Api\JobLevelController;
use App\Http\Controllers\Api\LeaveSubmissionController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ChangeShiftController;
use App\Http\Controllers\Api\OvertimeSubmissionController;
use App\Http\Controllers\Api\ShiftSubmissionController;
use App\Http\Controllers\Api\TimeOffController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\AttendanceLocationController;
use App\Http\Controllers\Api\EmployeeBulkUpdateController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RelationshipController;
use App\Http\Controllers\Api\ShiftScheduleController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\DiklatController;
use App\Http\Controllers\Api\DiklatEventController;
use App\Http\Controllers\Api\ApprovalLineBulkController;
use App\Http\Controllers\Api\ApprovalLineController;
use App\Http\Controllers\Api\ApprovalDelegationController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1', 'as' => 'api.'], function () {
    Route::post('login', [\App\Http\Controllers\Api\AuthController::class, 'login'])->name('login');
    Route::get('google', [\App\Http\Controllers\Api\GoogleAuthController::class, 'redirectToGoogle'])->name('google');
    Route::get('google/callback', [\App\Http\Controllers\Api\GoogleAuthController::class, 'handleGoogleCallback'])->name('google.callback');

    Route::get('/ping', function () {
        return response()->json(['waktu' => now()->toDateTimeString()]);
    });


    Route::group(['middleware' => 'auth:sanctum'], function () {
        Route::post('logout', [\App\Http\Controllers\Api\AuthController::class, 'logout'])->name('logout');

        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('positions', PositionController::class);
        Route::apiResource('job-levels', JobLevelController::class);

        Route::apiResource('employment-status', EmploymentStatusController::class);
        Route::apiResource('holidays', HolidayController::class);
        Route::post('holidays/sync', [HolidayController::class, 'syncNationalHolidays']);
        Route::apiResource('attendance-locations', AttendanceLocationController::class);

        Route::prefix('approval-settings')->group(function () {
            Route::get('/{userId}', [ApprovalLineController::class, 'show']);
            Route::post('/{userId}', [ApprovalLineController::class, 'update']);
        });

        Route::prefix('approval-settings-bulk')->group(function () {
            Route::get('/', [ApprovalLineBulkController::class, 'index']);
            Route::post('/export', [ApprovalLineBulkController::class, 'export']);
            Route::post('/import', [ApprovalLineBulkController::class, 'import']);
        });

        Route::prefix('approval-delegations')->group(function () {
            Route::get('/', [ApprovalDelegationController::class, 'index']);
            Route::post('/', [ApprovalDelegationController::class, 'store']);
            Route::get('/{id}', [ApprovalDelegationController::class, 'show']);
            Route::put('/{id}', [ApprovalDelegationController::class, 'update']);
            Route::delete('/{id}', [ApprovalDelegationController::class, 'destroy']);
        });

        Route::prefix('admin')->group(function () {
            Route::get('/approvals/live', [ApprovalController::class, 'liveApproval']);
            Route::get('/approvals/manual', [ApprovalController::class, 'manualRequest']);
            Route::post('/approvals/action', [ApprovalController::class, 'action']);
            Route::get('/approvals/detail/{id}', [ApprovalController::class, 'show']);
            Route::post('/approvals/bulk-action', [ApprovalController::class, 'bulkAction']);
        });

        // User Profile & Management
        Route::prefix('users')->group(function () {
            Route::get('me', [UserController::class, 'me']);
            Route::put('me/profile', [UserController::class, 'updateProfile']);
            Route::put('me/password', [UserController::class, 'updatePassword']);
            Route::put('{id}/reset-password', [UserController::class, 'resetPasswordByAdmin']);
            Route::put('{id}/role', [UserController::class, 'updateUserRole']);
        });

        Route::apiResource('users', UserController::class)->except(['update']);

        Route::prefix('relationships')->group(function () {
            Route::get('/', [RelationshipController::class, 'index']);
            Route::post('/', [RelationshipController::class, 'store']);
            Route::get('/{id}', [RelationshipController::class, 'show']);
            Route::put('/{id}', [RelationshipController::class, 'update']);
            Route::delete('/{id}', [RelationshipController::class, 'destroy']);
        });

        // Employees
        Route::prefix('employees/bulk-update')->group(function () {
            Route::post('/export', [EmployeeBulkUpdateController::class, 'export']);
            Route::post('/import', [EmployeeBulkUpdateController::class, 'import']);
        });

        Route::get('/employees/options', [EmployeeController::class, 'getOptions']);
        Route::apiResource('employees', EmployeeController::class);

        Route::prefix('attendance')->group(function () {
            Route::get('/', [AttendanceController::class, 'index']);
            Route::post('', [AttendanceController::class, 'store']);
            Route::get('/report-data', [AttendanceController::class, 'getReportData']);
            Route::get('/rewards', [AttendanceController::class, 'getRewardData']);
            Route::get('/summary', [AttendanceController::class, 'summaryHistory']);
            Route::get('/dashboard-summary', [AttendanceController::class, 'dashboardSummary']);
            Route::get('/my-monthly-history', [AttendanceController::class, 'getMonthlyHistory']);
            Route::post('/request', [AttendanceSubmissionController::class, 'store']);
            Route::get('/my-history', [AttendanceSubmissionController::class, 'myRequest']);
            Route::get('/need-aproval', [AttendanceSubmissionController::class, 'pendingRequests']);
            Route::get('/{id}', [AttendanceController::class, 'show']);
            Route::post('/{id}/approve-hrd', [AttendanceSubmissionController::class, 'approveHRD'])->middleware('role:admin');
            Route::post('/{id}/approve-director', [AttendanceSubmissionController::class, 'approveDirector'])->middleware('role:admin');
            Route::post('/{id}/reject', [AttendanceSubmissionController::class, 'reject']);
        });

        Route::prefix('leaves')->group(function () {
            Route::get('/balance', [LeaveSubmissionController::class, 'getMyBalance']);
            Route::get('/', [LeaveSubmissionController::class, 'index']);
            Route::get('/{id}', [LeaveSubmissionController::class, 'show']);
            Route::post('/', [LeaveSubmissionController::class, 'store']);
            Route::post('/{id}/action', [LeaveSubmissionController::class, 'action']);
        });

        Route::prefix('time-off')->group(function () {
            Route::get('/', [TimeOffController::class, 'index']);
            Route::post('/', [TimeOffController::class, 'store']);
            Route::get('/{id}', [TimeOffController::class, 'show']);
            Route::put('/{id}', [TimeOffController::class, 'update']);
            Route::delete('/{id}', [TimeOffController::class, 'destroy']);
        });

        Route::prefix('shift-schedules')->group(function () {
            Route::get('/', [ShiftScheduleController::class, 'index']);
            Route::post('/', [ShiftScheduleController::class, 'store']);
            Route::get('/units', [UnitController::class, 'index']);
            Route::post('/import', [ShiftScheduleController::class, 'import']);
            Route::get('/template', [ShiftScheduleController::class, 'downloadTemplate']);
            Route::post('/custom-export', [ShiftScheduleController::class, 'customExport']);
        });

        Route::prefix('shifts')->group(function () {
            Route::prefix('requests')->group(function () {
                Route::get('/', [ShiftSubmissionController::class, 'index']);
                Route::post('/', [ShiftSubmissionController::class, 'store']);
                Route::get('/{id}', [ShiftSubmissionController::class, 'show']);
                Route::post('/{id}/action', [ShiftSubmissionController::class, 'action']);
            });
            Route::get('/', [ShiftController::class, 'index']);
            Route::post('/', [ShiftController::class, 'store']);
            Route::put('/{id}', [ShiftController::class, 'update']);
            Route::delete('/{id}', [ShiftController::class, 'destroy']);
        });

        Route::apiResource('shifts', ShiftController::class);

        Route::prefix('overtimes')->group(function () {
            Route::get('/', [OvertimeSubmissionController::class, 'index']);
            Route::get('/report-data', [OvertimeSubmissionController::class, 'getReportData']);
            Route::get('/{id}', [OvertimeSubmissionController::class, 'show']);
            Route::post('/', [OvertimeSubmissionController::class, 'store']);
            Route::post('/{id}/action', [OvertimeSubmissionController::class, 'action']);
        });

        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
            Route::put('/read-all', [NotificationController::class, 'markAllRead']);
            Route::get('/unread-dropdown', [NotificationController::class, 'getUnreadDropdown']);
            Route::post('/bulk', [NotificationController::class, 'bulkAction']);
        });

        Route::get('/announcement-categories', [AnnouncementController::class, 'getCategories']);
        Route::post('/announcement-categories', [AnnouncementController::class, 'storeCategory']);
        Route::get('/announcements/my-dashboard', [AnnouncementController::class, 'myAnnouncements']);
        Route::post('/announcements/{id}', [AnnouncementController::class, 'update']);
        Route::apiResource('announcements', AnnouncementController::class);

        // Route Diklat
        Route::prefix('diklat')->group(function () {
            Route::get('/summary', [DiklatController::class, 'index']);
            Route::get('/settings/target', [DiklatController::class, 'getGlobalTarget']);
            Route::put('/settings/target', [DiklatController::class, 'updateGlobalTarget']);
            Route::get('export/yearly', [DiklatController::class, 'export']);
            Route::get('/events/import-template', [DiklatEventController::class, 'downloadImportTemplate']);
            Route::post('/events/{id}/import', [DiklatEventController::class, 'importAttendance']);
            Route::apiResource('events', DiklatEventController::class);;
            Route::get('/events/{id}/attendance-detail', [DiklatEventController::class, 'eventAttendanceDetail']);
            // Route::post('/events', [DiklatController::class, 'storeEvent']);
            // Route::get('/events/{id}', [DiklatController::class, 'eventDetail']);
            Route::get('/all-user-ids', [DiklatController::class, 'getAllUserIds']);
            Route::post('/events/{id}/attendance', [DiklatEventController::class, 'markAttendance']);
            Route::get('/categories', [DiklatController::class, 'getCategories']);
            Route::post('/categories', [DiklatController::class, 'storeCategory']);
            Route::put('/categories/{id}', [DiklatController::class, 'updateCategory']);
            Route::delete('/categories/{id}', [DiklatController::class, 'deleteCategory']);
        });
    });
});
