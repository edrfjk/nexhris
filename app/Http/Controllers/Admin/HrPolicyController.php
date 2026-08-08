<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HrPolicy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class HrPolicyController extends Controller
{
    public function index(Request $request)
    {
        $policies = HrPolicy::with('creator')
            ->when($request->search, fn ($q, $search) => $q->where('title', 'like', "%{$search}%"))
            ->when($request->category, fn ($q, $category) => $q->where('category', $category))
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->when($request->status, function ($q, $status) {
                $q->where('is_published', $status === 'published');
            })
            ->orderByDesc('is_pinned')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $categories = HrPolicy::whereNotNull('category')->distinct()->pluck('category');

        $totalCount = HrPolicy::count();
        $publishedCount = HrPolicy::where('is_published', true)->count();
        $draftCount = HrPolicy::where('is_published', false)->count();
        $fileCount = HrPolicy::where('type', 'file')->count();

        return view('admin.policies.index', compact(
            'policies', 'categories', 'totalCount', 'publishedCount', 'draftCount', 'fileCount'
        ));
    }

    public function create()
    {
        return view('admin.policies.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $policy = HrPolicy::create([
            ...collect($data)->except('file')->toArray(),
            'created_by' => Auth::id(),
            'published_at' => $data['is_published'] ?? false ? now() : null,
        ]);

        if ($request->hasFile('file')) {
            $this->storeFile($request, $policy);
        }

        return redirect()->route('admin.policies.index')->with('success', 'Policy created successfully.');
    }

    public function edit(HrPolicy $policy)
    {
        return view('admin.policies.edit', compact('policy'));
    }

    public function update(Request $request, HrPolicy $policy)
    {
        $data = $this->validated($request, $policy);

        $wasPublished = $policy->is_published;
        $nowPublished = $data['is_published'] ?? false;

        $policy->fill(collect($data)->except('file')->toArray());

        if (!$wasPublished && $nowPublished) {
            $policy->published_at = now();
        } elseif (!$nowPublished) {
            $policy->published_at = null;
        }

        $policy->save();

        if ($request->hasFile('file')) {
            if ($policy->file_path) {
                Storage::disk('public')->delete($policy->file_path);
            }
            $this->storeFile($request, $policy);
        }

        return redirect()->route('admin.policies.index')->with('success', 'Policy updated successfully.');
    }

    public function destroy(HrPolicy $policy)
    {
        if ($policy->file_path) {
            Storage::disk('public')->delete($policy->file_path);
        }
        $policy->delete();

        return redirect()->route('admin.policies.index')->with('success', 'Policy deleted.');
    }

    public function togglePublish(HrPolicy $policy)
    {
        $policy->update([
            'is_published' => !$policy->is_published,
            'published_at' => !$policy->is_published ? now() : null,
        ]);

        return back()->with('success', $policy->is_published ? 'Policy published.' : 'Policy unpublished.');
    }

    public function togglePin(HrPolicy $policy)
    {
        $policy->update(['is_pinned' => !$policy->is_pinned]);

        return back()->with('success', $policy->is_pinned ? 'Policy pinned.' : 'Policy unpinned.');
    }

    public function compliance(HrPolicy $policy)
    {
        abort_unless($policy->requires_acknowledgment, 404);

        $employees = User::where('role', 'employee')
            ->where('status', 'active')
            ->with(['policyViews' => fn ($q) => $q->where('hr_policy_id', $policy->id)])
            ->orderBy('name')
            ->get();

        return view('admin.policies.compliance', compact('policy', 'employees'));
    }

    private function validated(Request $request, ?HrPolicy $policy = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'type' => ['required', 'in:text,file,link'],
            'body' => ['required_if:type,text', 'nullable', 'string'],
            'link_url' => ['required_if:type,link', 'nullable', 'url', 'max:2048'],
            'file' => [
                $policy && $policy->type === 'file' ? 'nullable' : 'required_if:type,file',
                'nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx', 'max:10240',
            ],
            'is_published' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
            'requires_acknowledgment' => ['nullable', 'boolean'],
            'effective_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
        ]);
    }

    private function storeFile(Request $request, HrPolicy $policy): void
    {
        $file = $request->file('file');
        $path = $file->store('hr-policies', 'public');

        $policy->update([
            'file_path' => $path,
            'file_original_name' => $file->getClientOriginalName(),
        ]);
    }
}