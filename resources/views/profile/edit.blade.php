@extends('layouts.app')
@section('title', 'My Profile')

@section('content')

<x-page-header
    title="My Profile"
    subtitle="Your account details, photo and password." />

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- ============================================================
         LEFT — identity card and photo
         ============================================================ --}}
    <div class="space-y-5">

        <x-card>
            <div class="flex flex-col items-center text-center">
                <div class="w-28 h-28 rounded-full overflow-hidden bg-sand-100 ring-4 ring-white shadow-soft">
                    @if ($user->profile_photo_path)
                        <img src="{{ asset('storage/' . $user->profile_photo_path) }}"
                             alt="{{ $user->name }}" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-3xl font-bold text-sand-400">
                            {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                        </div>
                    @endif
                </div>

                <p class="mt-3 font-semibold text-sand-900">{{ $user->name }}</p>
                <p class="text-xs text-sand-500">{{ $user->position ?: $user->roleLabel() }}</p>

                <div class="mt-2">
                    <x-badge color="maroon">{{ $user->roleLabel() }}</x-badge>
                </div>

                <form method="POST" action="{{ route('profile.photo.update') }}"
                      enctype="multipart/form-data" class="w-full mt-5 pt-5 border-t border-sand-100">
                    @csrf
                    <label class="label">Change photo</label>
                    <input type="file" name="photo" accept="image/*" class="file-input" required>
                    <span class="hint">
                        JPG or PNG, up to 2 MB.{{ $user->isAdmin() ? '' : ' Used on your digital ID.' }}
                    </span>
                    <button class="btn btn-sm btn-secondary w-full mt-3">
                        <x-heroicon-o-arrow-up-tray />Upload photo
                    </button>
                </form>
            </div>
        </x-card>

        {{-- The HR Administrator is the system account that maintains these
             for everyone else, so "Managed by HR" is meaningless on their own
             profile. They see what their account is instead. --}}
        @if ($user->isAdmin())
            <x-card title="Account">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-xs text-sand-400 shrink-0">System role</dt>
                        <dd class="text-sand-700 text-right">{{ $user->roleLabel() }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-xs text-sand-400 shrink-0">Signed in since</dt>
                        <dd class="text-sand-700 text-right">
                            {{ $user->created_at?->format('M j, Y') ?: '—' }}
                        </dd>
                    </div>
                </dl>

                <p class="hint mt-4 leading-relaxed">
                    This is the administrator account for NexHRIS. Employee
                    records, colleges and approval routing are maintained under
                    People and Leave Management.
                </p>
            </x-card>
        @else
            <x-card title="Managed by HR">
                <dl class="space-y-3 text-sm">
                    @foreach ([
                        ['Employee number', $user->employee_number],
                        ['Position', $user->position],
                        ['College / Office', $user->collegeName()],
                        ['Department', $user->departmentName()],
                        ['System role', $user->roleLabel()],
                    ] as [$label, $value])
                        <div class="flex justify-between gap-3">
                            <dt class="text-xs text-sand-400 shrink-0">{{ $label }}</dt>
                            <dd class="text-sand-700 text-right">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>

                <p class="hint mt-4 leading-relaxed">
                    Your college decides which Dean signs your leave, so these are
                    changed by HR rather than here. Ask HR if any of them is wrong.
                </p>
            </x-card>
        @endif
    </div>

    {{-- ============================================================
         RIGHT — the editable parts
         ============================================================ --}}
    <div class="lg:col-span-2 space-y-5">

        <x-card title="Your details">
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="label label-required">Full name</label>
                        <input type="text" name="name" required
                               value="{{ old('name', $user->name) }}"
                               class="input @error('name') input-error @enderror">
                        @error('name') <span class="hint text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="label label-required">Email address</label>
                        <input type="email" name="email" required
                               value="{{ old('email', $user->email) }}"
                               class="input @error('email') input-error @enderror">
                        <span class="hint">You sign in with this address.</span>
                        @error('email') <span class="hint text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="label">Contact number</label>
                        <input type="text" name="contact_number"
                               value="{{ old('contact_number', $user->contact_number) }}"
                               placeholder="09XXXXXXXXX"
                               class="input @error('contact_number') input-error @enderror">
                        @error('contact_number') <span class="hint text-red-600">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex justify-end pt-2 border-t border-sand-100">
                    <button class="btn btn-md btn-primary">
                        <x-heroicon-o-check />Save changes
                    </button>
                </div>
            </form>
        </x-card>

        <x-card title="Change password">
            <form method="POST" action="{{ route('profile.password.update') }}" class="space-y-4">
                @csrf @method('PUT')

                <div>
                    <label class="label label-required">Current password</label>
                    <input type="password" name="current_password" required autocomplete="current-password"
                           class="input max-w-sm @error('current_password') input-error @enderror">
                    @error('current_password') <span class="hint text-red-600">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label label-required">New password</label>
                        <input type="password" name="password" required autocomplete="new-password"
                               class="input @error('password') input-error @enderror">
                        <span class="hint">At least 8 characters.</span>
                        @error('password') <span class="hint text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="label label-required">Confirm new password</label>
                        <input type="password" name="password_confirmation" required autocomplete="new-password"
                               class="input">
                    </div>
                </div>

                <div class="flex justify-end pt-2 border-t border-sand-100">
                    <button class="btn btn-md btn-primary">
                        <x-heroicon-o-key />Change password
                    </button>
                </div>
            </form>
        </x-card>

        @unless ($user->isAdmin())
        <x-card title="Quick links">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach ([
                    ['My Digital ID', 'my-id.show', 'identification'],
                    ['My Leave Ledger', 'leave.ledger.mine', 'book-open'],
                    ['My Personal Data Sheet', 'pds.edit', 'document-text'],
                ] as [$label, $route, $icon])
                    <a href="{{ route($route) }}"
                       class="card card-interactive px-3.5 py-3 flex items-center gap-3 group">
                        <div class="w-9 h-9 rounded border border-sand-200 bg-sand-50 text-sand-600
                                    flex items-center justify-center shrink-0
                                    group-hover:bg-maroon-700 group-hover:text-white
                                    group-hover:border-maroon-700 transition-colors">
                            <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-[18px] h-[18px]" />
                        </div>
                        <p class="text-[13px] font-medium text-sand-800">{{ $label }}</p>
                    </a>
                @endforeach
            </div>
        </x-card>
        @endunless
    </div>
</div>

@endsection
