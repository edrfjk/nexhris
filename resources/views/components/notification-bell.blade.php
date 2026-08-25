@php
    $user = auth()->user();
    $unread = $user?->unreadNotifications()->take(8)->get() ?? collect();
    $unreadCount = $user?->unreadNotifications()->count() ?? 0;
@endphp

<div x-data="{ open: false }" class="relative">
    <button @click="open = !open" type="button"
            class="relative p-2 rounded-lg text-sand-500 hover:bg-sand-100 hover:text-sand-800 transition-colors"
            :aria-expanded="open" aria-label="Notifications">
        <x-heroicon-o-bell class="w-5 h-5" />

        @if ($unreadCount > 0)
            <span class="absolute top-1 right-1 min-w-[1rem] h-4 px-1 rounded-full bg-maroon-800
                         text-white text-[10px] font-bold leading-4 text-center tabular">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div x-show="open" @click.outside="open = false" x-cloak x-transition
         class="absolute right-0 mt-2 w-[22rem] max-w-[90vw] card shadow-lift z-50 overflow-hidden">

        <div class="card-header">
            <h3 class="card-title">Notifications</h3>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button class="text-xs font-medium text-maroon-700 hover:text-maroon-900">
                        Mark all read
                    </button>
                </form>
            @endif
        </div>

        @if ($unread->isEmpty())
            <div class="px-5 py-8 text-center">
                <x-heroicon-o-check-circle class="w-7 h-7 text-forest-500 mx-auto mb-2" />
                <p class="text-[13px] font-medium text-sand-700">You're all caught up</p>
                <p class="text-xs text-sand-500 mt-0.5">Nothing needs your attention right now.</p>
            </div>
        @else
            <ul class="divide-y divide-sand-100 max-h-[24rem] overflow-y-auto">
                @foreach ($unread as $note)
                    @php
                        $data = $note->data;
                        $tone = match ($data['tone'] ?? 'info') {
                            'success' => 'text-forest-700 bg-forest-50',
                            'warning' => 'text-gold-800 bg-gold-50',
                            'error' => 'text-red-700 bg-red-50',
                            default => 'text-sky-700 bg-sky-50',
                        };
                    @endphp
                    <li>
                        <form method="POST" action="{{ route('notifications.read', $note->id) }}">
                            @csrf
                            <button class="w-full text-left px-4 py-3 hover:bg-sand-50 transition-colors flex gap-3">
                                <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 {{ $tone }}">
                                    <x-heroicon-o-bell-alert class="w-4 h-4" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[13px] font-medium text-sand-900">
                                        {{ $data['headline'] ?? 'Notification' }}
                                    </span>
                                    <span class="block text-xs text-sand-500 line-clamp-2">
                                        {{ $data['detail'] ?? '' }}
                                    </span>
                                    <span class="block text-[11px] text-sand-400 mt-0.5">
                                        {{ $note->created_at->diffForHumans() }}
                                    </span>
                                </span>
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="card-footer text-center">
            <a href="{{ route('notifications.index') }}"
               class="text-xs font-medium text-maroon-700 hover:text-maroon-900">
                View all notifications
            </a>
        </div>
    </div>
</div>
