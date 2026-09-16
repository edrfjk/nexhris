@props(['url'])
{{-- The letterhead every NexHRIS email carries. The order is the one a college
     document is read in: the Republic, then the institution, then the campus,
     and only then the system that sent the message. --}}
<tr>
<td class="header">
<p class="masthead-republic">Republic of the Philippines</p>
<p class="masthead-institution">Ilocos Sur Polytechnic State College</p>
<p class="masthead-campus">Tagudin Campus, Ilocos Sur</p>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr><td class="masthead-rule">&nbsp;</td></tr>
</table>

<a href="{{ $url }}" class="masthead-system">{{ trim($slot) === 'Laravel' ? config('app.name') : $slot }}</a><span class="masthead-tagline">&nbsp;&middot;&nbsp; Human Resource Information System</span>
</td>
</tr>
