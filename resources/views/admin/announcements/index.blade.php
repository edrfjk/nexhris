@extends('layouts.app')
@section('title', 'Announcements')

@section('content')

<x-page-header title="Announcements" subtitle="Post notices to the whole campus or a single college.">
    <x-slot:actions>
        <button type="button" class="btn btn-md btn-primary"
                onclick="document.getElementById('new-announcement').showModal()">
            <x-heroicon-o-plus />
            New Announcement
        </button>
    </x-slot:actions>
</x-page-header>

<x-card :padded="false">
    @if ($announcements->isEmpty())
        <x-empty-state title="Nothing posted yet"
                       message="Announcements you post appear on every employee's feed."
                       icon="megaphone" />
    @else
        <div class="table-wrap">
            {{-- A data table is wider than a phone. Without this the whole page scrolls sideways instead of the table. --}}
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th class="hidden md:table-cell">Audience</th>
                            <th class="hidden lg:table-cell">Posted</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($announcements as $announcement)
                            <tr>
                                <td>
                                    <span class="block font-medium text-sand-900">{{ $announcement->title }}</span>
                                    <span class="block text-xs text-sand-500 truncate max-w-md">
                                        {{ $announcement->excerpt(14) }}
                                    </span>
                                </td>
                                <td class="hidden md:table-cell">
                                    {{ $announcement->college->code ?? 'Campus-wide' }}
                                </td>
                                <td class="hidden lg:table-cell text-xs text-sand-500">
                                    {{ $announcement->published_at?->format('M j, Y') }}<br>
                                    {{ $announcement->author->name ?? 'HR' }}
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <span class="badge {{ $announcement->is_published ? 'badge-green' : 'badge-slate' }}">
                                            {{ $announcement->is_published ? 'Published' : 'Draft' }}
                                        </span>
                                        @if ($announcement->is_pinned)
                                            <span class="badge badge-maroon">Pinned</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-right">
                                    <button type="button" class="btn btn-xs btn-secondary"
                                            onclick="document.getElementById('edit-announcement-{{ $announcement->id }}').showModal()">
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}"
                                          class="inline" onsubmit="return confirm('Delete this announcement?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-xs btn-danger-soft">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer">{{ $announcements->links() }}</div>
    @endif
</x-card>

<dialog id="new-announcement" class="card w-[min(34rem,92vw)] p-0 backdrop:bg-sand-900/40">
    <form method="POST" action="{{ route('admin.announcements.store') }}">
        @csrf
        <div class="card-header">
            <h3 class="card-title"><x-heroicon-o-megaphone />New Announcement</h3>
        </div>

        <div class="card-body space-y-4">
            <div>
                <label class="label label-required">Title</label>
                <input type="text" name="title" required maxlength="150" class="input"
                       placeholder="Holiday schedule for December">
            </div>

            <div>
                <label class="label label-required">Message</label>
                <textarea name="body" required rows="6" maxlength="8000" class="textarea"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">Category</label>
                    <input type="text" name="category" maxlength="60" class="input" placeholder="General">
                </div>
                <div>
                    <label class="label">Audience</label>
                    <select name="college_id" class="select">
                        <option value="">Campus-wide</option>
                        @foreach ($colleges as $college)
                            <option value="{{ $college->id }}">{{ $college->code }} — {{ $college->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="space-y-2 pt-2 border-t border-sand-100">
                <label class="flex items-center gap-2.5 text-[13px] text-sand-700 cursor-pointer">
                    <input type="hidden" name="is_published" value="0">
                    <input type="checkbox" name="is_published" value="1" checked
                           class="rounded border-sand-300 text-maroon-800 focus:ring-maroon-500">
                    Publish immediately
                </label>
                <label class="flex items-center gap-2.5 text-[13px] text-sand-700 cursor-pointer">
                    <input type="hidden" name="is_pinned" value="0">
                    <input type="checkbox" name="is_pinned" value="1"
                           class="rounded border-sand-300 text-maroon-800 focus:ring-maroon-500">
                    Pin to the top of the feed
                </label>
                <label class="flex items-center gap-2.5 text-[13px] text-sand-700 cursor-pointer">
                    <input type="hidden" name="notify" value="0">
                    <input type="checkbox" name="notify" value="1" checked
                           class="rounded border-sand-300 text-maroon-800 focus:ring-maroon-500">
                    Notify by email and in-app
                </label>
            </div>
        </div>

        <div class="card-footer flex justify-end gap-2">
            <button type="button" class="btn btn-md btn-secondary"
                    onclick="document.getElementById('new-announcement').close()">Cancel</button>
            <button class="btn btn-md btn-primary">Post announcement</button>
        </div>
    </form>
</dialog>

{{-- Each edit dialog is scoped to its announcement so HR can change the
     audience, wording, pin and publication state without leaving the list. --}}
@foreach ($announcements as $announcement)
    <dialog id="edit-announcement-{{ $announcement->id }}" class="card w-[min(34rem,92vw)] p-0 backdrop:bg-sand-900/40">
        <form method="POST" action="{{ route('admin.announcements.update', $announcement) }}">
            @csrf @method('PUT')
            <div class="card-header">
                <h3 class="card-title"><x-heroicon-o-pencil-square />Edit announcement</h3>
            </div>

            <div class="card-body space-y-4">
                <div>
                    <label class="label label-required">Title</label>
                    <input type="text" name="title" required maxlength="150" class="input"
                           value="{{ $announcement->title }}">
                </div>

                <div>
                    <label class="label label-required">Message</label>
                    <textarea name="body" required rows="6" maxlength="8000" class="textarea">{{ $announcement->body }}</textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Category</label>
                        <input type="text" name="category" maxlength="60" class="input"
                               value="{{ $announcement->category }}" placeholder="General">
                    </div>
                    <div>
                        <label class="label">Audience</label>
                        <select name="college_id" class="select">
                            <option value="">Campus-wide</option>
                            @foreach ($colleges as $college)
                                <option value="{{ $college->id }}" @selected($announcement->college_id === $college->id)>
                                    {{ $college->code }} â€” {{ $college->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="space-y-2 border-t border-sand-100 pt-2">
                    <label class="flex cursor-pointer items-center gap-2.5 text-[13px] text-sand-700">
                        <input type="hidden" name="is_published" value="0">
                        <input type="checkbox" name="is_published" value="1" @checked($announcement->is_published)
                               class="rounded border-sand-300 text-maroon-800 focus:ring-maroon-500">
                        Publish to staff
                    </label>
                    <label class="flex cursor-pointer items-center gap-2.5 text-[13px] text-sand-700">
                        <input type="hidden" name="is_pinned" value="0">
                        <input type="checkbox" name="is_pinned" value="1" @checked($announcement->is_pinned)
                               class="rounded border-sand-300 text-maroon-800 focus:ring-maroon-500">
                        Pin to the top of the feed
                    </label>
                </div>
            </div>

            <div class="card-footer flex justify-end gap-2">
                <button type="button" class="btn btn-md btn-secondary"
                        onclick="document.getElementById('edit-announcement-{{ $announcement->id }}').close()">Cancel</button>
                <button class="btn btn-md btn-primary">Save changes</button>
            </div>
        </form>
    </dialog>
@endforeach

@endsection
