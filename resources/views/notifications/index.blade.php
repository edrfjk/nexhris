@extends('layouts.app')
@section('title', 'Notifications')

@section('content')

<x-page-header title="Notifications"
    :subtitle="$unreadCount > 0 ? $unreadCount . ' unread' : 'You are all caught up'">
    <x-slot:actions>
        @if ($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button class="btn btn-sm btn-secondary">
                    <x-heroicon-o-check />
                    Mark all read
                </button>
            </form>
        @endif
    </x-slot:actions>
</x-page-header>

<x-card :padded="false">
    @if ($notifications->isEmpty())
        <x-empty-state title="Nothing yet"
                       message="Leave approvals, returned forms and announcements will appear here."
                       icon="bell" />
    @else
        <ul class="divide-y divide-sand-100">
            @foreach ($notifications as $note)
                @php $data = $note->data; @endphp
                <li class="px-5 py-4 flex gap-3 {{ $note->read_at ? '' : 'bg-maroon-50/30' }}">
                    <span @class([
                        'w-8 h-8 rounded-lg flex items-center justify-center shrink-0',
                        'text-forest-700 bg-forest-50' => ($data['tone'] ?? '') === 'success',
                        'text-gold-800 bg-gold-50' => ($data['tone'] ?? '') === 'warning',
                        'text-red-700 bg-red-50' => ($data['tone'] ?? '') === 'error',
                        'text-sky-700 bg-sky-50' => ! in_array($data['tone'] ?? 'info', ['success','warning','error']),
                    ])>
                        <x-heroicon-o-bell-alert class="w-4 h-4" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="text-[13px] font-medium text-sand-900">{{ $data['headline'] ?? 'Notification' }}</p>
                        <p class="text-xs text-sand-600 mt-0.5">{{ $data['detail'] ?? '' }}</p>
                        <p class="text-[11px] text-sand-400 mt-1">{{ $note->created_at->diffForHumans() }}</p>
                    </div>

                    <div class="shrink-0">
                        @if (! empty($data['url']))
                            <form method="POST" action="{{ route('notifications.read', $note->id) }}">
                                @csrf
                                <button class="btn btn-xs btn-secondary">Open</button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="card-footer">{{ $notifications->links() }}</div>
    @endif
</x-card>

@endsection
