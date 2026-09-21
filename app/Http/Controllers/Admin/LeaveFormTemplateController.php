<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveFormTemplate;
use App\Models\LedgerTemplate;
use App\Services\TemplatePublisher;
use App\Services\XlsxToPdfService;
use App\Support\DocumentName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * HR publishes the blank leave form employees download, and seeds the master
 * ledger workbook every employee's own ledger is copied from.
 */
class LeaveFormTemplateController extends Controller
{
    public function __construct(private TemplatePublisher $publisher)
    {
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('admin.leave.templates', [
            // withCount, because the version table prints a filed-forms tally
            // on every row and counting per row is a query per row.
            'templates' => LeaveFormTemplate::with('uploader')
                ->withCount('applications')->orderByDesc('version')
                ->paginate(10, ['*'], 'leave_templates_page')->withQueryString(),
            'ledgerTemplates' => LedgerTemplate::with('uploader')->orderByDesc('version')
                ->paginate(10, ['*'], 'ledger_templates_page')->withQueryString(),
            'activeLedger' => LedgerTemplate::active(),
            'ledgersInUse' => \App\Models\EmployeeLedger::count(),
            // All three blank forms HR publishes live on one screen, rather
            // than the PDS one hiding inside a panel on another page.
            'pdsTemplates' => \App\Models\PdsTemplate::with('uploader')
                ->withCount('submissions')->orderByDesc('version')
                ->paginate(10, ['*'], 'pds_templates_page')->withQueryString(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'template' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'template.mimes' => 'The leave form must be an Excel workbook (.xlsx), '
                . 'so the system can convert the filled-in copy to PDF.',
        ]);

        $template = $this->publisher->publish(
            LeaveFormTemplate::class,
            $request->file('template'),
            $request->label,
            'leave-form-templates',
            $request->notes,
        );

        return back()->with('success',
            "Published \"{$template->label}\" as version {$template->version}. Employees download this version now.");
    }

    /**
     * Seeds or replaces the master ledger. Existing employee ledgers keep the
     * copy they were created from — a new master only affects ledgers created
     * after it.
     */
    public function storeLedgerMaster(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'template' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'template.mimes' => 'The master ledger must be an .xlsx workbook.',
        ]);

        $template = $this->publisher->publish(
            LedgerTemplate::class,
            $request->file('template'),
            $request->label,
            'ledger-templates',
            $request->notes,
        );

        $inUse = \App\Models\EmployeeLedger::count();

        return back()->with('success',
            "Master ledger v{$template->version} seeded."
            . ($inUse > 0
                ? " {$inUse} existing ledger(s) keep the version they were created from."
                : ' New employee ledgers will be copied from it.'));
    }

    /**
     * The published blank form, rendered so HR can actually read it.
     *
     * "View" used to link straight at the stored .xlsx. No browser renders a
     * workbook, so the new tab opened empty and the button looked broken.
     * Converting it is the same job the employee's filled form goes through,
     * so it comes out on A4 looking like the paper form.
     */
    public function preview(Request $request, LeaveFormTemplate $template, XlsxToPdfService $converter)
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($template->exists(), 404, 'The uploaded file for this version is missing.');

        // The original workbook, for HR to edit and re-publish. Served through
        // here rather than the /storage URL, which is built from APP_URL and
        // so points at the wrong host whenever that setting is stale.
        if ($request->query('format') === 'xlsx') {
            return Storage::disk('public')->download(
                $template->file_path,
                DocumentName::template('Leave Form Template', $template->version, 'xlsx'),
            );
        }

        return $converter->stream(
            $template->absolutePath(),
            DocumentName::template('Leave Form Template', $template->version),
            // The checksum pins the cache to this exact upload, so a
            // re-published version never serves the previous one's preview.
            cacheKey: 'leave-form-template:' . $template->checksum,
        );
    }

    public function activate(Request $request, LeaveFormTemplate $template)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $this->publisher->activate($template);

        return back()->with('success',
            "Version {$template->version} of \"{$template->label}\" is now the active leave form.");
    }

    public function activateLedgerMaster(Request $request, LedgerTemplate $template)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $this->publisher->activate($template);

        return back()->with('success', "Master ledger v{$template->version} is now active.");
    }

    /**
     * A version that leave forms were filled on is retired, never deleted.
     */
    public function destroy(Request $request, LeaveFormTemplate $template)
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($template->applications()->exists()) {
            $template->update(['is_active' => false, 'superseded_at' => now()]);

            return back()->with('success',
                "Version {$template->version} has leave forms filled on it, so it was retired rather than deleted.");
        }

        abort_if($template->is_active, 422,
            'Activate a different version before deleting the active template.');

        Storage::disk('public')->delete($template->file_path);
        $version = $template->version;
        $template->delete();

        return back()->with('success', "Version {$version} deleted.");
    }
}
