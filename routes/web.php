<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\LeaveLedgerController;
use App\Http\Controllers\Employee\LeaveApplicationController;
use App\Http\Controllers\Employee\PdsController;
use App\Http\Controllers\Employee\PdsWizardController;
use App\Http\Controllers\Employee\PdsChildController;
use App\Http\Controllers\Employee\PdsEducationController;
use App\Http\Controllers\Employee\PdsEligibilityController;
use App\Http\Controllers\Employee\PdsWorkExperienceController;
use App\Http\Controllers\Employee\PdsVoluntaryWorkController;
use App\Http\Controllers\Employee\PdsTrainingController;
use App\Http\Controllers\Employee\PdsReferenceController;
use App\Http\Controllers\Employee\PdsPdfController;
use App\Http\Controllers\Employee\MyIdController;
use App\Http\Controllers\Admin\PdsReviewController;
use App\Http\Controllers\Admin\HrPolicyController;
use App\Http\Controllers\Admin\DashboardController;




use Illuminate\Support\Facades\Route;


Route::get('/', fn () => redirect('/login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // ===== ADMIN ROUTES =====
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('employees', EmployeeController::class)->except(['destroy'])->parameters([
            'employees' => 'employee',
        ]);

        Route::patch('/employees/{employee}/status', [EmployeeController::class, 'updateStatus'])->name('employees.status.update');
        Route::post('/employees/{employee}/photo', [EmployeeController::class, 'updatePhoto'])->name('employees.photo.update');
        Route::get('/employees/export/pdf', [EmployeeController::class, 'exportPdf'])->name('employees.export.pdf');
        Route::resource('employees', EmployeeController::class)->except(['destroy'])->parameters(['employees' => 'employee',]);
        Route::get('/leave', [LeaveLedgerController::class, 'index'])->name('leave.index');
        Route::post('/leave/bulk-earned', [LeaveLedgerController::class, 'bulkStoreEarned'])->name('leave.bulk-earned.store');
        Route::get('/leave/pending', [LeaveLedgerController::class, 'pending'])->name('leave.pending');
        Route::post('/leave/{application}/approve', [LeaveLedgerController::class, 'approve'])->name('leave.approve');
        Route::post('/leave/{application}/decline', [LeaveLedgerController::class, 'decline'])->name('leave.decline');
        Route::get('/leave/{employee}/ledger', [LeaveLedgerController::class, 'show'])->name('leave.ledger');
        Route::post('/leave/{employee}/earned', [LeaveLedgerController::class, 'storeEarned'])->name('leave.earned.store');
        Route::post('/leave/{employee}/adjust', [LeaveLedgerController::class, 'storeAdjustment'])->name('leave.adjust.store');
        Route::get('/leave/{employee}/ledger/pdf', [LeaveLedgerController::class, 'exportLedgerPdf'])->name('leave.ledger.pdf');
        Route::get('/leave/calendar', [LeaveLedgerController::class, 'calendar'])->name('leave.calendar');
        Route::get('/leave/export/pdf', [LeaveLedgerController::class, 'exportAllPdf'])->name('leave.export.pdf');
        Route::get('/leave/export/excel', [LeaveLedgerController::class, 'exportAllExcel'])->name('leave.export.excel');
        Route::get('leave/calendar/export', [LeaveLedgerController::class, 'exportMonthPdf'])->name('leave.calendar.export');
        Route::get('/pds', [PdsReviewController::class, 'index'])->name('pds.index');
        Route::get('/pds/{employee}', [PdsReviewController::class, 'show'])->name('pds.show');
        Route::post('/pds/{employee}/approve', [PdsReviewController::class, 'approve'])->name('pds.approve');
        Route::post('/pds/{employee}/return', [PdsReviewController::class, 'returnForRevision'])->name('pds.return');
        Route::get('/pds/{employee}/download', [PdsReviewController::class, 'download'])->name('pds.download');
        Route::resource('policies', HrPolicyController::class)->except(['show']);
        Route::post('/policies/{policy}/toggle-publish', [HrPolicyController::class, 'togglePublish'])->name('policies.toggle-publish');
        Route::post('/policies/{policy}/toggle-pin', [HrPolicyController::class, 'togglePin'])->name('policies.toggle-pin');
Route::get('/policies/{policy}/compliance', [HrPolicyController::class, 'compliance'])->name('policies.compliance');
    });

        // ===== EMPLOYEE ROUTES =====
        Route::get('/dashboard', fn () => view('employee.dashboard'))->name('employee.dashboard');

        Route::prefix('leave')->name('leave.')->group(function () {
            Route::get('/', [LeaveApplicationController::class, 'index'])->name('index');
            Route::post('/', [LeaveApplicationController::class, 'store'])->name('store');
        });

        // ===== MY ID ROUTES =====
        Route::get('/my-id', [MyIdController::class, 'show'])->name('my-id.show');
        Route::post('/my-id/photo', [MyIdController::class, 'updatePhoto'])->name('my-id.photo.update');

    // ===== PDS ROUTES (employee-facing) =====
    Route::prefix('pds')->name('pds.')->group(function () {
        Route::get('/', fn () => redirect()->route('pds.step', ['step' => 1]))->name('edit');
        Route::get('/step/{step}', [PdsWizardController::class, 'show'])
            ->whereNumber('step')->name('step');

        Route::put('/personal', [PdsController::class, 'updatePersonal'])->name('personal.update');
        Route::put('/family', [PdsController::class, 'updateFamily'])->name('family.update');
        Route::put('/other', [PdsController::class, 'updateOther'])->name('other.update');
        Route::put('/questionnaire', [PdsController::class, 'updateQuestionnaire'])->name('questionnaire.update');
        Route::post('/declaration', [PdsController::class, 'updateDeclaration'])->name('declaration.update');
        Route::post('/submit', [PdsController::class, 'submit'])->name('submit');

        Route::post('/children', [PdsChildController::class, 'store'])->name('children.store');
        Route::delete('/children/{child}', [PdsChildController::class, 'destroy'])->name('children.destroy');

        Route::post('/education', [PdsEducationController::class, 'store'])->name('education.store');
        Route::put('/education/{education}', [PdsEducationController::class, 'update'])->name('education.update');
        Route::delete('/education/{education}', [PdsEducationController::class, 'destroy'])->name('education.destroy');

        Route::post('/eligibility', [PdsEligibilityController::class, 'store'])->name('eligibility.store');
        Route::delete('/eligibility/{eligibility}', [PdsEligibilityController::class, 'destroy'])->name('eligibility.destroy');

        Route::post('/work', [PdsWorkExperienceController::class, 'store'])->name('work.store');
        Route::delete('/work/{work}', [PdsWorkExperienceController::class, 'destroy'])->name('work.destroy');

        Route::post('/voluntary', [PdsVoluntaryWorkController::class, 'store'])->name('voluntary.store');
        Route::delete('/voluntary/{voluntary}', [PdsVoluntaryWorkController::class, 'destroy'])->name('voluntary.destroy');

        Route::post('/training', [PdsTrainingController::class, 'store'])->name('training.store');
        Route::delete('/training/{training}', [PdsTrainingController::class, 'destroy'])->name('training.destroy');

        Route::post('/references', [PdsReferenceController::class, 'store'])->name('references.store');
        Route::delete('/references/{reference}', [PdsReferenceController::class, 'destroy'])->name('references.destroy');

        Route::get('/download', [PdsPdfController::class, 'download'])->name('download');
    });
});