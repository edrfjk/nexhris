<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('admin.pdf.partials.styles')
</head>
<body>

    @include('admin.pdf.partials.header', [
        'title' => 'Employee Directory',
        'subtitle' => $employees->count() . ' employee' . ($employees->count() === 1 ? '' : 's') . ' listed',
    ])

    <table class="report">
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
                    <td class="left name">{{ $employee->name }}</td>
                    <td class="left">{{ $employee->email }}</td>
                    <td class="left">{{ $employee->employee_number }}</td>
                    <td class="left">
                        {{ $employee->department ?: '—' }}
                        @if ($employee->program)
                            <br><span class="muted">{{ $employee->program }}</span>
                        @endif
                    </td>
                    <td class="left">{{ $employee->contact_number ?: '—' }}</td>
                    <td class="left">
                        <span class="badge {{ $employee->status === 'active' ? 'badge-green' : 'badge-gray' }}">
                            {{ ucfirst($employee->status) }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr class="empty-row"><td colspan="6">No employees match the applied filters.</td></tr>
            @endforelse
        </tbody>
    </table>

    @include('admin.pdf.partials.footer')

</body>
</html>