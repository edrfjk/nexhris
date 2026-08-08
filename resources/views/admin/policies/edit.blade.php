@extends('layouts.app')

@section('title', 'Edit HR Policy')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
@endpush

@section('content')

<x-page-header
    title="Edit HR Policy"
    subtitle="Update the selected HR policy.">

    <x-slot:actions>
        <a href="{{ route('admin.policies.index') }}"
           class="inline-flex items-center gap-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            Back to Policies
        </a>
    </x-slot:actions>

</x-page-header>

<form id="policyForm"
      action="{{ route('admin.policies.update', $policy) }}"
      method="POST"
      enctype="multipart/form-data">

    @csrf
    @method('PUT')

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
                        value="{{ old('title', $policy->title) }}"
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
                        value="{{ old('category', $policy->category) }}"
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
            <div class="border-t border-gray-100 pt-4">

                <label class="block text-sm font-medium text-gray-700 mb-3">
                    Policy Format
                </label>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                    {{-- Text --}}
                    <label class="cursor-pointer border border-gray-300 rounded-lg p-4 flex gap-3 items-start has-[:checked]:border-maroon-700 has-[:checked]:bg-maroon-50 transition">

                        <input
                            type="radio"
                            name="type"
                            value="text"
                            {{ old('type', $policy->type) === 'text' ? 'checked' : '' }}
                            onchange="togglePolicyType('text')"
                            class="text-maroon-700 focus:ring-maroon-700">

                        <div>
                            <p class="text-sm font-medium text-gray-700">
                                Write Text
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                Create the policy using the editor.
                            </p>
                        </div>

                    </label>

                    {{-- File --}}
                    <label class="cursor-pointer border border-gray-300 rounded-lg p-4 flex gap-3 items-start has-[:checked]:border-maroon-700 has-[:checked]:bg-maroon-50 transition">

                        <input
                            type="radio"
                            name="type"
                            value="file"
                            {{ old('type', $policy->type) === 'file' ? 'checked' : '' }}
                            onchange="togglePolicyType('file')"
                            class="text-maroon-700 focus:ring-maroon-700">

                        <div>
                            <p class="text-sm font-medium text-gray-700">
                                Upload File
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                PDF, DOC, DOCX, PPT, PPTX
                            </p>
                        </div>

                    </label>

                    {{-- Link --}}
                    <label class="cursor-pointer border border-gray-300 rounded-lg p-4 flex gap-3 items-start has-[:checked]:border-maroon-700 has-[:checked]:bg-maroon-50 transition">

                        <input
                            type="radio"
                            name="type"
                            value="link"
                            {{ old('type', $policy->type) === 'link' ? 'checked' : '' }}
                            onchange="togglePolicyType('link')"
                            class="text-maroon-700 focus:ring-maroon-700">

                        <div>
                            <p class="text-sm font-medium text-gray-700">
                                Post a Link
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                Google Form, video, external portal
                            </p>
                        </div>

                    </label>

                </div>

            </div>

            {{-- Text Section --}}
            <div id="text-section"
                 class="{{ old('type', $policy->type) !== 'text' ? 'hidden' : '' }}">

                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Policy Content
                </label>

                <div id="editor"
                     class="bg-white border border-gray-300 rounded-lg overflow-hidden"
                     style="height:300px;">
                </div>

                <input
                    type="hidden"
                    name="body"
                    id="body-input"
                    value="{{ old('body', $policy->body) }}">

                @error('body')
                    <p class="text-xs text-red-600 mt-2">{{ $message }}</p>
                @enderror

            </div>

            {{-- File Section --}}
            <div id="file-section"
                 class="{{ old('type', $policy->type) !== 'file' ? 'hidden' : '' }}">

                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Upload File
                </label>

                @if ($policy->file_path)

                    <p class="text-sm text-gray-500 mb-2">
                        Current file:
                        <span class="font-medium text-gray-700">
                            {{ $policy->file_original_name }}
                        </span>
                    </p>

                @endif

                <input
                    type="file"
                    name="file"
                    accept=".pdf,.doc,.docx,.ppt,.pptx"
                    class="text-sm">

                <p class="text-xs text-gray-400 mt-2">
                    Leave blank to keep the current file. Maximum 10 MB.
                </p>

                @error('file')
                    <p class="text-xs text-red-600 mt-2">{{ $message }}</p>
                @enderror

            </div>

            {{-- Link Section --}}
            <div id="link-section"
                 class="{{ old('type', $policy->type) !== 'link' ? 'hidden' : '' }}">

                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Link URL
                </label>

                <input
                    type="url"
                    name="link_url"
                    value="{{ old('link_url', $policy->link_url ?? '') }}"
                    placeholder="https://forms.google.com/..."
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">

                <p class="text-xs text-gray-400 mt-1">
                    Employees will see this as an "Open Link" card.
                </p>

                @error('link_url')
                    <p class="text-red-600 text-xs mt-1">
                        {{ $message }}
                    </p>
                @enderror

            </div>

            {{-- Additional Settings --}}
            <div class="pt-5 border-t border-gray-100">

                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">
                    Additional Settings
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Effective Date (optional)
                        </label>

                        <input
                            type="date"
                            name="effective_date"
                            value="{{ old(
                                'effective_date',
                                $policy->effective_date
                                    ? \Carbon\Carbon::parse($policy->effective_date)->format('Y-m-d')
                                    : ''
                            ) }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Expiry Date (optional)
                        </label>

                        <input
                            type="date"
                            name="expiry_date"
                            value="{{ old(
                                'expiry_date',
                                $policy->expiry_date
                                    ? \Carbon\Carbon::parse($policy->expiry_date)->format('Y-m-d')
                                    : ''
                            ) }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                    </div>

                </div>

                <label class="flex items-center gap-2 text-sm mb-2">

                    <input
                        type="checkbox"
                        name="is_pinned"
                        value="1"
                        {{ old('is_pinned', $policy->is_pinned) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-maroon-700 focus:ring-maroon-700">

                    Pin to top of employee list

                </label>

                <label class="flex items-center gap-2 text-sm">

                    <input
                        type="checkbox"
                        name="requires_acknowledgment"
                        value="1"
                        {{ old('requires_acknowledgment', $policy->requires_acknowledgment) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-maroon-700 focus:ring-maroon-700">

                    Require employees to acknowledge reading this policy

                </label>

            </div>

            {{-- Publish --}}
            <div class="pt-4 border-t border-gray-100">

                <label class="flex items-center gap-2 text-sm">

                    <input
                        type="checkbox"
                        name="is_published"
                        value="1"
                        {{ old('is_published', $policy->is_published) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-maroon-700 focus:ring-maroon-700">

                    Published (visible to employees)

                </label>

            </div>

        </div>

        {{-- Footer --}}
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3">

            <a
                href="{{ route('admin.policies.index') }}"
                class="px-4 py-2 rounded-lg text-sm border border-gray-300 hover:bg-white transition">

                Cancel

            </a>

            <button
                type="submit"
                class="bg-maroon-800 hover:bg-maroon-900 text-white px-5 py-2 rounded-lg text-sm font-medium transition">

                Update Policy

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
            [{ header: [1, 2, 3, false] }],
            ['bold', 'italic', 'underline'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['link'],
            ['clean']
        ]
    }
});

@if(old('body', $policy->body))
quill.root.innerHTML = {!! json_encode(old('body', $policy->body)) !!};
@endif

document.getElementById('policyForm').addEventListener('submit', function () {

    document.getElementById('body-input').value =
        quill.root.innerHTML;

});

function togglePolicyType(type) {

    document.getElementById('text-section')
        .classList.toggle('hidden', type !== 'text');

    document.getElementById('file-section')
        .classList.toggle('hidden', type !== 'file');

    document.getElementById('link-section')
        .classList.toggle('hidden', type !== 'link');

}

togglePolicyType(
    document.querySelector('input[name="type"]:checked').value
);

</script>

@endpush