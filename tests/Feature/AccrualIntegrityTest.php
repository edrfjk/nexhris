<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\User;
use App\Services\LeaveLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A month's credits are granted once.
 *
 * "Post monthly credits to all employees" had no guard and the table had no
 * constraint, so a double-click, a refresh, or two HR staff acting on the same
 * day credited everybody twice. The running balance replays whatever rows
 * exist, so the error compounded silently and only surfaced when somebody's
 * leave was refused for credits they were never owed — which had already
 * happened in the live data before this was found.
 *
 * Absences are the opposite case and must stay unconstrained: a person may
 * take leave more than once in the same month and each is its own line.
 */
class AccrualIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $college = College::where('code', 'CAS')->firstOrFail();

        $this->hr = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        // Ledger lines record who encoded them, so there has to be somebody
        // signed in even when the service is called directly.
        $this->actingAs($this->hr);

        $this->employee = User::factory()->create([
            'role' => 'employee', 'status' => 'active', 'college_id' => $college->id,
        ]);
    }

    private function postMonthlyCredits(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->hr)->post(route('admin.leave.bulk-earned.store'), [
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
            'ledger' => 'leave',
            'vl_earned' => 1.25,
            'sl_earned' => 1.25,
        ]);
    }

    public function test_posting_the_same_month_twice_credits_it_once(): void
    {
        $this->postMonthlyCredits()->assertRedirect();
        $this->postMonthlyCredits()->assertRedirect();

        $this->assertSame(
            1,
            $this->employee->leaveLedgerEntries()->where('type', 'earned')->count(),
            'the second run credited the month again',
        );

        $this->assertEquals(
            1.25,
            (float) $this->employee->fresh()->leaveBalance->vl_balance,
            'the balance was credited twice for one month',
        );
    }

    public function test_the_second_run_says_who_already_had_it(): void
    {
        $this->postMonthlyCredits();

        $this->postMonthlyCredits()
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Already had this period')
                && str_contains($message, $this->employee->name));
    }

    public function test_the_database_refuses_a_duplicate_accrual_even_off_the_beaten_path(): void
    {
        $service = app(LeaveLedgerService::class);

        $service->postEntry(
            employee: $this->employee,
            periodFrom: '2026-09-01', periodTo: '2026-09-30',
            type: 'earned', remarks: 'September', vlEarned: 1.25,
        );

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        $service->postEntry(
            employee: $this->employee,
            periodFrom: '2026-09-01', periodTo: '2026-09-30',
            type: 'earned', remarks: 'September again', vlEarned: 1.25,
        );
    }

    public function test_two_absences_in_one_month_are_both_recorded(): void
    {
        $service = app(LeaveLedgerService::class);

        $service->postEntry(
            employee: $this->employee,
            periodFrom: '2026-10-01', periodTo: '2026-10-31',
            type: 'earned', remarks: 'October credits', vlEarned: 5,
        );

        // Someone can be away twice in a month, and each absence is its own
        // line on the official card. The constraint must not reach these.
        foreach (['Sick, 5 Oct', 'Vacation, 20 Oct'] as $remark) {
            $service->postEntry(
                employee: $this->employee,
                periodFrom: '2026-10-01', periodTo: '2026-10-31',
                type: 'used', remarks: $remark, vlUsed: 1,
            );
        }

        $this->assertSame(
            2,
            $this->employee->leaveLedgerEntries()->where('type', 'used')->count(),
            'the constraint is refusing legitimate absences',
        );
    }

    public function test_an_accrual_carries_the_period_it_credits(): void
    {
        app(LeaveLedgerService::class)->postEntry(
            employee: $this->employee,
            periodFrom: '2026-11-01', periodTo: '2026-11-30',
            type: 'earned', remarks: 'November', vlEarned: 1.25,
        );

        app(LeaveLedgerService::class)->postEntry(
            employee: $this->employee,
            periodFrom: '2026-11-01', periodTo: '2026-11-30',
            type: 'used', remarks: 'A day off', vlUsed: 1,
        );

        $accrual = $this->employee->leaveLedgerEntries()->where('type', 'earned')->first();
        $absence = $this->employee->leaveLedgerEntries()->where('type', 'used')->first();

        $this->assertSame('2026-11-01:2026-11-30', $accrual->accrual_period);
        $this->assertNull($absence->accrual_period, 'absences must stay outside the constraint');
    }
}
