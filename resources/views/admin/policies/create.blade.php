@extends('layouts.app')

@section('title', 'Add HR Policy')

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
@endpush

@section('content')

<x-page-header
    title="Add HR Policy"
    subtitle="Create a new HR policy for employees.">

    <x-slot:actions>
        <a href="{{ route('admin.policies.index') }}"
           class="btn btn-md btn-secondary">

            <x-heroicon-o-arrow-left class="w-4 h-4" />

            Back to Policies
        </a>
    </x-slot:actions>

</x-page-header>


<form id="policyForm"
      action="{{ route('admin.policies.store') }}"
      method="POST"
      enctype="multipart/form-data">

    @csrf

    <div class="card">

        <div class="border-b border-sand-100 px-6 py-4">
            <h3 class="font-semibold text-sand-800">New HR Policy</h3>
            <p class="mt-0.5 text-xs text-sand-400">Publish a policy for staff to read and acknowledge.</p>
        </div>

        <div class="p-6 space-y-6">

            {{-- =========================================================
                 TITLE + CATEGORY
            ========================================================== --}}

            <div>
                <p class="section-label mb-3">Policy details</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 -mt-3">

                {{-- Title --}}
                <div>
                    <label class="label">
                        Title
                    </label>

                    <input
                        type="text"
                        name="title"
                        value="{{ old('title') }}"
                        placeholder="e.g. Employee Leave Policy"
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
                        value="{{ old('category') }}"
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


            {{-- =========================================================
                 POLICY FORMAT
            ========================================================== --}}

            <div class="border-t border-sand-100 pt-4">

                <p class="section-label mb-3">Policy format</p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                    {{-- TEXT --}}
                    <label
                        class="cursor-pointer border border-sand-200 rounded-lg p-4 flex gap-3 items-start
                               has-[:checked]:border-maroon-700
                               has-[:checked]:bg-maroon-50
                               transition">

                        <input
                            type="radio"
                            name="type"
                            value="text"
                            {{ old('type', 'text') === 'text' ? 'checked' : '' }}
                            onchange="togglePolicyType('text')"
                            class="mt-0.5 text-maroon-700 focus:ring-maroon-700">

                        <div>
                            <p class="text-sm font-medium text-sand-700">
                                Write Text
                            </p>

                            <p class="text-xs text-sand-500 mt-1">
                                Create the policy using the editor.
                            </p>
                        </div>

                    </label>


                    {{-- FILE --}}
                    <label
                        class="cursor-pointer border border-sand-200 rounded-lg p-4 flex gap-3 items-start
                               has-[:checked]:border-maroon-700
                               has-[:checked]:bg-maroon-50
                               transition">

                        <input
                            type="radio"
                            name="type"
                            value="file"
                            {{ old('type') === 'file' ? 'checked' : '' }}
                            onchange="togglePolicyType('file')"
                            class="mt-0.5 text-maroon-700 focus:ring-maroon-700">

                        <div>
                            <p class="text-sm font-medium text-sand-700">
                                Upload File
                            </p>

                            <p class="text-xs text-sand-500 mt-1">
                                PDF, DOC, DOCX, PPT, PPTX
                            </p>
                        </div>

                    </label>


                    {{-- LINK --}}
                    <label
                        class="cursor-pointer border border-sand-200 rounded-lg p-4 flex gap-3 items-start
                               has-[:checked]:border-maroon-700
                               has-[:checked]:bg-maroon-50
                               transition">

                        <input
                            type="radio"
                            name="type"
                            value="link"
                            {{ old('type') === 'link' ? 'checked' : '' }}
                            onchange="togglePolicyType('link')"
                            class="mt-0.5 text-maroon-700 focus:ring-maroon-700">

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


            {{-- =========================================================
                 TEXT SECTION
            ========================================================== --}}

            <div id="text-section">

                <label class="label">
                    Policy Content
                </label>

                {{-- A field the user types into, so it carries the same
                     boundary as every other field rather than a card's. --}}
                <div
                    id="editor"
                    class="overflow-hidden rounded-lg border border-sand-400 bg-white shadow-inset"
                    style="height: 300px;">
                </div>

                <input
                    type="hidden"
                    name="body"
                    id="body-input"
                    value="{{ old('body') }}">

                @error('body')
                    <p class="text-xs text-red-600 mt-2">
                        {{ $message }}
                    </p>
                @enderror

            </div>


            {{-- =========================================================
                 FILE SECTION
            ========================================================== --}}

            <div id="file-section" class="hidden">

                <label class="label">
                    Upload File
                </label>

                <input
                    type="file"
                    name="file"
                    accept=".pdf,.doc,.docx,.ppt,.pptx"
                    class="file-input block file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-sand-100 file:text-sand-700 hover:file:bg-sand-200">

                <p class="text-xs text-sand-400 mt-2">
                    Accepted formats: PDF, DOC, DOCX, PPT, PPTX. Maximum 10 MB.
                </p>

                @error('file')
                    <p class="text-xs text-red-600 mt-2">
                        {{ $message }}
                    </p>
                @enderror

            </div>


            {{-- =========================================================
                 LINK SECTION
            ========================================================== --}}

            <div id="link-section" class="hidden">

                <label class="label">
                    Link URL
                </label>

                <input
                    type="url"
                    name="link_url"
                    value="{{ old('link_url') }}"
                    placeholder="https://forms.google.com/..."
                    class="input">

                <p class="text-xs text-sand-400 mt-1">
                    Employees will see this as an "Open Link" card.
                </p>

                @error('link_url')
                    <p class="text-xs text-red-600 mt-1">
                        {{ $message }}
                    </p>
                @enderror

            </div>


            {{-- =========================================================
                 ADDITIONAL SETTINGS
            ========================================================== --}}

            <div class="pt-5 border-t border-sand-100">

                <p class="section-label mb-3">
                    Additional Settings
                </p>


                {{-- Effective + Expiry --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">

                    {{-- Effective Date --}}
                    <div>
                        <label class="label">
                            Effective Date (optional)
                        </label>

                        <input
                            type="date"
                            name="effective_date"
                            value="{{ old('effective_date') }}"
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
                            value="{{ old('expiry_date') }}"
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
                        {{ old('is_pinned') ? 'checked' : '' }}
                        class="rounded border-sand-200 text-maroon-700 focus:ring-maroon-700">

                    <span>
                        Pin to top of employee list
                    </span>

                </label>


                {{-- Acknowledgment --}}
                <label class="flex items-center gap-2 text-sm">

                    <input
                        type="checkbox"
                        name="requires_acknowledgment"
                        value="1"
                        {{ old('requires_acknowledgment') ? 'checked' : '' }}
                        class="rounded border-sand-200 text-maroon-700 focus:ring-maroon-700">

                    <span>
                        Require employees to acknowledge reading this policy
                    </span>

                </label>

            </div>


            {{-- =========================================================
                 PUBLISH
            ========================================================== --}}

            <div class="pt-4 border-t border-sand-100">

                <label class="flex items-center gap-2 text-sm">

                    <input
                        type="checkbox"
                        name="is_published"
                        value="1"
                        {{ old('is_published') ? 'checked' : '' }}
                        class="rounded border-sand-200 text-maroon-700 focus:ring-maroon-700">

                    <span>
                        Publish immediately (visible to employees)
                    </span>

                </label>

            </div>

        </div>


        {{-- =============================================================
             FOOTER
        ============================================================== --}}

        <div class="px-6 py-4 bg-sand-50 border-t border-sand-100 flex justify-end gap-3">

            <a
                href="{{ route('admin.policies.index') }}"
                class="btn btn-md btn-secondary">

                Cancel

            </a>

            <button
                type="submit"
                class="btn btn-lg btn-primary">

                Save Policy

            </button>

        </div>

    </div>

</form>

@endsection


{{-- ================================================================
     JAVASCRIPT
================================================================ --}}

@push('scripts')

{{-- The same editor, at the same version, as the edit page. Creating a policy
     with Quill 1 and editing it with Quill 2 meant the two wrote different HTML
     for the same list, and opening a policy to fix a typo could turn its bullet
     points into a numbered list for every employee. --}}
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    /*
     * ---------------------------------------------------------------
     * Quill Editor
     * ---------------------------------------------------------------
     */

    const editorElement = document.getElementById('editor');

    let quill = null;

    if (editorElement) {

        quill = new Quill('#editor', {
            theme: 'snow',
            placeholder: 'Write your HR policy here...',
            modules: {
                // The edit page's toolbar exactly, so a policy can be written
                // with every tool it can later be edited with.
                toolbar: [
                    [{ header: [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ align: [] }],
                    ['link'],
                    ['clean']
                ]
            }
        });

        @if(old('body'))
            quill.root.innerHTML = @json(old('body'));
        @endif
    }


    /*
     * ---------------------------------------------------------------
     * Toggle Text / File / Link Sections
     * ---------------------------------------------------------------
     */

    window.togglePolicyType = function(type) {

        document.getElementById('text-section')
            .classList.toggle('hidden', type !== 'text');

        document.getElementById('file-section')
            .classList.toggle('hidden', type !== 'file');

        document.getElementById('link-section')
            .classList.toggle('hidden', type !== 'link');

    };


    /*
     * ---------------------------------------------------------------
     * Set Initial Policy Type
     * ---------------------------------------------------------------
     */

    const selectedType = document.querySelector(
        'input[name="type"]:checked'
    );

    if (selectedType) {
        togglePolicyType(selectedType.value);
    }


    /*
     * ---------------------------------------------------------------
     * Form Submit
     * ---------------------------------------------------------------
     */

    const policyForm = document.getElementById('policyForm');

    if (policyForm) {

        policyForm.addEventListener('submit', function () {

            if (quill) {
                document.getElementById('body-input').value =
                    quill.root.innerHTML;
            }

        });

    }

});
</script>

@endpush