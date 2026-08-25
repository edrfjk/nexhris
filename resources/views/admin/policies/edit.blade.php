@extends('layouts.app')

@section('title', 'Edit HR Policy')

@push('styles')
    {{-- Quill Editor CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
@endpush

@section('content')

<x-page-header
    title="Edit HR Policy"
    subtitle="Update the details of this HR policy.">

    <x-slot:actions>
        <a href="{{ route('admin.policies.index') }}"
           class="btn btn-md btn-secondary">

            <x-heroicon-o-arrow-left class="w-4 h-4" />

            Back to Policies
        </a>
    </x-slot:actions>

</x-page-header>

<form method="POST"
      action="{{ route('admin.policies.update', $policy) }}"
      enctype="multipart/form-data">

    @csrf
    @method('PUT')

    <div class="card">

        <div class="p-6 space-y-6">

            {{-- Title + Category --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                {{-- Title --}}
                <div>
                    <label class="label">
                        Title
                    </label>

                    <input
                        type="text"
                        name="title"
                        value="{{ old('title', $policy->title) }}"
                        class="input">

                    @error('title')
                        <p class="text-xs text-red-600 mt-1">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- Category --}}
                <div>
                    <label class="label">
                        Category (optional)
                    </label>

                    <input
                        type="text"
                        name="category"
                        value="{{ old('category', $policy->category) }}"
                        list="category-list"
                        placeholder="e.g. Leave, Benefits, Conduct"
                        class="input">

                    <datalist id="category-list">
                        @foreach(\App\Models\HrPolicy::whereNotNull('category')->distinct()->pluck('category') as $cat)
                            <option value="{{ $cat }}">
                        @endforeach
                    </datalist>

                    @error('category')
                        <p class="text-xs text-red-600 mt-1">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

            </div>


            {{-- Policy Type --}}
            <div class="border-t border-sand-100 pt-4">

                <label class="block text-sm font-medium text-sand-700 mb-3">
                    Policy Format
                </label>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                    {{-- Text --}}
                    <label class="cursor-pointer border border-sand-200 rounded-lg p-4 flex gap-3 items-start has-[:checked]:border-maroon-700 has-[:checked]:bg-maroon-50 transition">

                        <input
                            type="radio"
                            name="type"
                            value="text"
                            {{ old('type', $policy->type) === 'text' ? 'checked' : '' }}
                            onchange="togglePolicyType('text')"
                            class="text-maroon-700 focus:ring-maroon-700">

                        <div>
                            <p class="text-sm font-medium text-sand-700">
                                Write Text
                            </p>

                            <p class="text-xs text-sand-500 mt-1">
                                Create the policy using the editor.
                            </p>
                        </div>

                    </label>


                    {{-- File --}}
                    <label class="cursor-pointer border border-sand-200 rounded-lg p-4 flex gap-3 items-start has-[:checked]:border-maroon-700 has-[:checked]:bg-maroon-50 transition">

                        <input
                            type="radio"
                            name="type"
                            value="file"
                            {{ old('type', $policy->type) === 'file' ? 'checked' : '' }}
                            onchange="togglePolicyType('file')"
                            class="text-maroon-700 focus:ring-maroon-700">

                        <div>
                            <p class="text-sm font-medium text-sand-700">
                                Upload File
                            </p>

                            <p class="text-xs text-sand-500 mt-1">
                                PDF, DOC, DOCX, PPT, PPTX
                            </p>
                        </div>

                    </label>


                    {{-- Link --}}
                    <label class="cursor-pointer border border-sand-200 rounded-lg p-4 flex gap-3 items-start has-[:checked]:border-maroon-700 has-[:checked]:bg-maroon-50 transition">

                        <input
                            type="radio"
                            name="type"
                            value="link"
                            {{ old('type', $policy->type) === 'link' ? 'checked' : '' }}
                            onchange="togglePolicyType('link')"
                            class="text-maroon-700 focus:ring-maroon-700">

                        <div>
                            <p class="text-sm font-medium text-sand-700">
                                Post a Link
                            </p>

                            <p class="text-xs text-sand-500 mt-1">
                                Google Form, video, external portal
                            </p>
                        </div>

                    </label>

                </div>

                @error('type')
                    <p class="text-xs text-red-600 mt-2">
                        {{ $message }}
                    </p>
                @enderror

            </div>


            {{-- Text Section --}}
            <div id="text-section"
                 class="{{ old('type', $policy->type) !== 'text' ? 'hidden' : '' }}">

                <label class="label">
                    Policy Content
                </label>

                {{-- Quill Editor --}}
                <div id="editor"
                     class="card overflow-hidden"
                     style="height:300px;">
                </div>

                {{-- Hidden field submitted to Laravel --}}
                <input
                    type="hidden"
                    name="body"
                    id="body-input"
                    value="{{ old('body', $policy->body ?? '') }}">

                @error('body')
                    <p class="text-xs text-red-600 mt-2">
                        {{ $message }}
                    </p>
                @enderror

            </div>


            {{-- File Section --}}
            <div id="file-section"
                 class="{{ old('type', $policy->type) !== 'file' ? 'hidden' : '' }}">

                <label class="label">
                    Upload File
                </label>

                @if ($policy->file_path)

                    <p class="text-sm text-sand-500 mb-2">
                        Current file:

                        <span class="font-medium text-sand-700">
                            {{ $policy->file_original_name }}
                        </span>
                    </p>

                @endif

                <input
                    type="file"
                    name="file"
                    accept=".pdf,.doc,.docx,.ppt,.pptx"
                    class="file-input">

                <p class="text-xs text-sand-400 mt-2">
                    Leave blank to keep the current file. Maximum 10 MB.
                </p>

                @error('file')
                    <p class="text-xs text-red-600 mt-2">
                        {{ $message }}
                    </p>
                @enderror

            </div>


            {{-- Link Section --}}
            <div id="link-section"
                 class="{{ old('type', $policy->type) !== 'link' ? 'hidden' : '' }}">

                <label class="label">
                    Link URL
                </label>

                <input
                    type="url"
                    name="link_url"
                    value="{{ old('link_url', $policy->link_url ?? '') }}"
                    placeholder="https://forms.google.com/..."
                    class="input">

                <p class="text-xs text-sand-400 mt-1">
                    Employees will see this as an "Open Link" card.
                </p>

                @error('link_url')
                    <p class="text-red-600 text-xs mt-1">
                        {{ $message }}
                    </p>
                @enderror

            </div>


            {{-- Additional Settings --}}
            <div class="pt-5 border-t border-sand-100">

                <p class="section-label mb-3">
                    Additional Settings
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">

                    {{-- Effective Date --}}
                    <div>
                        <label class="label">
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
                            class="input">

                        @error('effective_date')
                            <p class="text-xs text-red-600 mt-1">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>


                    {{-- Expiry Date --}}
                    <div>
                        <label class="label">
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
                            class="input">

                        @error('expiry_date')
                            <p class="text-xs text-red-600 mt-1">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                </div>


                {{-- Pin --}}
                <label class="flex items-center gap-2 text-sm mb-2">

                    <input
                        type="checkbox"
                        name="is_pinned"
                        value="1"
                        {{ old('is_pinned', $policy->is_pinned) ? 'checked' : '' }}
                        class="rounded border-sand-200 text-maroon-700 focus:ring-maroon-700">

                    Pin to top of employee list

                </label>


                {{-- Acknowledgment --}}
                <label class="flex items-center gap-2 text-sm">

                    <input
                        type="checkbox"
                        name="requires_acknowledgment"
                        value="1"
                        {{ old('requires_acknowledgment', $policy->requires_acknowledgment) ? 'checked' : '' }}
                        class="rounded border-sand-200 text-maroon-700 focus:ring-maroon-700">

                    Require employees to acknowledge reading this policy

                </label>

            </div>


            {{-- Publish --}}
            <div class="pt-4 border-t border-sand-100">

                <label class="flex items-center gap-2 text-sm">

                    <input
                        type="checkbox"
                        name="is_published"
                        value="1"
                        {{ old('is_published', $policy->is_published) ? 'checked' : '' }}
                        class="rounded border-sand-200 text-maroon-700 focus:ring-maroon-700">

                    Published (visible to employees)

                </label>

            </div>

        </div>


        {{-- Footer --}}
        <div class="px-6 py-4 bg-sand-50 border-t border-sand-100 flex justify-end gap-3">

            <a
                href="{{ route('admin.policies.index') }}"
                class="btn btn-md btn-secondary">

                Cancel

            </a>

            <button
                type="submit"
                class="btn btn-lg btn-primary">

                Update Policy

            </button>

        </div>

    </div>

</form>

@endsection


@push('scripts')

{{-- Quill Editor JS --}}
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        /*
        |--------------------------------------------------------------------------
        | Policy Type Toggle
        |--------------------------------------------------------------------------
        */

        window.togglePolicyType = function (type) {

            const textSection = document.getElementById('text-section');
            const fileSection = document.getElementById('file-section');
            const linkSection = document.getElementById('link-section');

            if (textSection) {
                textSection.classList.toggle('hidden', type !== 'text');
            }

            if (fileSection) {
                fileSection.classList.toggle('hidden', type !== 'file');
            }

            if (linkSection) {
                linkSection.classList.toggle('hidden', type !== 'link');
            }
        };


        /*
        |--------------------------------------------------------------------------
        | Show Current Policy Type
        |--------------------------------------------------------------------------
        */

        const currentType = @json(old('type', $policy->type));

        togglePolicyType(currentType);


        /*
        |--------------------------------------------------------------------------
        | Quill Editor
        |--------------------------------------------------------------------------
        */

        const editorElement = document.getElementById('editor');
        const bodyInput = document.getElementById('body-input');

        if (editorElement && bodyInput && typeof Quill !== 'undefined') {

            const quill = new Quill('#editor', {
                theme: 'snow',

                modules: {
                    toolbar: [
                        [{ header: [1, 2, 3, false] }],

                        ['bold', 'italic', 'underline', 'strike'],

                        [
                            { list: 'ordered' },
                            { list: 'bullet' }
                        ],

                        [
                            { align: [] }
                        ],

                        ['link'],

                        ['clean']
                    ]
                }
            });


            /*
            |--------------------------------------------------------------------------
            | Load Existing Policy Content
            |--------------------------------------------------------------------------
            */

            const existingBody = @json(old('body', $policy->body ?? ''));

            if (existingBody) {
                quill.root.innerHTML = existingBody;
            }


            /*
            |--------------------------------------------------------------------------
            | Sync Quill → Hidden Input
            |--------------------------------------------------------------------------
            */

            quill.on('text-change', function () {

                bodyInput.value = quill.root.innerHTML;

            });


            /*
            |--------------------------------------------------------------------------
            | Initial Value
            |--------------------------------------------------------------------------
            */

            bodyInput.value = quill.root.innerHTML;


            /*
            |--------------------------------------------------------------------------
            | Before Form Submit
            |--------------------------------------------------------------------------
            */

            const form = editorElement.closest('form');

            if (form) {

                form.addEventListener('submit', function () {

                    bodyInput.value = quill.root.innerHTML;

                });

            }

        }

    });
</script>

@endpush