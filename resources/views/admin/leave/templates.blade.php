@extends('layouts.app')
@section('title', 'Templates')

@section('content')

<x-page-header
    title="Templates"
    subtitle="The blank PDS and leave forms employees download. Publishing never overwrites — each upload becomes a new version, and submissions keep the version they were filled on." />

<div x-data="{ tab: '{{ request('tab', 'pds') }}' }">

    {{-- Tabs --}}
    <div class="flex gap-1 border-b border-sand-200 mb-5">
        <button @click="tab = 'pds'"
                :class="tab === 'pds' ? 'border-maroon-800 text-maroon-800' : 'border-transparent text-sand-500 hover:text-sand-800'"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors">
            Personal Data Sheet
        </button>
        <button @click="tab = 'leave'"
                :class="tab === 'leave' ? 'border-maroon-800 text-maroon-800' : 'border-transparent text-sand-500 hover:text-sand-800'"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors">
            Leave Form
        </button>
    </div>

    {{-- ============================================================
         PDS TEMPLATE
         ============================================================ --}}
    <div x-show="tab === 'pds'" x-cloak class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <x-card title="Publish a new version">
            <x-slot:actions>
                <span class="badge badge-slate">v{{ \App\Models\PdsTemplate::nextVersion() }} next</span>
            </x-slot:actions>

            <form method="POST" action="{{ route('admin.pds.templates.store') }}"
                  enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div>
                    <label class="label label-required">Label</label>
                    <input type="text" name="label" required maxlength="120"
                           placeholder="CS Form No. 212 (Revised 2017)" class="input">
                </div>

                <div>
                    <label class="label label-required">Workbook</label>
                    <input type="file" name="file" required accept=".xlsx" class="file-input">
                    <span class="hint">
                        .xlsx only &mdash; the filled-in copy is converted to PDF with LibreOffice.
                    </span>
                </div>

                <button class="btn btn-md btn-primary w-full">
                    <x-heroicon-o-arrow-up-tray />
                    Publish version
                </button>
            </form>
        </x-card>

        <div class="lg:col-span-2">
            <x-card title="Version history" :padded="false">
                @if ($pdsTemplates->isEmpty())
                    <x-empty-state title="No PDS form published"
                                   message="Employees cannot download a blank PDS until you publish one."
                                   icon="document-arrow-up" />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Version</th>
                                    <th>Label</th>
                                    <th class="hidden md:table-cell">Published</th>
                                    <th class="num hidden lg:table-cell">Filed on it</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pdsTemplates as $template)
                                    <tr>
                                        <td>
                                            <span class="badge {{ $template->is_active ? 'badge-green' : 'badge-slate' }}">
                                                v{{ $template->version }}{{ $template->is_active ? ' · active' : '' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="block font-medium text-sand-900">{{ $template->label }}</span>
                                            <span class="block text-xs text-sand-500 truncate max-w-xs">
                                                {{ $template->original_filename }} · {{ $template->sizeLabel() }}
                                            </span>
                                            @if ($template->notes)
                                                <span class="block text-xs text-sand-400 italic">{{ $template->notes }}</span>
                                            @endif
                                        </td>
                                        <td class="hidden md:table-cell text-xs text-sand-500">
                                            {{ $template->created_at->format('M j, Y') }}<br>
                                            {{ $template->uploader->name ?? 'HR' }}
                                        </td>
                                        <td class="num hidden lg:table-cell">{{ $template->submissions_count }}</td>
                                        <td class="text-right whitespace-nowrap">
                                            @if ($template->url())
                                                <a href="{{ $template->url() }}" target="_blank" class="btn btn-xs btn-secondary">View</a>
                                            @endif

                                            @unless ($template->is_active)
                                                <form method="POST" action="{{ route('admin.pds.templates.activate', $template) }}" class="inline">
                                                    @csrf
                                                    <button class="btn btn-xs btn-primary">Use this</button>
                                                </form>
                                            @endunless

                                            <form method="POST" action="{{ route('admin.pds.templates.destroy', $template) }}"
                                                  class="inline"
                                                  onsubmit="return confirm({{ $template->is_active
                                                        ? Js::from('This is the ACTIVE template. Deleting it stops employees from downloading a blank PDS until another is activated. Delete anyway?')
                                                        : Js::from('Delete this version?') }})">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-xs btn-danger-soft">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>
    </div>

    {{-- ============================================================
         LEAVE FORM TEMPLATE
         ============================================================ --}}
    <div x-show="tab === 'leave'" x-cloak class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <x-card title="Publish a new version">
            <x-slot:actions>
                <span class="badge badge-slate">v{{ \App\Models\LeaveFormTemplate::nextVersion() }} next</span>
            </x-slot:actions>

            <form method="POST" action="{{ route('admin.leave.templates.store') }}"
                  enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div>
                    <label class="label label-required">Label</label>
                    <input type="text" name="label" required maxlength="120" value="{{ old('label') }}"
                           placeholder="CSC Form No. 6 (Revised 2020)" class="input">
                </div>

                <div>
                    <label class="label label-required">Workbook</label>
                    <input type="file" name="template" required accept=".xlsx,.xls" class="file-input">
                    <span class="hint">
                        Must be .xlsx — the filled-in copy is converted to PDF with LibreOffice.
                    </span>
                </div>

                <div>
                    <label class="label">What changed?</label>
                    <input type="text" name="notes" maxlength="255"
                           placeholder="Added service credit column" class="input">
                </div>

                <button class="btn btn-md btn-primary w-full">
                    <x-heroicon-o-arrow-up-tray />
                    Publish version
                </button>
            </form>
        </x-card>

        <div class="lg:col-span-2">
            <x-card title="Version history" :padded="false">
                @if ($templates->isEmpty())
                    <x-empty-state title="No leave form published"
                                   message="Employees fall back to the bundled default form until you publish one."
                                   icon="document-arrow-up" />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Version</th>
                                    <th>Label</th>
                                    <th class="hidden md:table-cell">Published</th>
                                    <th class="num hidden lg:table-cell">Forms filed</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($templates as $template)
                                    <tr>
                                        <td>
                                            <span class="badge {{ $template->is_active ? 'badge-green' : 'badge-slate' }}">
                                                v{{ $template->version }}{{ $template->is_active ? ' · active' : '' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="block font-medium text-sand-900">{{ $template->label }}</span>
                                            <span class="block text-xs text-sand-500 truncate max-w-xs">
                                                {{ $template->original_filename }} · {{ $template->sizeLabel() }}
                                            </span>
                                            @if ($template->notes)
                                                <span class="block text-xs text-sand-400 italic">{{ $template->notes }}</span>
                                            @endif
                                        </td>
                                        <td class="hidden md:table-cell text-xs text-sand-500">
                                            {{ $template->created_at->format('M j, Y') }}<br>
                                            {{ $template->uploader->name ?? 'HR' }}
                                        </td>
                                        <td class="num hidden lg:table-cell">{{ $template->applications_count }}</td>
                                        <td class="text-right whitespace-nowrap">
                                            @if ($template->url())
                                                <a href="{{ $template->url() }}" target="_blank" class="btn btn-xs btn-secondary">View</a>
                                            @endif

                                            @unless ($template->is_active)
                                                <form method="POST" action="{{ route('admin.leave.templates.activate', $template) }}" class="inline">
                                                    @csrf
                                                    <button class="btn btn-xs btn-primary">Use this</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.leave.templates.destroy', $template) }}"
                                                      class="inline" onsubmit="return confirm('Delete this version?')">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-xs btn-danger-soft">Delete</button>
                                                </form>
                                            @endunless
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>
    </div>

</div>

@endsection
