<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PdsTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PdsTemplateController extends Controller
{
    // No index() here anymore — templates are listed inside
    // PdsReviewController@index (the main PDS review page) instead.

    public function store(Request $request)
    {
        $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);

        $path = $request->file('file')->store('pds-templates', 'public');

        PdsTemplate::create([
            'label' => $request->label,
            'file_path' => $path,
            'original_filename' => $request->file('file')->getClientOriginalName(),
            'is_active' => false,
            'uploaded_by' => Auth::id(),
        ]);

        return redirect()->route('admin.pds.index')
            ->with('success', 'Template uploaded. Activate it to make it the current form.');
    }

    public function activate(PdsTemplate $template)
    {
        PdsTemplate::where('is_active', true)->update(['is_active' => false]);
        $template->update(['is_active' => true]);

        return redirect()->route('admin.pds.index')
            ->with('success', "\"{$template->label}\" is now the active PDS template.");
    }

    public function destroy(PdsTemplate $template)
    {
        $wasActive = $template->is_active;

        Storage::disk('public')->delete($template->file_path);
        $template->delete();

        $message = $wasActive
            ? "\"{$template->label}\" was deleted. No template is active — employees can't open the PDS editor until you activate another one."
            : "\"{$template->label}\" deleted.";

        return redirect()->route('admin.pds.index')
            ->with($wasActive ? 'error' : 'success', $message);
    }
}