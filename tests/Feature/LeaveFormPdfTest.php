<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\LeaveApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

/**
 * The uploaded leave form, readable in the browser.
 *
 * Employees fill the campus form in Excel and upload the workbook. No browser
 * previews an .xlsx, so every reviewer in the chain had to download the file
 * and open it in Excel before they could sign it, and the employee had no way
 * to see what was being read.
 */
class LeaveFormPdfTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;
    private College $cas;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->cas = College::where('code', 'CAS')->firstOrFail();
        $this->employee = User::factory()->create([
            'name' => 'Dela Cruz, Juan',
            'role' => 'employee',
            'status' => 'active',
            'college_id' => $this->cas->id,
        ]);
    }

    /** An application with a filled-in workbook attached, as employees file them. */
    private function application(array $attributes = []): LeaveApplication
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'APPLICATION FOR LEAVE');
        $sheet->setCellValue('A3', 'NAME');
        $sheet->setCellValue('B3', 'DELA CRUZ, JUAN');

        $path = tempnam(sys_get_temp_dir(), 'form') . '.xlsx';
        (new XlsxWriter($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        Storage::disk('public')->put('leave-applications/form.xlsx', file_get_contents($path));

        return LeaveApplication::create(array_merge([
            'user_id' => $this->employee->id,
            'leave_type' => 'VL',
            'date_from' => now()->addWeek(),
            'date_to' => now()->addWeek(),
            'days' => 1,
            'status' => 'submitted',
            'uploaded_at' => now(),
            'file_path' => 'leave-applications/form.xlsx',
            'file_original_name' => 'CSC Form 6.xlsx',
        ], $attributes));
    }

    private function reviewer(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => 'active',
            'college_id' => $this->cas->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Everyone in the chain
    // ------------------------------------------------------------------

    public static function reviewerRoles(): array
    {
        return [
            'dean' => ['dean', 'submitted'],
            'hr administrator' => ['admin', 'dean_approved'],
            'campus director' => ['campus_director', 'hr_approved'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('reviewerRoles')]
    public function test_every_reviewer_can_read_the_form_as_a_pdf(string $role, string $status): void
    {
        $application = $this->application(['status' => $status]);
        $reviewer = $this->reviewer($role);

        if ($role === 'dean') {
            $this->cas->update(['dean_id' => $reviewer->id]);
        }

        $response = $this->actingAs($reviewer)
            ->get(route('admin.leave.review.form.pdf', $application))
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('reviewerRoles')]
    public function test_the_review_page_shows_the_form_rather_than_offering_a_download(
        string $role,
        string $status,
    ): void {
        $application = $this->application(['status' => $status]);
        $reviewer = $this->reviewer($role);

        if ($role === 'dean') {
            $this->cas->update(['dean_id' => $reviewer->id]);
        }

        $this->actingAs($reviewer)
            ->get(route('admin.leave.review.show', $application))
            ->assertOk()
            // The converted copy is embedded, not just linked.
            ->assertSee(route('admin.leave.review.form.pdf', $application), false)
            ->assertSee('View as PDF')
            ->assertDontSee('which browsers cannot preview');
    }

    // ------------------------------------------------------------------
    // The person who filed it
    // ------------------------------------------------------------------

    public function test_the_employee_can_read_their_own_form_as_a_pdf(): void
    {
        $application = $this->application();

        $response = $this->actingAs($this->employee)
            ->get(route('leave.form.pdf', $application))
            ->assertOk();

        // The same converted copy the reviewers read.
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_the_employees_leave_page_offers_the_pdf(): void
    {
        $application = $this->application();

        $this->actingAs($this->employee)
            ->get(route('leave.index'))
            ->assertOk()
            ->assertSee(route('leave.form.pdf', $application), false)
            ->assertSee('View as PDF');
    }

    public function test_nobody_reads_somebody_elses_form(): void
    {
        $application = $this->application();
        $stranger = User::factory()->create(['role' => 'employee', 'status' => 'active']);

        $this->actingAs($stranger)
            ->get(route('leave.form.pdf', $application))
            ->assertForbidden();
    }

    public function test_a_dean_from_another_college_is_refused(): void
    {
        $application = $this->application();

        $cte = College::where('code', 'CTE')->firstOrFail();
        $otherDean = User::factory()->create([
            'role' => 'dean', 'status' => 'active', 'college_id' => $cte->id,
        ]);
        $cte->update(['dean_id' => $otherDean->id]);

        $this->actingAs($otherDean)
            ->get(route('admin.leave.review.form.pdf', $application))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Edges
    // ------------------------------------------------------------------

    public function test_the_original_upload_is_still_available(): void
    {
        $application = $this->application();
        $hr = $this->reviewer('admin');

        // Converting is for reading; the file as filed is still the record.
        $this->actingAs($hr)
            ->get(route('admin.leave.review.form', $application))
            ->assertOk();
    }

    public function test_a_missing_file_is_reported_rather_than_crashing(): void
    {
        $application = $this->application();
        Storage::disk('public')->delete('leave-applications/form.xlsx');

        $this->actingAs($this->reviewer('admin'))
            ->get(route('admin.leave.review.form.pdf', $application))
            ->assertNotFound();
    }

    public function test_the_converted_copy_is_named_for_the_employee(): void
    {
        $application = $this->application();

        $this->assertStringContainsString('Dela_Cruz', $application->formPdfName());
        $this->assertStringEndsWith('.pdf', $application->formPdfName());
    }
}
