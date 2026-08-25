@extends('layouts.app')
@section('title', 'Announcements')

@section('content')

<x-page-header title="Announcements" subtitle="Notices from the HR Office." />

@if ($announcements->isEmpty())
    <x-card>
        <x-empty-state title="No announcements yet"
                       message="Notices posted by HR will appear here."
                       icon="megaphone" />
    </x-card>
@else
    <div class="space-y-4">
        @foreach ($announcements as $announcement)
            <x-card>
                <div class="flex items-start justify-between gap-3 mb-2">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            @if ($announcement->is_pinned)
                                <span class="badge badge-maroon">
                                    <x-heroicon-o-bookmark />
                                    Pinned
                                </span>
                            @endif
                            @if ($announcement->category)
                                <span class="badge badge-slate">{{ $announcement->category }}</span>
                            @endif
                            @if ($announcement->college)
                                <span class="badge badge-blue">{{ $announcement->college->code }}</span>
                            @endif
                        </div>
                        <h2 class="text-[15px] font-semibold text-sand-900 mt-1.5">{{ $announcement->title }}</h2>
                    </div>

                    <span class="text-[11px] text-sand-400 shrink-0 whitespace-nowrap">
                        {{ $announcement->published_at?->format('M j, Y') }}
                    </span>
                </div>

                <div class="text-[13px] text-sand-700 leading-relaxed whitespace-pre-line">{{ $announcement->body }}</div>

                <p class="text-[11px] text-sand-400 mt-3 pt-3 border-t border-sand-100">
                    Posted by {{ $announcement->author->name ?? 'HR Office' }}
                </p>
            </x-card>
        @endforeach
    </div>

    <div class="mt-5">{{ $announcements->links() }}</div>
@endif

@endsection
