<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Arial, sans-serif; font-size: 10px; color: #1f2937; }
    h1 { font-size: 16px; color: #7a1f1f; margin-bottom: 2px; }
    p.sub { color: #6b7280; font-size: 10px; margin-bottom: 14px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #f3f4f6; border: 1px solid #d1d5db; padding: 6px; text-align: left; font-size: 9px; }
    td { border: 1px solid #e5e7eb; padding: 6px; font-size: 9px; }
</style>
</head>
<body>
    <h1>Employee Leave Balances</h1>
    <p class="sub">Ilocos Sur Polytechnic State College — Tagudin Campus · Generated {{ now()->format('F d, Y g:i A') }}</p>
    <table>
        <thead>
            <tr><th>Employee No.</th><th>Name</th><th>College/Office</th><th>VL Balance</th><th>SL Balance</th></tr>
        </thead>
        <tbody>
            @foreach ($employees as $employee)
                <tr>
                    <td>{{ $employee->employee_number }}</td>
                    <td>{{ $employee->name }}</td>
                    <td>{{ $employee->department }}</td>
                    <td>{{ number_format($employee->leaveBalance->vl_balance ?? 0, 3) }}</td>
                    <td>{{ number_format($employee->leaveBalance->sl_balance ?? 0, 3) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>