<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HrPolicy;
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
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $categories = HrPolicy::whereNotNull('category')->distinct()->pluck('category');

        return view('admin.policies.index', compact('policies', 'categories'));
    }

    public function create()
    {
        return view('admin.policies.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $policy = HrPolicy::create([
            ...$data,
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

        $policy->fill($data);

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

    private function validated(Request $request, ?HrPolicy $policy = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'type' => ['required', 'in:text,file'],
            'body' => ['required_if:type,text', 'nullable', 'string'],
            'file' => [
                $policy && $policy->type === 'file' ? 'nullable' : 'required_if:type,file',
                'nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx', 'max:10240',
            ],
            'is_published' => ['nullable', 'boolean'],
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