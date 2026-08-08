{{-- resources/views/admin/pdf/partials/styles.blade.php
     Shared stylesheet for every PDF export. Include this once in each report's
     <head> via @include('admin.pdf.partials.styles') so all reports share the
     same font, colors, spacing, and table/badge conventions. --}}
<style>
    @page { margin: 28px 32px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2937; }

    /* ---- Header ---- */
    .header { display: table; width: 100%; margin-bottom: 14px; border-bottom: 2px solid #7f1d1d; padding-bottom: 10px; }
    .header-left { display: table-cell; vertical-align: middle; }
    .header-right { display: table-cell; vertical-align: middle; text-align: right; }
    .title { font-size: 16px; font-weight: bold; color: #7f1d1d; margin: 0 0 2px; }
    .subtitle { font-size: 10px; color: #6b7280; margin: 0; }
    .meta-label { color: #9ca3af; font-size: 8.5px; }
    .meta-value { font-weight: bold; font-size: 10px; color: #1f2937; }

    /* ---- Filter chips (search/report scope) ---- */
    .filters { margin-bottom: 12px; font-size: 9px; color: #6b7280; }
    .filters span { display: inline-block; background: #fef2f2; color: #7f1d1d; border-radius: 10px; padding: 2px 8px; margin-right: 6px; }

    /* ---- Info / summary box (e.g. employee details above a ledger) ---- */
    .info-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px 14px; margin-bottom: 14px; }
    .info-row { display: table; width: 100%; }
    .info-col { display: table-cell; padding-right: 10px; }
    .info-col-label { color: #9ca3af; font-size: 8.5px; }
    .info-col-value { font-weight: bold; font-size: 10px; color: #1f2937; }

    /* ---- Summary/stat boxes (e.g. current leave balances) ---- */
    .stat-row { display: table; width: 100%; margin-bottom: 16px; }
    .stat-box { display: table-cell; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 4px; }
    .stat-box + .stat-box { border-left: none; }
    .stat-label { color: #9ca3af; font-size: 8.5px; }
    .stat-value { font-size: 18px; font-weight: bold; color: #7f1d1d; }

    /* ---- Standard report table ---- */
    table.report { width: 100%; border-collapse: collapse; margin-top: 4px; font-size: 9px; }
    table.report thead th {
        background: #f9fafb; text-align: left; font-size: 9px; text-transform: uppercase;
        letter-spacing: 0.03em; color: #6b7280; padding: 6px 8px; border: 1px solid #e5e7eb;
    }
    table.report tbody td { padding: 6px 8px; border: 1px solid #e5e7eb; vertical-align: top; }
    table.report tbody tr:nth-child(even) { background: #fafafa; }
    table.report td.center, table.report th.center { text-align: center; }
    table.report td.left { text-align: left; }

    .name { font-weight: bold; color: #111827; }
    .muted { color: #9ca3af; font-size: 9px; }
    .balance-cell { font-weight: bold; color: #1f2937; }

    /* ---- Status / type badges — reuse these classes everywhere ---- */
    .badge { display: inline-block; padding: 2px 8px; border-radius: 8px; font-size: 8.5px; font-weight: bold; }
    .badge-green  { background: #ecfdf5; color: #047857; }
    .badge-gray   { background: #f3f4f6; color: #6b7280; }
    .badge-red    { background: #fee2e2; color: #b91c1c; }
    .badge-blue   { background: #dbeafe; color: #1e40af; }
    .badge-yellow { background: #fef9c3; color: #854d0e; }

    /* ---- Footer ---- */
    .footer { position: fixed; bottom: -14px; left: 0; right: 0; text-align: center; font-size: 8px; color: #9ca3af; }

    /* ---- Empty state row ---- */
    .empty-row td { text-align: center; padding: 16px; color: #9ca3af; }
</style>