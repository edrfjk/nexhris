@extends('layouts.app')
@section('title', 'HR Policies')

@section('content')
<x-page-header title="HR Policies" subtitle="Manage policy documents and announcements visible to employees.">
    <x-slot:actions>
        <a href="{{ route('admin.policies.create') }}" class="bg-maroon-800 text-white px-4 py-2 rounded-lg text-sm hover:bg-maroon-900 transition">
            + Add Policy
        </a>
    </x-slot:actions>
</x-page-header>

<form method="GET" class="flex gap-2 mb-4">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search title"
           class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64 focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
    <select name="category" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-maroon-700 focus:border-transparent">
        <option value="">All Categories</option>
        @foreach ($categories as $cat)
            <option value="{{ $cat }}" @selected(request('category') === $cat)>{{ $cat }}</option>
        @endforeach
    </select>
    <button class="bg-gray-100 hover:bg-gray-200 px-3 py-2 rounded-lg text-sm transition">Filter</button>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-500">
            <tr>
                <th class="px-4 py-3 font-medium">Title</th>
                <th class="px-4 py-3 font-medium">Category</th>
                <th class="px-4 py-3 font-medium">Type</th>
                <th class="px-4 py-3 font-medium">Status</th>
                <th class="px-4 py-3 font-medium">Created By</th>
                <th class="px-4 py-3 font-medium text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($policies as $policy)
                <tr class="border-t border-gray-100 hover:bg-gray-50 transition">
                    <td class="px-4 py-3 font-medium text-gray-700">{{ $policy->title }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ $policy->category ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <x-badge :color="$policy->type === 'file' ? 'blue' : 'purple'">
                            {{ $policy->type === 'file' ? 'File' : 'Text' }}
                        </x-badge>
                    </td>
                    <td class="px-4 py-3">
                        <form action="{{ route('admin.policies.toggle-publish', $policy) }}" method="POST">
                            @csrf
                            <button>
                                <x-badge :color="$policy->is_published ? 'green' : 'gray'">
                                    {{ $policy->is_published ? 'Published' : 'Draft' }}
                                </x-badge>
                            </button>
                        </form>
                    </td>
                    <td class="px-4 py-3 text-gray-500">{{ $policy->creator->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <a href="{{ route('admin.policies.edit', $policy) }}" class="text-blue-600 hover:underline">Edit</a>
                        <form action="{{ route('admin.policies.destroy', $policy) }}" method="POST" class="inline"
                              onsubmit="return confirm('Delete this policy?')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state message="No policies added yet." /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $policies->links() }}</div>
@endsection