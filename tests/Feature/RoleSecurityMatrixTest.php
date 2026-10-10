<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveLedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 6: the role boundaries, probed directly. Most HR-only actions sit in
 * the shared "admin" route group (HR, Dean, Campus Director) and are guarded
 * inside the controller, so each is exercised as a Dean and a Campus Director
 * as well as an employee. In-memory SQLite and faked disks only.
 */
class RoleSecurityMatrixTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private User $dean;

    private User $director;

    private User $employee;

    private User $other;

    private User $outsider;

    private LeaveApplication $leave;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Notification::fake();

        $cas = College::firstOrCreate(['code' => 'CAS'], ['name' => 'College of Arts and Sciences']);
        $cte = College::firstOrCreate(['code' => 'CTE'], ['name' => 'College of Teacher Education']);
        $make = fn (string $role, array $extra = []) => User::factory()->create(array_merge(['role' => $role, 'status' => 'active'], $extra));

        $this->hr = $make('admin');
        $this->dean = $make('dean', ['college_id' => $cas->id]);
        $cas->update(['dean_id' => $this->dean->id]);
        $this->director = $make('campus_director');
        $this->employee = $make('employee', ['college_id' => $cas->id]);
        $this->other = $make('employee', ['college_id' => $cas->id]);
        $this->outsider = $make('employee', ['college_id' => $cte->id]);

        foreach ([$this->employee, $this->other, $this->outsider] as $u) {
            LeaveBalance::create(['user_id' => $u->id, 'vl_balance' => 10, 'sl_balance' => 10]);
        }

        $this->leave = LeaveApplication::create([
            'user_id' => $this->employee->id, 'leave_type' => 'VL', 'date_from' => '2026-11-16', 'date_to' => '2026-11-17',
            'days' => 2, 'status' => 'dean_returned', 'dean_status' => 'returned', 'filing_method' => 'online',
            'form_data' => ['details' => ['location' => 'within'], 'commutation' => 'not_requested', 'attachments' => [['path' => 'leave-attachments/1/a.pdf', 'name' => 'a.pdf', 'size' => 1]]],
        ]);
        Storage::disk('local')->put('leave-attachments/1/a.pdf', '%PDF-1.4');
    }

    public function test_hr_only_actions_in_the_shared_reviewer_group_refuse_deans_and_the_director(): void
    {
        $this->actingAs($this->hr);
        $entry = app(\App\Services\LeaveLedgerService::class)->postEntry(
            employee: $this->employee, periodFrom: '2026-01-01', periodTo: '2026-01-01', type: 'adjustment', remarks: 'fwd', vlEarned: 1,
        );
        $ledgerPost = ['period_from' => '2026-09-01', 'period_to' => '2026-09-30', 'vl_earned' => 1.25, 'sl_earned' => 1.25, 'ledger' => 'leave'];

        foreach ([$this->dean, $this->director] as $reviewer) {
            $this->actingAs($reviewer);
            $this->post(route('admin.leave.earned.store', $this->employee), $ledgerPost)->assertForbidden();
            $this->post(route('admin.leave.adjust.store', $this->employee), $ledgerPost + ['remarks' => 'x'])->assertForbidden();
            $this->post(route('admin.leave.bulk-earned.store'), $ledgerPost + ['employee_ids' => [$this->employee->id]])->assertForbidden();
            $this->put(route('admin.leave.ledger.entry.update', $entry), ['remarks' => 'tampered'])->assertForbidden();
            $this->delete(route('admin.leave.ledger.entry.destroy', $entry))->assertForbidden();
            $this->get(route('admin.leave.templates.index'))->assertForbidden();
            $this->post(route('admin.leave.templates.store'), ['label' => 'x'])->assertForbidden();
            $this->post(route('admin.pds.templates.store'), ['label' => 'x'])->assertForbidden();
            $this->get(route('admin.pds.index'))->assertForbidden();
            $this->get(route('admin.holidays.index'))->assertForbidden();
            $this->get(route('admin.activity-logs.index'))->assertForbidden();
            $this->get(route('admin.employees.create'))->assertForbidden();
            $this->patch(route('admin.employees.status.update', $this->employee), ['status' => 'inactive'])->assertForbidden();
        }

        $this->assertSame('active', $this->employee->fresh()->status);
        $this->assertSame(1, LeaveLedgerEntry::count());
    }

    public function test_employees_cannot_reach_any_reviewer_or_hr_screen(): void
    {
        $this->actingAs($this->other);

        foreach (['admin.dashboard', 'admin.employees.index', 'admin.leave.index', 'admin.leave.review.index', 'admin.leave.calendar', 'admin.pds.index', 'admin.holidays.index'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
        $this->get(route('admin.leave.review.show', $this->leave))->assertForbidden();
        $this->get(route('admin.leave.ledger', $this->employee))->assertForbidden();
        $this->post(route('admin.leave.review.approve', $this->leave))->assertForbidden();
    }

    public function test_an_employee_cannot_open_or_change_another_employees_leave(): void
    {
        $this->actingAs($this->other);

        $this->get(route('leave.edit', $this->leave))->assertForbidden();
        $this->get(route('leave.attachment', [$this->leave, 0]))->assertForbidden();
        $this->get(route('leave.form.download', $this->leave))->assertForbidden();
        $this->get(route('leave.form.pdf', $this->leave))->assertForbidden();
        $this->get(route('leave.print', $this->leave))->assertForbidden();
        $this->post(route('leave.update', $this->leave), ['leave_type' => 'VL', 'commutation' => 'not_requested'])->assertForbidden();
        $this->post(route('leave.resubmit', $this->leave), ['leave_form' => UploadedFile::fake()->create('f.pdf', 10, 'application/pdf')])->assertForbidden();

        $this->assertSame('dean_returned', $this->leave->fresh()->status);
    }

    public function test_a_dean_cannot_read_another_colleges_records(): void
    {
        $outside = LeaveApplication::create([
            'user_id' => $this->outsider->id, 'leave_type' => 'VL', 'date_from' => '2026-11-16', 'date_to' => '2026-11-17',
            'days' => 2, 'status' => 'submitted', 'filing_method' => 'online',
        ]);
        $this->actingAs($this->dean);

        $this->get(route('admin.employees.show', $this->outsider))->assertForbidden();
        $this->get(route('admin.leave.ledger', $this->outsider))->assertForbidden();
        $this->get(route('admin.leave.ledger.pdf', $this->outsider))->assertForbidden();
        $this->get(route('admin.leave.review.show', $outside))->assertForbidden();
        $this->get(route('admin.leave.review.print', $outside))->assertForbidden();
        $this->post(route('admin.leave.review.approve', $outside))->assertForbidden();
        $this->get(route('admin.employees.index'))->assertOk()->assertDontSee($this->outsider->name);
    }

    public function test_profile_updates_cannot_raise_privileges_or_move_colleges(): void
    {
        $this->actingAs($this->employee)->put(route('profile.update'), [
            'first_name' => 'Juan', 'last_name' => 'Cruz', 'name' => 'Cruz, Juan', 'email' => 'juan@example.test',
            'role' => 'admin', 'status' => 'active', 'college_id' => $this->outsider->college_id,
            'employee_number' => 'HACK-1', 'verification_token' => 'chosen',
        ]);

        $fresh = $this->employee->fresh();
        $this->assertSame('employee', $fresh->role);
        $this->assertSame($this->employee->college_id, $fresh->college_id);
        $this->assertSame($this->employee->employee_number, $fresh->employee_number);
        $this->assertNotSame('chosen', $fresh->verification_token);
    }

    public function test_unsafe_uploads_are_refused(): void
    {
        $this->actingAs($this->employee);

        // A script renamed as a picture, and an SVG (which can carry script).
        $this->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->create('me.php.jpg', 5, 'application/x-php')])->assertSessionHasErrors('photo');
        $this->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->create('me.svg', 5, 'image/svg+xml')])->assertSessionHasErrors('photo');
        $this->post(route('leave.store'), ['leave_type' => 'VL', 'date_from' => '2026-11-16', 'date_to' => '2026-11-17',
            'leave_form' => UploadedFile::fake()->create('form.exe', 5, 'application/x-msdownload')])->assertSessionHasErrors('leave_form');
        $this->post(route('pds.upload'), ['file' => UploadedFile::fake()->create('pds.pdf', 5, 'application/pdf')])->assertSessionHasErrors('file');
    }

    public function test_notifications_belong_to_their_owner(): void
    {
        // Notification::fake() stops notify() from storing, so write the row.
        $id = (string) \Illuminate\Support\Str::uuid();
        $this->other->notifications()->create(['id' => $id, 'type' => 'test', 'data' => ['headline' => 'h']]);

        $this->actingAs($this->employee)->post(route('notifications.read', ['id' => $id]))->assertNotFound();
        $this->assertNull($this->other->notifications()->first()->read_at);
    }

    public function test_the_public_id_page_shows_no_contact_details(): void
    {
        $this->get(route('verify.show', $this->employee->verification_token))
            ->assertOk()
            ->assertSee($this->employee->name)
            ->assertDontSee($this->employee->email)
            ->assertDontSee($this->employee->employee_number);
        $this->get(route('verify.show', 'not-a-real-token'))->assertNotFound();
    }

    public function test_an_inactive_account_is_signed_out_on_its_next_request(): void
    {
        $this->employee->update(['status' => 'inactive']);

        $this->actingAs($this->employee)->get(route('employee.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_state_changing_routes_are_protected_by_csrf(): void
    {
        // Laravel skips the token check while running tests, so assert that
        // the middleware is in the web stack and every form carries @csrf.
        $web = app(\Illuminate\Foundation\Http\Kernel::class)->getMiddlewareGroups()['web'];
        $this->assertContains(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, $web);

        $missing = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $html = file_get_contents($file->getPathname());
            preg_match_all('/<form\b[^>]*>(.*?)<\/form>/si', $html, $forms, PREG_SET_ORDER);
            foreach ($forms as $form) {
                $isPost = preg_match('/method="(post|POST)"/', $form[0]);
                if ($isPost && ! str_contains($form[1], '@csrf') && ! str_contains($form[1], 'csrf_field') && ! str_contains($form[1], '_token')) {
                    $missing[] = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }
        $this->assertSame([], array_values(array_unique($missing)), 'POST forms without a CSRF token');
    }
}
