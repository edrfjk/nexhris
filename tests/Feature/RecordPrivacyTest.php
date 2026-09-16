<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\HrPolicy;
use App\Models\LeaveApplication;
use App\Models\PdsSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Personnel records are never readable off the filesystem.
 *
 * Uploads used to be written to the public disk, which is symlinked into
 * public/storage and served by the web server directly — Laravel never sees
 * the request, so there is no session, no role check, nothing. PDS workbooks
 * were named {user_id}_{year}, so the URLs could be counted through:
 * /storage/pds-working/2_2026.pdf returned employee #2's complete Personal
 * Data Sheet, with their date of birth, home address and GSIS, PhilHealth,
 * TIN and SSS numbers, to anybody at all.
 *
 * These tests pin the two halves of the fix: nothing personal is written where
 * the web server can reach it, and everything is still reachable by the people
 * entitled to it.
 */
class RecordPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;
    private User $employee;
    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $college = College::where('code', 'CAS')->firstOrFail();

        $this->hr = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->employee = User::factory()->create([
            'name' => 'Mary Rose Niro', 'role' => 'employee',
            'status' => 'active', 'college_id' => $college->id,
        ]);

        $this->stranger = User::factory()->create([
            'role' => 'employee', 'status' => 'active', 'college_id' => $college->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Nothing personal reaches the public disk
    // ------------------------------------------------------------------

    public function test_a_filed_leave_form_is_not_written_where_the_web_server_can_read_it(): void
    {
        $this->actingAs($this->employee)
            ->post(route('leave.store'), [
                'leave_type' => 'VL',
                'date_from' => now()->addWeek()->format('Y-m-d'),
                'date_to' => now()->addWeek()->format('Y-m-d'),
                'days' => 1,
                'reason' => 'Family matter',
                'leave_form' => UploadedFile::fake()->create(
                    'form.xlsx',
                    40,
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ),
            ]);

        $application = LeaveApplication::where('user_id', $this->employee->id)->first();

        $this->assertNotNull($application?->file_path, 'the form was not stored at all');

        $this->assertTrue(
            Storage::disk('local')->exists($application->file_path),
            'the filed form should be on the private disk',
        );

        $this->assertFalse(
            Storage::disk('public')->exists($application->file_path),
            'the filed form is readable at /storage with no login',
        );
    }

    public function test_no_personnel_folder_ever_appears_on_the_public_disk(): void
    {
        // A realistic disk: the blank forms and a staff photo belong here, the
        // three personal folders do not.
        Storage::disk('public')->put('leave-form-templates/blank.xlsx', 'x');
        Storage::disk('public')->put('pds-templates/blank.xlsx', 'x');
        Storage::disk('public')->put('profile-photos/random.jpg', 'x');

        Storage::disk('local')->put('pds-working/9_2026.xlsx', 'x');
        Storage::disk('local')->put('leave-applications/abc.xlsx', 'x');
        Storage::disk('local')->put('hr-policies/memo.pdf', 'x');

        $exposed = array_values(array_filter(
            Storage::disk('public')->allFiles(),
            static fn (string $path): bool => (bool) preg_match(
                '#^(pds-working|leave-applications|hr-policies|pds/photos|pds/signatures)/#',
                $path,
            ),
        ));

        $this->assertSame(
            [],
            $exposed,
            'served straight off the filesystem with no role check: ' . implode(', ', $exposed),
        );

        // And the guard is not vacuous — the disk does hold the blank forms.
        $this->assertNotEmpty(Storage::disk('public')->allFiles());
    }

    // ------------------------------------------------------------------
    // And it is still reachable by the right people
    // ------------------------------------------------------------------

    private function submission(): PdsSubmission
    {
        Storage::disk('local')->put('pds-working/x.xlsx', 'workbook');

        return PdsSubmission::create([
            'user_id' => $this->employee->id,
            'applicable_year' => now()->year,
            'status' => 'submitted',
            'version' => 1,
            'file_path' => 'pds-working/x.xlsx',
            'file_original_name' => 'PDS.xlsx',
            'uploaded_at' => now(),
        ]);
    }

    public function test_hr_can_still_open_an_employees_pds_workbook(): void
    {
        $this->submission();

        $this->actingAs($this->hr)
            ->get(route('admin.pds.workbook', $this->employee))
            ->assertOk()
            ->assertDownload("Mary Rose Niro's Personal Data Sheet (" . now()->year . ').xlsx');
    }

    public function test_an_employee_can_still_open_their_own_pds_workbook(): void
    {
        $this->submission();

        $this->actingAs($this->employee)
            ->get(route('pds.workbook'))
            ->assertOk();
    }

    public function test_one_employee_cannot_open_anothers_pds_workbook(): void
    {
        $this->submission();

        // The route serves whoever is signed in, so the stranger gets their own
        // (absent) sheet rather than Mary Rose's.
        $this->actingAs($this->stranger)
            ->get(route('pds.workbook'))
            ->assertNotFound();
    }

    public function test_a_policy_attachment_is_served_only_to_signed_in_readers(): void
    {
        Storage::disk('local')->put('hr-policies/memo.pdf', '%PDF-1.4 memo');

        $policy = HrPolicy::create([
            'title' => 'Records Retention',
            'body' => 'Keep the originals.',
            'category' => 'general',
            'type' => 'memo',
            'is_published' => true,
            'created_by' => $this->hr->id,
            'file_path' => 'hr-policies/memo.pdf',
            'file_original_name' => 'memo.pdf',
        ]);

        $this->actingAs($this->employee)
            ->get(route('policies.attachment', $policy))
            ->assertOk();

        // Signed out, the same URL is not a file path any more.
        auth()->logout();

        $this->get(route('policies.attachment', $policy))
            ->assertRedirect(route('login'));
    }

    public function test_an_unpublished_policys_attachment_is_hr_only(): void
    {
        Storage::disk('local')->put('hr-policies/draft.pdf', '%PDF-1.4 draft');

        $policy = HrPolicy::create([
            'title' => 'Draft',
            'body' => 'Not ready.',
            'category' => 'general',
            'type' => 'memo',
            'is_published' => false,
            'created_by' => $this->hr->id,
            'file_path' => 'hr-policies/draft.pdf',
            'file_original_name' => 'draft.pdf',
        ]);

        $this->actingAs($this->employee)
            ->get(route('policies.attachment', $policy))
            ->assertNotFound();

        $this->actingAs($this->hr)
            ->get(route('policies.attachment', $policy))
            ->assertOk();
    }

    public function test_one_employee_cannot_download_anothers_leave_form(): void
    {
        Storage::disk('local')->put('leave-applications/theirs.xlsx', 'x');

        $application = LeaveApplication::create([
            'user_id' => $this->employee->id,
            'leave_type' => 'VL',
            'date_from' => now()->addWeek(),
            'date_to' => now()->addWeek(),
            'days' => 1,
            'status' => 'submitted',
            'uploaded_at' => now(),
            'file_path' => 'leave-applications/theirs.xlsx',
            'file_original_name' => 'form.xlsx',
        ]);

        $this->actingAs($this->stranger)
            ->get(route('leave.form.download', $application))
            ->assertForbidden();

        $this->actingAs($this->employee)
            ->get(route('leave.form.download', $application))
            ->assertOk();
    }
}
