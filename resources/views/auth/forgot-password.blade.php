@extends('layouts.guest')

@section('title', 'Forgot password')
@section('heading', 'Reset your password')
@section('subheading', 'Enter your NexHRIS email address and we will send you a reset link.')

@section('form')
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="label">Email address</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   required autofocus autocomplete="username"
                   class="input" placeholder="you@ispsc.edu.ph">
            <span class="hint">The link stays valid for 60 minutes.</span>
        </div>

        <button type="submit" class="btn btn-lg btn-primary w-full">
            Email me a reset link
        </button>
    </form>
@endsection

@section('footer')
    <a href="{{ route('login') }}" class="font-medium text-maroon-700 hover:text-maroon-900">
        &larr; Back to sign in
    </a>
@endsection
