<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\PdsSubmission;
use App\Models\PdsTemplate;
use App\Models\User;
use App\Notifications\PdsStatusChanged;
use App\Services\PdsSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PdsSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'employee_number' => 'E' . fake()->unique()->numberBetween(1000, 9999),
            'name' => ucfirst(str_replace('_', ' ', $role)) . ' ' . fake()->unique()->numberBetween(1, 999),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
            'role' => $role,
            'status' => 'active',
            'college_id' => College::where('code', 'CAS')->value('id'),
        ]);
    }

    /** A workbook with the given sheet names, saved as real .xlsx. */
    private function workbook(array $sheets = ['C1', 'C2', 'C3', 'C4'], string $name = 'pds.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheets as $i => $title) {
            $sheet = $spreadsheet->createSheet($i);
            $sheet->setTitle($title);
            $sheet->setCellValue('A1', 'Personal Data Sheet');
        }

        $path = tempnam(sys_get_temp_dir(), 'pds') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile($path, $name, null, null, true);
    }

    private function publishTemplate(User $hr): PdsTemplate
    {
        $this->actingAs($hr)->post(route('admin.pds.templates.store'), [
            'label' => 'CS Form 212 (Revised 2017)',
            'file' => $this->workbook(),
        ])->assertRedirect();

        return PdsTemplate::active();
    }

    // ------------------------------------------------------------------
    // Upload and validation
    // ------------------------------------------------------------------

    public function test_employee_downloads_the_published_blank(): void
    {
        Storage::fake('public');
        $hr = $this->user('admin');
        $template = $this->publishTemplate($hr);

        $this->actingAs($this->user('employee'))
            ->get(route('pds.template.download'))
            ->assertOk()
            ->assertDownload($template->original_filename);
    }

    public function test_a_workbook_with_the_wrong_sheets_is_rejected(): void
    {
        Storage::fake('public');
        $hr = $this->user('admin');
        $this->publishTemplate($hr);

        $this->actingAs($this->user('employee'))
            ->post(route('pds.upload'), ['file' => $this->workbook(['Sheet1'], 'wrong.xlsx')])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseMissing('pds_submissions', ['status' => 'draft']);
    }

    public function test_a_non_workbook_is_rejected(): void
    {
        Storage::fake('public');
        $hr = $this->user('admin');
        $this->publishTemplate($hr);

        $this->actingAs($this->user('employee'))
            ->post(route('pds.upload'), [
                'file' => UploadedFile::fake()->create('scan.pdf', 200, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_a_valid_upload_is_stored_and_converted(): void
    {
        Storage::fake('public');
        $hr = $this->user('admin');
        $template = $this->publishTemplate($hr);
        $employee = $this->user('employee');

        $this->actingAs($employee)
            ->post(route('pds.upload'), ['file' => $this->workbook()])
            ->assertRedirect();

        $submission = PdsSubmission::where('user_id', $employee->id)->firstOrFail();

        $this->assertSame('draft', $submission->status);
        $this->assertTrue($submission->workbookExists());
        $this->assertSame($template->id, $submission->pds_template_id);
        $this->assertSame(1, $submission->version);
        $this->assertDatabaseHas('activity_logs', ['action' => 'pds.uploaded']);

        // The PDF is produced at upload when LibreOffice is available.
        if (app(\App\Services\XlsxToPdfService::class)->isAvailable()) {
            $this->assertTrue($submission->pdfExists(), 'The workbook should have been converted at upload.');
            $this->assertNotNull($submission->converted_at);
        }
    }

    // ------------------------------------------------------------------
    // Review cycle
    // ------------------------------------------------------------------

    private function submitPds(User $employee, User $hr): PdsSubmission
    {
        $this->publishTemplate($hr);

        $this->actingAs($employee)->post(route('pds.upload'), ['file' => $this->workbook()]);
        $this->actingAs($employee)->post(route('pds.submit'))->assertRedirect();

        return PdsSubmission::where('user_id', $employee->id)->firstOrFail();
    }

    public function test_submitting_notifies_hr(): void
    {
        Storage::fake('public');
        Notification::fake();

        $hr = $this->user('admin');
        $employee = $this->user('employee');

        $submission = $this->submitPds($employee, $hr);

        $this->assertSame('submitted', $submission->status);
        $this->assertNotNull($submission->submitted_at);

        Notification::assertSentTo($hr, PdsStatusChanged::class);
        $this->assertDatabaseHas('activity_logs', ['action' => 'pds.submitted']);
    }

    public function test_hr_approves_and_the_employee_is_notified(): void
    {
        Storage::fake('public');
        $hr = $this->user('admin');
        $employee = $this->user('employee');

        $submission = $this->submitPds($employee, $hr);

        Notification::fake();

        $this->actingAs($hr)->post(route('admin.pds.approve', $employee))->assertRedirect();

        $submission->refresh();
        $this->assertSame('approved', $submission->status);
        $this->assertSame($hr->id, $submission->reviewed_by);
        $this->assertNotNull($submission->reviewed_at);

        Notification::assertSentTo($employee, PdsStatusChanged::class);
        $this->assertDatabaseHas('activity_logs', ['action' => 'pds.approved']);
    }

    public function test_returning_requires_remarks_and_notifies_the_employee(): void
    {
        Storage::fake('public');
        $hr = $this->user('admin');
        $employee = $this->user('employee');

        $this->submitPds($employee, $hr);

        $this->actingAs($hr)
            ->post(route('admin.pds.return', $employee), ['return_remarks' => ''])
            ->assertSessionHasErrors('return_remarks');

        Notification::fake();

        $this->actingAs($hr)->post(route('admin.pds.return', $employee), [
            'return_remarks' => 'Section IV is blank.',
        ])->assertRedirect();

        $submission = PdsSubmission::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame('returned', $submission->status);
        $this->assertSame('Section IV is blank.', $submission->return_remarks);

        Notification::assertSentTo($employee, PdsStatusChanged::class);
    }

    public function test_a_submitted_pds_cannot_be_re_uploaded_until_it_is_returned(): void
    {
        Storage::fake('public');
        $hr = $this->user('admin');
        $employee = $this->user('employee');

        $this->submitPds($employee, $hr);

        $this->actingAs($employee)
            ->post(route('pds.upload'), ['file' => $this->workbook()])
            ->assertStatus(422);
    }

    public function test_re_uploading_after_a_return_archives_the_previous_version(): void
    {
        Storage::fake('public');
        $hr = $this->user('admin');
        $employee = $this->user('employee');

        $submission = $this->submitPds($employee, $hr);
        $firstPath = $submission->file_path;

        $this->actingAs($hr)->post(route('admin.pds.return', $employee), [
            'return_remarks' => 'Please complete Section IV.',
        ]);

        $this->actingAs($employee)
            ->post(route('pds.upload'), ['file' => $this->workbook()])
            ->assertRedirect();

        $submission->refresh();

        // The corrected upload is v2, and v1 is preserved with its verdict.
        $this->assertSame(2, $submission->version);
        $this->assertSame('draft', $submission->status);
        $this->assertNull($submission->return_remarks);

        $this->assertDatabaseHas('pds_submission_revisions', [
            'pds_submission_id' => $submission->id,
            'version' => 1,
            'file_path' => $firstPath,
            'outcome' => 'returned',
            'remarks' => 'Please complete Section IV.',
        ]);
    }

    public function test_only_a_submitted_pds_can_be_approved(): void
    {
        Storage::fake('public');
        $hr = $this->user('admin');
        $employee = $this->user('employee');

        $this->publishTemplate($hr);
        $this->actingAs($employee)->post(route('pds.upload'), ['file' => $this->workbook()]);

        // Still a draft — approving it would skip the employee's own submit.
        $this->actingAs($hr)
            ->post(route('admin.pds.approve', $employee))
            ->assertStatus(422);
    }

    public function test_only_hr_reviews_the_pds(): void
    {
        Storage::fake('public');
        $hr = $this->user('admin');
        $employee = $this->user('employee');

        $this->submitPds($employee, $hr);

        foreach (['dean', 'campus_director'] as $role) {
            $this->actingAs($this->user($role))
                ->post(route('admin.pds.approve', $employee))
                ->assertForbidden();
        }
    }

    // ------------------------------------------------------------------
    // Deans and the Campus Director file a PDS too
    // ------------------------------------------------------------------

    public static function everyRole(): array
    {
        return [
            'employee' => ['employee'],
            'dean' => ['dean'],
            'campus director' => ['campus_director'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('everyRole')]
    public function test_every_role_can_file_a_pds(string $role): void
    {
        Storage::fake('public');
        $hr = $this->user('admin');
        $applicant = $this->user($role);

        $submission = $this->submitPds($applicant, $hr);

        $this->assertSame('submitted', $submission->status);

        // And they appear in HR's review queue.
        $this->actingAs($hr)
            ->get(route('admin.pds.index'))
            ->assertOk()
            ->assertSee($applicant->name);
    }

    public function test_the_employee_can_export_their_own_pds_as_pdf(): void
    {
        Storage::fake('public');
        $hr = $this->user('admin');
        $employee = $this->user('employee');

        $this->submitPds($employee, $hr);

        $response = $this->actingAs($employee)->get(route('pds.export'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_hr_can_preview_the_submitted_pds(): void
    {
        Storage::fake('public');
        $hr = $this->user('admin');
        $employee = $this->user('employee');

        $this->submitPds($employee, $hr);

        $this->actingAs($hr)
            ->get(route('admin.pds.download', $employee))
            ->assertOk();

        $this->actingAs($hr)
            ->get(route('admin.pds.workbook', $employee))
            ->assertOk();
    }

    public function test_a_submission_records_the_template_version_used(): void
    {
        Storage::fake('public');
        $hr = $this->user('admin');
        $employee = $this->user('employee');

        $v1 = $this->publishTemplate($hr);
        $this->actingAs($employee)->post(route('pds.upload'), ['file' => $this->workbook()]);

        $submission = PdsSubmission::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame($v1->id, $submission->pds_template_id);

        // A newer blank does not rewrite what was already filed.
        $this->actingAs($hr)->post(route('admin.pds.templates.store'), [
            'label' => 'CS Form 212 (2025)',
            'file' => $this->workbook(['C1', 'C2', 'C3', 'C4'], 'v2.xlsx'),
        ]);

        $this->assertSame($v1->id, $submission->fresh()->pds_template_id);
    }
}
