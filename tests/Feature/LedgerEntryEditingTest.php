<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\LeaveLedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HR corrects the ledger by editing its lines.
 *
 * This replaced the cell editor, which wrote into a copy of the workbook —
 * not where the printed card comes from — so a correction made there never
 * reached the card. Every line carries the balance as it stood after it, so
 * changing one in the middle replays everything below.
 */
class LedgerEntryEditingTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->employee = User::factory()->create(['role' => 'employee', 'status' => 'active']);

        LeaveBalance::create([
            'user_id' => $this->employee->id,
            'vl_balance' => 0, 'sl_balance' => 0, 'service_balance' => 0,
        ]);
    }

    private function line(array $attributes = []): LeaveLedgerEntry
    {
        static $month = 0;
        $month++;

        $entry = LeaveLedgerEntry::create(array_merge([
            'user_id' => $this->employee->id,
            'period_from' => now()->subMonths(12 - $month)->startOfMonth(),
            'period_to' => now()->subMonths(12 - $month)->endOfMonth(),
            'remarks' => 'Monthly accrual',
            'vl_earned' => 1.25, 'vl_used' => 0, 'vl_used_wop' => 0, 'vl_balance' => 0,
            'sl_earned' => 1.25, 'sl_used' => 0, 'sl_used_wop' => 0, 'sl_balance' => 0,
            'service_earned' => 0, 'service_used' => 0, 'service_balance' => 0,
            'encoded_by' => $this->hr->id,
        ], $attributes));

        // Running balances are computed, not stored by hand, so the fixture
        // has to be a real card rather than rows with zeroes in them.
        app(\App\Services\LeaveLedgerService::class)->recalculate($this->employee);

        return $entry->refresh();
    }

    private function correct(LeaveLedgerEntry $entry, array $changes = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->hr)->put(
            route('admin.leave.ledger.entry.update', $entry),
            array_merge([
                'period_from' => $entry->period_from->format('Y-m-d'),
                'period_to' => $entry->period_to->format('Y-m-d'),
                'remarks' => $entry->remarks,
                'vl_earned' => $entry->vl_earned,
                'vl_used' => $entry->vl_used,
                'vl_used_wop' => $entry->vl_used_wop,
                'sl_earned' => $entry->sl_earned,
                'sl_used' => $entry->sl_used,
                'sl_used_wop' => $entry->sl_used_wop,
                'service_earned' => $entry->service_earned,
                'service_used' => $entry->service_used,
            ], $changes),
        );
    }

    // ------------------------------------------------------------------
    // Corrections
    // ------------------------------------------------------------------

    public function test_hr_corrects_a_line(): void
    {
        $entry = $this->line();

        $this->correct($entry, ['remarks' => 'Corrected remark', 'vl_earned' => 2.5])
            ->assertRedirect();

        $entry->refresh();
        $this->assertSame('Corrected remark', $entry->remarks);
        $this->assertEqualsWithDelta(2.5, (float) $entry->vl_earned, 0.001);
    }

    public function test_correcting_a_line_replays_every_balance_below_it(): void
    {
        $first = $this->line();
        $second = $this->line();
        $third = $this->line();

        // Three months of accrual: 1.25, 2.50, 3.75.
        $this->assertEqualsWithDelta(3.75, (float) $third->fresh()->vl_balance, 0.001);

        // The first month was actually double.
        $this->correct($first, ['vl_earned' => 2.5])->assertRedirect();

        // Everything after it has to move with it, not just that line.
        $this->assertEqualsWithDelta(2.5, (float) $first->fresh()->vl_balance, 0.001);
        $this->assertEqualsWithDelta(3.75, (float) $second->fresh()->vl_balance, 0.001);
        $this->assertEqualsWithDelta(5.0, (float) $third->fresh()->vl_balance, 0.001);

        $this->assertEqualsWithDelta(5.0, (float) $this->employee->leaveBalance->fresh()->vl_balance, 0.001);
    }

    public function test_removing_a_line_replays_the_card(): void
    {
        $first = $this->line();
        $second = $this->line();

        $this->actingAs($this->hr)
            ->delete(route('admin.leave.ledger.entry.destroy', $first))
            ->assertRedirect();

        $this->assertDatabaseMissing('leave_ledger_entries', ['id' => $first->id]);

        // The surviving line is now the whole card.
        $this->assertEqualsWithDelta(1.25, (float) $second->fresh()->vl_balance, 0.001);
        $this->assertEqualsWithDelta(1.25, (float) $this->employee->leaveBalance->fresh()->vl_balance, 0.001);
    }

    public function test_a_correction_reaches_the_printed_card(): void
    {
        $entry = $this->line();

        $this->correct($entry, ['remarks' => 'Election duty, 2026 Barangay Elections']);

        // The card is drawn from these lines, so the correction shows on it —
        // which was the whole problem with editing workbook cells instead.
        $this->actingAs($this->hr)
            ->get(route('admin.leave.ledger.pdf', $this->employee))
            ->assertOk();

        $html = view('pdf.leave-ledger-card', [
            'employee' => $this->employee,
            'ledger' => $this->employee->leaveLedgerEntries()->orderBy('period_from')->get(),
            'balance' => $this->employee->leaveBalance,
            'serviceRows' => collect(),
            'generatedAt' => now(),
        ])->render();

        $this->assertStringContainsString('Election duty, 2026 Barangay Elections', $html);
    }

    public function test_a_service_credit_correction_shows_on_the_service_card(): void
    {
        // Which card a line sits on decides what it charges, so a service
        // credit line has to be written on the service card.
        $entry = $this->line([
            'ledger' => LeaveLedgerEntry::SERVICE,
            'service_earned' => 5,
            'remarks' => 'Summer 2026',
        ]);

        $this->correct($entry, ['service_earned' => 8]);

        $this->assertEqualsWithDelta(8.0, (float) $entry->fresh()->service_earned, 0.001);
        $this->assertEqualsWithDelta(8.0, (float) $this->employee->leaveBalance->fresh()->service_balance, 0.001);
    }

    // ------------------------------------------------------------------
    // Who may do it
    // ------------------------------------------------------------------

    public function test_nobody_but_hr_may_correct_a_line(): void
    {
        $entry = $this->line();

        foreach (['employee', 'dean', 'campus_director'] as $role) {
            $other = User::factory()->create(['role' => $role, 'status' => 'active']);

            $this->actingAs($other)
                ->put(route('admin.leave.ledger.entry.update', $entry), ['remarks' => 'Tampered'])
                ->assertForbidden();

            $this->actingAs($other)
                ->delete(route('admin.leave.ledger.entry.destroy', $entry))
                ->assertForbidden();
        }

        $this->assertSame('Monthly accrual', $entry->fresh()->remarks);
    }

    public function test_a_correction_is_validated(): void
    {
        $entry = $this->line();

        // A period that ends before it starts is not a correction.
        $this->correct($entry, [
            'period_from' => now()->format('Y-m-d'),
            'period_to' => now()->subYear()->format('Y-m-d'),
        ])->assertSessionHasErrors('period_to');

        $this->correct($entry, ['vl_earned' => -5])->assertSessionHasErrors('vl_earned');
    }

    // ------------------------------------------------------------------
    // The page
    // ------------------------------------------------------------------

    public function test_the_ledger_page_offers_correction_and_not_a_workbook(): void
    {
        $entry = $this->line();

        $html = $this->actingAs($this->hr)
            ->get(route('admin.leave.ledger', $this->employee))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            route('admin.leave.ledger.entry.update', $entry), $html);

        // The cell editor and the separate service record export are gone.
        $this->assertStringNotContainsString('Edit workbook', $html);
        $this->assertStringNotContainsString('Service record (PDF)', $html);
    }

    public function test_the_page_shows_service_credits_as_their_own_section(): void
    {
        $this->line(['ledger' => LeaveLedgerEntry::SERVICE, 'service_earned' => 5, 'remarks' => 'Election duty']);

        $this->actingAs($this->hr)
            ->get(route('admin.leave.ledger', $this->employee))
            ->assertOk()
            ->assertSee('Service credits')
            ->assertSee('Election duty');
    }

    public function test_an_employee_cannot_see_the_correction_controls(): void
    {
        $entry = $this->line();
        $dean = User::factory()->create([
            'role' => 'dean', 'status' => 'active',
            'college_id' => $this->employee->college_id,
        ]);

        $html = $this->actingAs($dean)
            ->get(route('admin.leave.ledger', $this->employee))
            ->getContent();

        $this->assertStringNotContainsString(
            route('admin.leave.ledger.entry.update', $entry), $html);
    }
}
