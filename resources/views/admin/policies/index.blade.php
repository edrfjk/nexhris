@extends('layouts.app')

@section('title', 'HR Policies')

@section('content')

<x-page-header
    title="HR Policies"
    subtitle="Manage HR policies, guidelines and employee acknowledgments.">
    <x-slot:actions>
        <a href="{{ route('admin.policies.create') }}" class="btn btn-md btn-primary">
            <x-heroicon-o-plus />
            Add Policy
        </a>
    </x-slot:actions>
</x-page-header>

<div class="space-y-6">

    {{-- Statistics --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

        {{-- Published --}}
        <div class="card ring-1 ring-forest-100 p-4">
            <div class="w-9 h-9 rounded-lg bg-forest-50 text-forest-600 flex items-center justify-center mb-3">
                <x-heroicon-o-check class="w-4.5 h-4.5" />
            </div>

            <p class="text-2xl font-bold text-sand-800 leading-none">
                {{ $publishedCount }}
            </p>

            <p class="text-xs text-sand-500 mt-1.5">
                Published
            </p>
        </div>


        {{-- Drafts --}}
        <div class="card ring-1 ring-sand-100 p-4">
            <div class="w-9 h-9 rounded-lg bg-sand-50 text-sand-600 flex items-center justify-center mb-3">
                <x-heroicon-o-pencil-square class="w-4.5 h-4.5" />
            </div>

            <p class="text-2xl font-bold text-sand-800 leading-none">
                {{ $draftCount }}
            </p>

            <p class="text-xs text-sand-500 mt-1.5">
                Drafts
            </p>
        </div>


        {{-- Uploaded Files --}}
        <div class="card ring-1 ring-sky-100 p-4">
            <div class="w-9 h-9 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center mb-3">
                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                </svg>
            </div>

            <p class="text-2xl font-bold text-sand-800 leading-none">
                {{ $fileCount }}
            </p>

            <p class="text-xs text-sand-500 mt-1.5">
                Uploaded Files
            </p>
        </div>

    </div>


    {{-- Filters --}}
    <div class="card p-4">

        <form method="GET"
              action="{{ route('admin.policies.index') }}"
              class="flex flex-wrap items-end gap-4">

            {{-- Search --}}
            <div class="flex-1 min-w-[220px]">
                <label class="label">
                    Search
                </label>

                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search policies..."
                    class="input">
            </div>


            {{-- Category --}}
            <div class="w-full sm:w-48">
                <label class="label">
                    Category
                </label>

                <select
                    name="category"
                    class="select">

                    <option value="">All Categories</option>

                    @foreach ($categories as $cat)
                        <option value="{{ $cat }}"
                            @selected(request('category') === $cat)>
                            {{ $cat }}
                        </option>
                    @endforeach

                </select>
            </div>


            {{-- Type --}}
            <div class="w-full sm:w-36">
                <label class="label">
                    Type
                </label>

                <select
                    name="type"
                    class="select">

                    <option value="">All Types</option>

                    <option value="text"
                        @selected(request('type') === 'text')>
                        Text
                    </option>

                    <option value="file"
                        @selected(request('type') === 'file')>
                        File
                    </option>

                    <option value="link"
                        @selected(request('type') === 'link')>
                        Link
                    </option>

                </select>
            </div>


            {{-- Status --}}
            <div class="w-full sm:w-36">
                <label class="label">
                    Status
                </label>

                <select
                    name="status"
                    class="select">

                    <option value="">All</option>

                    <option value="published"
                        @selected(request('status') === 'published')>
                        Published
                    </option>

                    <option value="draft"
                        @selected(request('status') === 'draft')>
                        Draft
                    </option>

                </select>
            </div>


            {{-- Buttons --}}
            <div class="flex gap-2">

                <button
                    type="submit"
                    class="btn btn-md btn-primary whitespace-nowrap">

                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              stroke-width="2"
                              d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"/>
                    </svg>

                    Apply
                </button>


                @if (request()->hasAny(['search', 'category', 'type', 'status']))

                    <a
                        href="{{ route('admin.policies.index') }}"
                        class="btn btn-md btn-secondary">

                        <x-heroicon-o-x-mark class="w-4 h-4" />

                        Clear
                    </a>

                @endif

            </div>

        </form>


        {{-- Active Filters --}}
        @if (request()->hasAny(['search', 'category', 'type', 'status']))

            <div class="flex flex-wrap gap-2 mt-3 pt-3 border-t border-sand-100">

                @if (request('search'))
                    <span class="chip chip-maroon">
                        Search: "{{ request('search') }}"
                    </span>
                @endif

                @if (request('category'))
                    <span class="chip chip-maroon">
                        Category: {{ request('category') }}
                    </span>
                @endif

                @if (request('type'))
                    <span class="chip chip-maroon">
                        Type: {{ ucfirst(request('type')) }}
                    </span>
                @endif

                @if (request('status'))
                    <span class="chip chip-maroon">
                        Status: {{ ucfirst(request('status')) }}
                    </span>
                @endif

            </div>

        @endif

    </div>


    {{-- Policies Table --}}
    <div class="card overflow-hidden">

        <div class="overflow-x-auto">

            <table class="table">

                <thead>

                    <tr>

                        <th class="text-left">
                            Policy
                        </th>

                        <th class="text-left">
                            Category
                        </th>

                        <th class="text-left">
                            Type
                        </th>

                        {{-- NEW STATUS COLUMN --}}
                        <th class="text-left">
                            Status
                        </th>

                        <th class="text-left">
                            Published
                        </th>

                        <th class="text-right">
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse ($policies as $policy)

                        <tr
                            x-data="{ preview: false }"
                            class="hover:bg-sand-50 transition">

                            {{-- Policy Name --}}
                            <td>

                                <div class="flex items-center gap-3">

                                    @php
                                        $meta = $policy->categoryMeta();
                                    @endphp

                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0
                                                bg-{{ $meta['color'] }}-50 text-{{ $meta['color'] }}-600">

                                        <svg
                                            class="w-4.5 h-4.5"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="1.5">

                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="{{ $meta['icon'] }}"/>

                                        </svg>

                                    </div>


                                    <div>

                                        {{-- PIN INDICATOR --}}
                                        <p class="font-medium text-sand-800 flex items-center gap-1.5">

                                            @if ($policy->is_pinned)

                                                <svg
                                                    class="w-3.5 h-3.5 text-maroon-700"
                                                    fill="currentColor"
                                                    viewBox="0 0 24 24">

                                                    <path d="M16 4v6l2 4H6l2-4V4h8zm-4 12v6"/>

                                                </svg>

                                            @endif

                                            {{ $policy->title }}

                                        </p>

                                        <p class="text-xs text-sand-400">
                                            {{ $policy->created_at->format('M d, Y') }}
                                        </p>

                                    </div>

                                </div>

                            </td>


                            {{-- Category --}}
                            <td>

                                <span class="text-sm text-sand-600">
                                    {{ $policy->category ?: 'Uncategorized' }}
                                </span>

                            </td>


                            {{-- Type --}}
                            <td>

                                <x-badge
                                    :color="match($policy->type) {
                                        'text' => 'purple',
                                        'file' => 'blue',
                                        'link' => 'green',
                                        default => 'gray'
                                    }">

                                    {{ ucfirst($policy->type) }}

                                </x-badge>

                            </td>


                            {{-- NEW STATUS COLUMN --}}
                            <td>

                                @php
                                    $status = $policy->statusLabel();
                                @endphp

                                <x-badge
                                    :color="match($status) {
                                        'expired' => 'red',
                                        'upcoming' => 'blue',
                                        default => 'green'
                                    }">

                                    {{ ucfirst($status) }}

                                </x-badge>

                            </td>


                            {{-- Published --}}
                            <td>

                                @if ($policy->is_published)

                                    <x-badge color="green">
                                        Published
                                    </x-badge>

                                @else

                                    <x-badge color="gray">
                                        Draft
                                    </x-badge>

                                @endif

                            </td>


                            {{-- Actions --}}
                            <td>

                                <div class="flex justify-end items-center gap-1">


                                    {{-- Preview --}}
                                    <button
                                        type="button"
                                        @click="preview = true"
                                        title="Preview"
                                        class="icon-btn">

                                        <svg
                                            class="w-5 h-5"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="1.5">

                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12z"/>

                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M15.375 12a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0z"/>

                                        </svg>

                                    </button>


                                    {{-- PIN / UNPIN --}}
                                    <form
                                        action="{{ route('admin.policies.toggle-pin', $policy) }}"
                                        method="POST">

                                        @csrf

                                        <button
                                            type="submit"
                                            title="{{ $policy->is_pinned ? 'Unpin' : 'Pin' }}"
                                            class="icon-btn {{ $policy->is_pinned ? 'icon-btn-on' : '' }}">

                                            <svg
                                                class="w-5 h-5"
                                                fill="{{ $policy->is_pinned ? 'currentColor' : 'none' }}"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="1.5">

                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M16 4v6l2 4H6l2-4V4h8zm-4 12v6"/>

                                            </svg>

                                        </button>

                                    </form>


                                    {{-- COMPLIANCE --}}
                                    @if ($policy->requires_acknowledgment)

                                        <a
                                            href="{{ route('admin.policies.compliance', $policy) }}"
                                            title="Compliance"
                                            class="icon-btn icon-btn-success">

                                            <x-heroicon-o-check-circle class="w-5 h-5" />

                                        </a>

                                    @endif


                                    {{-- Edit --}}
                                    <a
                                        href="{{ route('admin.policies.edit', $policy) }}"
                                        title="Edit"
                                        class="icon-btn icon-btn-info">

                                        <x-heroicon-o-pencil-square class="w-5 h-5" />

                                    </a>


                                    {{-- Delete --}}
                                    <form
                                        action="{{ route('admin.policies.destroy', $policy) }}"
                                        method="POST"
                                        onsubmit="return confirm('Delete this policy?')">

                                        @csrf
                                        @method('DELETE')

                                        <button
                                            type="submit"
                                            title="Delete"
                                            class="icon-btn icon-btn-danger">

                                            <svg
                                                class="w-5 h-5"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor">

                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="M6 7.5h12"/>

                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="M9.75 7.5V5.625A1.125 1.125 0 0110.875 4.5h2.25A1.125 1.125 0 0114.25 5.625V7.5"/>

                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="M8.25 7.5l.75 11.25A1.125 1.125 0 0010.122 19.5h3.756A1.125 1.125 0 0015 18.75L15.75 7.5"/>

                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="M10.5 10.5v6m3-6v6"/>

                                            </svg>

                                        </button>

                                    </form>

                                </div>


                                {{-- Preview Modal --}}
                                <template x-teleport="body">

                                    <div
                                        x-show="preview"
                                        x-cloak
                                        class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
                                        @click.self="preview = false">

                                        <div
                                            class="card max-w-2xl w-full max-h-[80vh] overflow-y-auto"
                                            @click.stop>

                                            {{-- Modal Header --}}
                                            <div class="px-6 py-4 border-b border-sand-100 flex justify-between items-start sticky top-0 bg-white">

                                                <div>

                                                    <p class="font-semibold text-sand-800">
                                                        {{ $policy->title }}
                                                    </p>

                                                    <p class="text-xs text-sand-400 mt-0.5">
                                                        {{ $policy->category ?: 'Uncategorized' }}
                                                        ·
                                                        {{ $policy->is_published ? 'Published' : 'Draft' }}
                                                    </p>

                                                </div>

                                                <button
                                                    @click="preview = false"
                                                    class="text-sand-400 hover:text-sand-600">

                                                    <x-heroicon-o-x-mark class="w-5 h-5" />

                                                </button>

                                            </div>


                                            {{-- Modal Content --}}
                                            <div class="p-6">

                                                {{-- TEXT --}}
                                                @if ($policy->type === 'text')

                                                    <div class="rich-text">
                                                        {!! $policy->body !!}
                                                    </div>


                                                {{-- FILE --}}
                                                @elseif ($policy->type === 'file')

                                                    <div class="text-center py-8">

                                                        <svg
                                                            class="w-12 h-12 mx-auto text-sand-300 mb-3"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            stroke="currentColor"
                                                            stroke-width="1">

                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>

                                                        </svg>

                                                        <p class="text-sm text-sand-500 mb-3">
                                                            {{ $policy->file_original_name }}
                                                        </p>

                                                        @if ($policy->file_path)

                                                            <a
                                                                href="{{ asset('storage/' . $policy->file_path) }}"
                                                                target="_blank"
                                                                class="btn btn-md btn-primary">

                                                                Open File

                                                            </a>

                                                        @endif

                                                    </div>


                                                {{-- LINK --}}
                                                @elseif ($policy->type === 'link')

                                                    <div class="text-center py-8">

                                                        <svg
                                                            class="w-12 h-12 mx-auto text-sand-300 mb-3"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            stroke="currentColor"
                                                            stroke-width="1.5">

                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                d="M13.5 6.75L17.25 3m0 0h-3.75m3.75 0v3.75M10.5 17.25L6.75 21m0 0h3.75m-3.75 0v-3.75M15 9l-6 6"/>

                                                        </svg>

                                                        <p class="text-sm text-sand-500 mb-4">
                                                            External Link
                                                        </p>

                                                        @if ($policy->link_url)

                                                            <a
                                                                href="{{ $policy->link_url }}"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                class="btn btn-md btn-primary">

                                                                Open Link

                                                            </a>

                                                        @endif

                                                    </div>

                                                @endif

                                            </div>

                                        </div>

                                    </div>

                                </template>

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="6">
                                <x-empty-state message="No policies match your search or filters." />
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        {{-- Pagination --}}
        @if ($policies->hasPages())

            <div class="px-5 py-4 border-t border-sand-100">
                {{ $policies->links() }}
            </div>

        @endif

    </div>

</div>

@endsection