{{--
    Printable approval sheet — A4 portrait.

    Produced only after all three reviewers have approved online. The employee
    prints this, attaches it to their filled-in leave form, and collects the
    wet signatures. Because the online chain already vetted the form, nobody
    prints a document that was going to be rejected.
--}}
@php
    $stages = [
        ['key' => 'dean', 'label' => 'Dean', 'user' => $application->dean,
         'at' => $application->dean_reviewed_at, 'remarks' => $application->dean_remarks],
        ['key' => 'hr', 'label' => 'HR Administrator', 'user' => $application->hrReviewer,
         'at' => $application->hr_reviewed_at, 'remarks' => $application->hr_remarks],
        ['key' => 'campus_director', 'label' => 'Campus Director', 'user' => $application->director,
         'at' => $application->director_reviewed_at, 'remarks' => $application->director_remarks],
    ];
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Leave Application Approval Sheet</title>
    <style>
        @page { size: A4 portrait; margin: 16mm 15mm 18mm 15mm; }

        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #111; margin: 0; }

        .masthead { text-align: center; border-bottom: 2px solid #7f1d1d; padding-bottom: 8px; margin-bottom: 14px; }
        .masthead .org { font-size: 11px; font-weight: bold; letter-spacing: 0.04em; }
        .masthead .campus { font-size: 8.5px; color: #444; margin-top: 1px; }
        .masthead .doc { font-size: 14px; font-weight: bold; color: #7f1d1d; margin-top: 8px; letter-spacing: 0.05em; }

        .approved-stamp {
            border: 2px solid #15803d;
            color: #15803d;
            font-weight: bold;
            font-size: 10px;
            letter-spacing: 0.12em;
            text-align: center;
            padding: 5px;
            margin: 0 0 14px;
        }

        table.detail { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.detail th, table.detail td { border: 0.8px solid #999; padding: 5px 7px; vertical-align: top; }
        table.detail th {
            background: #f3f4f6; text-align: left; width: 22%;
            font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.03em; color: #374151;
        }

        h2.section {
            font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em;
            color: #7f1d1d; border-bottom: 1px solid #d1d5db;
            padding-bottom: 3px; margin: 16px 0 8px;
        }

        table.chain { width: 100%; border-collapse: collapse; }
        table.chain th, table.chain td { border: 0.8px solid #999; padding: 5px 7px; }
        table.chain th { background: #f3f4f6; font-size: 8.5px; text-transform: uppercase; color: #374151; }
        table.chain td.ok { color: #15803d; font-weight: bold; text-align: center; }

        /* Wet-signature block — one ruled line per approver. */
        table.sig { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.sig td { width: 33.33%; padding: 22px 8px 0; vertical-align: bottom; }
        .sigline { border-bottom: 1px solid #000; height: 1px; }
        .signame { text-align: center; font-weight: bold; font-size: 9px; text-transform: uppercase; padding-top: 3px; }
        .sigrole { text-align: center; font-size: 7.5px; color: #444; }

        .note {
            margin-top: 18px; padding: 7px 9px; background: #fffbeb;
            border-left: 3px solid #d97706; font-size: 8px; color: #713f12;
        }

        .foot {
            position: fixed; bottom: -12mm; left: 0; right: 0;
            text-align: center; font-size: 7px; color: #666;
        }
    </style>
</head>
<body>

<div class="foot">
    NexHRIS · Reference No. LV-{{ str_pad($application->id, 5, '0', STR_PAD_LEFT) }} ·
    Generated {{ $generatedAt->format('F j, Y g:i A') }}
</div>

<div class="masthead">
    <div class="org">ILOCOS SUR POLYTECHNIC STATE COLLEGE</div>
    <div class="campus">Tagudin Campus · Human Resource Management Office</div>
    <div class="doc">LEAVE APPLICATION APPROVAL SHEET</div>
</div>

<div class="approved-stamp">
    APPROVED THROUGH THE COMPLETE REVIEW CHAIN — DEAN · HR ADMINISTRATOR · CAMPUS DIRECTOR
</div>

<table class="detail">
    <tr>
        <th>Employee</th>
        <td><strong>{{ $employee->name }}</strong></td>
        <th>Employee No.</th>
        <td>{{ $employee->employee_number ?: '—' }}</td>
    </tr>
    <tr>
        <th>Position</th>
        <td>{{ $employee->position ?: '—' }}</td>
        <th>College / Office</th>
        <td>{{ $employee->department ?: '—' }}</td>
    </tr>
    <tr>
        <th>Program</th>
        <td colspan="3">{{ $employee->program ?: '—' }}</td>
    </tr>
    <tr>
        <th>Type of Leave</th>
        <td>{{ $application->leave_type === 'VL' ? 'Vacation Leave' : 'Sick Leave' }}</td>
        <th>Working Days</th>
        <td>{{ number_format((float) $application->days, 2) }}</td>
    </tr>
    <tr>
        <th>Inclusive Dates</th>
        <td colspan="3">
            {{ $application->date_from?->format('F j, Y') }}
            @if ($application->date_to && ! $application->date_to->eq($application->date_from))
                &nbsp;to&nbsp; {{ $application->date_to->format('F j, Y') }}
            @endif
        </td>
    </tr>
    <tr>
        <th>Reason</th>
        <td colspan="3">{{ $application->reason ?: '—' }}</td>
    </tr>
    <tr>
        <th>Form Submitted</th>
        <td colspan="3">
            {{ $application->file_original_name ?: '—' }}
            @if ($application->uploaded_at)
                &nbsp;·&nbsp; uploaded {{ $application->uploaded_at->format('F j, Y g:i A') }}
            @endif
        </td>
    </tr>
</table>

<h2 class="section">Online Review Chain</h2>

<table class="chain">
    <thead>
        <tr>
            <th style="width:6%;">#</th>
            <th style="width:24%;">Reviewer</th>
            <th style="width:24%;">Name</th>
            <th style="width:12%;">Decision</th>
            <th style="width:18%;">Date Reviewed</th>
            <th>Remarks</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($stages as $i => $stage)
            <tr>
                <td style="text-align:center;">{{ $i + 1 }}</td>
                <td>{{ $stage['label'] }}</td>
                <td>{{ $stage['user']->name ?? '—' }}</td>
                <td class="ok">APPROVED</td>
                <td>{{ $stage['at']?->format('M j, Y g:i A') ?: '—' }}</td>
                <td>{{ $stage['remarks'] ?: '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<h2 class="section">Signatures</h2>

<table class="sig">
    <tr>
        @foreach ($stages as $stage)
            <td>
                <div class="sigline"></div>
                <div class="signame">{{ $stage['user']->name ?? '' }}</div>
                <div class="sigrole">{{ strtoupper($stage['label']) }}</div>
            </td>
        @endforeach
    </tr>
</table>

<div class="note">
    <strong>Note:</strong> This sheet certifies that the leave form was reviewed and approved
    online by the Dean, the HR Administrator and the Campus Director before printing.
    Attach it to the accomplished leave form and submit the signed copy to the HR Office,
    which will then post the leave to the employee's ledger card.
</div>

</body>
</html>
