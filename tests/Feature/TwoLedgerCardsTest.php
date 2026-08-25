<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\LeaveLedgerEntry;
use App\Models\User;
use App\Services\LeaveLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The campus keeps two cards, and they do not mix.
 *
 * A leave line is charged against the vacation or sick balance and kept to
 * two decimal places. A service credit line is charged against service
 * credits — even when the day taken was sick or vacation leave — and kept to
 * three. HR decides which card an approved leave is written on.
 */
class TwoLedgerCardsTest extends TestCase
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

    private function line(array $attributes): LeaveLedgerEntry
    {
        $entry = LeaveLedgerEntry::create(array_merge([
            'user_id' => $this->employee->id,
            'period_from' => now()->subMonth()->startOfMonth(),
            'period_to' => now()->subMonth()->endOfMonth(),
            'remarks' => 'Entry',
            'ledger' => LeaveLedgerEntry::LEAVE,
            'vl_earned' => 0, 'vl_used' => 0, 'vl_used_wop' => 0, 'vl_balance' => 0,
            'sl_earned' => 0, 'sl_used' => 0, 'sl_used_wop' => 0, 'sl_balance' => 0,
            'service_earned' => 0, 'service_used' => 0, 'service_balance' => 0,
            'encoded_by' => $this->hr->id,
        ], $attributes));

        app(LeaveLedgerService::class)->recalculate($this->employee);

        return $entry->refresh();
    }

    private function balance(): LeaveBalance
    {
        return $this->employee->leaveBalance->fresh();
    }

    // ------------------------------------------------------------------
    // What each card charges
    // ------------------------------------------------------------------

    public function test_a_leave_card_line_charges_the_leave_balance(): void
    {
        $this->line(['vl_earned' => 10]);
        $this->line(['vl_used' => 2, 'remarks' => 'AUG. 22 VL']);

        $this->assertEqualsWithDelta(8.0, (float) $this->balance()->vl_balance, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $this->balance()->service_balance, 0.001);
    }

    public function test_a_service_card_line_charges_service_credits(): void
    {
        $this->line([
            'ledger' => LeaveLedgerEntry::SERVICE,
            'service_earned' => 5,
            'remarks' => 'SERVICE CREDITS EARNED DURING MAY 9, 2022 NATIONAL AND LOCAL ELECTIONS',
        ]);

        // A sick day written on the service card comes off service credits,
        // not the sick balance — this is the client's own rule.
        $this->line([
            'ledger' => LeaveLedgerEntry::SERVICE,
            'sl_used' => 1,
            'remarks' => 'SEPT. 25 SL',
        ]);

        $this->assertEqualsWithDelta(4.0, (float) $this->balance()->service_balance, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $this->balance()->sl_balance, 0.001);
    }

    public function test_a_vacation_day_on_the_service_card_also_charges_service_credits(): void
    {
        $this->line(['ledger' => LeaveLedgerEntry::SERVICE, 'service_earned' => 10]);
        $this->line(['ledger' => LeaveLedgerEntry::SERVICE, 'vl_used' => 2, 'remarks' => 'OCT. 4 VL']);

        $this->assertEqualsWithDelta(8.0, (float) $this->balance()->service_balance, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $this->balance()->vl_balance, 0.001);
    }

    public function test_the_two_cards_do_not_touch_each_other(): void
    {
        $this->line(['vl_earned' => 10]);
        $this->line(['ledger' => LeaveLedgerEntry::SERVICE, 'service_earned' => 5]);
        $this->line(['ledger' => LeaveLedgerEntry::SERVICE, 'vl_used' => 3]);

        // The service card spent three days; the leave balance is untouched.
        $this->assertEqualsWithDelta(10.0, (float) $this->balance()->vl_balance, 0.001);
        $this->assertEqualsWithDelta(2.0, (float) $this->balance()->service_balance, 0.001);
    }

    // ------------------------------------------------------------------
    // What each card prints
    // ------------------------------------------------------------------

    private function cardHtml(): string
    {
        $ledger = $this->employee->leaveLedgerEntries()->orderBy('period_from')->get();

        return view('pdf.leave-ledger-card', [
            'employee' => $this->employee,
            'ledger' => $ledger,
            'balance' => $this->employee->leaveBalance,
            'serviceRows' => $ledger->filter->touchesServiceCredits()->values(),
            'generatedAt' => now(),
        ])->render();
    }

    public function test_a_leave_line_never_appears_on_the_service_card(): void
    {
        $this->line(['vl_used' => 1, 'remarks' => 'LEAVE CARD ONLY']);
        $this->line(['ledger' => LeaveLedgerEntry::SERVICE, 'service_earned' => 5, 'remarks' => 'SERVICE CARD ONLY']);

        $html = $this->cardHtml();

        // Each remark appears exactly once — on its own card.
        $this->assertSame(1, substr_count($html, 'LEAVE CARD ONLY'));
        $this->assertSame(1, substr_count($html, 'SERVICE CARD ONLY'));
    }

    public function test_the_leave_card_prints_two_decimal_places(): void
    {
        $this->line(['vl_earned' => 12.5]);

        $this->assertStringContainsString('12.50', $this->cardHtml());
    }

    public function test_the_service_card_prints_three_decimal_places(): void
    {
        // The client's card carries figures like 5.956 and 10.956.
        $this->line(['ledger' => LeaveLedgerEntry::SERVICE, 'service_earned' => 5.956]);

        $this->assertStringContainsString('5.956', $this->cardHtml());
    }

    // ------------------------------------------------------------------
    // HR's choice
    // ------------------------------------------------------------------

    public function test_hr_must_say_which_card_an_approved_leave_goes_on(): void
    {
        $application = \App\Models\LeaveApplication::create([
            'user_id' => $this->employee->id,
            'leave_type' => 'VL',
            'date_from' => now()->addWeek(),
            'date_to' => now()->addWeek(),
            'days' => 1,
            'status' => 'cd_approved',
            'uploaded_at' => now(),
        ]);

        // Leaving it unsaid would silently pick one, and the two cards behave
        // differently, so the choice is required.
        $this->actingAs($this->hr)
            ->post(route('admin.leave.review.post-to-ledger', $application), [
                'period_from' => now()->addWeek()->format('Y-m-d'),
                'period_to' => now()->addWeek()->format('Y-m-d'),
                'days' => 1,
                'vl_used' => 1,
                'remarks' => 'VL',
            ])
            ->assertSessionHasErrors('ledger');
    }

    public function test_the_card_choice_is_submitted_with_the_posting_form(): void
    {
        $application = \App\Models\LeaveApplication::create([
            'user_id' => $this->employee->id,
            'leave_type' => 'VL',
            'date_from' => now()->addWeek(),
            'date_to' => now()->addWeek(),
            'days' => 1,
            'status' => 'cd_approved',
            'uploaded_at' => now(),
        ]);

        $html = $this->actingAs($this->hr)
            ->get(route('admin.leave.review.show', $application))
            ->assertOk()
            ->getContent();

        // Presence is not enough: a control outside the <form> is never
        // submitted, which made HR's first attempt fail on a required field
        // it had already answered.
        $formStart = strpos($html, route('admin.leave.review.post-to-ledger', $application));
        $this->assertNotFalse($formStart, 'The posting form is missing.');

        $formEnd = strpos($html, '</form>', $formStart);
        $form = substr($html, $formStart, $formEnd - $formStart);

        $this->assertStringContainsString('name="ledger" value="leave"', $form);
        $this->assertStringContainsString('name="ledger" value="service"', $form);
    }

    public function test_hr_records_an_approved_leave_straight_from_the_page(): void
    {
        $application = \App\Models\LeaveApplication::create([
            'user_id' => $this->employee->id,
            'leave_type' => 'VL',
            'date_from' => now()->addWeek(),
            'date_to' => now()->addWeek(),
            'days' => 1,
            'status' => 'cd_approved',
            'uploaded_at' => now(),
        ]);

        $this->line(['vl_earned' => 5]);

        // The whole journey HR actually takes: the leave card is preselected,
        // so posting without touching the choice has to succeed.
        $this->actingAs($this->hr)
            ->post(route('admin.leave.review.post-to-ledger', $application), [
                'period_from' => now()->addWeek()->format('Y-m-d'),
                'period_to' => now()->addWeek()->format('Y-m-d'),
                'days' => 1,
                'vl_used' => 1,
                'remarks' => 'VL — family matter',
                'ledger' => 'leave',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertTrue($application->fresh()->ledger_posted);
        $this->assertEqualsWithDelta(4.0, (float) $this->balance()->vl_balance, 0.001);
    }

    public function test_the_review_page_offers_both_cards(): void
    {
        $application = \App\Models\LeaveApplication::create([
            'user_id' => $this->employee->id,
            'leave_type' => 'VL',
            'date_from' => now()->addWeek(),
            'date_to' => now()->addWeek(),
            'days' => 1,
            'status' => 'cd_approved',
            'uploaded_at' => now(),
        ]);

        $this->actingAs($this->hr)
            ->get(route('admin.leave.review.show', $application))
            ->assertOk()
            ->assertSee('Record on')
            ->assertSee('Leave ledger')
            ->assertSee('Service credits');
    }

    public function test_hr_posts_an_approved_leave_to_the_service_card(): void
    {
        $this->line(['ledger' => LeaveLedgerEntry::SERVICE, 'service_earned' => 5]);

        $application = \App\Models\LeaveApplication::create([
            'user_id' => $this->employee->id,
            'leave_type' => 'SL',
            'date_from' => now()->addWeek(),
            'date_to' => now()->addWeek(),
            'days' => 1,
            'status' => 'cd_approved',
            'uploaded_at' => now(),
        ]);

        $this->actingAs($this->hr)
            ->post(route('admin.leave.review.post-to-ledger', $application), [
                'period_from' => now()->addWeek()->format('Y-m-d'),
                'period_to' => now()->addWeek()->format('Y-m-d'),
                'days' => 1,
                'sl_used' => 1,
                'remarks' => 'SL charged to service credits',
                'ledger' => LeaveLedgerEntry::SERVICE,
            ])
            ->assertRedirect();

        $entry = $application->fresh()->ledgerEntry;
        $this->assertSame(LeaveLedgerEntry::SERVICE, $entry->ledger);

        // The sick day came off service credits, not the sick balance.
        $this->assertEqualsWithDelta(4.0, (float) $this->balance()->service_balance, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $this->balance()->sl_balance, 0.001);
    }

    // ------------------------------------------------------------------
    // Posting credits by hand
    // ------------------------------------------------------------------

    public function test_hr_posts_monthly_credits_to_the_leave_card(): void
    {
        $this->actingAs($this->hr)
            ->post(route('admin.leave.earned.store', $this->employee), [
                'period_from' => now()->startOfMonth()->format('Y-m-d'),
                'period_to' => now()->endOfMonth()->format('Y-m-d'),
                'vl_earned' => 1.25,
                'sl_earned' => 1.25,
                'remarks' => 'Monthly accrual',
                'ledger' => LeaveLedgerEntry::LEAVE,
            ])->assertRedirect();

        $this->assertEqualsWithDelta(1.25, (float) $this->balance()->vl_balance, 0.001);
        $this->assertEqualsWithDelta(1.25, (float) $this->balance()->sl_balance, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $this->balance()->service_balance, 0.001);
    }

    public function test_hr_posts_service_credits_to_the_service_card(): void
    {
        // Posted without naming the card, these landed on the leave card,
        // where service credits are not counted, and silently did nothing.
        $this->actingAs($this->hr)
            ->post(route('admin.leave.earned.store', $this->employee), [
                'period_from' => now()->format('Y-m-d'),
                'period_to' => now()->format('Y-m-d'),
                'service_earned' => 5,
                'remarks' => 'Service credits earned during the 2026 Barangay Elections',
                'ledger' => LeaveLedgerEntry::SERVICE,
            ])->assertRedirect();

        $entry = LeaveLedgerEntry::where('user_id', $this->employee->id)->latest('id')->first();

        $this->assertSame(LeaveLedgerEntry::SERVICE, $entry->ledger);
        $this->assertEqualsWithDelta(5.0, (float) $this->balance()->service_balance, 0.001);

        // And the leave balances are untouched.
        $this->assertEqualsWithDelta(0.0, (float) $this->balance()->vl_balance, 0.001);
    }

    public function test_each_posting_form_names_the_card_it_writes_on(): void
    {
        $html = $this->actingAs($this->hr)
            ->get(route('admin.leave.ledger', $this->employee))
            ->assertOk()
            ->getContent();

        // Both forms post to the same endpoint, so each has to say which card
        // it means or the endpoint has to guess.
        $this->assertStringContainsString('name="ledger" value="leave"', $html);
        $this->assertStringContainsString('name="ledger" value="service"', $html);
    }

    public function test_a_card_name_that_is_not_one_of_the_two_is_refused(): void
    {
        $this->actingAs($this->hr)
            ->post(route('admin.leave.earned.store', $this->employee), [
                'period_from' => now()->format('Y-m-d'),
                'period_to' => now()->format('Y-m-d'),
                'vl_earned' => 1,
                'ledger' => 'somewhere-else',
            ])->assertSessionHasErrors('ledger');
    }
}
