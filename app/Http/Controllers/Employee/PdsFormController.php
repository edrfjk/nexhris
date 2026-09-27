<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PdsSubmission;
use App\Models\PdsTemplate;
use App\Models\User;
use App\Services\Pds\PdsTemplateMismatch;
use App\Services\Pds\PdsWorkbookGenerator;
use App\Services\PdsSubmissionService;
use App\Services\XlsxToPdfService;
use App\Support\DocumentName;
use App\Support\Pds\PdsFormSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * The PDS filled in on screen, section by section, and printed into the
 * official CS Form 212 on demand.
 *
 * Answers belong to the year's submission, so they are saved as the employee
 * goes and nothing is lost by leaving half way. Next year's form starts from
 * this year's answers.
 */
class PdsFormController extends Controller
{
    public function __construct(private PdsSubmissionService $pds)
    {
    }

    public function edit(?string $section = null)
    {
        $section ??= array_key_first(PdsFormSchema::sections());
        abort_unless(PdsFormSchema::has($section), 404);

        $submission = $this->pds->forYear(Auth::user());

        return view('employee.pds.form', [
            'submission' => $submission,
            'sections' => PdsFormSchema::sections(),
            'section' => $section,
            'data' => $this->answers($submission, Auth::user()),
            'template' => PdsTemplate::active(),
        ]);
    }

    public function update(Request $request, string $section)
    {
        abort_unless(PdsFormSchema::has($section), 404);

        $submission = $this->pds->forYear($request->user());

        if (! $submission->isEditable()) {
            return back()->with('error', $submission->isApproved()
                ? 'Your PDS for this year has already been approved by HR.'
                : 'Your PDS is with HR for review. Ask them to return it if you need to change something.');
        }

        // "N/A" typed into a box means the same as leaving it blank — the
        // form prints N/A for either — so it must not trip a year or date
        // rule. Treat it as blank before anything is validated.
        $request->replace($this->withoutNotApplicable($request->all()));

        // Blank rows from the form's tables are noise, not entries. Drop them
        // before validating, so a row is only held to its rules once the
        // employee has actually started it.
        $tables = array_keys(PdsFormSchema::lists()[$section] ?? []);

        if ($section === 'family') {
            $tables[] = 'children';
        }

        foreach ($tables as $table) {
            $request->merge([$table => $this->withoutBlankRows($request->input($table))]);
        }

        $validated = $request->validate(
            PdsFormSchema::rules($section),
            PdsFormSchema::messages($section),
            PdsFormSchema::attributes($section),
        );

        if ($section === 'questions' && $missing = PdsFormSchema::missingDetails($validated)) {
            throw \Illuminate\Validation\ValidationException::withMessages($missing);
        }

        $data = $submission->form_data ?: $this->answers($submission, $request->user());
        $data[$section] = $this->clean($validated);
        // Saved, even if there was nothing to declare — the step list ticks it.
        $data['_saved'][$section] = now()->toIso8601String();

        $submission->update([
            'form_data' => $data,
            'form_updated_at' => now(),
        ]);

        $sections = array_keys(PdsFormSchema::sections());
        $next = $sections[array_search($section, $sections, true) + 1] ?? null;

        if ($request->input('then') === 'stay' || ! $next) {
            return redirect()->route('pds.form', $section)
                ->with('success', PdsFormSchema::sections()[$section]['title'] . ' saved.');
        }

        return redirect()->route('pds.form', $next)
            ->with('success', PdsFormSchema::sections()[$section]['title'] . ' saved.');
    }

    /** The answers printed into the official form, shown in the browser. */
    public function preview(
        Request $request,
        PdsWorkbookGenerator $generator,
        XlsxToPdfService $converter,
    ) {
        $user = $request->user();
        $submission = $this->pds->forYear($user);
        $template = PdsTemplate::active();

        if (! $template) {
            return back()->with('error', 'HR has not published a PDS template yet.');
        }

        if (! $submission->form_data) {
            return redirect()->route('pds.form')
                ->with('error', 'Fill in and save at least one section before previewing.');
        }

        $path = Storage::disk('local')->path(
            "pds-generated/{$submission->user_id}_{$submission->applicable_year}.xlsx"
        );

        try {
            $generator->generate($template, $submission->form_data, $path);
        } catch (PdsTemplateMismatch $e) {
            return back()->with('error', $e->getMessage());
        }

        return $converter->stream(
            $path,
            DocumentName::personalDataSheet($user, $submission->applicable_year),
            // Keyed to the owner and to when they last saved, so a preview is
            // never another person's, nor an earlier draft of their own.
            cacheKey: 'pds-form-' . $submission->id . '-' . optional($submission->form_updated_at)->timestamp,
        );
    }

    /**
     * The answers printed into the official workbook, as an .xlsx to keep,
     * print from Excel, or finish by hand — the same file the preview shows.
     */
    public function downloadWorkbook(Request $request, PdsWorkbookGenerator $generator)
    {
        $user = $request->user();
        $submission = $this->pds->forYear($user);
        $template = PdsTemplate::active();

        if (! $template) {
            return back()->with('error', 'HR has not published a PDS template yet.');
        }

        if (! $submission->form_data) {
            return redirect()->route('pds.form')
                ->with('error', 'Fill in and save at least one section before downloading.');
        }

        $path = Storage::disk('local')->path(
            "pds-generated/{$submission->user_id}_{$submission->applicable_year}.xlsx"
        );

        try {
            $generator->generate($template, $submission->form_data, $path);
        } catch (PdsTemplateMismatch $e) {
            return back()->with('error', $e->getMessage());
        }

        return response()->download(
            $path,
            DocumentName::personalDataSheet($user, $submission->applicable_year, 'xlsx'),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * Prints the answers into the official form, files it as this year's PDS
     * and sends it to HR — the same filing an uploaded workbook gets.
     */
    public function submit(Request $request, PdsWorkbookGenerator $generator)
    {
        $user = $request->user();
        $submission = $this->pds->forYear($user);
        $template = PdsTemplate::active();

        if (! $submission->isEditable()) {
            return redirect()->route('pds.editor')->with('error', $submission->isApproved()
                ? 'Your PDS for this year has already been approved by HR.'
                : 'Your PDS is already with HR for review.');
        }

        if (! $template) {
            return back()->with('error', 'HR has not published a PDS template yet.');
        }

        if ($missing = $this->incompleteSection($submission->form_data ?? [])) {
            return redirect()->route('pds.form', $missing[0])->with('error', $missing[1]);
        }

        $path = Storage::disk('local')->path(
            "pds-generated/{$submission->user_id}_{$submission->applicable_year}.xlsx"
        );

        try {
            $generator->generate($template, $submission->form_data, $path);
        } catch (PdsTemplateMismatch $e) {
            return back()->with('error', $e->getMessage());
        }

        $submission = $this->pds->storeGenerated(
            $submission,
            $path,
            DocumentName::personalDataSheet($user, $submission->applicable_year, 'xlsx'),
            $template,
        );
        $this->pds->submit($submission);

        return redirect()->route('pds.editor')
            ->with('success', 'Your PDS was printed in the official format and submitted to HR for review.');
    }

    /**
     * The first section that still needs answers before the PDS can go to
     * HR, with a message saying what is missing.
     *
     * @return array{0: string, 1: string}|null
     */
    private function incompleteSection(array $data): ?array
    {
        $personal = $data['personal'] ?? [];

        foreach (['surname', 'first_name', 'date_of_birth', 'sex', 'civil_status', 'citizenship'] as $field) {
            if (! filled($personal[$field] ?? null)) {
                return ['personal', 'Complete and save your Personal Information before submitting.'];
            }
        }

        $questions = $data['questions'] ?? [];

        foreach (array_keys(PdsFormSchema::QUESTIONS) as $question) {
            if (! in_array($questions[$question] ?? null, ['yes', 'no'], true)) {
                return ['questions', 'Answer every one of questions 34 to 40 and save them before submitting.'];
            }
        }

        return null;
    }

    /**
     * What the form shows: this year's answers, else last year's, else what
     * the account already knows about the person.
     */
    private function answers(PdsSubmission $submission, User $user): array
    {
        if ($submission->form_data) {
            return $submission->form_data;
        }

        $previous = PdsSubmission::where('user_id', $user->id)
            ->where('applicable_year', '<', $submission->applicable_year)
            ->whereNotNull('form_data')
            ->orderByDesc('applicable_year')
            ->value('form_data');

        if ($previous) {
            $previous = is_array($previous) ? $previous : (json_decode($previous, true) ?: []);

            // Last year's answers are a starting point; this year's sections
            // still have to be looked over and saved.
            unset($previous['_saved']);

            return $previous;
        }

        return [
            'personal' => array_filter([
                'surname' => $user->last_name,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'email' => $user->email,
                'mobile' => $user->contact_number,
                'agency_employee_no' => $user->employee_number,
                'citizenship' => 'filipino',
            ], 'filled'),
        ];
    }

    /** Blanks every answer typed as N/A (n/a, NA, N.A.), at any depth. */
    private function withoutNotApplicable(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $input[$key] = $this->withoutNotApplicable($value);
            } elseif (is_string($value) && preg_match('#^\s*n\s*[/.]?\s*a\.?\s*$#i', $value)) {
                $input[$key] = '';
            }
        }

        return $input;
    }

    /** @return array<int, array> the rows that have at least one answer */
    private function withoutBlankRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, fn ($row) => is_array($row)
            && collect($row)->contains(fn ($value) => is_string($value) ? trim($value) !== '' : filled($value))));
    }

    /** Trims every answer and drops the empty ones. */
    private function clean(array $values): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $value = $this->clean($value);
                $value = Arr::isList($value) ? array_values($value) : $value;
            } elseif (is_string($value)) {
                $value = trim($value);
            }

            if (filled($value)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
