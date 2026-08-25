{{--
    EMPLOYEE'S LEAVE LEDGER CARD

    Laid out to match the campus template (LEAVE AND SERVICE LEDGER.xlsx)
    column for column: PERIOD over FROM / TO / REMARKS, then VACATION LEAVE
    and SICK LEAVE each over EARNED, the two ABSENCE / UNDERTIME columns and
    BALANCE, in the widths the sheet sets for columns A to K.

    The template is one card layout and it serves both records unchanged. The
    service credit ledger is the same card with the same columns; what differs
    is which entries appear on it — the ones that move service credits.

    The sheet's own formatting is followed: the seal drawn at B1, an Arial
    20pt bold title, the identity block bold at 11pt, row 6 ruled medium above
    and below rather than shaded, the headings wrapped and centred, and the
    body at 8pt with thin rules all round.

    Built as HTML rather than converted from the workbook because this form
    does not change, and a rebuilt table lets a long remark wrap and push its
    row taller instead of being clipped by a fixed cell.
--}}
@php
    $parts = $employee->nameParts();

    // The seal comes out of whichever ledger template is published, so a new
    // template brings its own logo with it.
    $logo = app(\App\Services\LedgerCardAssets::class)->logoDataUri();

    // The leave card is kept to two places and the service credit card to
    // three, as the client's own cards are.
    $money = fn ($value, $places = 2) => (float) $value == 0.0
        ? ''
        : rtrim(rtrim(number_format((float) $value, $places, '.', ''), '0'), '.');

    $pages = [
        [
            'title' => "EMPLOYEE'S LEAVE LEDGER CARD",
            'caption' => null,
            'rows' => $ledger->reject->isOnServiceCard()->values(),
            'service' => false,
            'places' => 2,
        ],
        [
            // The sheet heads both records identically; the caption stands
            // in for the tab that told them apart in the workbook.
            'title' => "EMPLOYEE'S LEAVE LEDGER CARD",
            'caption' => 'SERVICE CREDITS',
            'rows' => $serviceRows,
            'service' => true,
            'places' => 3,
        ],
    ];
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* The sheet is A4 portrait with narrow margins. */
        @page { size: A4 portrait; margin: 8mm 5mm 8mm 5mm; }

        /* Arial is one of Dompdf's core faces and covers the accented
           characters in Filipino names. Calibri, which the sheet uses for
           the smaller text, is not available to it, so one family carries
           the whole card. */
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            color: #000;
            margin: 0;
        }

        .card + .card { page-break-before: always; }

        /* ---- masthead: the seal beside the title, as row 1 has it ---- */
        .masthead { width: 100%; border-collapse: collapse; margin-bottom: 2pt; }
        .masthead td { vertical-align: middle; padding: 0; }
        .masthead .seal { width: 62pt; }
        .masthead .seal img { width: 50pt; height: 50pt; }

        .title {
            text-align: center;
            font-size: 20pt;
            font-weight: bold;
            line-height: 1.05;
        }

        /* The workbook told the two records apart by their sheet tab. A PDF
           has none, so the second card names itself. */
        .caption-line {
            display: block;
            font-size: 9pt;
            font-weight: bold;
            letter-spacing: 1pt;
            padding-top: 1pt;
        }

        /* ---- identity block: bold, 11pt on the sheet ---- */
        .identity { width: 100%; border-collapse: collapse; margin-bottom: 3pt; }
        .identity td {
            padding: 0 0 1pt 0;
            vertical-align: bottom;
            font-size: 9pt;
            font-weight: bold;
        }
        .identity .label { white-space: nowrap; padding-right: 3pt; }
        .identity .value { border-bottom: 0.75pt solid #000; padding: 0 3pt 1pt 3pt; }
        .identity .caption { font-size: 6pt; font-weight: normal; text-align: center; }

        /* ---- the ruled table ---- */
        table.ledger {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.ledger th,
        table.ledger td {
            border: 0.75pt solid #000;
            padding: 1pt 2pt;
        }

        /* Row 6: the band naming PERIOD and each leave type. Ruled medium
           above and below — the form is ruled, not shaded. */
        table.ledger th.band {
            font-size: 9pt;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            border-top: 1.5pt solid #000;
            border-bottom: 1.5pt solid #000;
            height: 15pt;
        }

        /* Row 7: column headings, wrapped and centred. */
        table.ledger th.heading {
            font-size: 7pt;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            line-height: 1.1;
            height: 32pt;
        }

        table.ledger td {
            font-size: 8pt;
            height: 14pt;
            vertical-align: top;
        }

        /* A long reason wraps and takes the row with it — the whole reason
           this page is built rather than converted. */
        td.remarks { text-align: left; word-wrap: break-word; overflow-wrap: break-word; }
        td.period { text-align: left; }
        td.num { text-align: center; }

        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        .closing td { font-weight: bold; border-top: 1.5pt solid #000; }

        .empty { text-align: center; padding: 10pt; font-style: italic; color: #333; }

        .foot { margin-top: 5pt; width: 100%; font-size: 6pt; color: #333; }
        .foot td { padding: 0; }
    </style>
</head>
<body>

@foreach ($pages as $page)
    <div class="card">

        <table class="masthead">
            <tr>
                <td class="seal">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="">
                    @endif
                </td>
                <td class="title">
                    {{ $page['title'] }}
                    @if ($page['caption'])
                        <span class="caption-line">{{ $page['caption'] }}</span>
                    @endif
                </td>
                {{-- Balances the seal so the title sits centred on the page. --}}
                <td class="seal"></td>
            </tr>
        </table>

        {{-- NAME / OFFICE / FIRST DAY OF GOVERNMENT SERVICE, as the form has it --}}
        <table class="identity">
            <tr>
                <td class="label" style="width: 8%;">NAME:</td>
                <td class="value" style="width: 24%;">{{ $parts['family'] ?? '' }}</td>
                <td class="value" style="width: 20%;">{{ $parts['first'] ?? '' }}</td>
                <td class="value" style="width: 8%;">{{ $parts['middle'] ?? '' }}</td>
                <td class="label" style="width: 8%; padding-left: 8pt;">OFFICE:</td>
                <td class="value">{{ $employee->collegeName() ?: '' }}</td>
            </tr>
            <tr>
                <td></td>
                <td class="caption">(FAMILY NAME)</td>
                <td class="caption">(FIRST NAME)</td>
                <td class="caption">(M.I.)</td>
                <td colspan="2"></td>
            </tr>
            <tr>
                <td class="label" colspan="2" style="padding-top: 3pt;">
                    FIRST DAY OF GOVERNMENT SERVICE:
                </td>
                <td class="value" colspan="2" style="vertical-align: bottom;">
                    {{ optional($employee->first_day_of_service ?? $employee->date_hired)->format('F j, Y') }}
                </td>
                <td colspan="2"></td>
            </tr>
        </table>

        <table class="ledger">
            {{-- Widths in the same proportion the template sets:
                 A 11.0, B 17.8, C 13.2, D–K 11.0 each, of 130.0 total. --}}
            <colgroup>
                <col style="width: 8.46%;">  {{-- A  FROM --}}
                <col style="width: 13.69%;"> {{-- B  TO --}}
                <col style="width: 10.15%;"> {{-- C  REMARKS --}}
                <col style="width: 8.46%;">  {{-- D  earned --}}
                <col style="width: 8.46%;">  {{-- E  w/pay --}}
                <col style="width: 8.46%;">  {{-- F  w/o pay --}}
                <col style="width: 8.46%;">  {{-- G  balance --}}
                <col style="width: 8.46%;">  {{-- H  earned --}}
                <col style="width: 8.46%;">  {{-- I  w/pay --}}
                <col style="width: 8.46%;">  {{-- J  w/o pay --}}
                <col style="width: 8.46%;">  {{-- K  balance --}}
            </colgroup>

            <thead>
                <tr>
                    {{-- Both records use the sheet's own columns unchanged;
                         what differs is which entries land on each card. --}}
                    <th class="band" colspan="3">PERIOD</th>
                    <th class="band" colspan="4">VACATION LEAVE</th>
                    <th class="band" colspan="4">SICK LEAVE</th>
                </tr>
                <tr>
                    <th class="heading">FROM</th>
                    <th class="heading">TO</th>
                    <th class="heading">REMARKS</th>
                    <th class="heading">EARNED</th>
                    <th class="heading">ABSENCE / UNDERTIME / W/PAY</th>
                    <th class="heading">ABSENCE / UNDERTIME / W/O PAY</th>
                    <th class="heading">BALANCE</th>
                    <th class="heading">EARNED</th>
                    <th class="heading">ABSENCE / UNDERTIME / W/PAY</th>
                    <th class="heading">ABSENCE / UNDERTIME / W/O PAY</th>
                    <th class="heading">BALANCE</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($page['rows'] as $row)
                    <tr>
                        <td class="period">{{ $row->period_from?->format('M j, Y') ?: '' }}</td>
                        <td class="period">
                            {{ $row->period_to && ! $row->period_to->eq($row->period_from)
                                ? $row->period_to->format('M j, Y') : '' }}
                        </td>
                        <td class="remarks">{{ $row->remarks }}</td>

                        @php $p = $page['places']; @endphp
                        <td class="num">{{ $money($row->vl_earned, $p) }}</td>
                        <td class="num">{{ $money($row->vl_used, $p) }}</td>
                        <td class="num">{{ $money($row->vl_used_wop, $p) }}</td>
                        <td class="num">
                            {{-- On the service card the running figure is the
                                 service credit balance, shown at the end. --}}
                            {{ $page['service'] ? '' : $money($row->vl_balance, $p) }}
                        </td>
                        <td class="num">{{ $money($row->sl_earned, $p) }}</td>
                        <td class="num">{{ $money($row->sl_used, $p) }}</td>
                        <td class="num">{{ $money($row->sl_used_wop, $p) }}</td>
                        <td class="num">
                            {{ $page['service']
                                ? $money($row->service_balance, $p)
                                : $money($row->sl_balance, $p) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="empty">
                            {{ $page['service']
                                ? 'No service credits have been recorded.'
                                : 'No leave has been recorded on this card yet.' }}
                        </td>
                    </tr>
                @endforelse

                {{-- Closing balance, the way the card ends. The service
                     card states the figure it is kept for; the leave card
                     states both leave balances. --}}
                @if ($balance)
                    <tr class="closing">
                        <td colspan="3" style="text-align: right;">
                            {{ $page['service'] ? 'SERVICE CREDIT BALANCE' : 'BALANCE' }}
                            AS OF {{ strtoupper($generatedAt->format('F j, Y')) }}
                        </td>

                        @if ($page['service'])
                            <td class="num" colspan="7"></td>
                            <td class="num">{{ number_format((float) $balance->service_balance, 3) }}</td>
                        @else
                            <td class="num"></td>
                            <td class="num"></td>
                            <td class="num"></td>
                            <td class="num">{{ number_format((float) $balance->vl_balance, 2) }}</td>
                            <td class="num"></td>
                            <td class="num"></td>
                            <td class="num"></td>
                            <td class="num">{{ number_format((float) $balance->sl_balance, 2) }}</td>
                        @endif
                    </tr>
                @endif

            </tbody>
        </table>

        <table class="foot">
            <tr>
                <td>Generated {{ $generatedAt->format('F j, Y g:i A') }}</td>
                <td style="text-align: right;">
                    ISPSC Tagudin Campus · Human Resource Management Office
                </td>
            </tr>
        </table>
    </div>
@endforeach

</body>
</html>
