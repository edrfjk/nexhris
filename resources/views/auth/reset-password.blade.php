@extends('layouts.guest')

@section('title', 'Set a new password')
@section('heading', 'Set a new password')
@section('subheading', 'Choose a password you do not use anywhere else.')

@section('form')
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="label">Email address</label>
            <input type="email" id="email" name="email" value="{{ old('email', $email) }}"
                   required autocomplete="username" class="input">
        </div>

        <div>
            <label for="password" class="label label-required">New password</label>
            <input type="password" id="password" name="password"
                   required autocomplete="new-password" class="input">
            <span class="hint">At least 8 characters, including a letter and a number.</span>
        </div>

        <div>
            <label for="password_confirmation" class="label label-required">Confirm new password</label>
            <input type="password" id="password_confirmation" name="password_confirmation"
                   required autocomplete="new-password" class="input">
        </div>

        <button type="submit" class="btn btn-lg btn-primary w-full">
            Change my password
        </button>
    </form>
@endsection

@section('footer')
    <a href="{{ route('login') }}" class="font-medium text-maroon-700 hover:text-maroon-900">
        &larr; Back to sign in
    </a>
@endsection
