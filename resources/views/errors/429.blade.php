@extends('errors.layout')

@section('title', 'Too many attempts')
@section('code', '429')
@section('heading', 'Too many attempts, too quickly')

@section('message')
    Sign-in is deliberately slowed after several failed attempts. Wait a minute
    and try again, or use "Forgot password" if you are no longer sure of it.
@endsection
