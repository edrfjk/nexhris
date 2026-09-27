<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\LeaveApplication;
use App\Models\User;
use App\Services\LeaveWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Places where one rule was enforced differently depending on the screen.
 *
 * A Dean with no college "covers nobody" according to deanCoversEmployee(),
 * but where('college_id', null) compiles to IS NULL, so the list scope and
 * two direct comparisons handed that Dean every unassigned employee instead.
 */
class ConsistencyAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;
    private User $collegelessDean;
    private User $unassigned;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->collegelessDean = User::factory()->create([
            'role' => 'dean', 'status' => 'active', 'college_id' => null,
        ]);
        $this->unassigned = User::factory()->create([
            'name' => 'Unassigned Person', 'role' => 'employee',
            'status' => 'active', 'college_id' => null,
        ]);
    }

    // ------------------------------------------------------------------
    // A Dean with no college sees nobody, on every screen
    // ------------------------------------------------------------------

    public function test_visible_to_scope_gives_a_collegeless_dean_nobody(): void
    {
        $this->assertSame(0, User::personnel()->visibleTo($this->collegelessDean)->count());
    }

    public function test_a_collegeless_dean_cannot_open_an_unassigned_employee(): void
    {
        $this->actingAs($this->collegelessDean)
            ->get(route('admin.employees.show', $this->unassigned))
            ->assertForbidden();
    }

    public function test_a_collegeless_dean_cannot_print_an_unassigned_ledger_card(): void
    {
        $this->actingAs($this->collegelessDean)
            ->get(route('admin.leave.ledger.pdf', $this->unassigned))
            ->assertForbidden();
    }

    public function test_a_collegeless_dean_has_an_empty_review_queue(): void
    {
        LeaveApplication::create([
            'user_id' => $this->unassigned->id,
            'leave_type' => 'VL',
            'date_from' => now()->addWeek(),
            'date_to' => now()->addWeek(),
            'days' => 1,
            'status' => 'submitted',
        ]);

        $this->assertSame(0, app(LeaveWorkflowService::class)->queueFor($this->collegelessDean)->count());
    }

    public function test_a_dean_still_sees_their_own_college(): void
    {
        $college = College::where('code', 'CAS')->firstOrFail();
        $dean = User::factory()->create(['role' => 'dean', 'status' => 'active', 'college_id' => $college->id]);
        $member = User::factory()->create(['role' => 'employee', 'status' => 'active', 'college_id' => $college->id]);

        $this->assertTrue(User::personnel()->visibleTo($dean)->whereKey($member->id)->exists());
        $this->actingAs($dean)->get(route('admin.employees.show', $member))->assertOk();
    }

    // ------------------------------------------------------------------
    // The directory manages personnel, not HR accounts
    // ------------------------------------------------------------------

    public function test_hr_accounts_cannot_be_edited_through_the_employee_directory(): void
    {
        $otherHr = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($this->hr)->get(route('admin.employees.edit', $otherHr))->assertNotFound();

        $this->actingAs($this->hr)
            ->put(route('admin.employees.update', $otherHr), [
                'employee_number' => 'X-1', 'email' => $otherHr->email, 'role' => 'employee',
            ])
            ->assertNotFound();

        $this->actingAs($this->hr)
            ->patch(route('admin.employees.status.update', $this->hr), ['status' => 'inactive'])
            ->assertNotFound();

        $this->assertSame('admin', $otherHr->fresh()->role);
        $this->assertSame('active', $this->hr->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Deactivation takes effect immediately
    // ------------------------------------------------------------------

    public function test_a_deactivated_user_is_signed_out_on_their_next_request(): void
    {
        $this->actingAs($this->unassigned)->get(route('employee.dashboard'))->assertOk();

        $this->unassigned->update(['status' => 'inactive']);

        $this->get(route('employee.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // ------------------------------------------------------------------
    // Error pages carry the reason
    // ------------------------------------------------------------------

    public function test_a_specific_403_reason_reaches_the_page(): void
    {
        $this->actingAs($this->collegelessDean)
            ->post(route('admin.leave.bulk-earned.store'), [
                'period_from' => '2026-01-01', 'period_to' => '2026-01-31', 'vl_earned' => 1.25,
            ])
            ->assertForbidden()
            ->assertSee('Only HR can post leave credits.');
    }

    public function test_a_422_is_rendered_as_a_branded_page_with_its_reason(): void
    {
        // As in production. The debug page quotes source lines, including
        // this test's own strings, so it would pass without the view.
        config(['app.debug' => false]);

        $this->app['router']->get('/__422', fn () => abort(422, 'This leave has already been posted to the ledger.'));

        $this->get('/__422')
            ->assertStatus(422)
            ->assertSee('That could not be done')
            ->assertSee('This leave has already been posted to the ledger.');
    }
}
