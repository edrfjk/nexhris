<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\EmployeeLedger;
use App\Models\LeaveApplication;
use App\Models\LeaveFormTemplate;
use App\Models\LedgerTemplate;
use App\Models\PdsTemplate;
use App\Models\User;
use App\Services\EmployeeLedgerService;
use App\Services\XlsxToPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class TemplateVersioningTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'employee_number' => 'E' . fake()->unique()->numberBetween(1000, 9999),
            'name' => ucfirst($role) . ' User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
            'role' => $role,
            'status' => 'active',
            'college_id' => College::where('code', 'CAS')->value('id'),
        ]);
    }

    /** A small but genuine .xlsx, so PhpSpreadsheet can actually open it. */
    private function workbook(string $name, string $marker = 'LEDGER'): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('BALANCE');
        $sheet->setCellValue('A1', $marker);
        $sheet->setCellValue('A2', 'Vacation');
        $sheet->setCellValue('B2', 5);

        $path = tempnam(sys_get_temp_dir(), 'wb') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    // ------------------------------------------------------------------
    // Versioning
    // ------------------------------------------------------------------

    public function test_publishing_creates_a_new_version_and_retires_the_old_one(): void
    {
        Storage::fake('public');
        $hr = $this->user('admin');

        $this->actingAs($hr)->post(route('admin.leave.templates.store'), [
            'label' => 'CSC Form 6 (2020)',
            'template' => $this->workbook('form-2020.xlsx', 'V1'),
        ])->assertRedirect();

        $this->actingAs($hr)->post(route('admin.leave.templates.store'), [
            'label' => 'CSC Form 6 (2024)',
            'template' => $this->workbook('form-2024.xlsx', 'V2'),
        ])->assertRedirect();

        $this->assertSame(2, LeaveFormTemplate::count());

        $v1 = LeaveFormTemplate::where('version', 1)->firstOrFail();
        $v2 = LeaveFormTemplate::where('version', 2)->firstOrFail();

        // The old version survives, retired rather than overwritten.
        $this->assertFalse($v1->is_active);
        $this->assertNotNull($v1->superseded_at);
        $this->assertTrue($v2->is_active);
        $this->assertSame($v2->id, LeaveFormTemplate::active()->id);
    }

    public function test_an_identical_reupload_does_not_create_a_duplicate_version(): void
    {
        Storage::fake('public');
        $hr = $this->user('admin');

        // Same bytes twice.
        $path = tempnam(sys_get_temp_dir(), 'wb') . '.xlsx';
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->setCellValue('A1', 'SAME');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        foreach (['first.xlsx', 'second.xlsx'] as $name) {
            $this->actingAs($hr)->post(route('admin.leave.templates.store'), [
                'label' => 'Same workbook',
                'template' => new UploadedFile($path, $name, null, null, true),
            ]);
        }

        $this->assertSame(1, LeaveFormTemplate::count(),
            'A byte-identical re-upload should reinstate the existing version.');
    }

    public function test_a_submission_records_the_template_version_it_was_filled_on(): void
    {
        Storage::fake('public');
        $hr = $this->user('admin');
        $employee = $this->user('employee');

        $this->actingAs($hr)->post(route('admin.leave.templates.store'), [
            'label' => 'Form v1',
            'template' => $this->workbook('v1.xlsx', 'V1'),
        ]);

        $v1 = LeaveFormTemplate::active();

        $this->actingAs($employee)->post(route('leave.store'), [
            'leave_type' => 'VL',
            'date_from' => now()->addWeek()->format('Y-m-d'),
            'date_to' => now()->addWeek()->addDay()->format('Y-m-d'),
            'leave_form' => UploadedFile::fake()->create('filled.xlsx', 40),
        ])->assertRedirect();

        $application = LeaveApplication::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame($v1->id, $application->leave_form_template_id);

        // HR publishes a newer blank; the filed form still points at v1.
        $this->actingAs($hr)->post(route('admin.leave.templates.store'), [
            'label' => 'Form v2',
            'template' => $this->workbook('v2.xlsx', 'V2'),
        ]);

        $this->assertSame($v1->id, $application->fresh()->leave_form_template_id);
        $this->assertSame(2, LeaveFormTemplate::active()->version);
    }

    public function test_a_version_with_submissions_is_retired_not_deleted(): void
    {
        Storage::fake('public');
        $hr = $this->user('admin');
        $employee = $this->user('employee');

        $this->actingAs($hr)->post(route('admin.leave.templates.store'), [
            'label' => 'Form v1', 'template' => $this->workbook('v1.xlsx'),
        ]);
        $v1 = LeaveFormTemplate::active();

        $this->actingAs($employee)->post(route('leave.store'), [
            'leave_type' => 'VL',
            'date_from' => now()->addWeek()->format('Y-m-d'),
            'date_to' => now()->addWeek()->format('Y-m-d'),
            'leave_form' => UploadedFile::fake()->create('filled.xlsx', 40),
        ]);

        $this->actingAs($hr)->post(route('admin.leave.templates.store'), [
            'label' => 'Form v2', 'template' => $this->workbook('v2.xlsx', 'V2'),
        ]);

        $this->actingAs($hr)->delete(route('admin.leave.templates.destroy', $v1))->assertRedirect();

        $this->assertDatabaseHas('leave_form_templates', ['id' => $v1->id]);
        $this->assertNotNull($v1->fresh()->superseded_at);
    }

    public function test_hr_can_roll_back_to_an_earlier_version(): void
    {
        Storage::fake('public');
        $hr = $this->user('admin');

        foreach ([['v1', 'A'], ['v2', 'B']] as [$label, $marker]) {
            $this->actingAs($hr)->post(route('admin.leave.templates.store'), [
                'label' => $label, 'template' => $this->workbook("{$label}.xlsx", $marker),
            ]);
        }

        $v1 = LeaveFormTemplate::where('version', 1)->firstOrFail();

        $this->actingAs($hr)->post(route('admin.leave.templates.activate', $v1))->assertRedirect();

        $this->assertSame($v1->id, LeaveFormTemplate::active()->id);
        $this->assertFalse(LeaveFormTemplate::where('version', 2)->first()->is_active);
    }

    public function test_pds_templates_version_the_same_way(): void
    {
        Storage::fake('public');
        $hr = $this->user('admin');

        foreach ([['CS Form 212 (2017)', 'A'], ['CS Form 212 (2025)', 'B']] as [$label, $marker]) {
            $this->actingAs($hr)->post(route('admin.pds.templates.store'), [
                'label' => $label,
                'file' => $this->workbook(str_replace(' ', '-', $label) . '.xlsx', $marker),
            ])->assertRedirect();
        }

        $this->assertSame(2, PdsTemplate::count());
        $this->assertSame(2, PdsTemplate::active()->version);
        $this->assertNotNull(PdsTemplate::where('version', 1)->first()->superseded_at);
    }

    public function test_only_hr_publishes_templates(): void
    {
        Storage::fake('public');

        foreach (['dean', 'campus_director', 'employee'] as $role) {
            $this->actingAs($this->user($role))
                ->post(route('admin.leave.templates.store'), [
                    'label' => 'Nope', 'template' => $this->workbook('nope.xlsx'),
                ])->assertForbidden();
        }

        $this->assertSame(0, LeaveFormTemplate::count());
    }

    // ------------------------------------------------------------------
    // Master ledger + copy on first use
    // ------------------------------------------------------------------

    public function test_ledger_is_copied_from_the_master_on_first_use(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $hr = $this->user('admin');
        $employee = $this->user('employee');

        $this->actingAs($hr)->post(route('admin.leave.ledger-template.store'), [
            'label' => 'Master Ledger',
            'template' => $this->workbook('master.xlsx', 'MASTER'),
        ])->assertRedirect();

        $master = LedgerTemplate::active();
        $this->assertNotNull($master);

        $ledger = app(EmployeeLedgerService::class)->forEmployee($employee);

        $this->assertSame("ledgers/ledger_{$employee->id}.xlsx", $ledger->file_path);
        $this->assertTrue($ledger->exists());
        $this->assertSame($master->version, $ledger->template_version);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ledger.created']);

        // Asking again returns the same copy rather than re-seeding.
        $again = app(EmployeeLedgerService::class)->forEmployee($employee);
        $this->assertSame($ledger->id, $again->id);
        $this->assertSame(1, EmployeeLedger::count());
    }

    public function test_seeding_a_new_master_does_not_disturb_existing_ledgers(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $hr = $this->user('admin');
        $employee = $this->user('employee');

        $this->actingAs($hr)->post(route('admin.leave.ledger-template.store'), [
            'label' => 'Master v1', 'template' => $this->workbook('m1.xlsx', 'M1'),
        ]);

        $ledger = app(EmployeeLedgerService::class)->forEmployee($employee);
        $this->assertSame(1, $ledger->template_version);

        $this->actingAs($hr)->post(route('admin.leave.ledger-template.store'), [
            'label' => 'Master v2', 'template' => $this->workbook('m2.xlsx', 'M2'),
        ]);

        $ledger->refresh();
        $this->assertSame(1, $ledger->template_version, 'An existing ledger must keep its own copy.');
        $this->assertTrue($ledger->isBehindMaster());
    }

    public function test_editing_a_cell_writes_the_workbook_and_records_the_change(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $hr = $this->user('admin');
        $employee = $this->user('employee');

        $this->actingAs($hr)->post(route('admin.leave.ledger-template.store'), [
            'label' => 'Master', 'template' => $this->workbook('m.xlsx', 'MASTER'),
        ]);

        $service = app(EmployeeLedgerService::class);
        $ledger = $service->forEmployee($employee);

        $changed = $service->updateCells($ledger, 'BALANCE', ['B2' => '7.5'], $hr);

        $this->assertSame(1, $changed);

        // The real workbook now holds the new value.
        $sheet = $service->readSheet($ledger->fresh(), 'BALANCE');
        $this->assertSame('7.5', (string) $sheet['cells']['B2']['value']);

        $this->assertDatabaseHas('ledger_changes', [
            'employee_ledger_id' => $ledger->id,
            'cell' => 'B2',
            'old_value' => '5',
            'new_value' => '7.5',
            'changed_by' => $hr->id,
        ]);

        $this->assertDatabaseHas('activity_logs', ['action' => 'ledger.updated']);
        $this->assertNotNull($ledger->fresh()->last_edited_at);
    }

    public function test_an_unchanged_cell_records_nothing(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $hr = $this->user('admin');
        $employee = $this->user('employee');

        $this->actingAs($hr)->post(route('admin.leave.ledger-template.store'), [
            'label' => 'Master', 'template' => $this->workbook('m.xlsx'),
        ]);

        $service = app(EmployeeLedgerService::class);
        $ledger = $service->forEmployee($employee);

        $this->assertSame(0, $service->updateCells($ledger, 'BALANCE', ['B2' => '5'], $hr));
        $this->assertDatabaseCount('ledger_changes', 0);
    }

    public function test_a_ledger_cannot_be_created_without_a_master(): void
    {
        Storage::fake('local');
        $employee = $this->user('employee');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No master ledger template has been seeded');

        app(EmployeeLedgerService::class)->forEmployee($employee);
    }

    // ------------------------------------------------------------------
    // Conversion
    // ------------------------------------------------------------------

    public function test_the_converter_renders_a_real_workbook_as_a4_pdf(): void
    {
        $converter = app(XlsxToPdfService::class);

        if (! $converter->isAvailable()) {
            $this->markTestSkipped('LibreOffice is not installed on this machine.');
        }

        $pdf = $converter->convert(resource_path('templates/leave-ledger-template.xlsx'));

        $this->assertFileExists($pdf);

        $content = file_get_contents($pdf);
        $this->assertStringStartsWith('%PDF', $content);

        // Forced to A4 — the source workbook is authored on US Letter.
        $this->assertMatchesRegularExpression(
            '/MediaBox\s*\[\s*[\d.]+\s+[\d.]+\s+595\.\d+\s+841\.\d+/',
            $content,
            'The converted PDF is not A4.'
        );
    }

    public function test_conversion_results_are_cached(): void
    {
        $converter = app(XlsxToPdfService::class);

        if (! $converter->isAvailable()) {
            $this->markTestSkipped('LibreOffice is not installed on this machine.');
        }

        $source = resource_path('templates/leave-form-template.xlsx');

        $first = $converter->convert($source);
        $started = microtime(true);
        $second = $converter->convert($source);
        $elapsed = microtime(true) - $started;

        $this->assertSame($first, $second);
        $this->assertLessThan(1.0, $elapsed, 'A cached conversion should return immediately.');
    }
}
