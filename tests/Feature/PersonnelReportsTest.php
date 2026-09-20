<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\HrPolicy;
use App\Models\LeaveBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Campus-wide reports cover the whole campus.
 *
 * The balances export, the Excel export, the monthly accrual run and the
 * policy compliance list each filtered on role = 'employee'. The Dean and
 * the Campus Director hold ledger cards like anyone else, so every one of
 * those quietly dropped them: on a campus whose only plain employee was one
 * person, the balances PDF printed exactly one row.
 */
class PersonnelReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;
    private User $employee;
    private User $dean;
    private User $director;

    protected function setUp(): void
    {
        parent::setUp();

        $college = College::where('code', 'CAS')->firstOrFail();

        $this->hr = User::factory()->create([
            'name' => 'HR Administrator', 'role' => 'admin', 'status' => 'active',
        ]);

        $this->employee = User::factory()->create([
            'name' => 'Mary Rose Niro', 'role' => 'employee',
            'status' => 'active', 'college_id' => $college->id,
        ]);

        $this->dean = User::factory()->create([
            'name' => 'Eidref Jake Manalansan', 'role' => 'dean',
            'status' => 'active', 'college_id' => $college->id,
        ]);

        $this->director = User::factory()->create([
            'name' => 'Diana Sofia Reyes', 'role' => 'campus_director', 'status' => 'active',
        ]);

        foreach ([$this->employee, $this->dean, $this->director] as $person) {
            LeaveBalance::updateOrCreate(
                ['user_id' => $person->id],
                ['vl_balance' => 5, 'sl_balance' => 5, 'service_balance' => 0],
            );
        }
    }

    /** Text drawn into a Dompdf page, so a name can be looked for. */
    private function textOf(string $pdf): string
    {
        $text = '';
        preg_match_all('/stream\r?\n/', $pdf, $starts, PREG_OFFSET_CAPTURE);

        foreach ($starts[0] as $hit) {
            $from = $hit[1] + strlen($hit[0]);
            $body = substr($pdf, $from, strpos($pdf, 'endstream', $from) - $from);

            $inflated = @gzuncompress($body);
            if ($inflated === false) {
                continue;
            }

            if (preg_match_all('/\[(.*?)\]\s*TJ/s', $inflated, $runs)) {
                foreach ($runs[1] as $run) {
                    preg_match_all('/\((.*?)\)/s', $run, $pieces);

                    $drawn = implode('', array_map('stripcslashes', $pieces[1]));

                    // A subset font addresses its glyphs with two-byte codes,
                    // so its strings arrive as UTF-16BE. Decode each run on
                    // its own — a separator inserted first would shift every
                    // byte pair after it.
                    if (substr_count($drawn, "\0") > strlen($drawn) / 4) {
                        $drawn = mb_convert_encoding($drawn, 'UTF-8', 'UTF-16BE');
                    }

                    $text .= $drawn . "\n";
                }
            }
        }

        return $text;
    }

    /** The bytes of a streamed Dompdf response. */
    private function pdfBytes(string $url, ?User $viewer = null): string
    {
        $response = $this->actingAs($viewer ?? $this->hr)->get($url)->assertOk();

        return $response->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse
            ? $response->streamedContent()
            : $response->getContent();
    }

    // ------------------------------------------------------------------
    // The reported bug
    // ------------------------------------------------------------------

    public function test_the_balances_report_lists_the_dean_and_the_director_too(): void
    {
        $text = $this->textOf($this->pdfBytes(route('admin.leave.export.pdf')));

        // Dompdf breaks a line into runs, so match on the distinctive surname.
        $this->assertStringContainsString('Niro', $text, 'the plain employee is missing');
        $this->assertStringContainsString('Manalansan', $text, 'the Dean is missing from the balances report');
        $this->assertStringContainsString('Reyes', $text, 'the Campus Director is missing from the balances report');
    }

    public function test_the_balances_report_covers_everyone_the_list_screen_shows(): void
    {
        $onScreen = $this->actingAs($this->hr)
            ->get(route('admin.leave.index'))
            ->assertOk()
            ->getContent();

        $inReport = $this->textOf($this->pdfBytes(route('admin.leave.export.pdf')));

        foreach ([$this->employee, $this->dean, $this->director] as $person) {
            $surname = last(explode(' ', $person->name));

            $this->assertStringContainsString($surname, $onScreen);
            $this->assertStringContainsString(
                $surname,
                $inReport,
                $person->name . ' is on the ledger list but not in its PDF export',
            );
        }
    }

    public function test_the_excel_export_covers_the_same_people(): void
    {
        $this->assertSame(
            3,
            User::personnel()->count(),
            'the personnel scope should hold the employee, the Dean and the Director',
        );

        $this->actingAs($this->hr)
            ->get(route('admin.leave.export.excel'))
            ->assertOk();
    }

    // ------------------------------------------------------------------
    // The same filter, in the places it did quieter damage
    // ------------------------------------------------------------------

    public function test_the_monthly_accrual_credits_the_dean_and_the_director(): void
    {
        $this->actingAs($this->hr)
            ->post(route('admin.leave.bulk-earned.store'), [
                'period_from' => now()->startOfMonth()->format('Y-m-d'),
                'period_to' => now()->endOfMonth()->format('Y-m-d'),
                'ledger' => 'leave',
                'vl_earned' => 1.25,
                'sl_earned' => 1.25,
            ])
            ->assertRedirect();

        foreach ([$this->employee, $this->dean, $this->director] as $person) {
            $this->assertSame(
                1,
                $person->leaveLedgerEntries()->count(),
                $person->name . ' was skipped by the monthly accrual',
            );
        }
    }

    public function test_policy_compliance_expects_an_acknowledgment_from_every_member_of_staff(): void
    {
        $policy = HrPolicy::create([
            'title' => 'Records Retention',
            'body' => 'Keep the originals.',
            'category' => 'general',
            'type' => 'memo',
            'requires_acknowledgment' => true,
            'is_published' => true,
            'created_by' => $this->hr->id,
        ]);

        $html = $this->actingAs($this->hr)
            ->get(route('admin.policies.compliance', $policy))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Manalansan', $html, 'the Dean owes an acknowledgment too');
        $this->assertStringContainsString('Reyes', $html, 'the Campus Director owes an acknowledgment too');
    }

    // ------------------------------------------------------------------
    // The button that was clicked
    // ------------------------------------------------------------------

    public function test_the_two_export_buttons_say_what_they_export(): void
    {
        $html = $this->actingAs($this->hr)
            ->get(route('admin.leave.index'))
            ->assertOk()
            ->getContent();

        // A bare "PDF" in the header next to a bare "PDF" on every row is
        // what sent HR to the balances report expecting a ledger card.
        $this->assertStringContainsString('Balances (PDF)', $html);
        $this->assertStringContainsString('Balances (Excel)', $html);
    }

    public function test_each_ledger_card_prints_its_own_owner(): void
    {
        foreach ([$this->employee, $this->dean, $this->director] as $person) {
            $text = strtoupper(
                $this->textOf($this->pdfBytes(route('admin.leave.ledger.pdf', $person)))
            );

            $surname = strtoupper(last(explode(' ', $person->name)));
            $this->assertStringContainsString($surname, $text);

            foreach ([$this->employee, $this->dean, $this->director] as $other) {
                if ($other->is($person)) {
                    continue;
                }

                $foreign = strtoupper(last(explode(' ', $other->name)));
                $this->assertDoesNotMatchRegularExpression(
                    '/(?<![A-Z])' . preg_quote($foreign, '/') . '(?![A-Z])/',
                    $text,
                    $person->name . ' printed ' . $other->name . ' on their ledger card',
                );
            }
        }
    }

    public function test_a_dean_can_export_only_their_colleges_balances(): void
    {
        $cte = College::where('code', 'CTE')->firstOrFail();
        $outside = User::factory()->create([
            'name' => 'Outside College Person',
            'role' => 'employee',
            'status' => 'active',
            'college_id' => $cte->id,
        ]);
        LeaveBalance::create(['user_id' => $outside->id, 'vl_balance' => 5, 'sl_balance' => 5]);

        $pdf = $this->textOf($this->pdfBytes(route('admin.leave.export.pdf'), $this->dean));

        $this->assertStringContainsString('Manalansan', $pdf);
        $this->assertStringNotContainsString('Outside College Person', $pdf);

        $this->actingAs($this->dean)
            ->get(route('admin.leave.export.excel'))
            ->assertOk();

        $this->actingAs($this->dean)
            ->get(route('admin.leave.ledger.pdf', $outside))
            ->assertForbidden();
    }
}
