{{-- Rendered through the shared NexHRIS mail shell in
     resources/views/vendor/mail, the same one the password reset, leave, PDS
     and announcement notices use. Nothing here styles itself, and the opening
     and sign-off match those notices line for line. --}}
<x-mail::message>
# Hello {{ $user->name }},

Someone signed in to NexHRIS with your email address and password. To finish
signing in as **{{ $user->roleLabel() }}**, enter the code below.

<x-mail::code>
{{ $code }}
</x-mail::code>

The code expires in **{{ $ttlMinutes }} minutes** and can be used only once.
Never share it. NexHRIS staff will not ask you for it.

<x-mail::panel>
If you did not try to sign in, your password may no longer be private. Change it
and notify the Human Resource Management Office as soon as you can.
</x-mail::panel>

&mdash; NexHRIS, ISPSC Tagudin Campus
</x-mail::message>
