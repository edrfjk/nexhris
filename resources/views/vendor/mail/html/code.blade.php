@props(['label' => 'Verification code'])
{{-- A one-time code, set apart from the prose so it can be read off a phone
     without hunting for it. Not markdown-parsed: the digits are printed
     exactly as issued. --}}
<table class="code" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="code-cell">
<p class="code-label">{{ $label }}</p>
<p class="code-value">{{ trim($slot) }}</p>
</td>
</tr>
</table>
