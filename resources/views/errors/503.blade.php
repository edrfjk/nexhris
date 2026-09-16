@extends('errors.layout')

@section('title', 'Down for maintenance')
@section('code', '503')
@section('heading', 'NexHRIS is briefly down for maintenance')

@section('message')
    The system is being updated and will be back shortly. Records already filed
    are untouched — nothing needs to be submitted again.
@endsection
