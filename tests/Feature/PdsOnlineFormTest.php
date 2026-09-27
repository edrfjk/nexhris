<?php

namespace Tests\Feature;

use App\Models\PdsSubmission;
use App\Models\PdsTemplate;
use App\Models\User;
use App\Services\Pds\PdsTemplateMismatch;
use App\Services\Pds\PdsWorkbookGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * The PDS answered on screen and printed into the official CS Form 212.
 */
class PdsOnlineFormTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->employee = User::factory()->create([
            'role' => 'employee', 'status' => 'active',
            'first_name' => 'Maria', 'middle_name' => 'Santos', 'last_name' => 'Dela Cruz',
            'employee_number' => 'ISPSC-041', 'contact_number' => '09171234567',
        ]);
    }

    private function publishOfficialTemplate(?string $source = null): PdsTemplate
    {
        Storage::disk('public')->put(
            'pds-templates/cs-form-212.xlsx',
            file_get_contents($source ?? resource_path('templates/CS-Form-212-2026.xlsx')),
        );

        return PdsTemplate::create([
            'label' => 'CS Form 212 (Revised 2026)',
            'version' => 1,
            'file_path' => 'pds-templates/cs-form-212.xlsx',
            'original_filename' => 'CS-Form-212-2026.xlsx',
            'checksum' => 'test',
            'is_active' => true,
            'uploaded_by' => User::factory()->create(['role' => 'admin'])->id,
        ]);
    }

    private function personal(array $overrides = []): array
    {
        return array_merge([
            'surname' => 'DELA CRUZ',
            'first_name' => 'MARIA',
            'middle_name' => 'SANTOS',
            'date_of_birth' => '1988-03-14',
            'place_of_birth' => 'Tagudin, Ilocos Sur',
            'sex' => 'female',
            'civil_status' => 'married',
            'citizenship' => 'filipino',
            'residential' => ['city' => 'Tagudin', 'province' => 'Ilocos Sur', 'zip' => '2714'],
            'mobile' => '0917 123 4567',
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // The screens
    // ------------------------------------------------------------------

    public function test_every_section_renders(): void
    {
        foreach (['personal', 'family', 'education'] as $section) {
            $this->actingAs($this->employee)
                ->get(route('pds.form', $section))
                ->assertOk()
                ->assertSee('Fill In My PDS');
        }
    }

    public function test_an_unknown_section_is_not_found(): void
    {
        $this->actingAs($this->employee)->get(route('pds.form', 'nonsense'))->assertNotFound();
        $this->actingAs($this->employee)->put(route('pds.form.update', 'nonsense'), [])->assertNotFound();
    }

    public function test_a_new_form_starts_from_what_the_account_already_knows(): void
    {
        $this->actingAs($this->employee)
            ->get(route('pds.form', 'personal'))
            ->assertSee('value="Dela Cruz"', false)
            ->assertSee('value="Maria"', false)
            ->assertSee('value="ISPSC-041"', false)
            ->assertSee('value="09171234567"', false);
    }

    public function test_saving_a_section_keeps_the_answers_and_moves_on(): void
    {
        $this->actingAs($this->employee)
            ->put(route('pds.form.update', 'personal'), $this->personal())
            ->assertRedirect(route('pds.form', 'family'));

        $data = PdsSubmission::where('user_id', $this->employee->id)->value('form_data');
        $data = is_array($data) ? $data : json_decode($data, true);

        $this->assertSame('DELA CRUZ', $data['personal']['surname']);
        $this->assertSame('Tagudin', $data['personal']['residential']['city']);
        // Blanks are not stored; the printed form shows them as N/A.
        $this->assertArrayNotHasKey('telephone', $data['personal']);
    }

    public function test_required_answers_are_enforced(): void
    {
        $this->actingAs($this->employee)
            ->from(route('pds.form', 'personal'))
            ->put(route('pds.form.update', 'personal'), $this->personal(['surname' => '', 'sex' => 'unknown']))
            ->assertRedirect(route('pds.form', 'personal'))
            ->assertSessionHasErrors(['surname', 'sex']);
    }

    public function test_dual_citizenship_needs_its_details(): void
    {
        $this->actingAs($this->employee)
            ->from(route('pds.form', 'personal'))
            ->put(route('pds.form.update', 'personal'), $this->personal(['citizenship' => 'dual']))
            ->assertSessionHasErrors(['dual_by', 'dual_country']);
    }

    public function test_empty_child_rows_are_dropped(): void
    {
        $this->actingAs($this->employee)->put(route('pds.form.update', 'family'), [
            'children' => [
                ['name' => 'Andrea Dela Cruz', 'date_of_birth' => '2014-06-02'],
                ['name' => '', 'date_of_birth' => ''],
            ],
        ]);

        $data = PdsSubmission::where('user_id', $this->employee->id)->first()->form_data;

        $this->assertCount(1, $data['family']['children']);
    }

    public function test_answers_cannot_change_while_hr_is_reviewing(): void
    {
        $this->actingAs($this->employee)->put(route('pds.form.update', 'personal'), $this->personal());
        PdsSubmission::where('user_id', $this->employee->id)->update(['status' => 'submitted']);

        $this->actingAs($this->employee)
            ->put(route('pds.form.update', 'personal'), $this->personal(['surname' => 'CHANGED']))
            ->assertSessionHas('error');

        $this->assertSame(
            'DELA CRUZ',
            PdsSubmission::where('user_id', $this->employee->id)->first()->form_data['personal']['surname'],
        );
    }

    public function test_next_year_starts_from_last_years_answers(): void
    {
        PdsSubmission::create([
            'user_id' => $this->employee->id,
            'applicable_year' => now()->year - 1,
            'status' => 'approved',
            'version' => 1,
            'form_data' => ['personal' => ['surname' => 'FROM LAST YEAR']],
        ]);

        $this->actingAs($this->employee)
            ->get(route('pds.form', 'personal'))
            ->assertSee('value="FROM LAST YEAR"', false);
    }

    public function test_preview_prints_the_answers_into_the_official_workbook(): void
    {
        $this->publishOfficialTemplate();

        $this->actingAs($this->employee)->put(route('pds.form.update', 'personal'), $this->personal());

        $this->actingAs($this->employee)->get(route('pds.form.preview'))->assertOk();

        $submission = PdsSubmission::where('user_id', $this->employee->id)->first();
        $path = Storage::disk('local')->path("pds-generated/{$this->employee->id}_{$submission->applicable_year}.xlsx");

        $this->assertFileExists($path);
        $sheet = IOFactory::load($path)->getSheetByName('C1');
        $this->assertSame('DELA CRUZ', (string) $sheet->getCell('D10')->getValue());
    }

    public function test_the_filled_form_can_be_downloaded_as_excel(): void
    {
        $this->publishOfficialTemplate();
        $this->actingAs($this->employee)->put(route('pds.form.update', 'personal'), $this->personal());

        $response = $this->actingAs($this->employee)->get(route('pds.form.workbook'));

        $response->assertOk();
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('content-type'));

        // The official workbook, filled in — not an export of the answers.
        $book = IOFactory::load($response->getFile()->getPathname());
        $this->assertSame(['C1', 'C2', 'C3', 'C4', 'Lookup'], $book->getSheetNames());
        $this->assertSame('DELA CRUZ', (string) $book->getSheetByName('C1')->getCell('D10')->getValue());
    }

    public function test_excel_download_needs_saved_answers_first(): void
    {
        $this->publishOfficialTemplate();

        $this->actingAs($this->employee)
            ->get(route('pds.form.workbook'))
            ->assertRedirect(route('pds.form'))
            ->assertSessionHas('error');
    }

    public function test_option_one_offers_both_pdf_and_excel(): void
    {
        $this->publishOfficialTemplate();
        $this->actingAs($this->employee)->put(route('pds.form.update', 'personal'), $this->personal());

        $this->actingAs($this->employee)
            ->get(route('pds.editor'))
            ->assertSee('Preview PDF')
            ->assertSee('Download as Excel')
            ->assertSee(route('pds.form.workbook'), false);

        $this->actingAs($this->employee)
            ->get(route('pds.form', 'personal'))
            ->assertSee(route('pds.form.workbook'), false);
    }

    public function test_preview_without_answers_sends_the_employee_to_the_form(): void
    {
        $this->publishOfficialTemplate();

        $this->actingAs($this->employee)
            ->get(route('pds.form.preview'))
            ->assertRedirect(route('pds.form'));
    }

    // ------------------------------------------------------------------
    // The generator
    // ------------------------------------------------------------------

    private function generate(array $data): string
    {
        $template = $this->publishOfficialTemplate();
        $out = Storage::disk('local')->path('test-generated.xlsx');

        return app(PdsWorkbookGenerator::class)->generate($template, $data, $out);
    }

    public function test_answers_land_in_the_official_cells(): void
    {
        $path = $this->generate([
            'personal' => $this->personal([
                'name_extension' => 'Jr.',
                'permanent' => ['city' => 'Candon', 'province' => 'Ilocos Sur'],
            ]),
            'family' => [
                'spouse' => ['surname' => 'DELA CRUZ', 'first_name' => 'JUAN'],
                'children' => [['name' => 'Andrea Dela Cruz', 'date_of_birth' => '2014-06-02']],
            ],
            'education' => [
                'college' => ['school' => 'ISPSC', 'degree' => 'BSEd', 'from' => '2004', 'to' => '2008', 'year_graduated' => '2008'],
            ],
        ]);

        $c1 = IOFactory::load($path)->getSheetByName('C1');
        $cell = fn (string $ref) => trim((string) $c1->getCell($ref)->getValue());

        $this->assertSame('DELA CRUZ', $cell('D10'));
        $this->assertSame('MARIA', $cell('D11'));
        $this->assertStringEndsWith('JR.', $cell('L11'));
        $this->assertSame('14/03/1988', $cell('D13'));
        $this->assertSame('Tagudin', $cell('I22'));
        $this->assertSame('Candon', $cell('I29'));
        $this->assertSame('JUAN', $cell('D37'));
        $this->assertSame('Andrea Dela Cruz', $cell('I37'));
        $this->assertSame('02/06/2014', $cell('M37'));
        $this->assertSame('ISPSC', $cell('D57'));
        $this->assertSame('2008', $cell('M57'));

        // The form asks for N/A rather than blanks.
        $this->assertSame('N/A', $cell('D27'));
        $this->assertSame('N/A', $cell('D54'));
    }

    public function test_tick_boxes_are_ticked_and_every_control_survives(): void
    {
        $original = new \ZipArchive();
        $original->open(resource_path('templates/CS-Form-212-2026.xlsx'));
        $controlsBefore = $this->countControls($original);
        $original->close();

        $path = $this->generate(['personal' => $this->personal()]);

        $zip = new \ZipArchive();
        $zip->open($path);
        $vml = $zip->getFromName('xl/drawings/vmlDrawing1.vml');

        // Female, Married and Filipino — and nothing else on the page.
        $this->assertSame(3, substr_count($vml, '<x:Checked>1</x:Checked>'));
        $this->assertSame($controlsBefore, $this->countControls($zip));
        $zip->close();
    }

    public function test_a_template_with_a_different_layout_is_refused(): void
    {
        $other = new Spreadsheet();
        $other->getActiveSheet()->setTitle('C1');
        $source = Storage::disk('local')->path('other-layout.xlsx');
        @mkdir(dirname($source), 0775, true);
        (new Xlsx($other))->save($source);

        $template = $this->publishOfficialTemplate($source);

        $this->expectException(PdsTemplateMismatch::class);

        app(PdsWorkbookGenerator::class)->generate(
            $template,
            ['personal' => $this->personal()],
            Storage::disk('local')->path('never.xlsx'),
        );
    }

    // ------------------------------------------------------------------
    // Pages 2 to 4
    // ------------------------------------------------------------------

    private function answeredQuestions(array $overrides = []): array
    {
        return array_merge(
            array_fill_keys(array_keys(\App\Support\Pds\PdsFormSchema::QUESTIONS), 'no'),
            $overrides,
        );
    }

    private function workEntry(int $year, array $overrides = []): array
    {
        return array_merge([
            'from' => "{$year}-01-02", 'to' => "{$year}-12-31",
            'position' => "Instructor {$year}", 'department' => 'ISPSC',
            'salary' => '30000', 'grade' => '12-1', 'status' => 'Permanent', 'government' => 'Y',
        ], $overrides);
    }

    public function test_the_remaining_sections_render(): void
    {
        foreach (['eligibility', 'work', 'voluntary', 'learning', 'other', 'questions', 'references'] as $section) {
            $this->actingAs($this->employee)->get(route('pds.form', $section))->assertOk();
        }
    }

    public function test_a_table_row_needs_its_key_answer_and_blank_rows_are_ignored(): void
    {
        $this->actingAs($this->employee)
            ->from(route('pds.form', 'work'))
            ->put(route('pds.form.update', 'work'), ['items' => [
                ['from' => '2020-01-01', 'position' => ''],
                ['from' => '', 'position' => '', 'department' => ''],
            ]])
            ->assertSessionHasErrors('items.0.position');

        $this->actingAs($this->employee)
            ->put(route('pds.form.update', 'work'), ['items' => [
                $this->workEntry(2020),
                ['from' => '', 'position' => ''],
            ]])
            ->assertSessionHasNoErrors();

        $this->assertCount(1, PdsSubmission::where('user_id', $this->employee->id)->first()->form_data['work']['items']);
    }

    public function test_an_end_date_cannot_come_before_the_start(): void
    {
        $this->actingAs($this->employee)
            ->from(route('pds.form', 'work'))
            ->put(route('pds.form.update', 'work'), ['items' => [
                $this->workEntry(2020, ['from' => '2020-05-01', 'to' => '2020-04-01']),
            ]])
            ->assertSessionHasErrors('items.0.to');
    }

    public function test_every_question_must_be_answered_and_a_yes_explained(): void
    {
        $this->actingAs($this->employee)
            ->from(route('pds.form', 'questions'))
            ->put(route('pds.form.update', 'questions'), ['q34a' => 'no'])
            ->assertSessionHasErrors(['q35a', 'q40c']);

        $this->actingAs($this->employee)
            ->from(route('pds.form', 'questions'))
            ->put(route('pds.form.update', 'questions'), $this->answeredQuestions(['q37' => 'yes', 'q34a' => 'yes']))
            ->assertSessionHasErrors(['q37_details', 'q34_details']);

        $this->actingAs($this->employee)
            ->put(route('pds.form.update', 'questions'), $this->answeredQuestions(['q37' => 'yes', 'q37_details' => 'Resigned, 2015']))
            ->assertSessionHasNoErrors();
    }

    public function test_a_section_saved_with_nothing_to_declare_counts_as_done(): void
    {
        $this->actingAs($this->employee)->put(route('pds.form.update', 'voluntary'), ['items' => []]);

        $data = PdsSubmission::where('user_id', $this->employee->id)->first()->form_data;
        $this->assertArrayHasKey('voluntary', $data['_saved']);
    }

    public function test_tables_print_most_recent_first_with_dates_and_salary_formatted(): void
    {
        $path = $this->generate([
            'eligibility' => ['items' => [['name' => 'Career Service Professional', 'rating' => '84.52', 'exam_date' => '2009-08-16']]],
            'work' => ['items' => [
                $this->workEntry(2015),
                $this->workEntry(2021, ['to' => '']),
            ]],
            'learning' => ['items' => [['title' => 'OBE Seminar', 'from' => '2024-03-10', 'to' => '2024-03-12', 'hours' => '24']]],
            'other' => ['skills' => [['text' => 'Public speaking']]],
        ]);

        $book = IOFactory::load($path);
        $c2 = $book->getSheetByName('C2');
        $c3 = $book->getSheetByName('C3');

        $this->assertSame('Career Service Professional', (string) $c2->getCell('A5')->getValue());
        $this->assertSame('16/08/2009', (string) $c2->getCell('G5')->getValue());
        // Newest first, and an open-ended post reads PRESENT.
        $this->assertSame('02/01/2021', (string) $c2->getCell('A18')->getValue());
        $this->assertSame('PRESENT', (string) $c2->getCell('C18')->getValue());
        $this->assertSame('30,000.00', (string) $c2->getCell('J18')->getValue());
        $this->assertSame('02/01/2015', (string) $c2->getCell('A19')->getValue());

        $this->assertSame('OBE Seminar', (string) $c3->getCell('A18')->getValue());
        $this->assertSame('Public speaking', (string) $c3->getCell('A42')->getValue());
        // Empty tables read N/A.
        $this->assertSame('N/A', (string) $c3->getCell('A6')->getValue());
        $this->assertSame('N/A', (string) $c3->getCell('I42')->getValue());
    }

    public function test_a_table_longer_than_its_page_continues_on_a_copy_of_the_page(): void
    {
        $work = array_map(fn ($i) => $this->workEntry(2025 - $i), range(0, 29));

        $path = $this->generate(['work' => ['items' => $work]]);
        $book = IOFactory::load($path);

        $this->assertSame(['C1', 'C2', 'C2 (2)', 'C3', 'C4', 'Lookup'], $book->getSheetNames());

        $continued = $book->getSheetByName('C2 (2)');
        $this->assertSame('Instructor 1996', (string) $continued->getCell('D19')->getValue());
        // Only the table being continued is shown on the continuation page.
        $this->assertFalse($continued->getRowDimension(5)->getVisible());
        $this->assertTrue($continued->getRowDimension(18)->getVisible());
        $this->assertSame('A2:M48', $continued->getPageSetup()->getPrintArea());
        // Page 4's print area still points at page 4.
        $this->assertSame('A1:M71', $book->getSheetByName('C4')->getPageSetup()->getPrintArea());
    }

    public function test_page_four_ticks_one_box_per_question_and_prints_details_only_for_yes(): void
    {
        $path = $this->generate(['questions' => $this->answeredQuestions([
            'q35b' => 'yes', 'q35b_date_filed' => '2020-02-11', 'q35b_status' => 'Dismissed',
            'q40c' => 'yes', 'q40c_details' => 'SP-2021-0045',
            // Saved details on a question answered NO must not print.
            'q36_details' => 'should not appear',
        ])]);

        $zip = new \ZipArchive();
        $zip->open($path);
        $vml = $zip->getFromName('xl/drawings/vmlDrawing2.vml');
        $zip->close();

        $this->assertSame(12, substr_count($vml, '<x:Checked>1</x:Checked>'));
        $this->assertMatchesRegularExpression('/_x0000_s4103".*?<x:Checked>1<\/x:Checked>/s', $vml);

        $c4 = IOFactory::load($path)->getSheetByName('C4');
        $this->assertSame('11/02/2020', (string) $c4->getCell('K20')->getValue());
        $this->assertSame('Dismissed', (string) $c4->getCell('K21')->getValue());
        $this->assertSame('SP-2021-0045', (string) $c4->getCell('L48')->getValue());
        $this->assertSame('', (string) $c4->getCell('I25')->getValue());
    }

    public function test_references_and_government_id_are_printed(): void
    {
        $path = $this->generate(['references' => [
            'references' => [['name' => 'DR. JOSE RIZAL', 'address' => 'ISPSC Tagudin', 'contact' => '0917 000 1111']],
            'government_id' => ['type' => 'PRC ID', 'number' => '1234567', 'issued' => '14/03/2019, Baguio City'],
        ]]);

        $c4 = IOFactory::load($path)->getSheetByName('C4');
        $this->assertSame('DR. JOSE RIZAL', (string) $c4->getCell('A52')->getValue());
        $this->assertSame('ISPSC Tagudin', (string) $c4->getCell('F52')->getValue());
        $this->assertSame('PRC ID', (string) $c4->getCell('D61')->getValue());
        $this->assertSame('1234567', (string) $c4->getCell('D62')->getValue());
    }

    // ------------------------------------------------------------------
    // Submitting
    // ------------------------------------------------------------------

    public function test_submitting_an_incomplete_pds_sends_the_employee_to_what_is_missing(): void
    {
        $this->publishOfficialTemplate();
        $this->actingAs($this->employee)->put(route('pds.form.update', 'personal'), $this->personal());

        $this->actingAs($this->employee)
            ->post(route('pds.form.submit'))
            ->assertRedirect(route('pds.form', 'questions'))
            ->assertSessionHas('error');

        $this->assertNotSame('submitted', PdsSubmission::where('user_id', $this->employee->id)->value('status'));
    }

    public function test_submitting_files_the_printed_form_and_sends_it_to_hr(): void
    {
        $this->publishOfficialTemplate();
        $this->actingAs($this->employee)->put(route('pds.form.update', 'personal'), $this->personal());
        $this->actingAs($this->employee)->put(route('pds.form.update', 'questions'), $this->answeredQuestions());

        $this->actingAs($this->employee)
            ->post(route('pds.form.submit'))
            ->assertRedirect(route('pds.editor'))
            ->assertSessionHas('success');

        $submission = PdsSubmission::where('user_id', $this->employee->id)->first();

        $this->assertSame('submitted', $submission->status);
        $this->assertTrue($submission->workbookExists());
        $this->assertSame('DELA CRUZ', (string) IOFactory::load($submission->workbookPath())->getSheetByName('C1')->getCell('D10')->getValue());
        $this->assertDatabaseHas('activity_logs', ['action' => 'pds.generated']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'pds.submitted']);

        // And it is locked while HR reviews it.
        $this->actingAs($this->employee)
            ->post(route('pds.form.submit'))
            ->assertRedirect(route('pds.editor'))
            ->assertSessionHas('error');
    }

    // ------------------------------------------------------------------
    // One look for every answer
    // ------------------------------------------------------------------

    /**
     * The template's answer cells run from 5pt to 10pt, bold and not, italic
     * and not, left, centred and "general". Every answer printed must instead
     * be in the one house style, whichever cell it lands in.
     */
    public function test_every_printed_answer_is_in_the_same_style(): void
    {
        $path = $this->generate([
            'personal' => $this->personal([
                'height' => '1.58', 'umid' => '0111-2345678-9', 'tin' => '123-456-789-000',
                'permanent' => ['city' => 'Candon', 'province' => 'Ilocos Sur', 'zip' => '2710'],
                'citizenship' => 'dual', 'dual_by' => 'birth', 'dual_country' => 'Japan',
            ]),
            'family' => [
                'spouse' => ['surname' => 'DELA CRUZ', 'first_name' => 'JUAN'],
                'children' => [['name' => 'Andrea Dela Cruz', 'date_of_birth' => '2014-06-02']],
            ],
            'education' => ['college' => ['school' => 'ISPSC', 'degree' => 'BSEd', 'from' => '2004', 'to' => '2008']],
            'eligibility' => ['items' => [['name' => 'Career Service Professional', 'rating' => '84.52', 'exam_date' => '2009-08-16']]],
            'work' => ['items' => [$this->workEntry(2021)]],
            'voluntary' => ['items' => [['organization' => 'Red Cross', 'from' => '2019-06-01', 'hours' => '40']]],
            'learning' => ['items' => [['title' => 'OBE Seminar', 'from' => '2024-03-10', 'hours' => '24', 'sponsor' => 'CHED']]],
            'other' => ['skills' => [['text' => 'Public speaking']]],
            'questions' => $this->answeredQuestions(['q35b' => 'yes', 'q35b_date_filed' => '2020-02-11', 'q35b_status' => 'Dismissed']),
            'references' => [
                'references' => [['name' => 'DR. JOSE RIZAL', 'address' => 'ISPSC', 'contact' => '0917 000 1111']],
                'government_id' => ['type' => 'PRC ID', 'number' => '1234567'],
            ],
        ]);

        $template = IOFactory::load(resource_path('templates/CS-Form-212-2026.xlsx'));
        $filled = IOFactory::load($path);
        $checked = 0;

        foreach (['C1', 'C2', 'C3', 'C4'] as $name) {
            $before = $template->getSheetByName($name);
            $after = $filled->getSheetByName($name);

            foreach ($after->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $ref = $cell->getCoordinate();
                    $value = $cell->getValue();
                    $value = $value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText ? $value->getPlainText() : $value;

                    // The name-extension boxes keep the form's own caption
                    // beside the answer, so they are two styles by design.
                    if ($value === null || $value === '' || ($name === 'C1' && in_array($ref, ['L11', 'G37', 'G44'], true))) {
                        continue;
                    }

                    $original = $before->getCell($ref)->getValue();
                    $original = $original instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText ? $original->getPlainText() : $original;

                    // Only what the system wrote: cells the template left empty
                    // (the one caption rewritten, B56, is excluded).
                    if (($original !== null && $original !== '') || ($name === 'C1' && $ref === 'B56')) {
                        continue;
                    }

                    $style = $after->getStyle($ref);
                    $font = $style->getFont();
                    $where = "{$name}!{$ref} ('{$value}')";

                    $this->assertSame('Arial Narrow', $font->getName(), "{$where} font");
                    $this->assertTrue($font->getBold(), "{$where} is not bold");
                    $this->assertFalse($font->getItalic(), "{$where} is italic");
                    $this->assertSame(9.0, (float) $font->getSize(), "{$where} size");
                    $this->assertSame('FF000000', $font->getColor()->getARGB(), "{$where} colour");
                    $this->assertContains($style->getAlignment()->getHorizontal(), ['left', 'center'], "{$where} alignment");
                    $this->assertSame('center', $style->getAlignment()->getVertical(), "{$where} vertical alignment");
                    $checked++;
                }
            }
        }

        // Enough answers were looked at for the check to mean something.
        $this->assertGreaterThan(60, $checked);
    }

    public function test_text_too_long_for_its_box_is_set_smaller_rather_than_cut_off(): void
    {
        $long = str_repeat('Seminar-Workshop on Outcomes-Based Education and Assessment ', 3);

        $path = $this->generate(['learning' => ['items' => [['title' => trim($long), 'from' => '2024-03-10']]]]);
        $font = IOFactory::load($path)->getSheetByName('C3')->getStyle('A18')->getFont();

        $this->assertLessThan(9.0, (float) $font->getSize());
        $this->assertGreaterThanOrEqual(6.0, (float) $font->getSize());
    }

    // ------------------------------------------------------------------
    // N/A, badges, notices
    // ------------------------------------------------------------------

    public function test_na_is_accepted_wherever_something_does_not_apply(): void
    {
        $this->actingAs($this->employee)
            ->put(route('pds.form.update', 'education'), [
                'vocational' => ['school' => 'N/A', 'from' => 'N/A', 'to' => 'n/a', 'year_graduated' => 'NA'],
                'graduate' => ['school' => 'N/A', 'from' => 'N/A', 'to' => 'N/A', 'year_graduated' => 'N/A'],
                'college' => ['school' => 'ISPSC', 'from' => '2004', 'to' => '2008', 'year_graduated' => '2008'],
            ])
            ->assertSessionHasNoErrors();

        $data = PdsSubmission::where('user_id', $this->employee->id)->first()->form_data['education'];

        // Stored as not applicable, which the form prints as N/A.
        $this->assertArrayNotHasKey('vocational', $data);
        $this->assertSame('2004', $data['college']['from']);

        $path = $this->generate(['education' => $data]);
        $this->assertSame('N/A', (string) IOFactory::load($path)->getSheetByName('C1')->getCell('D56')->getValue());
    }

    public function test_hr_sees_how_many_pds_await_review_in_the_sidebar(): void
    {
        $hr = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        foreach (range(1, 3) as $i) {
            PdsSubmission::create([
                'user_id' => User::factory()->create(['role' => 'employee'])->id,
                'applicable_year' => now()->year, 'status' => 'submitted', 'version' => 1,
            ]);
        }
        // Approved ones, and HR's own, are not waiting on HR.
        PdsSubmission::create(['user_id' => User::factory()->create(['role' => 'employee'])->id, 'applicable_year' => now()->year, 'status' => 'approved', 'version' => 1]);
        PdsSubmission::create(['user_id' => $hr->id, 'applicable_year' => now()->year, 'status' => 'submitted', 'version' => 1]);

        $html = $this->actingAs($hr)->get(route('admin.dashboard'))->getContent();

        $this->assertMatchesRegularExpression('/PDS Requests.{0,400}?>\s*3\s*</s', $html);
    }

    public function test_the_dashboard_explains_what_was_returned_and_why(): void
    {
        PdsSubmission::create([
            'user_id' => $this->employee->id, 'applicable_year' => now()->year, 'status' => 'returned',
            'version' => 1, 'return_remarks' => 'Please add your 2024 trainings.', 'reviewed_at' => now(),
        ]);

        $this->actingAs($this->employee)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('Needs your attention')
            ->assertSee('Personal Data Sheet (' . now()->year . ')')
            ->assertSee('Please add your 2024 trainings.')
            ->assertSee('Correct my PDS');
    }

    public function test_if_yes_captions_print_without_their_typed_underscores(): void
    {
        $path = $this->generate(['questions' => $this->answeredQuestions(['q37' => 'yes', 'q37_details' => 'Resigned, 2015'])]);
        $c4 = IOFactory::load($path)->getSheetByName('C4');

        foreach (['G10', 'G14', 'G19', 'G24', 'G28'] as $caption) {
            $text = (string) $c4->getCell($caption)->getValue();
            $this->assertStringContainsString('If YES, give details:', $text);
            $this->assertStringNotContainsString('_', $text);
        }

        $this->assertSame('Resigned, 2015', (string) $c4->getCell('I29')->getValue());
    }

    public function test_the_fill_in_page_has_no_breadcrumb(): void
    {
        $this->actingAs($this->employee)
            ->get(route('pds.form', 'personal'))
            ->assertOk()
            ->assertDontSee('aria-label="Breadcrumb"', false);
    }

    // ------------------------------------------------------------------
    // The My PDS page
    // ------------------------------------------------------------------

    public function test_every_role_is_offered_both_ways_to_file_a_pds(): void
    {
        $this->publishOfficialTemplate();

        foreach (['employee', 'dean', 'campus_director', 'admin'] as $role) {
            $person = User::factory()->create(['role' => $role, 'status' => 'active']);

            $this->actingAs($person)
                ->get(route('pds.editor'))
                ->assertOk()
                ->assertSee('Choose how to fill in your PDS')
                ->assertSee('Fill in on screen')
                ->assertSee('Download, fill in Excel, upload')
                ->assertSee(route('pds.form'), false)
                ->assertSee(route('pds.template.download'), false)
                ->assertSee(route('pds.upload'), false);
        }
    }

    public function test_an_uploaded_draft_is_labelled_and_can_be_submitted(): void
    {
        $this->publishOfficialTemplate();
        $submission = app(\App\Services\PdsSubmissionService::class)->forYear($this->employee);
        Storage::disk('local')->put('pds-working/draft.xlsx', 'x');
        $submission->update(['status' => 'draft', 'file_path' => 'pds-working/draft.xlsx', 'file_original_name' => 'my-own-pds.xlsx', 'uploaded_at' => now()]);

        $this->actingAs($this->employee)
            ->get(route('pds.editor'))
            ->assertSee('Uploaded from Excel')
            ->assertSee('my-own-pds.xlsx')
            ->assertSee('Submit PDS to HR')
            // Still editable, so the choice is offered for making changes.
            ->assertSee('Need to make changes? Choose how');
    }

    public function test_the_options_are_withdrawn_while_hr_reviews(): void
    {
        $this->publishOfficialTemplate();
        $this->actingAs($this->employee)->put(route('pds.form.update', 'personal'), $this->personal());
        $this->actingAs($this->employee)->put(route('pds.form.update', 'questions'), $this->answeredQuestions());
        $this->actingAs($this->employee)->post(route('pds.form.submit'));

        $this->actingAs($this->employee)
            ->get(route('pds.editor'))
            ->assertSee('Filled in on screen')
            ->assertSee('with HR for review')
            ->assertDontSee('Download, fill in Excel, upload')
            ->assertDontSee(route('pds.upload'), false);
    }

    public function test_without_a_published_template_neither_option_is_offered(): void
    {
        $this->actingAs($this->employee)
            ->get(route('pds.editor'))
            ->assertOk()
            ->assertSee('HR has not published a PDS template yet')
            ->assertDontSee('Fill in on screen');
    }

    private function countControls(\ZipArchive $zip): int
    {
        $count = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_starts_with($zip->getNameIndex($i), 'xl/ctrlProps/')) {
                $count++;
            }
        }

        return $count;
    }
}
