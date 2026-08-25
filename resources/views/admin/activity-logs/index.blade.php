@extends('layouts.app')
@section('title', 'Activity Log')

@section('content')

<x-page-header
    title="Activity Log"
    subtitle="Every sign-in, approval and record change, with the account and address behind it." />

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
    <x-stat-card label="Entries today" :value="number_format($todayCount)" icon="clipboard-document-list" />
    <x-stat-card label="Failed sign-ins today" :value="number_format($failedToday)"
                 :color="$failedToday > 0 ? 'red' : 'gray'" icon="shield-exclamation" />
    <x-stat-card label="Total entries" :value="number_format($logs->total())" color="blue" icon="server-stack" />
</div>

{{-- ------------------------------------------------------------------
     Filters
     ------------------------------------------------------------------ --}}
@php
    $prettyAction = fn ($a) => ucfirst(str_replace(['.', '_'], ' ', $a));

    $chips = [
        ['key' => 'search', 'label' => 'Search', 'value' => request('search')],
        ['key' => 'action', 'label' => 'Action',
         'value' => request('action') ? $prettyAction(request('action')) : null],
        ['key' => 'user_id', 'label' => 'Person',
         'value' => $people->firstWhere('id', (int) request('user_id'))?->name],
        ['key' => 'from', 'label' => 'From',
         'value' => request('from') ? \Carbon\Carbon::parse(request('from'))->format('M j, Y') : null],
        ['key' => 'to', 'label' => 'To',
         'value' => request('to') ? \Carbon\Carbon::parse(request('to'))->format('M j, Y') : null],
    ];
@endphp

<x-filter-bar :chips="$chips" :clear="route('admin.activity-logs.index')">

    <x-filter-field label="Search" :span="2">
        <x-filter-search placeholder="Person, description or IP address" />
    </x-filter-field>

    <x-filter-field label="Action">
        <select name="action" class="select">
            <option value="">All actions</option>
            @foreach ($actions as $action)
                <option value="{{ $action }}" @selected(request('action') === $action)>
                    {{ $prettyAction($action) }}
                </option>
            @endforeach
        </select>
    </x-filter-field>

    <x-filter-field label="Person">
        <select name="user_id" class="select">
            <option value="">Anyone</option>
            @foreach ($people as $person)
                <option value="{{ $person->id }}" @selected((string) request('user_id') === (string) $person->id)>
                    {{ $person->name }}
                </option>
            @endforeach
        </select>
    </x-filter-field>

    {{-- These were two unlabelled date boxes side by side, told apart only by
         a tooltip. Which one is which now says so on the page. --}}
    <x-filter-field label="From date">
        <input type="date" name="from" value="{{ request('from') }}"
               max="{{ request('to') ?: now()->format('Y-m-d') }}" class="input">
    </x-filter-field>

    <x-filter-field label="To date">
        <input type="date" name="to" value="{{ request('to') }}"
               min="{{ request('from') }}" max="{{ now()->format('Y-m-d') }}" class="input">
    </x-filter-field>

</x-filter-bar>

{{-- ------------------------------------------------------------------
     Trail
     ------------------------------------------------------------------ --}}
<x-card :padded="false">
    @if ($logs->isEmpty())
        <x-empty-state
            title="No activity recorded"
            message="Nothing matches these filters yet."
            icon="clipboard-document-list" />
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Who</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th class="hidden lg:table-cell">Record</th>
                        <th class="hidden md:table-cell">IP address</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap">
                                <span class="block text-sand-900">{{ $log->created_at->format('M j, Y') }}</span>
                                <span class="block text-xs text-sand-400 tabular">{{ $log->created_at->format('g:i:s A') }}</span>
                            </td>
                            <td>
                                @if ($log->user)
                                    <span class="block font-medium text-sand-900">{{ $log->user->name }}</span>
                                    <span class="block text-xs text-sand-400">{{ $log->user->roleLabel() }}</span>
                                @else
                                    <span class="text-sand-400 italic">Unauthenticated</span>
                                @endif
                            </td>
                            <td>
                                <x-badge :color="$log->tone()">{{ $log->actionLabel() }}</x-badge>
                            </td>
                            <td class="max-w-sm">
                                <span class="block truncate" title="{{ $log->description }}">
                                    {{ $log->description ?: '—' }}
                                </span>
                            </td>
                            <td class="hidden lg:table-cell text-xs text-sand-500">
                                {{ $log->subjectLabel() ?: '—' }}
                            </td>
                            <td class="hidden md:table-cell text-xs text-sand-500 tabular">
                                {{ $log->ip_address ?: '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-footer">
            {{ $logs->links() }}
        </div>
    @endif
</x-card>

@endsection
