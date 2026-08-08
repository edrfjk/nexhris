@extends('layouts.app')

@section('title', 'HR Policies')

@section('content')

<div class="space-y-6">

    {{-- Page Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-800">HR Policies</h1>
            <p class="text-sm text-gray-500 mt-1">
                Manage HR policies, guidelines, and employee acknowledgments.
            </p>
        </div>

        <a href="{{ route('admin.policies.create') }}"
           class="inline-flex items-center gap-1.5 bg-maroon-800 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-maroon-900 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Add Policy
        </a>
    </div>


    {{-- Statistics --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

        {{-- Published --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-green-100 p-4">
            <div class="w-9 h-9 rounded-lg bg-green-50 text-green-600 flex items-center justify-center mb-3">
                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M4.5 12.75l6 6 9-13.5"/>
                </svg>
            </div>

            <p class="text-2xl font-bold text-gray-800 leading-none">
                {{ $publishedCount }}
            </p>

            <p class="text-xs text-gray-500 mt-1.5">
                Published
            </p>
        </div>


        {{-- Drafts --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-gray-100 p-4">
            <div class="w-9 h-9 rounded-lg bg-gray-50 text-gray-600 flex items-center justify-center mb-3">
                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/>
                </svg>
            </div>

            <p class="text-2xl font-bold text-gray-800 leading-none">
                {{ $draftCount }}
            </p>

            <p class="text-xs text-gray-500 mt-1.5">
                Drafts
            </p>
        </div>


        {{-- Uploaded Files --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-blue-100 p-4">
            <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mb-3">
                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                </svg>
            </div>

            <p class="text-2xl font-bold text-gray-800 leading-none">
                {{ $fileCount }}
            </p>

            <p class="text-xs text-gray-500 mt-1.5">
                Uploaded Files
            </p>
        </div>

    </div>


    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">

        <form method="GET"
              action="{{ route('admin.policies.index') }}"
              class="flex flex-wrap items-end gap-4">

            {{-- Search --}}
            <div class="flex-1 min-w-[220px]">
                <label class="block text-xs font-medium text-gray-500 mb-1.5">
                    Search
                </label>

                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search policies..."
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
            </div>


            {{-- Category --}}
            <div class="w-full sm:w-48">
                <label class="block text-xs font-medium text-gray-500 mb-1.5">
                    Category
                </label>

                <select
                    name="category"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent">

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
                <label class="block text-xs font-medium text-gray-500 mb-1.5">
                    Type
                </label>

                <select
                    name="type"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent">

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
                <label class="block text-xs font-medium text-gray-500 mb-1.5">
                    Status
                </label>

                <select
                    name="status"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent">

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
                    class="inline-flex items-center gap-1.5 bg-gray-800 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-900 transition whitespace-nowrap">

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
                        class="inline-flex items-center gap-1.5 text-gray-500 border border-gray-300 px-3 py-2 rounded-lg text-sm hover:bg-gray-50 transition whitespace-nowrap">

                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                             stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M6 18L18 6M6 6l12 12"/>
                        </svg>

                        Clear
                    </a>

                @endif

            </div>

        </form>


        {{-- Active Filters --}}
        @if (request()->hasAny(['search', 'category', 'type', 'status']))

            <div class="flex flex-wrap gap-2 mt-3 pt-3 border-t border-gray-100">

                @if (request('search'))
                    <span class="inline-flex items-center gap-1 bg-maroon-50 text-maroon-800 text-xs font-medium px-2.5 py-1 rounded-full">
                        Search: "{{ request('search') }}"
                    </span>
                @endif

                @if (request('category'))
                    <span class="inline-flex items-center gap-1 bg-maroon-50 text-maroon-800 text-xs font-medium px-2.5 py-1 rounded-full">
                        Category: {{ request('category') }}
                    </span>
                @endif

                @if (request('type'))
                    <span class="inline-flex items-center gap-1 bg-maroon-50 text-maroon-800 text-xs font-medium px-2.5 py-1 rounded-full">
                        Type: {{ ucfirst(request('type')) }}
                    </span>
                @endif

                @if (request('status'))
                    <span class="inline-flex items-center gap-1 bg-maroon-50 text-maroon-800 text-xs font-medium px-2.5 py-1 rounded-full">
                        Status: {{ ucfirst(request('status')) }}
                    </span>
                @endif

            </div>

        @endif

    </div>


    {{-- Policies Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

        <div class="overflow-x-auto">

            <table class="w-full text-sm">

                <thead class="bg-gray-50 border-b border-gray-100">

                    <tr>

                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Policy
                        </th>

                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Category
                        </th>

                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Type
                        </th>

                        {{-- NEW STATUS COLUMN --}}
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Status
                        </th>

                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Published
                        </th>

                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody class="divide-y divide-gray-100">

                    @forelse ($policies as $policy)

                        <tr
                            x-data="{ preview: false }"
                            class="hover:bg-gray-50 transition">

                            {{-- Policy Name --}}
                            <td class="px-5 py-3">

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
                                        <p class="font-medium text-gray-800 flex items-center gap-1.5">

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

                                        <p class="text-xs text-gray-400">
                                            {{ $policy->created_at->format('M d, Y') }}
                                        </p>

                                    </div>

                                </div>

                            </td>


                            {{-- Category --}}
                            <td class="px-5 py-3">

                                <span class="text-sm text-gray-600">
                                    {{ $policy->category ?: 'Uncategorized' }}
                                </span>

                            </td>


                            {{-- Type --}}
                            <td class="px-5 py-3">

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
                            <td class="px-5 py-3">

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
                            <td class="px-5 py-3">

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
                            <td class="px-5 py-3">

                                <div class="flex justify-end items-center gap-1">


                                    {{-- Preview --}}
                                    <button
                                        type="button"
                                        @click="preview = true"
                                        title="Preview"
                                        class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-500 hover:text-maroon-700 hover:bg-maroon-50 transition">

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
                                            class="inline-flex items-center justify-center w-9 h-9 rounded-lg transition
                                            {{ $policy->is_pinned
                                                ? 'text-maroon-700 bg-maroon-50'
                                                : 'text-gray-400 hover:bg-gray-50' }}">

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
                                            class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-500 hover:text-green-700 hover:bg-green-50 transition">

                                            <svg
                                                class="w-5 h-5"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="1.5">

                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>

                                            </svg>

                                        </a>

                                    @endif


                                    {{-- Edit --}}
                                    <a
                                        href="{{ route('admin.policies.edit', $policy) }}"
                                        title="Edit"
                                        class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-500 hover:text-blue-600 hover:bg-blue-50 transition">

                                        <svg
                                            class="w-5 h-5"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor">

                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="1.5"
                                                d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/>

                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="1.5"
                                                d="M19.5 7.125L16.875 4.5"/>

                                        </svg>

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
                                            class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-500 hover:text-red-600 hover:bg-red-50 transition">

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
                                            class="bg-white rounded-xl shadow-lg max-w-2xl w-full max-h-[80vh] overflow-y-auto"
                                            @click.stop>

                                            {{-- Modal Header --}}
                                            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-start sticky top-0 bg-white">

                                                <div>

                                                    <p class="font-semibold text-gray-800">
                                                        {{ $policy->title }}
                                                    </p>

                                                    <p class="text-xs text-gray-400 mt-0.5">
                                                        {{ $policy->category ?: 'Uncategorized' }}
                                                        ·
                                                        {{ $policy->is_published ? 'Published' : 'Draft' }}
                                                    </p>

                                                </div>

                                                <button
                                                    @click="preview = false"
                                                    class="text-gray-400 hover:text-gray-600">

                                                    <svg
                                                        class="w-5 h-5"
                                                        fill="none"
                                                        viewBox="0 0 24 24"
                                                        stroke="currentColor">

                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="1.5"
                                                            d="M6 18L18 6M6 6l12 12"/>

                                                    </svg>

                                                </button>

                                            </div>


                                            {{-- Modal Content --}}
                                            <div class="p-6">

                                                {{-- TEXT --}}
                                                @if ($policy->type === 'text')

                                                    <div class="prose prose-sm max-w-none">
                                                        {!! $policy->body !!}
                                                    </div>


                                                {{-- FILE --}}
                                                @elseif ($policy->type === 'file')

                                                    <div class="text-center py-8">

                                                        <svg
                                                            class="w-12 h-12 mx-auto text-gray-300 mb-3"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            stroke="currentColor"
                                                            stroke-width="1">

                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>

                                                        </svg>

                                                        <p class="text-sm text-gray-500 mb-3">
                                                            {{ $policy->file_original_name }}
                                                        </p>

                                                        @if ($policy->file_path)

                                                            <a
                                                                href="{{ asset('storage/' . $policy->file_path) }}"
                                                                target="_blank"
                                                                class="inline-flex items-center gap-1.5 bg-maroon-800 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-maroon-900 transition">

                                                                Open File

                                                            </a>

                                                        @endif

                                                    </div>


                                                {{-- LINK --}}
                                                @elseif ($policy->type === 'link')

                                                    <div class="text-center py-8">

                                                        <svg
                                                            class="w-12 h-12 mx-auto text-gray-300 mb-3"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            stroke="currentColor"
                                                            stroke-width="1.5">

                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                d="M13.5 6.75L17.25 3m0 0h-3.75m3.75 0v3.75M10.5 17.25L6.75 21m0 0h3.75m-3.75 0v-3.75M15 9l-6 6"/>

                                                        </svg>

                                                        <p class="text-sm text-gray-500 mb-4">
                                                            External Link
                                                        </p>

                                                        @if ($policy->link_url)

                                                            <a
                                                                href="{{ $policy->link_url }}"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                class="inline-flex items-center gap-1.5 bg-maroon-800 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-maroon-900 transition">

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

            <div class="px-5 py-4 border-t border-gray-100">
                {{ $policies->links() }}
            </div>

        @endif

    </div>

</div>

@endsection