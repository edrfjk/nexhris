@extends('layouts.app')
@section('title', $policy->title)

@section('content')
@php
    $status = $policy->statusLabel();
    $catMeta = $policy->categoryMeta();
    // categoryMeta() falls back to config('policy_categories.default'), but guard here too
    // in case that config key is missing a color for a given category.
    $catColor = $catMeta['color'] ?? 'gray';
    $colorMap = [
        'gray' => ['from' => 'from-gray-700', 'to' => 'to-gray-900'],
        'blue' => ['from' => 'from-blue-700', 'to' => 'to-blue-900'],
        'green' => ['from' => 'from-green-700', 'to' => 'to-green-900'],
        'yellow' => ['from' => 'from-yellow-600', 'to' => 'to-yellow-800'],
        'red' => ['from' => 'from-red-700', 'to' => 'to-red-900'],
        'purple' => ['from' => 'from-purple-700', 'to' => 'to-purple-900'],
        'maroon' => ['from' => 'from-maroon-800', 'to' => 'to-maroon-900'],
    ];
    $theme = $colorMap[$catColor] ?? $colorMap['gray'];
@endphp

{{-- Slim reading-progress bar, only meaningful for long text policies --}}
@if ($policy->type === 'text')
    <div class="fixed top-0 left-0 right-0 h-1 bg-gray-100 z-40 print:hidden">
        <div id="reading-progress" class="h-full bg-gradient-to-r {{ $theme['from'] }} {{ $theme['to'] }} transition-all duration-150" style="width: 0%"></div>
    </div>
@endif

<x-page-header title="{{ $policy->title }}" subtitle="{{ $policy->category ?: 'General' }}">
    <x-slot:actions>
        @if ($policy->type === 'text')
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition print:hidden">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                Print
            </button>
        @endif
        <a href="{{ route('policies.index') }}"
           class="inline-flex items-center gap-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition print:hidden">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            Back to Policies
        </a>
    </x-slot:actions>
</x-page-header>



@if ($status === 'expired')
    <div class="mb-4 flex items-center gap-2 bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3 print:hidden">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
        This policy expired on {{ $policy->expiry_date->format('M d, Y') }} and may no longer be in effect.
    </div>
@elseif ($status === 'upcoming')
    <div class="mb-4 flex items-center gap-2 bg-blue-50 border border-blue-200 text-blue-800 text-sm rounded-lg px-4 py-3 print:hidden">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        This policy becomes effective on {{ $policy->effective_date->format('M d, Y') }}.
    </div>
@endif

{{-- Hero banner, colored by category --}}
<div class="rounded-xl overflow-hidden shadow-sm border border-gray-100 mb-6 print:hidden">
    <div class="bg-gradient-to-r {{ $theme['from'] }} {{ $theme['to'] }} px-6 py-8 relative overflow-hidden">
        <div class="absolute -right-8 -top-8 w-40 h-40 rounded-full bg-white/5"></div>
        <div class="absolute right-16 bottom-0 w-24 h-24 rounded-full bg-white/5"></div>
        <div class="relative flex items-start justify-between flex-wrap gap-3">
            <div>
                @if ($policy->category)
                    <span class="inline-block text-xs font-semibold uppercase tracking-wider text-white/70 mb-2">{{ $policy->category }}</span>
                @endif
                <h1 class="text-2xl font-bold text-white leading-tight">{{ $policy->title }}</h1>
            </div>
            <div class="flex items-center gap-2">
                @if ($policy->isNew())
                    <span class="inline-flex items-center gap-1 bg-white/15 backdrop-blur text-white text-xs font-semibold px-2.5 py-1 rounded-full">New</span>
                @endif
                @if ($policy->is_pinned)
                    <span class="inline-flex items-center gap-1 bg-white/15 backdrop-blur text-white text-xs font-semibold px-2.5 py-1 rounded-full">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M16 3l5 5-5.5 1.5L12 14l-2-2 4.5-4.5L13 6l3-3z"/></svg>
                        Pinned
                    </span>
                @endif
                @if ($policy->requires_acknowledgment)
                    <span class="inline-flex items-center gap-1 bg-white/15 backdrop-blur text-white text-xs font-semibold px-2.5 py-1 rounded-full">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Acknowledgment Required
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Meta strip with icons instead of a plain text line --}}
    <div class="bg-white px-6 py-3 flex flex-wrap items-center gap-x-5 gap-y-1.5 text-xs text-gray-500 border-t border-gray-100">
        <span class="inline-flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
            Published {{ $policy->published_at?->format('M d, Y') ?? 'recently' }}
        </span>
        @if ($policy->effective_date && $status !== 'upcoming')
            <span class="inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Effective {{ $policy->effective_date->format('M d, Y') }}
            </span>
        @endif
        @if ($policy->expiry_date && $status !== 'expired')
            <span class="inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                Expires {{ $policy->expiry_date->format('M d, Y') }}
            </span>
        @endif
        @if ($policy->readingTime())
            <span class="inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ $policy->readingTime() }}
            </span>
        @endif
        @if ($policy->creator)
            <span class="inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                {{ $policy->creator->name }}
            </span>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

    @php $toc = $policy->tableOfContents(); @endphp
    @if (count($toc) > 1)
        <aside class="lg:col-span-1 print:hidden">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sticky top-20">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                    Contents
                </p>
                <ul class="space-y-0.5 text-sm border-l-2 border-gray-100">
                    @foreach ($toc as $item)
                        <li style="padding-left: {{ 0.75 + ($item['level'] - 1) * 0.75 }}rem" class="-ml-0.5 border-l-2 border-transparent hover:border-maroon-300 transition">
                            <a href="#{{ $item['slug'] }}" class="block py-1 text-gray-600 hover:text-maroon-800 transition {{ $item['level'] === 1 ? 'font-medium' : '' }}">{{ $item['text'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>
    @endif

    <div class="{{ count($toc) > 1 ? 'lg:col-span-3' : 'lg:col-span-4' }}">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 sm:p-8">
                @if ($policy->type === 'text')
                    <div class="prose prose-sm sm:prose-base max-w-none policy-body prose-headings:scroll-mt-24 prose-a:text-maroon-800">{!! $policy->renderedBody() !!}</div>

                @elseif ($policy->type === 'file')
                    <div class="text-center py-12 border-2 border-dashed border-gray-200 rounded-xl bg-gray-50/50 print:hidden">
                        <div class="w-16 h-16 mx-auto rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        </div>
                        <p class="text-sm font-semibold text-gray-700 mb-1">{{ $policy->file_original_name }}</p>
                        <p class="text-xs text-gray-400 mb-5">Click below to view or download this document.</p>
                        <a href="{{ asset('storage/' . $policy->file_path) }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1.5 bg-maroon-800 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-maroon-900 transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75L12 3.75m0 6l3-3m-3 3l-3-3M3.75 15.75v3a2.25 2.25 0 002.25 2.25h12a2.25 2.25 0 002.25-2.25v-3"/></svg>
                            Open Document
                        </a>
                    </div>

                @elseif ($policy->type === 'link')
                    @php $host = parse_url($policy->link_url, PHP_URL_HOST) ?: $policy->link_url; @endphp
                    <div class="border-2 border-dashed border-gray-200 rounded-xl p-12 text-center bg-gray-50/50 print:hidden">
                        <div class="w-16 h-16 mx-auto rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                        </div>
                        <p class="text-sm font-semibold text-gray-700 mb-1">{{ $host }}</p>
                        <p class="text-xs text-gray-400 mb-5">This policy links to an external page.</p>
                        <a href="{{ $policy->link_url }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1.5 bg-maroon-800 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-maroon-900 transition shadow-sm">
                            Open Link
                        </a>
                    </div>

                @else
                    <p class="text-sm text-gray-400 text-center py-10">This policy's content format isn't supported for display.</p>
                @endif
            </div>

            @if ($policy->requires_acknowledgment)
                <div class="px-6 sm:px-8 py-5 border-t border-gray-100 rounded-b-xl print:hidden
                    {{ $myView?->acknowledged_at ? 'bg-green-50/50' : 'bg-gradient-to-r from-amber-50 to-white' }}">
                    @if ($myView?->acknowledged_at)
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-green-100 text-green-700 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-green-800">Acknowledged</p>
                                <p class="text-xs text-green-600">{{ $myView->acknowledged_at->format('M d, Y g:i A') }}</p>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-3 flex-wrap justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                                </div>
                                <p class="text-sm text-gray-700">This policy requires your acknowledgment.</p>
                            </div>
                            <form method="POST" action="{{ route('policies.acknowledge', $policy) }}"
                                  onsubmit="return confirm('Confirm that you have read and understood this policy? This will be recorded with your name and timestamp.')">
                                @csrf
                                <button class="inline-flex items-center gap-1.5 bg-green-700 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-green-800 transition shadow-sm">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                    I have read and understood this
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        @if ($related->isNotEmpty())
            <div class="mt-6 print:hidden">
                <p class="text-sm font-semibold text-gray-700 mb-3">More in {{ $policy->category ?: 'this category' }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @foreach ($related as $r)
                        <a href="{{ route('policies.show', $r) }}"
                           class="group bg-white rounded-lg border border-gray-100 p-4 hover:shadow-md hover:-translate-y-0.5 hover:border-maroon-200 transition">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-medium text-gray-800 leading-snug mb-1 group-hover:text-maroon-800 transition">{{ $r->title }}</p>
                                <svg class="w-4 h-4 text-gray-300 group-hover:text-maroon-800 flex-shrink-0 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            </div>
                            <p class="text-xs text-gray-400">{{ $r->published_at?->format('M d, Y') }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>

@if ($policy->type === 'text')
    <script>
        // Slim progress bar tracking scroll position through the policy body only
        // (not the whole page), so it reaches 100% right as the reader finishes.
        (function () {
            const bar = document.getElementById('reading-progress');
            const body = document.querySelector('.policy-body');
            if (!bar || !body) return;

            function update() {
                const rect = body.getBoundingClientRect();
                const total = rect.height - window.innerHeight;
                const scrolled = Math.min(Math.max(-rect.top, 0), Math.max(total, 1));
                const percent = total > 0 ? (scrolled / total) * 100 : 100;
                bar.style.width = Math.min(percent, 100) + '%';
            }

            document.addEventListener('scroll', update, { passive: true });
            window.addEventListener('resize', update);
            update();
        })();
    </script>
@endif

<style>
    @media print {
        header, .print\:hidden { display: none !important; }
        main { padding: 0 !important; }
    }
</style>
@endsection