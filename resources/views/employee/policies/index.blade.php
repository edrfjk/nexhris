@extends('layouts.app')
@section('title', 'HR Policies')

@section('content')
<x-page-header title="HR Policies" subtitle="Official policies, guidelines, and announcements from the HR Office." />

@php
    // Tailwind's JIT compiler needs complete, literal class strings to scan at build
    // time — bg-{{ $var }}-50 never gets compiled since it can't see "bg-sky-50" as
    // a whole string, only fragments with a variable in between. This static map is
    // the fix: every combination that could actually appear is spelled out literally
    // somewhere in this file, so Tailwind picks it up.
    $categoryColorMap = [
        'gray' => ['badge' => 'bg-sand-50 text-sand-600'],
        'blue' => ['badge' => 'bg-sky-50 text-sky-600'],
        'green' => ['badge' => 'bg-forest-50 text-forest-600'],
        'yellow' => ['badge' => 'bg-gold-50 text-gold-600'],
        'red' => ['badge' => 'bg-red-50 text-red-600'],
        'purple' => ['badge' => 'bg-violet-50 text-violet-600'],
        'maroon' => ['badge' => 'bg-maroon-50 text-maroon-800'],
    ];

    // Preserve every current query param except 'tab' and 'page' when switching tabs,
    // so an active search/category filter doesn't silently disappear.
    $tabQuery = request()->except(['tab', 'page']);
@endphp

<!-- Featured / pinned banner -->
@if ($featured && $tab === 'all' && !request()->hasAny(['search', 'category']))
    @php
        $fMeta = $featured->categoryMeta();
        $fBadge = $categoryColorMap[$fMeta['color'] ?? 'gray']['badge'] ?? $categoryColorMap['gray']['badge'];
    @endphp
    <a href="{{ route('policies.show', $featured) }}"
       class="block bg-maroon-800 rounded shadow-soft p-6 mb-6 text-white hover:shadow-soft transition relative overflow-hidden">
        <div class="relative z-10">
            <div class="flex items-center gap-2 mb-2">
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-white/15">
                    <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $fMeta['icon'] ?? 'M16 4v6l2 4H6l2-4V4h8zm-4 12v6' }}"/></svg>
                </span>
                <span class="text-xs font-semibold uppercase tracking-wider text-sand-300">Pinned Announcement</span>
                @if ($featured->isNew())
                    <span class="text-xs font-semibold bg-ispscgold text-maroon-900 px-2 py-0.5 rounded-full">New</span>
                @endif
            </div>
            <h2 class="text-xl font-bold mb-1">{{ $featured->title }}</h2>
            <p class="text-sm text-sand-300">{{ $featured->category ?: 'General' }} · {{ $featured->published_at?->format('M d, Y') }}
                @if ($featured->readingTime()) · {{ $featured->readingTime() }} @endif
            </p>
        </div>
    </a>
@endif

<!-- Tabs -->
<div class="flex gap-1 mb-6 border-b border-sand-200">
    <a href="{{ route('policies.index', array_merge($tabQuery, ['tab' => 'all'])) }}"
       class="px-4 py-2.5 text-sm font-medium border-b-2 transition {{ $tab === 'all' ? 'border-maroon-800 text-maroon-800' : 'border-transparent text-sand-500 hover:text-sand-700' }}">
        All Policies
    </a>
    <a href="{{ route('policies.index', array_merge($tabQuery, ['tab' => 'for_you'])) }}"
       class="relative px-4 py-2.5 text-sm font-medium border-b-2 transition {{ $tab === 'for_you' ? 'border-maroon-800 text-maroon-800' : 'border-transparent text-sand-500 hover:text-sand-700' }}">
        For You
        @if ($forYouCount > 0)
            <span class="ml-1 bg-red-600 text-white text-[10px] font-bold rounded-full px-1.5 py-0.5">{{ $forYouCount }}</span>
        @endif
    </a>
</div>

@if ($tab === 'for_you' && $forYouCount === 0)
    <div class="flex items-center gap-2 bg-forest-50 border border-forest-200 text-forest-800 text-sm rounded-lg px-4 py-3 mb-6">
        <x-heroicon-o-check class="w-4 h-4 flex-shrink-0" />
        You're all caught up — no policies currently require your acknowledgment.
    </div>
@endif

<!-- Filter toolbar -->
<div class="card p-4 mb-6">
    <form method="GET" class="flex flex-col sm:flex-row sm:items-end gap-3">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="flex-1 min-w-[200px]">
            <label class="label">Search</label>
            <div class="relative">
                <x-heroicon-o-magnifying-glass class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-sand-400" />
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search policy title"
                       class="input pl-9 pr-3">
            </div>
        </div>
        <div class="w-full sm:w-56">
            <label class="label">Category</label>
            <select name="category" class="select">
                <option value="">All Categories</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat }}" @selected(request('category') === $cat)>{{ $cat }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn btn-md btn-primary whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"/></svg>
                Apply
            </button>
            @if (request()->hasAny(['search', 'category']))
                <a href="{{ route('policies.index', ['tab' => $tab]) }}" class="btn btn-md btn-secondary">
                    <x-heroicon-o-x-mark class="w-4 h-4" />
                    Clear
                </a>
            @endif
        </div>
    </form>

    @if (request()->hasAny(['search', 'category']))
        <div class="flex flex-wrap gap-2 mt-3 pt-3 border-t border-sand-100">
            @if (request('search'))
                <span class="chip chip-maroon">Search: "{{ request('search') }}"</span>
            @endif
            @if (request('category'))
                <span class="chip chip-maroon">Category: {{ request('category') }}</span>
            @endif
        </div>
    @endif
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse ($policies as $policy)
        @php
            $meta = $policy->categoryMeta();
            $badgeClasses = $categoryColorMap[$meta['color'] ?? 'gray']['badge'] ?? $categoryColorMap['gray']['badge'];
            $status = $policy->statusLabel();
            $viewed = $myViews->has($policy->id);
            $acknowledged = $myViews->get($policy->id)?->acknowledged_at;
        @endphp
        <a href="{{ route('policies.show', $policy) }}"
           class="card p-5 hover:shadow-soft transition relative">

            <div class="absolute top-3 right-3 flex items-center gap-1.5">
                @if ($policy->isNew())
                    <span class="text-[10px] font-semibold bg-sky-100 text-sky-700 px-2 py-0.5 rounded-full">New</span>
                @endif
                @if ($policy->is_pinned)
                    <svg class="w-4 h-4 text-maroon-700" fill="currentColor" viewBox="0 0 24 24" title="Pinned"><path d="M16 4v6l2 4H6l2-4V4h8zm-4 12v6"/></svg>
                @endif
            </div>

            <div class="w-10 h-10 rounded-lg flex items-center justify-center mb-3 {{ $badgeClasses }}">
                @if ($policy->type === 'link')
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                @elseif ($policy->type === 'file')
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                @else
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $meta['icon'] }}"/></svg>
                @endif
            </div>

            <p class="font-semibold text-sand-800 leading-snug mb-1 pr-10">{{ $policy->title }}</p>
            <p class="text-xs text-sand-400 mb-3">
                {{ $policy->category ?: 'General' }} · {{ $policy->published_at?->format('M d, Y') }}
                @if ($policy->readingTime()) · {{ $policy->readingTime() }} @endif
            </p>

            <div class="flex items-center gap-2 flex-wrap">
                @if ($status === 'upcoming')
                    <x-badge color="blue">Upcoming</x-badge>
                @elseif ($status === 'expired')
                    <x-badge color="red">Expired</x-badge>
                @endif

                @if ($policy->requires_acknowledgment)
                    @if ($acknowledged)
                        <x-badge color="green">Acknowledged</x-badge>
                    @else
                        <x-badge color="yellow">Action Required</x-badge>
                    @endif
                @elseif ($viewed)
                    <span class="text-xs text-sand-400">Viewed</span>
                @endif
            </div>
        </a>
    @empty
        <div class="col-span-full">
            <x-empty-state message="No policies found." />
        </div>
    @endforelse
</div>

<div class="mt-6">{{ $policies->links() }}</div>
@endsection