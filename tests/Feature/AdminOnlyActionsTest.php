<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\HrPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Only HR administers the system.
 *
 * The middleware guarding /admin is named "admin" but admits admin, dean and
 * campus_director alike — it is a "is this a reviewer" check wearing an
 * administrator's name. Every controller under that prefix therefore has to
 * state its own rule, and the ones that forgot let a Dean reach screens that
 * create staff accounts and publish policies.
 *
 * A Dean setting a role is the sharp end of it: the role decides who signs
 * whose leave, so being able to write it is being able to grant yourself HR.
 */
class AdminOnlyActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $dean;
    private User $director;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $college = College::where('code', 'CAS')->firstOrFail();

        $this->dean = User::factory()->create([
            'role' => 'dean', 'status' => 'active', 'college_id' => $college->id,
        ]);

        $this->director = User::factory()->create([
            'role' => 'campus_director', 'status' => 'active',
        ]);

        $this->employee = User::factory()->create([
            'role' => 'employee', 'status' => 'active', 'college_id' => $college->id,
        ]);
    }

    /** @return array<string, array{string}> */
    public static function reviewers(): array
    {
        return ['a dean' => ['dean'], 'the campus director' => ['director']];
    }

    private function actor(string $which): User
    {
        return $which === 'dean' ? $this->dean : $this->director;
    }

    // ------------------------------------------------------------------
    // Staff accounts
    // ------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\DataProvider('reviewers')]
    public function test_a_reviewer_cannot_open_the_create_account_screen(string $which): void
    {
        $this->actingAs($this->actor($which))
            ->get(route('admin.employees.create'))
            ->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('reviewers')]
    public function test_a_reviewer_cannot_create_a_staff_account(string $which): void
    {
        $this->actingAs($this->actor($which))
            ->post(route('admin.employees.store'), [
                'employee_number' => 'ESCALATE-1',
                'name' => 'Created By A Reviewer',
                'email' => 'escalate@example.ph',
                'role' => 'admin',
                'first_day_of_service' => '2020-01-01',
                'college_id' => College::where('code', 'CAS')->value('id'),
                'password' => 'passwords9',
                'password_confirmation' => 'passwords9',
            ])
            ->assertForbidden();

        $this->assertNull(User::where('email', 'escalate@example.ph')->first());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('reviewers')]
    public function test_a_reviewer_cannot_grant_themselves_hr(string $which): void
    {
        $actor = $this->actor($which);

        $this->actingAs($actor)
            ->put(route('admin.employees.update', $actor), [
                'employee_number' => $actor->employee_number,
                'name' => $actor->name,
                'email' => $actor->email,
                'role' => 'admin',
            ])
            ->assertForbidden();

        $this->assertNotSame('admin', $actor->fresh()->role);
    }

    // ------------------------------------------------------------------
    // Policies
    // ------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\DataProvider('reviewers')]
    public function test_a_reviewer_cannot_publish_a_policy(string $which): void
    {
        $this->actingAs($this->actor($which))
            ->post(route('admin.policies.store'), [
                'title' => 'Published By A Reviewer',
                'body' => 'Should not be possible.',
                'category' => 'general',
                'type' => 'memo',
                'is_published' => 1,
            ])
            ->assertForbidden();

        $this->assertNull(HrPolicy::where('title', 'Published By A Reviewer')->first());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('reviewers')]
    public function test_a_reviewer_cannot_edit_a_policy(string $which): void
    {
        $policy = HrPolicy::create([
            'title' => 'Records Retention',
            'body' => 'Keep the originals.',
            'category' => 'general',
            'type' => 'memo',
            'is_published' => true,
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
        ]);

        $this->actingAs($this->actor($which))
            ->get(route('admin.policies.edit', $policy))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // And HR still can
    // ------------------------------------------------------------------

    public function test_hr_still_administers_everything(): void
    {
        $hr = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($hr)->get(route('admin.employees.create'))
            ->assertRedirect(route('admin.employees.index', ['add' => 1]));
        $this->actingAs($hr)->get(route('admin.policies.create'))->assertOk();

        $this->actingAs($hr)
            ->post(route('admin.employees.store'), [
                'employee_number' => 'HR-MADE-1',
                'name' => 'Created By HR',
                'email' => 'hr.made@example.ph',
                'role' => 'employee',
                'first_day_of_service' => '2020-01-01',
                'college_id' => College::where('code', 'CAS')->value('id'),
                'password' => 'passwords9',
                'password_confirmation' => 'passwords9',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotNull(User::where('email', 'hr.made@example.ph')->first());
    }

    /**
     * The list is scoped; the record behind it must be too.
     *
     * A Dean could reach any employee on the campus by typing the id into the
     * address bar, which is precisely what a query-level boundary is supposed
     * to prevent.
     */
    public function test_a_dean_cannot_open_a_record_from_another_college(): void
    {
        $other = College::where('code', 'CTE')->firstOrFail();

        $outsider = User::factory()->create([
            'role' => 'employee', 'status' => 'active', 'college_id' => $other->id,
        ]);

        $this->actingAs($this->dean)
            ->get(route('admin.employees.show', $outsider))
            ->assertForbidden();

        // Their own college is still theirs to read.
        $this->actingAs($this->dean)
            ->get(route('admin.employees.show', $this->employee))
            ->assertOk();
    }

    /** The Campus Director signs for the whole campus, so sees all of it. */
    public function test_the_campus_director_reads_any_record(): void
    {
        $this->actingAs($this->director)
            ->get(route('admin.employees.show', $this->employee))
            ->assertOk();
    }

    /** Reviewers keep the screens their work actually needs. */
    public function test_reviewers_keep_the_screens_they_need(): void
    {
        foreach ([$this->dean, $this->director] as $actor) {
            $this->actingAs($actor)->get(route('admin.leave.review.index'))->assertOk();
            $this->actingAs($actor)->get(route('admin.employees.index'))->assertOk();
            $this->actingAs($actor)->get(route('admin.leave.calendar'))->assertOk();
            $this->actingAs($actor)->get(route('policies.index'))->assertOk();
        }
    }
}
