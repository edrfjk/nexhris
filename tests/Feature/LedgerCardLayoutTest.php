<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\LeaveLedgerEntry;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The printed leave ledger card.
 *
 * Laid out from the campus template rather than converted from it: the form
 * does not change, and building it as HTML lets a long remark wrap and push
 * its row taller instead of being clipped by a fixed-height cell.
 */
class LedgerCardLayoutTest extends TestCase
{
    use RefreshDatabase;

    private const LONG_REMARK = 'Vacation leave to attend the Regional Training Workshop on '
        . 'Records Management and Digital Archiving held at the Provincial Capitol, Vigan '
        . 'City, as endorsed by the Office of the Campus Director per Special Order 2026-114.';

    private User $hr;

    private function employee(): User
    {
        // Ledger rows record who posted them, so HR has to exist first.
        $this->hr ??= User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $employee = User::factory()->create([
            'name' => 'TRESMANIO, Olga S.',
            'role' => 'employee',
            'status' => 'active',
            'college_id' => \App\Models\College::where('code', 'CAS')->value('id'),
        ]);

        LeaveBalance::create([
            'user_id' => $employee->id,
            'vl_balance' => 12.5, 'sl_balance' => 8.25, 'service_balance' => 3,
        ]);

        return $employee;
    }

    private function entry(User $employee, array $attributes = []): LeaveLedgerEntry
    {
        return LeaveLedgerEntry::create(array_merge([
            'user_id' => $employee->id,
            'period_from' => now()->subMonth()->startOfMonth(),
            'period_to' => now()->subMonth()->endOfMonth(),
            'remarks' => 'Monthly accrual',
            'vl_earned' => 1.25, 'vl_used' => 0, 'vl_used_wop' => 0, 'vl_balance' => 12.5,
            'sl_earned' => 1.25, 'sl_used' => 0, 'sl_used_wop' => 0, 'sl_balance' => 8.25,
            'service_earned' => 0, 'service_used' => 0, 'service_balance' => 3,
            'encoded_by' => $this->hr->id,
        ], $attributes));
    }

    /** The rendered card with entities decoded, so "EMPLOYEE'S" reads as it prints. */
    private function text(User $employee): string
    {
        return html_entity_decode($this->html($employee), ENT_QUOTES);
    }

    private function html(User $employee): string
    {
        $ledger = $employee->leaveLedgerEntries()->orderBy('period_from')->get();

        return view('pdf.leave-ledger-card', [
            'employee' => $employee,
            'ledger' => $ledger,
            'balance' => $employee->leaveBalance,
            'serviceRows' => $ledger->filter->touchesServiceCredits()->values(),
            'generatedAt' => now(),
        ])->render();
    }

    private function pdf(User $employee): string
    {
        $ledger = $employee->leaveLedgerEntries()->orderBy('period_from')->get();

        return Pdf::loadView('pdf.leave-ledger-card', [
            'employee' => $employee,
            'ledger' => $ledger,
            'balance' => $employee->leaveBalance,
            'serviceRows' => $ledger->filter->touchesServiceCredits()->values(),
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait')->output();
    }

    // ------------------------------------------------------------------
    // Matching the template
    // ------------------------------------------------------------------

    public function test_the_card_carries_the_templates_headings(): void
    {
        $employee = $this->employee();
        $this->entry($employee);

        $html = $this->text($employee);

        // Column headings, word for word from LEAVE AND SERVICE LEDGER.xlsx.
        foreach ([
            "EMPLOYEE'S LEAVE LEDGER CARD",
            'NAME:', '(FAMILY NAME)', '(FIRST NAME)', '(M.I.)',
            'OFFICE:', 'FIRST DAY OF GOVERNMENT SERVICE:',
            'PERIOD', 'VACATION LEAVE', 'SICK LEAVE',
            'FROM', 'TO', 'REMARKS', 'EARNED', 'BALANCE',
            'ABSENCE / UNDERTIME / W/PAY', 'ABSENCE / UNDERTIME / W/O PAY',
        ] as $heading) {
            $this->assertStringContainsString($heading, $html, "The card is missing “{$heading}”.");
        }
    }

    public function test_the_columns_are_grouped_as_the_template_groups_them(): void
    {
        $employee = $this->employee();
        $this->entry($employee);

        $html = $this->html($employee);

        // PERIOD spans three columns, each leave type spans four — eleven in
        // all, exactly as columns A to K of the template.
        $this->assertStringContainsString('colspan="3"', $html);
        $this->assertStringContainsString('colspan="4"', $html);
        $this->assertSame(22, preg_match_all('/<col style="width/', $html),
            'Expected eleven columns on each of the two pages.');
    }

    public function test_the_name_is_split_into_the_templates_three_boxes(): void
    {
        $employee = $this->employee();
        $this->entry($employee);

        $html = $this->html($employee);

        // The form has separate boxes for family name, first name and initial.
        $this->assertStringContainsString('TRESMANIO', $html);
        $this->assertStringContainsString('OLGA', $html);
        $this->assertStringContainsString('S.', $html);
    }

    public function test_the_service_credit_page_repeats_the_same_table(): void
    {
        $employee = $this->employee();
        $this->entry($employee, [
            'ledger' => \App\Models\LeaveLedgerEntry::SERVICE,
            'service_earned' => 5, 'service_balance' => 8, 'remarks' => 'Summer 2026',
        ]);

        $html = $this->text($employee);

        // The template keeps service credits on their own sheet in the same
        // table style, so the card prints it as a second page.
        // The sheet heads both records the same way and gives them the same
        // columns; only the entries on each card differ.
        $this->assertSame(2, substr_count($html, "EMPLOYEE'S LEAVE LEDGER CARD"));
        $this->assertSame(2, substr_count($html, 'VACATION LEAVE'));
        $this->assertSame(2, substr_count($html, 'SICK LEAVE'));
        $this->assertSame(2, substr_count($html, '>PERIOD<'));

        // The workbook separated them by sheet tab; the PDF names the second.
        $this->assertSame(1, substr_count($html, 'SERVICE CREDITS'));
        $this->assertStringContainsString('page-break-before', $html);
        $this->assertStringContainsString('Summer 2026', $html);
    }

    // ------------------------------------------------------------------
    // The flexibility the conversion could not give
    // ------------------------------------------------------------------

    public function test_a_long_remark_is_printed_in_full(): void
    {
        $employee = $this->employee();
        $this->entry($employee, ['remarks' => self::LONG_REMARK]);

        $html = $this->html($employee);

        // Nothing trimmed to make it fit the column.
        $this->assertStringContainsString('Regional Training Workshop', $html);
        $this->assertStringContainsString('Special Order 2026-114.', $html);
    }

    public function test_a_long_remark_wraps_instead_of_overflowing(): void
    {
        $employee = $this->employee();
        $this->entry($employee, ['remarks' => self::LONG_REMARK]);

        $html = $this->html($employee);

        // The cell grows to hold the text rather than letting it run across
        // the neighbouring columns — the whole point of rebuilding this page.
        $this->assertStringContainsString('overflow-wrap: break-word', $html);
        $this->assertStringContainsString('class="remarks"', $html);

        // nowrap is fine on the "NAME:" labels, but must never reach the
        // remarks column, which is the one that has to grow.
        $this->assertDoesNotMatchRegularExpression(
            '/td\.remarks\s*\{[^}]*white-space:\s*nowrap/s', $html);
    }

    public function test_a_long_remark_does_not_change_the_paper_size(): void
    {
        $employee = $this->employee();

        foreach (range(1, 12) as $i) {
            $this->entry($employee, [
                'remarks' => $i % 3 === 0 ? self::LONG_REMARK : 'Monthly accrual',
                'period_from' => now()->subMonths($i)->startOfMonth(),
                'period_to' => now()->subMonths($i)->endOfMonth(),
            ]);
        }

        preg_match_all(
            '/MediaBox\s*\[\s*[\d.]+\s+[\d.]+\s+([\d.]+)\s+([\d.]+)/',
            $this->pdf($employee),
            $m,
        );

        $boxes = array_unique(array_map(
            fn ($i) => round((float) $m[1][$i], 1) . ' x ' . round((float) $m[2][$i], 1),
            array_keys($m[1]),
        ));

        $this->assertSame(['595.3 x 841.9'], array_values($boxes),
            'Every page of the card must stay A4 portrait.');
    }

    // ------------------------------------------------------------------
    // Content
    // ------------------------------------------------------------------

    public function test_an_empty_card_says_so_rather_than_printing_blank(): void
    {
        $html = $this->html($this->employee());

        $this->assertStringContainsString('No leave has been recorded', $html);
        $this->assertStringContainsString('No service credits have been recorded', $html);
    }

    public function test_the_closing_balance_is_printed(): void
    {
        $employee = $this->employee();
        $this->entry($employee);

        $html = preg_replace('/\s+/', ' ', $this->html($employee));

        // The leave card runs to two places; the service card to three.
        $this->assertStringContainsString('BALANCE AS OF', $html);
        $this->assertStringContainsString('12.50', $html);
        $this->assertStringContainsString('8.25', $html);
    }

    public function test_zero_columns_are_left_blank_as_on_the_paper_form(): void
    {
        $employee = $this->employee();
        $this->entry($employee, ['vl_used' => 0, 'vl_earned' => 1.25]);

        $html = $this->html($employee);

        // The card is written by hand; unused columns are empty, not "0.00".
        $this->assertStringNotContainsString('>0.000<', $html);
        $this->assertStringContainsString('1.25', $html);
    }

    public function test_both_routes_still_serve_the_card(): void
    {
        $employee = $this->employee();
        $this->entry($employee);

        $this->actingAs($this->hr)
            ->get(route('admin.leave.ledger.pdf', $employee))
            ->assertOk();

        $this->actingAs($employee)
            ->get(route('leave.ledger.pdf'))
            ->assertOk();
    }

    public function test_the_column_widths_follow_the_template(): void
    {
        $employee = $this->employee();
        $this->entry($employee);

        $html = $this->html($employee);

        // The sheet sets A 11.0, B 17.8, C 13.2 and D–K 11.0 of 130.0 total.
        // The REMARKS column is the wide one, and it must stay that way.
        $this->assertStringContainsString('width: 13.69%', $html);
        $this->assertStringContainsString('width: 10.15%', $html);
        $this->assertStringContainsString('width: 8.46%', $html);
    }

    public function test_the_service_page_closes_with_its_own_balance(): void
    {
        $employee = $this->employee();
        $this->entry($employee, [
            'ledger' => \App\Models\LeaveLedgerEntry::SERVICE,
            'service_earned' => 5, 'service_balance' => 8,
        ]);

        $html = preg_replace('/\s+/', ' ', $this->text($employee));

        // Both cards end on a stated balance, as the paper form does, and the
        // service card names which balance it is.
        $this->assertSame(2, substr_count($html, 'BALANCE AS OF'));
        $this->assertStringContainsString('SERVICE CREDIT BALANCE AS OF', $html);
        $this->assertStringContainsString('3.000', $html);
    }

    // ------------------------------------------------------------------
    // The template's own look
    // ------------------------------------------------------------------

    /**
     * Publishes a ledger template carrying a drawing, so the seal extraction
     * is exercised rather than skipped.
     */
    private function publishTemplateWithLogo(): void
    {
        $seal = imagecreatetruecolor(600, 600);
        imagefill($seal, 0, 0, imagecolorallocate($seal, 120, 0, 0));
        ob_start();
        imagepng($seal);
        $png = (string) ob_get_clean();
        imagedestroy($seal);

        $image = tempnam(sys_get_temp_dir(), 'seal') . '.png';
        file_put_contents($image, $png);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('BALANCE');
        $sheet->setCellValue('A1', "EMPLOYEE'S LEAVE LEDGER CARD");

        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setPath($image);
        $drawing->setCoordinates('B1');
        $drawing->setWidth(64);
        $drawing->setWorksheet($sheet);

        $workbook = tempnam(sys_get_temp_dir(), 'tpl') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($workbook);
        $spreadsheet->disconnectWorksheets();

        \Illuminate\Support\Facades\Storage::disk('public')
            ->put('ledger-templates/test.xlsx', file_get_contents($workbook));

        \App\Models\LedgerTemplate::create([
            'label' => 'Test Ledger Template',
            'version' => 99,
            'file_path' => 'ledger-templates/test.xlsx',
            'original_filename' => 'LEAVE AND SERVICE LEDGER.xlsx',
            'checksum' => hash_file('sha256', $workbook),
            'is_active' => true,
            'uploaded_by' => $this->hr->id,
        ]);
    }


    public function test_the_campus_seal_from_the_template_is_printed(): void
    {
        $employee = $this->employee();
        $this->entry($employee);

        $this->publishTemplateWithLogo();

        // Taken out of the published template, so a new template brings its
        // own seal rather than leaving a stale one behind.
        $this->assertStringContainsString('data:image/png;base64,', $this->html($employee));

        $pdf = $this->pdf($employee);
        $this->assertSame(2, preg_match_all('/\/Subtype\s*\/Image/', $pdf),
            'The seal should appear on both cards.');
    }

    public function test_the_seal_is_resampled_rather_than_embedded_whole(): void
    {
        $this->employee();
        $this->publishTemplateWithLogo();

        $logo = app(\App\Services\LedgerCardAssets::class)->logoPath();
        $this->assertNotNull($logo, 'The seal was not extracted from the template.');

        // The real template holds it at 4096 square while drawing it about an
        // inch wide; embedding the original puts a quarter megabyte in every
        // card.
        [$width, $height] = getimagesize($logo);

        $this->assertLessThanOrEqual(320, max($width, $height));
    }

    public function test_the_form_is_ruled_rather_than_shaded(): void
    {
        $employee = $this->employee();
        $this->entry($employee);

        $html = $this->html($employee);

        // No cell on the sheet carries a fill; the structure comes from rules.
        $this->assertStringNotContainsString('background', $html);

        // Row 6 is ruled medium above and below, the body thin all round.
        $this->assertStringContainsString('border-top: 1.5pt solid #000', $html);
        $this->assertStringContainsString('border: 0.75pt solid #000', $html);
    }

    public function test_the_typography_follows_the_sheet(): void
    {
        $employee = $this->employee();
        $this->entry($employee);

        $html = $this->html($employee);

        // Arial 20pt bold title, 8pt body — as the template specifies.
        $this->assertStringContainsString('font-family: Arial', $html);
        $this->assertStringContainsString('font-size: 20pt', $html);
        $this->assertStringContainsString('font-size: 8pt', $html);
    }

    public function test_the_card_does_not_embed_a_font(): void
    {
        $employee = $this->employee();
        $this->entry($employee);

        // Arial resolves to a core PDF face, which keeps the card small
        // enough to open quickly over a slow connection.
        $this->assertSame(0, preg_match_all('/\/FontFile\d?/', $this->pdf($employee)));
    }
}
