{{-- Identification block of the ledger card: name split into family / first /
     middle initial, the office, and the first day of government service —
     laid out exactly as rows 3–5 of the official workbook. --}}
<table class="ident">
    <tr>
        <td class="lbl" style="width:9%;">NAME:</td>
        <td class="fill" style="width:24%;">{{ $parts['family'] }}</td>
        <td class="fill" style="width:22%;">{{ $parts['first'] }}</td>
        <td class="fill" style="width:8%;">{{ $parts['middle'] }}</td>
        <td class="lbl" style="width:9%; padding-left:8px;">OFFICE:</td>
        <td class="fill" style="width:28%;">{{ $employee->department ?: $employee->program }}</td>
    </tr>
    <tr>
        <td></td>
        <td class="caption">(FAMILY NAME)</td>
        <td class="caption">(FIRST NAME)</td>
        <td class="caption">(M.I.)</td>
        <td></td>
        <td></td>
    </tr>
    <tr>
        <td class="lbl" colspan="2" style="padding-top:3px;">FIRST DAY OF GOVERNMENT SERVICE:</td>
        <td class="fill" colspan="2" style="padding-top:3px;">
            {{ $employee->first_day_of_service?->format('F j, Y') }}
        </td>
        <td class="lbl" style="padding-left:8px;">POSITION:</td>
        <td class="fill">{{ $employee->position }}</td>
    </tr>
</table>
