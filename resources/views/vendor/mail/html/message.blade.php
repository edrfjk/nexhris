<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
Automated message from {{ config('app.name') }}. Please do not reply to this email.

For assistance, contact the Human Resource Management Office, ISPSC Tagudin Campus.

&copy; {{ date('Y') }} Ilocos Sur Polytechnic State College &middot; Tagudin Campus
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
