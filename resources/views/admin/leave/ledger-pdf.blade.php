<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('admin.pdf.partials.styles')
</head>
<body>

    @include('admin.pdf.partials.header', ['title' => 'Leave Ledger Report'])

    <div class="info-box">
        <div class="info-row">
            <div class="info-col" style="width: 30%;">
                <div class="info-col-label">Employee</div>
                <div class="info-col-value">{{ $employee->name }}</div>
            </div>
            <div class="info-col" style="width: 20%;">
                <div class="info-col-label">Employee No.</div>
                <div class="info-col-value">{{ $employee->employee_number ?: '—' }}</div>
            </div>
            <div class="info-col" style="width: 25%;">
                <div class="info-col-label">Position</div>
                <div class="info-col-value">{{ $employee->position ?: '—' }}</div>
            </div>
            <div class="info-col" style="width: 25%;">
                <div class="info-col-label">College/Office</div>
                <div class="info-col-value">{{ $employee->department ?: '—' }}</div>
            </div>
        </div>
    </div>

    <div class="stat-row">
        <div class="stat-box" style="width: 50%;">
            <div class="stat-label">Current Vacation Leave Balance</div>
            <div class="stat-value">{{ number_format($balance->vl_balance ?? 0, 3) }}</div>
        </div>
        <div class="stat-box" style="width: 50%;">
            <div class="stat-label">Current Sick Leave Balance</div>
            <div class="stat-value">{{ number_format($balance->sl_balance ?? 0, 3) }}</div>
        </div>
    </div>

    <table class="report">
        <thead>
            <tr>
                <th rowspan="2" style="width: 12%;">Period</th>
                <th rowspan="2" style="width: 8%;">Type</th>
                <th rowspan="2" style="width: 20%;">Remarks / Reason</th>
                <th colspan="3" class="center">Vacation Leave</th>
                <th colspan="3" class="center">Sick Leave</th>
            </tr>
            <tr>
                <th class="center" style="width: 8%;">Earned</th>
                <th class="center" style="width: 8%;">Used</th>
                <th class="center" style="width: 9%;">Balance</th>
                <th class="center" style="width: 8%;">Earned</th>
                <th class="center" style="width: 8%;">Used</th>
                <th class="center" style="width: 9%;">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ledger as $row)
                @php
                    $typeClass = match (true) {
                        $row->type === 'earned' => 'badge-green',
                        str_contains($row->type, 'deduction') => 'badge-red',
                        $row->type === 'adjustment' => 'badge-blue',
                        default => 'badge-gray',
                    };
                @endphp
                <tr>
                    <td class="left">
                        {{ $row->period_from->format('M d, Y') }}
                        @if ($row->period_from->ne($row->period_to))
                            – {{ $row->period_to->format('M d, Y') }}
                        @endif
                    </td>
                    <td class="center">
                        <span class="badge {{ $typeClass }}">{{ ucfirst(str_replace('_', ' ', $row->type)) }}</span>
                    </td>
                    <td class="left muted">{{ $row->remarks ?: '—' }}</td>
                    <td class="center">{{ $row->vl_earned > 0 ? number_format($row->vl_earned, 3) : '' }}</td>
                    <td class="center">{{ $row->vl_used > 0 ? number_format($row->vl_used, 3) : '' }}</td>
                    <td class="center balance-cell">{{ number_format($row->vl_balance, 3) }}</td>
                    <td class="center">{{ $row->sl_earned > 0 ? number_format($row->sl_earned, 3) : '' }}</td>
                    <td class="center">{{ $row->sl_used > 0 ? number_format($row->sl_used, 3) : '' }}</td>
                    <td class="center balance-cell">{{ number_format($row->sl_balance, 3) }}</td>
                </tr>
            @empty
                <tr class="empty-row"><td colspan="9">No ledger entries recorded for this employee.</td></tr>
            @endforelse
        </tbody>
    </table>

    @include('admin.pdf.partials.footer')

</body>
</html>