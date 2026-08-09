<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 30px 35px; }
    body { font-family: Arial, sans-serif; font-size: 10px; color: #1f2937; }
    .header { display: table; width: 100%; margin-bottom: 14px; }
    .header-left { display: table-cell; width: 60%; vertical-align: top; }
    .header-right { display: table-cell; width: 40%; vertical-align: top; text-align: right; }
    .title { font-size: 18px; font-weight: bold; color: #7a1f1f; margin-bottom: 2px; }
    .subtitle { font-size: 11px; color: #6b7280; }
    .meta-label { color: #9ca3af; font-size: 9px; }
    .meta-value { font-weight: bold; font-size: 11px; }

    .info-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px 14px; margin-bottom: 16px; }
    .info-row { display: table; width: 100%; }
    .info-col { display: table-cell; width: 25%; padding-right: 10px; }
    .info-col-label { color: #9ca3af; font-size: 9px; }
    .info-col-value { font-weight: bold; font-size: 11px; color: #1f2937; }

    .balance-row { display: table; width: 100%; margin-bottom: 16px; }
    .balance-box { display: table-cell; width: 50%; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 4px; }
    .balance-box + .balance-box { padding-left: 20px; border-left: none; }
    .balance-label { color: #9ca3af; font-size: 9px; }
    .balance-value { font-size: 20px; font-weight: bold; color: #7a1f1f; }

    table.ledger { width: 100%; border-collapse: collapse; font-size: 9px; }
    table.ledger th { background: #f3f4f6; border: 1px solid #d1d5db; padding: 5px 6px; text-align: center; font-size: 8.5px; }
    table.ledger td { border: 1px solid #e5e7eb; padding: 5px 6px; text-align: center; }
    table.ledger td.left { text-align: left; }
    table.ledger td.remarks { text-align: left; color: #4b5563; }
    table.ledger tr:nth-child(even) { background: #fafafa; }
    .balance-cell { font-weight: bold; color: #1f2937; }

    .type-badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 8px; font-weight: bold; }
    .type-earned { background: #dcfce7; color: #15803d; }
    .type-deduction { background: #fee2e2; color: #b91c1c; }
    .type-adjustment { background: #dbeafe; color: #1e40af; }
    .type-opening { background: #f3f4f6; color: #4b5563; }

    .footer { margin-top: 16px; font-size: 8px; color: #9ca3af; text-align: right; }
</style>
</head>
<body>

<div class="header">
    <div class="header-left">
        <div class="title">My Leave Ledger</div>
        <div class="subtitle">Ilocos Sur Polytechnic State College — Tagudin Campus</div>
    </div>
    <div class="header-right">
        <div class="meta-label">Generated On</div>
        <div class="meta-value">{{ $generatedAt->format('F d, Y g:i A') }}</div>
    </div>
</div>

<div class="info-box">
    <div class="info-row">
        <div class="info-col">
            <div class="info-col-label">Employee</div>
            <div class="info-col-value">{{ $employee->name }}</div>
        </div>
        <div class="info-col">
            <div class="info-col-label">Employee No.</div>
            <div class="info-col-value">{{ $employee->employee_number ?: '—' }}</div>
        </div>
        <div class="info-col">
            <div class="info-col-label">Position</div>
            <div class="info-col-value">{{ $employee->position ?: '—' }}</div>
        </div>
        <div class="info-col">
            <div class="info-col-label">College/Office</div>
            <div class="info-col-value">{{ $employee->department ?: '—' }}</div>
        </div>
    </div>
</div>

<div class="balance-row">
    <div class="balance-box">
        <div class="balance-label">Current Vacation Leave Balance</div>
        <div class="balance-value">{{ number_format($balance->vl_balance ?? 0, 3) }}</div>
    </div>
    <div class="balance-box">
        <div class="balance-label">Current Sick Leave Balance</div>
        <div class="balance-value">{{ number_format($balance->sl_balance ?? 0, 3) }}</div>
    </div>
</div>

<table class="ledger">
    <thead>
        <tr>
            <th rowspan="2" style="width: 12%;">Period</th>
            <th rowspan="2" style="width: 8%;">Type</th>
            <th rowspan="2" style="width: 20%;">Remarks / Reason</th>
            <th colspan="3">Vacation Leave</th>
            <th colspan="3">Sick Leave</th>
        </tr>
        <tr>
            <th style="width: 8%;">Earned</th>
            <th style="width: 8%;">Used</th>
            <th style="width: 9%;">Balance</th>
            <th style="width: 8%;">Earned</th>
            <th style="width: 8%;">Used</th>
            <th style="width: 9%;">Balance</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($ledger as $row)
            <tr>
                <td class="left">
                    {{ $row->period_from->format('M d, Y') }}
                    @if ($row->period_from->ne($row->period_to))
                        – {{ $row->period_to->format('M d, Y') }}
                    @endif
                </td>
                <td>
                    <span class="type-badge type-{{ str_replace('_', '', $row->type) === 'earned' ? 'earned' : (str_replace('_', '', $row->type) === 'leavededuction' ? 'deduction' : ($row->type === 'adjustment' ? 'adjustment' : 'opening')) }}">
                        {{ ucfirst(str_replace('_', ' ', $row->type)) }}
                    </span>
                </td>
                <td class="remarks">{{ $row->remarks ?: '—' }}</td>
                <td>{{ $row->vl_earned > 0 ? number_format($row->vl_earned, 3) : '' }}</td>
                <td>{{ $row->vl_used > 0 ? number_format($row->vl_used, 3) : '' }}</td>
                <td class="balance-cell">{{ number_format($row->vl_balance, 3) }}</td>
                <td>{{ $row->sl_earned > 0 ? number_format($row->sl_earned, 3) : '' }}</td>
                <td>{{ $row->sl_used > 0 ? number_format($row->sl_used, 3) : '' }}</td>
                <td class="balance-cell">{{ number_format($row->sl_balance, 3) }}</td>
            </tr>
        @empty
            <tr><td colspan="9" style="text-align:center; color:#9ca3af; padding: 16px;">No ledger entries recorded.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    NexHRIS — Personal leave ledger for {{ $employee->name }}, generated {{ $generatedAt->format('F d, Y g:i A') }}
</div>

</body>
</html>