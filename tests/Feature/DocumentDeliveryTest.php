<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\LeaveBalance;
use App\Models\LeaveFormTemplate;
use App\Models\PdsTemplate;
use App\Models\User;
use App\Services\XlsxToPdfService;
use App\Support\DocumentName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * How a document reaches the person who asked for it.
 *
 * Two long-standing complaints, both about delivery rather than content:
 *
 *  - "View" on a published template opened an empty tab, because the link
 *    pointed at the stored .xlsx and no browser renders a workbook.
 *  - Saved PDFs were called "pdf". The Content-Disposition header was right
 *    all along; Chrome names an inline document after the URL's last segment,
 *    and every document route ended in "/pdf", "/export" or "/download".
 *
 * So the tests below check the URL and the header together — a document is
 * only delivered properly when both carry the same real name.
 */
class DocumentDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        // Templates published here are fixtures, not real publications;
        // without this the suite leaves one behind on every run.
        Storage::fake('public');
        Storage::fake('local');

        if (! app(XlsxToPdfService::class)->canRender()) {
            $this->markTestSkipped('No PDF renderer is available.');
        }

        $college = College::where('code', 'CAS')->firstOrFail();

        $this->hr = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->employee = User::factory()->create([
            'name' => 'Mary Rose Niro', 'role' => 'employee',
            'status' => 'active', 'college_id' => $college->id,
        ]);

        LeaveBalance::updateOrCreate(
            ['user_id' => $this->employee->id],
            ['vl_balance' => 5, 'sl_balance' => 5, 'service_balance' => 0],
        );
    }

    /** A small but genuine .xlsx, so PhpSpreadsheet can open it. */
    private function workbook(string $name): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('FORM');
        $sheet->setCellValue('A1', 'APPLICATION FOR LEAVE');
        $sheet->setCellValue('A2', 'Name of employee');

        $path = tempnam(sys_get_temp_dir(), 'tpl') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            $name,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    private function publishLeaveTemplate(): LeaveFormTemplate
    {
        $this->actingAs($this->hr)
            ->post(route('admin.leave.templates.store'), [
                'label' => 'CS Form No. 6',
                'template' => $this->workbook('leave-form.xlsx'),
            ])
            ->assertRedirect();

        return LeaveFormTemplate::latest('version')->firstOrFail();
    }

    private function publishPdsTemplate(): PdsTemplate
    {
        $this->actingAs($this->hr)
            // The PDS form posts its upload as "file"; the leave form uses
            // "template". Matching each controller rather than assuming.
            ->post(route('admin.pds.templates.store'), [
                'label' => 'CS Form No. 212',
                'file' => $this->workbook('pds.xlsx'),
            ])
            ->assertRedirect();

        return PdsTemplate::latest('version')->firstOrFail();
    }

    /** The name the browser will save the file as, from the URL's last segment. */
    private function nameFromUrl(string $url): string
    {
        return urldecode(basename(parse_url($url, PHP_URL_PATH)));
    }

    /** The name the server asked for, from the Content-Disposition header. */
    private function nameFromHeader(string $disposition): string
    {
        preg_match('/filename="?([^";]+)"?/', $disposition, $m);

        return $m[1] ?? '';
    }

    // ------------------------------------------------------------------
    // Viewing a published template
    // ------------------------------------------------------------------

    public function test_viewing_a_leave_form_template_returns_a_readable_pdf(): void
    {
        $template = $this->publishLeaveTemplate();

        $response = $this->actingAs($this->hr)
            ->get(route('admin.leave.templates.preview', [
                $template,
                DocumentName::template('Leave Form Template', $template->version),
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $this->bodyOf($response));
    }

    public function test_viewing_a_pds_template_returns_a_readable_pdf(): void
    {
        $template = $this->publishPdsTemplate();

        $response = $this->actingAs($this->hr)
            ->get(route('admin.pds.templates.preview', [
                $template,
                DocumentName::template('Personal Data Sheet Template', $template->version),
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $this->bodyOf($response));
    }

    public function test_the_view_button_points_at_the_preview_not_the_raw_workbook(): void
    {
        $this->publishLeaveTemplate();
        $this->publishPdsTemplate();

        $html = $this->actingAs($this->hr)
            ->get(route('admin.leave.templates.index'))
            ->assertOk()
            ->getContent();

        preg_match_all('/href="([^"]+)"[^>]*>\s*View\s*</', $html, $m);

        $this->assertNotEmpty($m[1], 'the templates page has no View buttons');

        foreach ($m[1] as $href) {
            $this->assertStringContainsString(
                'preview',
                html_entity_decode($href),
                'View still links straight at a file the browser cannot render',
            );
        }
    }

    public function test_the_original_workbook_is_still_reachable(): void
    {
        $template = $this->publishLeaveTemplate();

        $this->actingAs($this->hr)
            ->get(route('admin.leave.templates.preview', [
                $template,
                DocumentName::template('Leave Form Template', $template->version, 'xlsx'),
                'format' => 'xlsx',
            ]))
            ->assertOk()
            ->assertDownload('Leave Form Template v1.xlsx');
    }

    public function test_only_hr_may_preview_a_template(): void
    {
        $template = $this->publishLeaveTemplate();

        $this->actingAs($this->employee)
            ->get(route('admin.leave.templates.preview', [$template, 'x.pdf']))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // What the browser saves the file as
    // ------------------------------------------------------------------

    /**
     * @dataProvider documents
     */
    public function test_a_document_url_and_its_header_agree_on_the_name(string $case): void
    {
        [$actor, $url, $expected] = $this->document($case);

        $response = $this->actingAs($actor)->get($url)->assertOk();

        $this->assertSame(
            $expected,
            $this->nameFromUrl($url),
            'the browser would save this under the wrong name',
        );

        $this->assertSame(
            $expected,
            $this->nameFromHeader($response->headers->get('Content-Disposition') ?? ''),
            'the Content-Disposition disagrees with the URL',
        );
    }

    public static function documents(): array
    {
        return [
            'ledger card, printed by HR' => ['admin-ledger'],
            'ledger card, printed by its owner' => ['own-ledger'],
            'leave balances' => ['balances'],
            'leave calendar' => ['calendar'],
            'employee directory' => ['directory'],
        ];
    }

    /** Built here rather than in the provider, which runs before setUp(). */
    private function document(string $case): array
    {
        return match ($case) {
            'admin-ledger' => [
                $this->hr,
                route('admin.leave.ledger.pdf', [
                    $this->employee, DocumentName::ledgerCard($this->employee),
                ]),
                "Mary Rose Niro's Leave Ledger Card.pdf",
            ],
            'own-ledger' => [
                $this->employee,
                route('leave.ledger.pdf', [DocumentName::ledgerCard($this->employee)]),
                "Mary Rose Niro's Leave Ledger Card.pdf",
            ],
            'balances' => [
                $this->hr,
                route('admin.leave.export.pdf', ['filename' => DocumentName::leaveBalances()]),
                DocumentName::leaveBalances(),
            ],
            'calendar' => [
                $this->hr,
                route('admin.leave.calendar.export', [
                    'filename' => DocumentName::leaveCalendar(now()),
                ]),
                DocumentName::leaveCalendar(now()),
            ],
            'directory' => [
                $this->hr,
                route('admin.employees.export.pdf', [
                    'filename' => DocumentName::employeeDirectory(),
                ]),
                DocumentName::employeeDirectory(),
            ],
        };
    }

    public function test_no_document_link_ends_in_a_bare_pdf_or_export_segment(): void
    {
        $screens = [
            [$this->hr, route('admin.leave.index')],
            [$this->hr, route('admin.leave.ledger', $this->employee)],
            [$this->hr, route('admin.employees.index')],
            [$this->hr, route('admin.leave.calendar')],
            [$this->employee, route('leave.ledger.mine')],
        ];

        foreach ($screens as [$actor, $screen]) {
            $html = $this->actingAs($actor)->get($screen)->assertOk()->getContent();

            preg_match_all('#href="([^"]*/(?:pdf|export|download))"#', $html, $m);

            $this->assertEmpty(
                $m[1],
                $screen . ' still links at ' . implode(', ', $m[1] ?? [])
                    . ' — Chrome saves those as a file called "pdf"',
            );
        }
    }

    // ------------------------------------------------------------------

    public function test_an_apostrophe_survives_the_disposition_header(): void
    {
        $response = $this->actingAs($this->hr)->get(route('admin.leave.ledger.pdf', [
            $this->employee, DocumentName::ledgerCard($this->employee),
        ]))->assertOk();

        $header = $response->headers->get('Content-Disposition') ?? '';

        // addslashes() used to build this header, and it escapes the
        // apostrophe as well as the quote: "Niro\'s Leave Ledger Card.pdf".
        $this->assertStringNotContainsString('\\', $header);
        $this->assertStringContainsString("Niro's Leave Ledger Card.pdf", $header);
    }

    public function test_a_name_that_would_break_a_filesystem_is_cleaned_up(): void
    {
        $awkward = User::factory()->create(['name' => 'Ana/Maria "Nena" Reyes']);

        $this->assertSame(
            "Ana Maria Nena Reyes' Leave Ledger Card.pdf",
            DocumentName::ledgerCard($awkward),
        );
    }

    private function bodyOf($response): string
    {
        return $response->baseResponse instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            ? file_get_contents($response->baseResponse->getFile()->getPathname())
            : $response->getContent();
    }
}
