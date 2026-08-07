@extends('layouts.app')
@section('title', 'Edit HR Policy')

@section('content')
<x-page-header title="Edit HR Policy" :subtitle="$policy->title">
    <x-slot:actions>
        <a href="{{ route('admin.policies.index') }}"
           class="inline-flex items-center gap-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            Back
        </a>
    </x-slot:actions>
</x-page-header>

<form method="POST" action="{{ route('admin.policies.update', $policy) }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Policy Details</h3>
        </div>

        <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input name="title" value="{{ old('title', $policy->title) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category (optional)</label>
                    <input name="category" value="{{ old('category', $policy->category) }}" list="category-list"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                    <datalist id="category-list">
                        @foreach (\App\Models\HrPolicy::whereNotNull('category')->distinct()->pluck('category') as $cat)
                            <option value="{{ $cat }}">
                        @endforeach
                    </datalist>
                </div>
            </div>

            <div class="pt-2 border-t border-gray-100">
                <label class="block text-sm font-medium text-gray-700 mb-2">Policy Format</label>
                <div class="flex gap-3">
                    <label class="flex-1 flex items-center gap-2 border border-gray-300 rounded-lg px-4 py-3 cursor-pointer has-[:checked]:border-maroon-700 has-[:checked]:bg-maroon-50 transition">
                        <input type="radio" name="type" value="text" {{ $policy->type === 'text' ? 'checked' : '' }} onchange="togglePolicyType('text')" class="text-maroon-700 focus:ring-maroon-700">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Write text directly</p>
                            <p class="text-xs text-gray-400">Use the rich text editor below</p>
                        </div>
                    </label>
                    <label class="flex-1 flex items-center gap-2 border border-gray-300 rounded-lg px-4 py-3 cursor-pointer has-[:checked]:border-maroon-700 has-[:checked]:bg-maroon-50 transition">
                        <input type="radio" name="type" value="file" {{ $policy->type === 'file' ? 'checked' : '' }} onchange="togglePolicyType('file')" class="text-maroon-700 focus:ring-maroon-700">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Upload a file</p>
                            <p class="text-xs text-gray-400">PDF, Word, or PowerPoint</p>
                        </div>
                    </label>
                </div>
            </div>

            <div id="text-section" class="{{ $policy->type !== 'text' ? 'hidden' : '' }}">
                <label class="block text-sm font-medium text-gray-700 mb-1">Policy Content</label>
                <div id="editor" style="height: 250px;" class="bg-white border border-gray-300 rounded-lg overflow-hidden"></div>
                <input type="hidden" name="body" id="body-input" value="{{ old('body', $policy->body) }}">
            </div>

            <div id="file-section" class="{{ $policy->type !== 'file' ? 'hidden' : '' }}">
                <label class="block text-sm font-medium text-gray-700 mb-1">Upload File</label>
                @if ($policy->file_path)
                    <p class="text-sm text-gray-500 mb-2">Current file: <span class="font-medium text-gray-700">{{ $policy->file_original_name }}</span></p>
                @endif
                <input type="file" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx" class="text-sm">
                <p class="text-xs text-gray-400 mt-1">Leave blank to keep the current file. Max 10MB.</p>
            </div>

            <label class="flex items-center gap-2 text-sm pt-2 border-t border-gray-100">
                <input type="checkbox" name="is_published" value="1" {{ old('is_published', $policy->is_published) ? 'checked' : '' }} class="rounded border-gray-300 text-maroon-700 focus:ring-maroon-700">
                Published (visible to employees)
            </label>
        </div>

        <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
            <a href="{{ route('admin.policies.index') }}" class="px-4 py-2 rounded-lg text-sm border border-gray-300 hover:bg-white transition">Cancel</a>
            <button class="bg-maroon-800 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-maroon-900 transition">Update Policy</button>
        </div>
    </div>
</form>

<link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<script>
    const quill = new Quill('#editor', { theme: 'snow' });
    quill.root.innerHTML = document.getElementById('body-input').value || '';
    document.querySelector('form').addEventListener('submit', function () {
        document.getElementById('body-input').value = quill.root.innerHTML;
    });
    function togglePolicyType(type) {
        document.getElementById('text-section').classList.toggle('hidden', type !== 'text');
        document.getElementById('file-section').classList.toggle('hidden', type !== 'file');
    }
</script>
@endsection