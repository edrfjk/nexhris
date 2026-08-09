<?php

use Illuminate\Support\Facades\Route;


// ============================================================
// AUTHENTICATION
// ============================================================

use App\Http\Controllers\Auth\LoginController;


// ============================================================
// ADMIN CONTROLLERS
// ============================================================

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\LeaveLedgerController;
use App\Http\Controllers\Admin\PdsReviewController;
use App\Http\Controllers\Admin\PdsTemplateController;
use App\Http\Controllers\Admin\HrPolicyController;


// ============================================================
// EMPLOYEE CONTROLLERS
// ============================================================

use App\Http\Controllers\Employee\LeaveApplicationController;
use App\Http\Controllers\Employee\PdsEditorController;
use App\Http\Controllers\Employee\MyIdController;
use App\Http\Controllers\Employee\PolicyController;


// ============================================================
// ROOT
// ============================================================

Route::get('/', fn () => redirect('/login'));


// ============================================================
// GUEST / AUTHENTICATION ROUTES
// ============================================================

Route::middleware('guest')->group(function () {

    Route::get('/login', [LoginController::class, 'create'])
        ->name('login');

    Route::post('/login', [LoginController::class, 'store']);

});


// ============================================================
// AUTHENTICATED ROUTES
// ============================================================

Route::middleware('auth')->group(function () {


    // ========================================================
    // LOGOUT
    // ========================================================

    Route::post('/logout', [LoginController::class, 'destroy'])
        ->name('logout');


    // ========================================================
    // ADMIN ROUTES
    // ========================================================

    Route::middleware('admin')
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {


        // ----------------------------------------------------
        // ADMIN DASHBOARD
        // ----------------------------------------------------

        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');


        // ----------------------------------------------------
        // EMPLOYEE MANAGEMENT
        // ----------------------------------------------------

        Route::resource('employees', EmployeeController::class)
            ->except(['destroy'])
            ->parameters([
                'employees' => 'employee',
            ]);

        Route::patch(
            '/employees/{employee}/status',
            [EmployeeController::class, 'updateStatus']
        )->name('employees.status.update');

        Route::post(
            '/employees/{employee}/photo',
            [EmployeeController::class, 'updatePhoto']
        )->name('employees.photo.update');

        Route::get(
            '/employees/export/pdf',
            [EmployeeController::class, 'exportPdf']
        )->name('employees.export.pdf');


        // ----------------------------------------------------
        // LEAVE MANAGEMENT
        // ----------------------------------------------------

        Route::get(
            '/leave',
            [LeaveLedgerController::class, 'index']
        )->name('leave.index');

        Route::post(
            '/leave/bulk-earned',
            [LeaveLedgerController::class, 'bulkStoreEarned']
        )->name('leave.bulk-earned.store');

        Route::get(
            '/leave/pending',
            [LeaveLedgerController::class, 'pending']
        )->name('leave.pending');

        Route::post(
            '/leave/{application}/approve',
            [LeaveLedgerController::class, 'approve']
        )->name('leave.approve');

        Route::post(
            '/leave/{application}/decline',
            [LeaveLedgerController::class, 'decline']
        )->name('leave.decline');

        Route::get(
            '/leave/{employee}/ledger',
            [LeaveLedgerController::class, 'show']
        )->name('leave.ledger');

        Route::post(
            '/leave/{employee}/earned',
            [LeaveLedgerController::class, 'storeEarned']
        )->name('leave.earned.store');

        Route::post(
            '/leave/{employee}/adjust',
            [LeaveLedgerController::class, 'storeAdjustment']
        )->name('leave.adjust.store');

        Route::get(
            '/leave/{employee}/ledger/pdf',
            [LeaveLedgerController::class, 'exportLedgerPdf']
        )->name('leave.ledger.pdf');

        Route::get(
            '/leave/calendar',
            [LeaveLedgerController::class, 'calendar']
        )->name('leave.calendar');

        Route::get(
            '/leave/export/pdf',
            [LeaveLedgerController::class, 'exportAllPdf']
        )->name('leave.export.pdf');

        Route::get(
            '/leave/export/excel',
            [LeaveLedgerController::class, 'exportAllExcel']
        )->name('leave.export.excel');

        Route::get(
            '/leave/calendar/export',
            [LeaveLedgerController::class, 'exportMonthPdf']
        )->name('leave.calendar.export');


        // ====================================================
        // PDS REVIEW / ADMIN
        // ====================================================

        Route::get(
            '/pds',
            [PdsReviewController::class, 'index']
        )->name('pds.index');

        Route::get(
            '/pds/{employee}',
            [PdsReviewController::class, 'show']
        )->name('pds.show');

        Route::post(
            '/pds/{employee}/approve',
            [PdsReviewController::class, 'approve']
        )->name('pds.approve');

        Route::post(
            '/pds/{employee}/return',
            [PdsReviewController::class, 'returnForRevision']
        )->name('pds.return');

        Route::get(
            '/pds/{employee}/download',
            [PdsReviewController::class, 'download']
        )->name('pds.download');


        // ====================================================
        // PDS TEMPLATES
        // ====================================================
        // Managed directly from the PDS Requests page.
        // There is NO separate PDS Templates index page.
        // ====================================================

        Route::post(
            '/pds-templates',
            [PdsTemplateController::class, 'store']
        )->name('pds.templates.store');

        Route::post(
            '/pds-templates/{template}/activate',
            [PdsTemplateController::class, 'activate']
        )->name('pds.templates.activate');

        Route::delete(
            '/pds-templates/{template}',
            [PdsTemplateController::class, 'destroy']
        )->name('pds.templates.destroy');


        // ----------------------------------------------------
        // HR POLICIES / ADMIN
        // ----------------------------------------------------

        Route::resource('policies', HrPolicyController::class)
            ->except(['show']);

        Route::post(
            '/policies/{policy}/toggle-publish',
            [HrPolicyController::class, 'togglePublish']
        )->name('policies.toggle-publish');

        Route::post(
            '/policies/{policy}/toggle-pin',
            [HrPolicyController::class, 'togglePin']
        )->name('policies.toggle-pin');

        Route::get(
            '/policies/{policy}/compliance',
            [HrPolicyController::class, 'compliance']
        )->name('policies.compliance');

    });


    // ========================================================
    // EMPLOYEE ROUTES
    // ========================================================


    // --------------------------------------------------------
    // EMPLOYEE DASHBOARD
    // --------------------------------------------------------

    Route::get(
        '/dashboard',
        fn () => view('employee.dashboard')
    )->name('employee.dashboard');


    // --------------------------------------------------------
    // LEAVE APPLICATION
    // --------------------------------------------------------

    Route::prefix('leave')
        ->name('leave.')
        ->group(function () {

        Route::get(
            '/',
            [LeaveApplicationController::class, 'index']
        )->name('index');

        Route::post(
            '/',
            [LeaveApplicationController::class, 'store']
        )->name('store');

        Route::get(
            '/ledger/pdf',
            [LeaveApplicationController::class, 'exportLedgerPdf']
        )->name('ledger.pdf');

    });


    // --------------------------------------------------------
    // MY ID
    // --------------------------------------------------------

    Route::get(
        '/my-id',
        [MyIdController::class, 'show']
    )->name('my-id.show');

    Route::post(
        '/my-id/photo',
        [MyIdController::class, 'updatePhoto']
    )->name('my-id.photo.update');


    // --------------------------------------------------------
    // HR POLICIES / EMPLOYEE
    // --------------------------------------------------------

    Route::prefix('policies')
        ->name('policies.')
        ->group(function () {

        Route::get(
            '/',
            [PolicyController::class, 'index']
        )->name('index');

        Route::get(
            '/{policy}',
            [PolicyController::class, 'show']
        )->name('show');

        Route::post(
            '/{policy}/acknowledge',
            [PolicyController::class, 'acknowledge']
        )->name('acknowledge');

    });


    // ========================================================
    // PDS / PERSONAL DATA SHEET
    // ========================================================
    // Employee PDS is now handled by PdsEditorController.
    //
    // Existing route names are preserved:
    // pds.edit
    // pds.save
    // pds.submit
    // pds.download
    // ========================================================

    Route::prefix('pds')->name('pds.')->group(function () {
    Route::get('/', [PdsEditorController::class, 'show'])->name('editor');
    Route::post('/upload', [PdsEditorController::class, 'upload'])->name('upload');
    Route::post('/submit', [PdsEditorController::class, 'submit'])->name('submit');
    Route::get('/export', [PdsEditorController::class, 'exportPdf'])->name('export');

        // ----------------------------------------------------
        // PDS EDITOR
        // ----------------------------------------------------

        Route::get(
            '/',
            [PdsEditorController::class, 'show']
        )->name('edit');


        // ----------------------------------------------------
        // SAVE PDS
        // ----------------------------------------------------

        Route::post(
            '/save',
            [PdsEditorController::class, 'save']
        )->name('save');


        // ----------------------------------------------------
        // SUBMIT PDS
        // ----------------------------------------------------

        Route::post(
            '/submit',
            [PdsEditorController::class, 'submit']
        )->name('submit');


        // ----------------------------------------------------
        // DOWNLOAD / EXPORT PDS
        // ----------------------------------------------------

        Route::get(
            '/download',
            [PdsEditorController::class, 'exportPdf']
        )->name('download');

    });

});