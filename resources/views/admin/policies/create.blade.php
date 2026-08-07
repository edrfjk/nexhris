@extends('layouts.app')

@section('title', 'Add HR Policy')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
@endpush

@section('content')

<x-page-header
    title="Add HR Policy"
    subtitle="Create a new HR policy for employees.">

    <x-slot:actions>
        <a href="{{ route('admin.policies.index') }}"
           class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-500 hover:text-maroon-800 hover:bg-maroon-50 transition"
           title="Back">

            <svg class="w-5 h-5"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke="currentColor">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="1.5"
                      d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
            </svg>
        </a>
    </x-slot:actions>

</x-page-header>

<form id="policyForm"
      action="{{ route('admin.policies.store') }}"
      method="POST"
      enctype="multipart/form-data">

    @csrf

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">

        <div class="p-6 space-y-6">

            {{-- Title + Category --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Title
                    </label>

                    <input
                        type="text"
                        name="title"
                        value="{{ old('title') }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">

                    @error('title')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Category (optional)
                    </label>

                    <input
                        type="text"
                        name="category"
                        value="{{ old('category') }}"
                        list="category-list"
                        placeholder="e.g. Leave, Benefits, Conduct"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">

                    <datalist id="category-list">
                        @foreach(\App\Models\HrPolicy::whereNotNull('category')->distinct()->pluck('category') as $cat)
                            <option value="{{ $cat }}">
                        @endforeach
                    </datalist>
                </div>

            </div>

            {{-- Policy Type --}}
            <div class="border-t pt-4">

                <label class="block text-sm font-medium text-gray-700 mb-3">
                    Policy Format
                </label>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <label class="cursor-pointer border rounded-lg p-4 flex gap-3 items-start">
                        <input
                            type="radio"
                            name="type"
                            value="text"
                            checked
                            onchange="togglePolicyType('text')">

                        <div>
                            <p class="font-medium">Write Text</p>
                            <p class="text-xs text-gray-500">
                                Create the policy using the editor.
                            </p>
                        </div>
                    </label>

                    <label class="cursor-pointer border rounded-lg p-4 flex gap-3 items-start">
                        <input
                            type="radio"
                            name="type"
                            value="file"
                            onchange="togglePolicyType('file')">

                        <div>
                            <p class="font-medium">Upload File</p>
                            <p class="text-xs text-gray-500">
                                PDF, DOC, DOCX, PPT, PPTX
                            </p>
                        </div>
                    </label>

                </div>

            </div>

            {{-- Text Editor --}}
            <div id="text-section">

                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Policy Content
                </label>

                <div id="editor"
                     class="bg-white"
                     style="height:300px;">
                </div>

                <input
                    type="hidden"
                    name="body"
                    id="body-input"
                    value="{{ old('body') }}">

                @error('body')
                    <p class="text-xs text-red-600 mt-2">{{ $message }}</p>
                @enderror

            </div>

            {{-- File Upload --}}
            <div id="file-section" class="hidden">

                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Upload File
                </label>

                <input
                    type="file"
                    name="file"
                    accept=".pdf,.doc,.docx,.ppt,.pptx">

                <p class="text-xs text-gray-400 mt-2">
                    Accepted formats: PDF, DOC, DOCX, PPT, PPTX (Maximum 10 MB)
                </p>

                @error('file')
                    <p class="text-xs text-red-600 mt-2">{{ $message }}</p>
                @enderror

            </div>

            {{-- Publish --}}
            <div class="border-t pt-4">

                <label class="flex items-center gap-3">

                    <input
                        type="checkbox"
                        name="is_published"
                        value="1"
                        {{ old('is_published') ? 'checked' : '' }}>

                    <span class="text-sm">
                        Publish immediately
                    </span>

                </label>

            </div>

        </div>

        {{-- Footer --}}
        <div class="px-6 py-4 bg-gray-50 border-t flex justify-end gap-3">

            <a href="{{ route('admin.policies.index') }}"
               class="px-4 py-2 rounded-lg border hover:bg-white">

                Cancel

            </a>

            <button
                type="submit"
                class="bg-maroon-800 hover:bg-maroon-900 text-white px-5 py-2 rounded-lg">

                Save Policy

            </button>

        </div>

    </div>

</form>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>

<script>

const quill = new Quill('#editor', {
    theme: 'snow',
    placeholder: 'Write your HR policy here...',
    modules: {
        toolbar: [
            [{ header: [1,2,3,false] }],
            ['bold','italic','underline'],
            [{ list:'ordered' }, { list:'bullet' }],
            ['link'],
            ['clean']
        ]
    }
});

@if(old('body'))
quill.root.innerHTML = {!! json_encode(old('body')) !!};
@endif

document.getElementById('policyForm').addEventListener('submit', function () {

    document.getElementById('body-input').value = quill.root.innerHTML;

});

function togglePolicyType(type){

    document.getElementById('text-section').classList.toggle('hidden', type !== 'text');

    document.getElementById('file-section').classList.toggle('hidden', type !== 'file');

}

togglePolicyType(document.querySelector('input[name=type]:checked').value);

</script>
@endpush