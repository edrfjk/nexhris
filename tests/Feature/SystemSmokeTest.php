<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\College;
use App\Models\HrPolicy;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveFormTemplate;
use App\Models\PdsSubmission;
use App\Models\PdsTemplate;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Every page of the system, opened by every role.
 *
 * Builds one realistic campus — templates published, a leave form filed, a
 * PDS filled in on screen, a policy and an announcement — then requests every
 * GET route as the HR Administrator, a Dean, the Campus Director and an
 * Employee. No page may fail with a server error for anyone, and an Employee
 * may not be let into any administrative page.
 */
class SystemSmokeTest extends TestCase
{
    use RefreshDatabase;

    /** Routes that are not pages of the system. */
    private const SKIP = ['storage.local', 'storage.local.upload'];

    private array $users = [];

    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $college = College::where('code', 'CAS')->firstOrFail();

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'name' => 'HR Admin']);
        $dean = User::factory()->create(['role' => 'dean', 'status' => 'active', 'college_id' => $college->id]);
        $director = User::factory()->create(['role' => 'campus_director', 'status' => 'active']);
        $employee = User::factory()->create([
            'role' => 'employee', 'status' => 'active', 'college_id' => $college->id,
            'first_name' => 'Maria', 'last_name' => 'Dela Cruz', 'employee_number' => 'ISPSC-041',
        ]);
        $college->update(['dean_id' => $dean->id]);

        foreach ([$dean, $director, $employee] as $person) {
            LeaveBalance::create(['user_id' => $person->id, 'vl_balance' => 10, 'sl_balance' => 8, 'service_balance' => 2]);
        }

        // The official forms, published.
        Storage::disk('public')->put('pds-templates/pds.xlsx', file_get_contents(resource_path('templates/CS-Form-212-2026.xlsx')));
        $pdsTemplate = PdsTemplate::create([
            'label' => 'CS Form 212 (Revised 2026)', 'version' => 1, 'file_path' => 'pds-templates/pds.xlsx',
            'original_filename' => 'CS-Form-212-2026.xlsx', 'checksum' => 'a', 'is_active' => true, 'uploaded_by' => $admin->id,
        ]);

        Storage::disk('public')->put('leave-form-templates/leave.xlsx', file_get_contents(resource_path('templates/CS-Form-6-2020.xlsx')));
        $leaveTemplate = LeaveFormTemplate::create([
            'label' => 'CS Form 6', 'version' => 1, 'file_path' => 'leave-form-templates/leave.xlsx',
            'original_filename' => 'leave-form-template.xlsx', 'checksum' => 'b', 'is_active' => true, 'uploaded_by' => $admin->id,
        ]);

        // A leave form waiting on the Dean.
        Storage::disk('local')->put('leave-applications/form.xlsx', file_get_contents(resource_path('templates/CS-Form-6-2020.xlsx')));
        $application = LeaveApplication::create([
            'user_id' => $employee->id, 'leave_form_template_id' => $leaveTemplate->id,
            'leave_type' => 'VL', 'date_from' => now()->addWeek(), 'date_to' => now()->addWeek()->addDay(),
            'days' => 2, 'reason' => 'Family matter', 'status' => 'submitted',
            'file_path' => 'leave-applications/form.xlsx', 'file_original_name' => 'form.xlsx', 'uploaded_at' => now(),
        ]);

        // A PDS filled in on screen and submitted.
        $this->actingAs($employee)->put(route('pds.form.update', 'personal'), [
            'surname' => 'DELA CRUZ', 'first_name' => 'MARIA', 'date_of_birth' => '1988-03-14',
            'sex' => 'female', 'civil_status' => 'married', 'citizenship' => 'filipino',
        ]);
        $this->actingAs($employee)->put(route('pds.form.update', 'questions'),
            array_fill_keys(array_keys(\App\Support\Pds\PdsFormSchema::QUESTIONS), 'no'));
        $this->actingAs($employee)->post(route('pds.form.submit'));
        auth()->logout();

        $policy = HrPolicy::create([
            'title' => 'Office Hours', 'category' => array_key_first(config('policy_categories') ?: ['general' => 'General']) ?? 'general',
            'type' => 'text', 'body' => '<p>Office hours are 8:00 to 5:00.</p>',
            'is_published' => true, 'published_at' => now(), 'effective_date' => now()->subDay(),
            'requires_acknowledgment' => true, 'created_by' => $admin->id,
        ]);

        $announcement = Announcement::create([
            'title' => 'Campus notice', 'body' => 'Welcome back.', 'category' => 'general',
            'is_published' => true, 'published_at' => now(), 'posted_by' => $admin->id,
        ]);
        $employee->notify(new AnnouncementPosted($announcement));

        $this->users = compact('admin', 'dean', 'director', 'employee');
        $this->ids = [
            'employee' => $employee->id,
            'application' => $application->id,
            'policy' => $policy->id,
            'pdsTemplate' => $pdsTemplate->id,
            'leaveTemplate' => $leaveTemplate->id,
            'token' => $employee->fresh()->verification_token,
            'notification' => $employee->notifications()->value('id'),
        ];
    }

    public function test_no_page_fails_for_any_role(): void
    {
        $failures = [];
        $visited = 0;

        foreach ($this->users as $role => $user) {
            foreach ($this->pages() as $name => $url) {
                $this->actingAs($user);
                $status = $this->get($url)->getStatusCode();
                $visited++;

                if ($status >= 500) {
                    $failures[] = "{$role} {$name} ({$url}) → {$status}";
                }

                // SMOKE_REPORT=1 prints the whole role × page matrix.
                if (getenv('SMOKE_REPORT')) {
                    fwrite(STDERR, sprintf("%-9s %3d  %s\n", $role, $status, $name));
                }
            }
        }

        $this->assertSame([], $failures, "Pages that failed:\n" . implode("\n", $failures));
        $this->assertGreaterThan(200, $visited);
    }

    public function test_an_employee_is_kept_out_of_every_administrative_page(): void
    {
        $leaks = [];

        foreach ($this->pages() as $name => $url) {
            if (! str_starts_with($name, 'admin.')) {
                continue;
            }

            $this->actingAs($this->users['employee']);
            $status = $this->get($url)->getStatusCode();

            if ($status === 200) {
                $leaks[] = "{$name} ({$url})";
            }
        }

        $this->assertSame([], $leaks, "Employee reached:\n" . implode("\n", $leaks));
    }

    /** @return array<string, string> route name => URL for every GET page */
    private function pages(): array
    {
        $pages = [];

        /** @var Route $route */
        foreach (Router::getRoutes() as $route) {
            $name = $route->getName();

            if (! $name || ! in_array('GET', $route->methods(), true) || in_array($name, self::SKIP, true)) {
                continue;
            }

            $parameters = [];

            foreach ($route->parameterNames() as $parameter) {
                $value = match ($parameter) {
                    'employee' => $this->ids['employee'],
                    'application' => $this->ids['application'],
                    'policy' => $this->ids['policy'],
                    'template' => str_contains($name, 'pds.') ? $this->ids['pdsTemplate'] : $this->ids['leaveTemplate'],
                    'token' => $this->ids['token'],
                    'id' => $this->ids['notification'],
                    'section' => 'personal',
                    'filename' => null,
                    default => null,
                };

                if ($value !== null) {
                    $parameters[$parameter] = $value;
                }
            }

            try {
                $pages[$name] = route($name, $parameters);
            } catch (\Throwable) {
                // A parameter this crawl does not know how to fill; left for
                // the dedicated feature tests.
            }
        }

        return $pages;
    }
}
