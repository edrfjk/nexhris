@extends('layouts.guest')

@section('title', 'Sign in')
@section('heading', 'Sign in')
@section('subheading', 'Use the credentials issued by the HR Office.')

@section('form')
    @unless (\App\Services\TwoFactorService::isEnabled())
        {{-- Deliberately loud. A disabled security control that nobody can see
             is the kind that reaches production by accident. --}}
        <div class="alert alert-warning mb-5">
            <x-heroicon-o-shield-exclamation />
            <div>
                <p class="font-semibold">Two-factor verification is switched OFF</p>
                <p class="text-[13px] mt-0.5 leading-relaxed">
                    Development mode — HR, Deans and the Campus Director are signing in
                    with a password alone. Set <code class="text-xs">TWO_FACTOR_ENABLED=true</code>
                    in <code class="text-xs">.env</code> to restore it.
                </p>
            </div>
        </div>
    @endunless

    <form method="POST" action="{{ route('login') }}" class="space-y-4" autocomplete="off">
        @csrf

        <div>
            <label for="email" class="label">Email address</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   required autofocus autocomplete="username"
                   class="input" placeholder="you@ispsc.edu.ph">
        </div>

        <div x-data="{ show: false }">
            <label for="password" class="label">Password</label>
            <div class="relative">
                <input :type="show ? 'text' : 'password'" id="password" name="password"
                       required autocomplete="current-password"
                       class="input pr-11" placeholder="••••••••">
                <button type="button" @click="show = !show"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-sand-400 hover:text-sand-700"
                        :aria-label="show ? 'Hide password' : 'Show password'">
                    <x-heroicon-o-eye x-show="!show" class="w-4 h-4" />
                    <x-heroicon-o-eye-slash x-show="show" x-cloak class="w-4 h-4" />
                </button>
            </div>
        </div>

        <div class="flex items-center justify-between pt-1">
            <label class="flex items-center gap-2 text-[13px] text-sand-600 cursor-pointer">
                <input type="checkbox" name="remember"
                       class="rounded border-sand-300 text-maroon-800 focus:ring-maroon-500">
                Remember me
            </label>

            <a href="{{ route('password.request') }}"
               class="text-[13px] font-medium text-maroon-700 hover:text-maroon-900">
                Forgot password?
            </a>
        </div>

        <button type="submit" class="btn btn-lg btn-primary w-full">
            Sign in
        </button>
    </form>
@endsection

@section('footer')
    Accounts are issued by the HR Office. Contact them if you need access or a password reset.
@endsection

@push('scripts')
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.13.5/cdn.min.js"></script>
@endpush
