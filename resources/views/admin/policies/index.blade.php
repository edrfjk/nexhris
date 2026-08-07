@extends('layouts.app')
@section('title', 'HR Policies')

@section('content')
<x-page-header title="HR Policies" subtitle="Manage policy documents and announcements visible to employees.">
    <x-slot:actions>
        <a href="{{ route('admin.policies.create') }}"
           class="inline-flex items-center gap-1.5 bg-maroon-800 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-maroon-900 transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Add Policy
        </a>
    </x-slot:actions>
</x-page-header>

@if (session('success'))
    <div class="mb-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 12.75l6 6 9-13.5"/></svg>
        {{ session('success') }}
    </div>
@endif

<!-- Stat cards -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-gray-100 p-4">
        <div class="w-9 h-9 rounded-lg bg-gray-50 text-gray-600 flex items-center justify-center mb-3">
            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ $totalCount }}</p>
        <p class="text-xs text-gray-500 mt-1.5">Total Policies</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-green-100 p-4">
        <div class="w-9 h-9 rounded-lg bg-green-50 text-green-600 flex items-center justify-center mb-3">
            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ $publishedCount }}</p>
        <p class="text-xs text-gray-500 mt-1.5">Published</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-gray-100 p-4">
        <div class="w-9 h-9 rounded-lg bg-gray-50 text-gray-600 flex items-center justify-center mb-3">
            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ $draftCount }}</p>
        <p class="text-xs text-gray-500 mt-1.5">Drafts</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 ring-1 ring-blue-100 p-4">
        <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mb-3">
            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
        </div>
        <p class="text-2xl font-bold text-gray-800 leading-none">{{ $fileCount }}</p>
        <p class="text-xs text-gray-500 mt-1.5">Uploaded Files</p>
    </div>
</div>

<!-- Filter toolbar -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="flex flex-col sm:flex-row sm:items-end gap-3">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-medium text-gray-500 mb-1.5">Search</label>
            <div class="relative">
                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search policy title"
                       class="border border-gray-300 rounded-lg pl-9 pr-3 py-2 text-sm w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
            </div>
        </div>

        <div class="w-full sm:w-48">
            <label class="block text-xs font-medium text-gray-500 mb-1.5">Category</label>
            <select name="category" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                <option value="">All Categories</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat }}" @selected(request('category') === $cat)>{{ $cat }}</option>
                @endforeach
            </select>
        </div>

        <div class="w-full sm:w-36">
            <label class="block text-xs font-medium text-gray-500 mb-1.5">Type</label>
            <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                <option value="">All Types</option>
                <option value="text" @selected(request('type') === 'text')>Text</option>
                <option value="file" @selected(request('type') === 'file')>File</option>
            </select>
        </div>

        <div class="w-full sm:w-36">
            <label class="block text-xs font-medium text-gray-500 mb-1.5">Status</label>
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-full focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
                <option value="">All</option>
                <option value="published" @selected(request('status') === 'published')>Published</option>
                <option value="draft" @selected(request('status') === 'draft')>Draft</option>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-1.5 bg-gray-800 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-900 transition whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"/></svg>
                Apply
            </button>
            @if (request()->hasAny(['search', 'category', 'type', 'status']))
                <a href="{{ route('admin.policies.index') }}" class="inline-flex items-center gap-1.5 text-gray-500 border border-gray-300 px-3 py-2 rounded-lg text-sm hover:bg-gray-50 transition whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Clear
                </a>
            @endif
        </div>
    </form>

    @if (request()->hasAny(['search', 'category', 'type', 'status']))
        <div class="flex flex-wrap gap-2 mt-3 pt-3 border-t border-gray-100">
            @if (request('search'))
                <span class="inline-flex items-center gap-1 bg-maroon-50 text-maroon-800 text-xs font-medium px-2.5 py-1 rounded-full">Search: "{{ request('search') }}"</span>
            @endif
            @if (request('category'))
                <span class="inline-flex items-center gap-1 bg-maroon-50 text-maroon-800 text-xs font-medium px-2.5 py-1 rounded-full">Category: {{ request('category') }}</span>
            @endif
            @if (request('type'))
                <span class="inline-flex items-center gap-1 bg-maroon-50 text-maroon-800 text-xs font-medium px-2.5 py-1 rounded-full">Type: {{ ucfirst(request('type')) }}</span>
            @endif
            @if (request('status'))
                <span class="inline-flex items-center gap-1 bg-maroon-50 text-maroon-800 text-xs font-medium px-2.5 py-1 rounded-full">Status: {{ ucfirst(request('status')) }}</span>
            @endif
        </div>
    @endif
</div>

<!-- Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-500">
            <tr>
                <th class="px-5 py-3 font-medium">Policy</th>
                <th class="px-5 py-3 font-medium">Category</th>
                <th class="px-5 py-3 font-medium">Type</th>
                <th class="px-5 py-3 font-medium">Status</th>
                <th class="px-5 py-3 font-medium">Created By</th>
                <th class="px-5 py-3 font-medium text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($policies as $policy)
                <tr class="border-t border-gray-100 hover:bg-gray-50 transition" x-data="{ preview: false }">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 {{ $policy->type === 'file' ? 'bg-blue-50 text-blue-600' : 'bg-purple-50 text-purple-600' }}">
                                @if ($policy->type === 'file')
                                    <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                @else
                                    <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                                @endif
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">{{ $policy->title }}</p>
                                <p class="text-xs text-gray-400">{{ $policy->created_at->format('M d, Y') }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-gray-600">{{ $policy->category ?: '—' }}</td>
                    <td class="px-5 py-3">
                        <x-badge :color="$policy->type === 'file' ? 'blue' : 'purple'">{{ $policy->type === 'file' ? 'File' : 'Text' }}</x-badge>
                    </td>
                    <td class="px-5 py-3">
                        <form action="{{ route('admin.policies.toggle-publish', $policy) }}" method="POST">
                            @csrf
                            <button type="submit">
                                <x-badge :color="$policy->is_published ? 'green' : 'gray'">
                                    {{ $policy->is_published ? 'Published' : 'Draft' }}
                                </x-badge>
                            </button>
                        </form>
                    </td>
                    <td class="px-5 py-3 text-gray-500">{{ $policy->creator->name ?? '—' }}</td>
<td class="px-5 py-3">
    <div class="flex items-center justify-end gap-1.5" x-data="{ preview: false }">

        {{-- Preview --}}
        <button
            @click="preview = true"
            title="Preview"
            class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-500 hover:text-maroon-800 hover:bg-maroon-50 transition">

            <svg class="w-5 h-5"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke="currentColor">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="1.5"
                      d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="1.5"
                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </button>

        {{-- Edit --}}
        <a href="{{ route('admin.policies.edit', $policy) }}"
           title="Edit"
           class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-500 hover:text-blue-600 hover:bg-blue-50 transition">

            <svg class="w-5 h-5"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke="currentColor">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="1.5"
                      d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/>
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="1.5"
                      d="M19.5 7.125L16.875 4.5"/>
            </svg>
        </a>

        {{-- Delete --}}
        <form action="{{ route('admin.policies.destroy', $policy) }}"
              method="POST"
              onsubmit="return confirm('Delete this policy?')">

            @csrf
            @method('DELETE')

            <button
                type="submit"
                title="Delete"
                class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-500 hover:text-red-600 hover:bg-red-50 transition">

                <svg class="w-5 h-5"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="1.5"
                          d="M6 7.5h12"/>
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="1.5"
                          d="M9.75 7.5V5.625A1.125 1.125 0 0110.875 4.5h2.25A1.125 1.125 0 0114.25 5.625V7.5"/>
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="1.5"
                          d="M8.25 7.5l.75 11.25A1.125 1.125 0 0010.122 19.5h3.756A1.125 1.125 0 0015 18.75L15.75 7.5"/>
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="1.5"
                          d="M10.5 10.5v6m3-6v6"/>
                </svg>
            </button>

        </form>

        {{-- Preview Modal --}}
        <template x-teleport="body">
            <!-- your existing preview modal -->
        </template>

    </div>
</td>

                    <!-- Preview modal -->
                    <template x-teleport="body">
                        <div x-show="preview" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="preview = false">
                            <div class="bg-white rounded-xl shadow-lg max-w-2xl w-full max-h-[80vh] overflow-y-auto" @click.stop>
                                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-start sticky top-0 bg-white">
                                    <div>
                                        <p class="font-semibold text-gray-800">{{ $policy->title }}</p>
                                        <p class="text-xs text-gray-400 mt-0.5">{{ $policy->category ?: 'Uncategorized' }} · {{ $policy->is_published ? 'Published' : 'Draft' }}</p>
                                    </div>
                                    <button @click="preview = false" class="text-gray-400 hover:text-gray-600">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                                <div class="p-6">
                                    @if ($policy->type === 'text')
                                        <div class="prose prose-sm max-w-none">{!! $policy->body !!}</div>
                                    @else
                                        <div class="text-center py-8">
                                            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                            <p class="text-sm text-gray-500 mb-3">{{ $policy->file_original_name }}</p>
                                            <a href="{{ asset('storage/' . $policy->file_path) }}" target="_blank"
                                               class="inline-flex items-center gap-1.5 bg-maroon-800 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-maroon-900 transition">
                                                Open File
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </template>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state message="No policies match your search or filters." /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $policies->links() }}</div>
@endsection