{{-- Fallback for any 5xx without its own page (502, 504 ...). --}}
@extends('errors.500')

@section('code', $exception?->getStatusCode() ?? 500)
