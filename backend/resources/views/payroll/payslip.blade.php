{{--
    Payslip, rendered to PDF by dompdf.

    dompdf supports only a narrow slice of CSS: no flexbox, no grid, no custom
    properties. Everything here is tables and inline-ish styles on purpose, and
    changing it to a modern layout will silently produce a blank page.

    Every figure is read from the slip's frozen columns and lines rather than
    recalculated, so a payslip reprinted a year later is byte-identical to the
    one issued at the time.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip {{ $slip->slip_number }}</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 17px; margin: 0 0 2px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 5px 0; vertical-align: top; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        .rule { border-bottom: 1px solid #e5e7eb; }
        .head { background: #f3f4f6; font-weight: bold; text-transform: uppercase; font-size: 9px; letter-spacing: .04em; }
        .head td { padding: 5px 8px; }
        .row td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; }
        .total td { padding: 7px 8px; font-weight: bold; border-top: 1px solid #d1d5db; }
        .net { background: #111827; color: #fff; }
        .net td { padding: 9px 8px; font-size: 13px; font-weight: bold; }
        .note { color: #6b7280; font-size: 9px; line-height: 1.5; }
    </style>
</head>
<body>

<table>
    <tr>
        <td>
            <h1>{{ $employer }}</h1>
            <div class="muted">Payslip</div>
        </td>
        <td class="right">
            <div><strong>{{ $slip->slip_number }}</strong></div>
            <div class="muted">{{ $period }}</div>
        </td>
    </tr>
</table>

<div class="rule" style="margin: 10px 0 14px;"></div>

<table>
    <tr>
        <td width="50%">
            <div class="muted">Employee</div>
            <div><strong>{{ $slip->employee?->name ?? '—' }}</strong></div>
            <div class="muted">{{ $slip->employee?->email }}</div>
        </td>
        <td width="50%">
            <div class="muted">Pay date</div>
            <div>{{ $payDate }}</div>
            <div class="muted">Paid in {{ $slip->currency_code }}</div>
            @if ($profile?->maskedAccountNumber() || $profile?->maskedIban())
                {{-- Masked: a payslip is forwarded and filed far more casually than it is guarded. --}}
                <div class="muted" style="margin-top: 4px;">
                    {{ $profile->bank_name ? $profile->bank_name.' ' : '' }}{{ $profile->maskedAccountNumber() ?? $profile->maskedIban() }}
                </div>
            @endif
        </td>
    </tr>
</table>

<div style="height: 16px;"></div>

<table>
    <tr class="head"><td>Earnings</td><td class="right">Amount</td></tr>
    <tr class="row">
        <td>Basic salary</td>
        <td class="right">{{ $money($slip->basic_salary) }}</td>
    </tr>
    @foreach ($earnings as $line)
        <tr class="row">
            <td>{{ $line->name }}@if ($line->is_statutory) <span class="muted">(statutory)</span>@endif</td>
            <td class="right">{{ $money($line->amount) }}</td>
        </tr>
    @endforeach
    <tr class="total">
        <td>Gross pay</td>
        <td class="right">{{ $money($slip->gross_salary) }}</td>
    </tr>
</table>

<div style="height: 14px;"></div>

<table>
    <tr class="head"><td>Deductions</td><td class="right">Amount</td></tr>
    @forelse ($deductions as $line)
        <tr class="row">
            <td>{{ $line->name }}@if ($line->is_statutory) <span class="muted">(statutory)</span>@endif</td>
            <td class="right">{{ $money($line->amount) }}</td>
        </tr>
    @empty
        <tr class="row"><td colspan="2" class="muted">None</td></tr>
    @endforelse
    <tr class="total">
        <td>Total deductions</td>
        <td class="right">{{ $money($slip->total_deductions) }}</td>
    </tr>
</table>

<div style="height: 14px;"></div>

<table>
    <tr class="net">
        <td>Net pay</td>
        <td class="right">{{ $money($slip->net_salary) }}</td>
    </tr>
</table>

@if ($contributions->isNotEmpty())
    <div style="height: 14px;"></div>
    <table>
        {{-- Shown for transparency but not taken from net pay. --}}
        <tr class="head"><td>Employer contributions</td><td class="right">Amount</td></tr>
        @foreach ($contributions as $line)
            <tr class="row">
                <td>{{ $line->name }}</td>
                <td class="right">{{ $money($line->amount) }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td>Paid by {{ $employer }}, on top of net pay</td>
            <td class="right">{{ $money($slip->employer_contributions) }}</td>
        </tr>
    </table>
@endif

<div style="height: 22px;"></div>
<div class="rule"></div>
<div style="height: 8px;"></div>

<p class="note">
    Computer-generated payslip; no signature is required. Figures are those recorded when this payroll was approved
    @if ($approvedAt) on {{ $approvedAt }}@endif and do not change afterwards.
    @if ((float) $slip->paid_amount > 0 && $slip->outstanding() > 0)
        {{ $money($slip->paid_amount) }} has been paid against it, leaving {{ $money($slip->outstanding()) }} outstanding.
    @endif
</p>

</body>
</html>
