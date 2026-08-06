@extends('layouts.app')
@section('title', 'Add HR Policy')

@section('content')
<x-page-header title="Add HR Policy" subtitle="Publish a text-based or file-based policy for employees to view." />

<x-card>
    <form method="POST" action="{{ route('admin.policies.store') }}" enctype="multipart/form-data" class="space-y-5">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
            <input name="title" value="{{ old('title') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
            @error('title') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Category (optional)</label>
            <input name="category" value="{{ old('category') }}" placeholder="e.g. Leave, Conduct, Benefits"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Policy Format</label>
            <div class="flex gap-4 text-sm">
                <label class="flex items-center gap-2">
                    <input type="radio" name="type" value="text" checked onchange="togglePolicyType('text')" class="text-maroon-700 focus:ring-maroon-700"> Write text directly
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="type" value="file" onchange="togglePolicyType('file')" class="text-maroon-700 focus:ring-maroon-700"> Upload a file (PDF/Word/PPT)
                </label>
            </div>
        </div>

        <div id="text-section">
            <label class="block text-sm font-medium text-gray-700 mb-1">Policy Content</label>
            <div id="editor" style="height: 250px;" class="bg-white border border-gray-300 rounded-lg overflow-hidden"></div>
            <input type="hidden" name="body" id="body-input" value="{{ old('body') }}">
            @error('body') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div id="file-section" class="hidden">
            <label class="block text-sm font-medium text-gray-700 mb-1">Upload File</label>
            <input type="file" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx" class="text-sm">
            <p class="text-xs text-gray-400 mt-1">Accepted: PDF, Word, PowerPoint. Max 10MB.</p>
            @error('file') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_published" value="1" {{ old('is_published') ? 'checked' : '' }} class="rounded border-gray-300 text-maroon-700 focus:ring-maroon-700">
            Publish immediately (visible to employees)
        </label>

        <div class="flex gap-2 pt-2 border-t border-gray-100">
            <button class="bg-maroon-800 text-white px-4 py-2 rounded-lg text-sm hover:bg-maroon-900 transition mt-4">Save Policy</button>
            <a href="{{ route('admin.policies.index') }}" class="px-4 py-2 rounded-lg text-sm border border-gray-300 hover:bg-gray-50 transition mt-4">Cancel</a>
        </div>
    </form>
</x-card>

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