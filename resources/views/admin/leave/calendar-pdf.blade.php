<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Leave Calendar — {{ $start->format('F Y') }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; color: #1f2937; font-size: 10px; }
        .header { border-bottom: 2px solid #7f1d1d; padding-bottom: 10px; margin-bottom: 14px; }
        .header h1 { font-size: 16px; margin: 0 0 2px; color: #7f1d1d; }
        .header p { margin: 0; color: #6b7280; font-size: 10px; }
        table.cal { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.cal th { background: #f9fafb; color: #6b7280; font-weight: 600; padding: 6px; border: 1px solid #e5e7eb; text-align: center; font-size: 9px; text-transform: uppercase; }
        table.cal td { border: 1px solid #e5e7eb; padding: 4px; vertical-align: top; height: 70px; width: 14.28%; }
        table.cal td.blank { background: #fafafa; }
        .day-num { font-weight: bold; font-size: 10px; color: #374151; margin-bottom: 3px; }
        .entry { font-size: 8px; padding: 1px 3px; border-radius: 3px; margin-bottom: 1px; display: block; }
        .entry.vl { background: #dbeafe; color: #1d4ed8; }
        .entry.sl { background: #dcfce7; color: #15803d; }
        .footer { margin-top: 14px; font-size: 8px; color: #9ca3af; text-align: right; }
        .legend { margin-bottom: 10px; font-size: 9px; color: #6b7280; }
        .legend span { margin-right: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Leave Calendar — {{ $start->format('F Y') }}</h1>
        <p>Approved leaves only</p>
    </div>

    <div class="legend">
        <span><span style="display:inline-block;width:8px;height:8px;background:#1d4ed8;margin-right:4px;"></span>Vacation Leave</span>
        <span><span style="display:inline-block;width:8px;height:8px;background:#15803d;margin-right:4px;"></span>Sick Leave</span>
    </div>

    <table class="cal">
        <thead>
            <tr>
                <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
            </tr>
        </thead>
        <tbody>
            @php
                $leadingBlanks = $start->dayOfWeek;
                $cells = [];
                for ($i = 0; $i < $leadingBlanks; $i++) { $cells[] = null; }
                foreach ($days as $date => $apps) { $cells[] = $date; }
                while (count($cells) % 7 !== 0) { $cells[] = null; }
                $weeks = array_chunk($cells, 7);
            @endphp

            @foreach ($weeks as $week)
                <tr>
                    @foreach ($week as $date)
                        @if (is_null($date))
                            <td class="blank"></td>
                        @else
                            @php $dateObj = \Carbon\Carbon::parse($date); $apps = $days[$date]; @endphp
                            <td>
                                <div class="day-num">{{ $dateObj->day }}</div>
                                @foreach ($apps as $app)
                                    <span class="entry {{ strtolower($app->leave_type) }}">{{ Str::limit($app->user->name, 14) }}</span>
                                @endforeach
                            </td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Generated on {{ $generatedAt->format('M d, Y g:i A') }}</div>
</body>
</html>