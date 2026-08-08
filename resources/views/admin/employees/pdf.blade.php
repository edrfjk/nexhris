<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }

        .header { display: table; width: 100%; margin-bottom: 14px; border-bottom: 2px solid #7f1d1d; padding-bottom: 10px; }
        .header-left { display: table-cell; vertical-align: middle; }
        .header-right { display: table-cell; vertical-align: middle; text-align: right; font-size: 10px; color: #6b7280; }
        .title { font-size: 18px; font-weight: bold; color: #7f1d1d; margin: 0; }
        .subtitle { font-size: 11px; color: #6b7280; margin: 2px 0 0; }

        .filters { margin-bottom: 12px; font-size: 10px; color: #6b7280; }
        .filters span { display: inline-block; background: #fef2f2; color: #7f1d1d; border-radius: 10px; padding: 2px 8px; margin-right: 6px; }

        table.roster { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.roster thead th {
            background: #f9fafb; text-align: left; font-size: 10px; text-transform: uppercase;
            letter-spacing: 0.03em; color: #6b7280; padding: 6px 8px; border-bottom: 1px solid #e5e7eb;
        }
        table.roster tbody td { padding: 6px 8px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        table.roster tbody tr:nth-child(even) { background: #fafafa; }

        .name { font-weight: bold; color: #111827; }
        .muted { color: #9ca3af; font-size: 10px; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 8px; font-size: 9.5px; font-weight: bold; }
        .badge-active { background: #ecfdf5; color: #047857; }
        .badge-inactive { background: #f3f4f6; color: #6b7280; }

        .footer { position: fixed; bottom: -10px; left: 0; right: 0; text-align: center; font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>

    <div class="header">
        <div class="header-left">
            <p class="title">Employee Directory</p>
            <p class="subtitle">{{ $employees->count() }} employee{{ $employees->count() === 1 ? '' : 's' }} listed</p>
        </div>
        <div class="header-right">
            Generated {{ $generatedAt->format('F j, Y \a\t g:i A') }}<br>
            by {{ $generatedBy }}
        </div>
    </div>

    @if ($filtersApplied->isNotEmpty())
        <div class="filters">
            Filters applied:
            @foreach ($filtersApplied as $label => $value)
                <span>{{ $label }}: {{ $value }}</span>
            @endforeach
        </div>
    @endif

    <table class="roster">
        <thead>
            <tr>
                <th style="width: 22%;">Name</th>
                <th style="width: 20%;">Email</th>
                <th style="width: 12%;">Employee No.</th>
                <th style="width: 20%;">College / Program</th>
                <th style="width: 14%;">Contact No.</th>
                <th style="width: 12%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employees as $employee)
                <tr>
                    <td class="name">{{ $employee->name }}</td>
                    <td>{{ $employee->email }}</td>
                    <td>{{ $employee->employee_number }}</td>
                    <td>
                        {{ $employee->department ?: '—' }}
                        @if ($employee->program)
                            <br><span class="muted">{{ $employee->program }}</span>
                        @endif
                    </td>
                    <td>{{ $employee->contact_number ?: '—' }}</td>
                    <td>
                        <span class="badge {{ $employee->status === 'active' ? 'badge-active' : 'badge-inactive' }}">
                            {{ ucfirst($employee->status) }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center; padding: 16px; color: #9ca3af;">No employees match the applied filters.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">NexHRIS — Employee Directory Report</div>

</body>
</html>