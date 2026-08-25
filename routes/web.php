<?php

use Illuminate\Support\Facades\Route;


// ============================================================
// AUTHENTICATION
// ============================================================

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicVerificationController;
use App\Http\Controllers\AnnouncementFeedController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Auth\PasswordResetController;


// ============================================================
// ADMIN CONTROLLERS
// ============================================================

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\CollegeController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\LeaveLedgerController;
use App\Http\Controllers\Admin\LeaveReviewController;
use App\Http\Controllers\Admin\LeaveFormTemplateController;
use App\Http\Controllers\Admin\PdsReviewController;
use App\Http\Controllers\Admin\PdsTemplateController;
use App\Http\Controllers\Admin\HrPolicyController;


// ============================================================
// EMPLOYEE CONTROLLERS
// ============================================================

use App\Http\Controllers\Employee\LeaveApplicationController;
use App\Http\Controllers\Employee\DashboardController as EmployeeDashboardController;
use App\Http\Controllers\Employee\PdsEditorController;
use App\Http\Controllers\Employee\MyIdController;
use App\Http\Controllers\Employee\MyLedgerController;
use App\Http\Controllers\Employee\PolicyController;


// ============================================================
// ROOT
// ============================================================

Route::get('/', fn () => redirect('/login'));


// ============================================================
// PUBLIC ID VERIFICATION
// ============================================================
// The page a digital ID's QR code points at. No sign-in, and no more than
// name, position, college and active status. Addressed by an unguessable
// token so staff cannot be enumerated.

Route::get('/verify/{token}', [PublicVerificationController::class, 'show'])
    ->name('verify.show');


// ============================================================
// GUEST / AUTHENTICATION ROUTES
// ============================================================

Route::middleware('guest')->group(function () {

    Route::get('/login', [LoginController::class, 'create'])
        ->name('login');

    Route::post('/login', [LoginController::class, 'store']);


    // --------------------------------------------------------
    // FORGOT / RESET PASSWORD
    // --------------------------------------------------------

    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])
        ->name('password.request');

    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])
        ->name('password.reset');

    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:6,1')
        ->name('password.update');

});


// ============================================================
// TWO-FACTOR CHALLENGE
// ============================================================
// Reached only while a session is half-authenticated: the password was
// correct but the emailed code has not been entered yet. These routes sit
// outside the 2FA gate, otherwise they would redirect to themselves.

Route::middleware('auth')->withoutMiddleware([\App\Http\Middleware\EnsureTwoFactorVerified::class])->group(function () {

    Route::get('/two-factor', [TwoFactorController::class, 'show'])
        ->name('two-factor.challenge');

    Route::post('/two-factor', [TwoFactorController::class, 'verify'])
        ->middleware('throttle:20,15')
        ->name('two-factor.verify');

    Route::post('/two-factor/resend', [TwoFactorController::class, 'resend'])
        ->middleware('throttle:6,10')
        ->name('two-factor.resend');

    Route::post('/two-factor/cancel', [TwoFactorController::class, 'cancel'])
        ->name('two-factor.cancel');

});


// ============================================================
// AUTHENTICATED ROUTES
// ============================================================

Route::middleware('auth')->group(function () {


    // ========================================================
    // NOTIFICATIONS (all roles)
    // ========================================================

    Route::get('/announcements', [AnnouncementFeedController::class, 'index'])
        ->name('announcements.index');

    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');

    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');

    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');


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
        // ACTIVITY LOG / AUDIT TRAIL
        // ----------------------------------------------------

        Route::get('/activity-logs', [ActivityLogController::class, 'index'])
            ->middleware('role:admin')
            ->name('activity-logs.index');


        // ----------------------------------------------------
        // ANNOUNCEMENTS
        // ----------------------------------------------------

        Route::middleware('role:admin')->group(function () {
            Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
            Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
            Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
            Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
        });


        // ----------------------------------------------------
        // COLLEGES / DEPARTMENTS
        // ----------------------------------------------------
        // HR only: the college a person belongs to decides which Dean signs
        // their leave, so Deans must not be able to edit it.

        Route::middleware('role:admin')->group(function () {
            Route::get('/colleges', [CollegeController::class, 'index'])->name('colleges.index');
            Route::post('/colleges', [CollegeController::class, 'store'])->name('colleges.store');
            Route::put('/colleges/{college}', [CollegeController::class, 'update'])->name('colleges.update');
            Route::delete('/colleges/{college}', [CollegeController::class, 'destroy'])->name('colleges.destroy');

            // Departments / programmes / offices, nested under their college.
            Route::post('/colleges/{college}/departments', [DepartmentController::class, 'store'])
                ->name('departments.store');
            Route::put('/departments/{department}', [DepartmentController::class, 'update'])
                ->name('departments.update');
            Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])
                ->name('departments.destroy');
        });


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

        // Static leave segments must be declared before /leave/{employee},
        // otherwise "calendar" and "review" get bound as employee ids.

        Route::get(
            '/leave',
            [LeaveLedgerController::class, 'index']
        )->name('leave.index');

        Route::get(
            '/leave/calendar',
            [LeaveLedgerController::class, 'calendar']
        )->name('leave.calendar');

        Route::get(
            '/leave/calendar/export',
            [LeaveLedgerController::class, 'exportMonthPdf']
        )->name('leave.calendar.export');

        Route::get(
            '/leave/export/pdf',
            [LeaveLedgerController::class, 'exportAllPdf']
        )->name('leave.export.pdf');

        Route::get(
            '/leave/export/excel',
            [LeaveLedgerController::class, 'exportAllExcel']
        )->name('leave.export.excel');

        Route::post(
            '/leave/bulk-earned',
            [LeaveLedgerController::class, 'bulkStoreEarned']
        )->name('leave.bulk-earned.store');


        // ----------------------------------------------------
        // LEAVE REVIEW — Dean → HR → Campus Director
        // ----------------------------------------------------
        // One set of screens for all three reviewers. Each sees only the
        // forms waiting on their own stage.

        Route::prefix('leave/review')
            ->name('leave.review.')
            ->group(function () {

            Route::get('/', [LeaveReviewController::class, 'index'])
                ->name('index');

            Route::get('/{application}', [LeaveReviewController::class, 'show'])
                ->name('show');

            Route::get('/{application}/form', [LeaveReviewController::class, 'viewForm'])
                ->name('form');

            // The same form converted, so a reviewer can read it in the
            // browser instead of downloading a workbook to sign it.
            Route::get('/{application}/form.pdf', [LeaveReviewController::class, 'viewFormAsPdf'])
                ->name('form.pdf');

            Route::get('/{application}/print', [LeaveReviewController::class, 'printApproved'])
                ->name('print');

            Route::post('/{application}/approve', [LeaveReviewController::class, 'approve'])
                ->name('approve');

            Route::post('/{application}/return', [LeaveReviewController::class, 'returnForRevision'])
                ->name('return');

            Route::post('/{application}/post-to-ledger', [LeaveReviewController::class, 'postToLedger'])
                ->name('post-to-ledger');

        });


        // ----------------------------------------------------
        // LEAVE FORM TEMPLATES (HR publishes the blank form)
        // ----------------------------------------------------

        Route::get('/leave-form-templates', [LeaveFormTemplateController::class, 'index'])
            ->name('leave.templates.index');

        Route::post('/leave-form-templates', [LeaveFormTemplateController::class, 'store'])
            ->name('leave.templates.store');

        Route::post('/leave-form-templates/{template}/activate', [LeaveFormTemplateController::class, 'activate'])
            ->name('leave.templates.activate');

        Route::delete('/leave-form-templates/{template}', [LeaveFormTemplateController::class, 'destroy'])
            ->name('leave.templates.destroy');


        // ----------------------------------------------------
        // MASTER LEDGER TEMPLATE
        // ----------------------------------------------------
        // Seeded once. Each employee's ledger is copied from it on first use;
        // it is never handed to employees directly.

        Route::post('/ledger-template', [LeaveFormTemplateController::class, 'storeLedgerMaster'])
            ->name('leave.ledger-template.store');

        Route::post('/ledger-template/{template}/activate', [LeaveFormTemplateController::class, 'activateLedgerMaster'])
            ->name('leave.ledger-template.activate');


        // ----------------------------------------------------
        // SERVICE RECORDS
        // ----------------------------------------------------






        // ----------------------------------------------------
        // PER-EMPLOYEE LEDGER CARD
        // ----------------------------------------------------

        Route::get(
            '/leave/{employee}/ledger',
            [LeaveLedgerController::class, 'show']
        )->name('leave.ledger');

        Route::get(
            '/leave/{employee}/ledger/pdf',
            [LeaveLedgerController::class, 'exportLedgerPdf']
        )->name('leave.ledger.pdf');

        Route::post(
            '/leave/{employee}/earned',
            [LeaveLedgerController::class, 'storeEarned']
        )->name('leave.earned.store');

        Route::post(
            '/leave/{employee}/adjust',
            [LeaveLedgerController::class, 'storeAdjustment']
        )->name('leave.adjust.store');


        // ----------------------------------------------------
        // LEDGER WORKBOOK EDITOR
        // ----------------------------------------------------
        // HR corrects the card by editing its lines. The printed card is
        // drawn from these, so a correction here is what actually shows.

        Route::put(
            '/leave/ledger-entries/{entry}',
            [LeaveLedgerController::class, 'updateEntry']
        )->middleware('role:admin')->name('leave.ledger.entry.update');

        Route::delete(
            '/leave/ledger-entries/{entry}',
            [LeaveLedgerController::class, 'destroyEntry']
        )->middleware('role:admin')->name('leave.ledger.entry.destroy');


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

        // The original workbook, for when the PDF preview is not enough.
        Route::get(
            '/pds/{employee}/workbook',
            [PdsReviewController::class, 'downloadWorkbook']
        )->name('pds.workbook');


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
        [EmployeeDashboardController::class, 'index']
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

        // Blank form published by HR, for the employee to fill in.
        Route::get(
            '/template/download',
            [LeaveApplicationController::class, 'downloadTemplate']
        )->name('template.download');

        // Corrected re-upload after a reviewer returns the form.
        Route::post(
            '/{application}/resubmit',
            [LeaveApplicationController::class, 'resubmit']
        )->name('resubmit');

        // Unlocked only once the Campus Director has approved.
        Route::get(
            '/{application}/print',
            [LeaveApplicationController::class, 'printApproved']
        )->name('print');

        // The employee's own uploaded form, as the reviewers will read it.
        Route::get(
            '/{application}/form.pdf',
            [LeaveApplicationController::class, 'exportFormPdf']
        )->name('form.pdf');

        Route::get(
            '/ledger/pdf',
            [LeaveApplicationController::class, 'exportLedgerPdf']
        )->name('ledger.pdf');

        // A page framing the official card, which is drawn from the posted
        // ledger entries rather than converted from a workbook.
        Route::get(
            '/my-ledger',
            [MyLedgerController::class, 'show']
        )->name('ledger.mine');

    });


    // --------------------------------------------------------
    // MY PROFILE
    // --------------------------------------------------------
    // Everyone edits their own name, email, contact number, photo and
    // password here. Employee number, position, role and college stay with
    // HR — the college decides who approves your leave.

    Route::prefix('profile')
        ->name('profile.')
        ->group(function () {

        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');
        Route::post('/photo', [ProfileController::class, 'updatePhoto'])->name('photo.update');
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
    // Every role files a PDS, including Deans and the Campus Director.
    // The employee downloads the official blank, fills it offline, and
    // uploads the workbook back; the system converts it to PDF.

    Route::prefix('pds')->name('pds.')->group(function () {

        Route::get('/', [PdsEditorController::class, 'show'])->name('editor');

        // `pds.edit` is kept as an alias: the sidebar and several views
        // already link to it.
        Route::get('/edit', [PdsEditorController::class, 'show'])->name('edit');

        Route::get('/template/download', [PdsEditorController::class, 'downloadTemplate'])
            ->name('template.download');

        Route::post('/upload', [PdsEditorController::class, 'upload'])->name('upload');

        Route::post('/submit', [PdsEditorController::class, 'submit'])->name('submit');

        // Two names for one action, both already in use across the views.
        Route::get('/export', [PdsEditorController::class, 'exportPdf'])->name('export');
        Route::get('/download', [PdsEditorController::class, 'exportPdf'])->name('download');

    });

});
