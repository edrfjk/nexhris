<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('admin.pdf.partials.styles')
</head>
<body>

    @include('admin.pdf.partials.header', [
        'title' => 'Employee Leave Balances',
        'subtitle' => $employees->count() . ' employee' . ($employees->count() === 1 ? '' : 's') . ' listed',
    ])

    <table class="report">
        <thead>
            <tr>
                <th style="width: 12%;">Employee No.</th>
                <th style="width: 26%;">Name</th>
                <th style="width: 22%;">College / Office</th>
                <th class="center" style="width: 13.5%;">VL Balance</th>
                <th class="center" style="width: 13.5%;">SL Balance</th>
                <th class="center" style="width: 13%;">Service Credits</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employees as $employee)
                @php
                    $vl = $employee->leaveBalance->vl_balance ?? 0;
                    $sl = $employee->leaveBalance->sl_balance ?? 0;
                    $sc = $employee->leaveBalance->service_balance ?? 0;
                @endphp
                <tr>
                    <td class="left">{{ $employee->employee_number }}</td>
                    <td class="left name">{{ $employee->name }}</td>
                    <td class="left">{{ $employee->department ?: '—' }}</td>
                    <td class="center balance-cell" style="{{ $vl < 5 ? 'color: #b91c1c;' : '' }}">
                        {{ number_format($vl, 2) }}
                    </td>
                    <td class="center balance-cell" style="{{ $sl < 5 ? 'color: #b91c1c;' : '' }}">
                        {{ number_format($sl, 2) }}
                    </td>
                    <td class="center balance-cell">{{ number_format($sc, 2) }}</td>
                </tr>
            @empty
                <tr class="empty-row"><td colspan="6">No employees found.</td></tr>
            @endforelse
        </tbody>
    </table>

    @include('admin.pdf.partials.footer')

</body>
</html>