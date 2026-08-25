<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PdsTemplate;
use App\Services\TemplatePublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Templates are listed inside PdsReviewController@index rather than on a page
 * of their own.
 */
class PdsTemplateController extends Controller
{
    public function __construct(private TemplatePublisher $publisher)
    {
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'file.mimes' => 'The PDS template must be an .xlsx workbook.',
        ]);

        // Publishing is additive: this becomes the next version and the
        // previous one is retired, so submissions filled on it stay readable.
        $template = $this->publisher->publish(
            PdsTemplate::class,
            $request->file('file'),
            $request->label,
            'pds-templates',
            $request->notes,
        );

        return redirect()->route('admin.pds.index')->with('success',
            "Published \"{$template->label}\" as version {$template->version}. "
            . 'Employees now download this version.');
    }

    /** Rolls back to an earlier version. */
    public function activate(Request $request, PdsTemplate $template)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $this->publisher->activate($template);

        return redirect()->route('admin.pds.index')->with('success',
            "Version {$template->version} of \"{$template->label}\" is now the active PDS template.");
    }

    /**
     * A version that submissions were filled on is never deleted — doing so
     * would orphan those records. It is retired instead.
     */
    public function destroy(Request $request, PdsTemplate $template)
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($template->submissions()->exists()) {
            $template->update(['is_active' => false, 'superseded_at' => now()]);

            return redirect()->route('admin.pds.index')->with('success',
                "Version {$template->version} has submissions filled on it, so it was retired rather than deleted.");
        }

        abort_if($template->is_active, 422,
            'Activate another version before deleting the active template.');

        Storage::disk('public')->delete($template->file_path);
        $label = $template->label;
        $version = $template->version;
        $template->delete();

        return redirect()->route('admin.pds.index')
            ->with('success', "Version {$version} of \"{$label}\" was deleted.");
    }
}
