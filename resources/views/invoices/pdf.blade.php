<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; background: #fff; }

        .page { padding: 40px 48px; }

        /* Header */
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 36px; }
        .brand-logo { height: 40px; width: auto; margin-bottom: 4px; }
        .brand { font-size: 24px; font-weight: 700; color: #4f46e5; letter-spacing: -0.5px; }
        .brand-sub { font-size: 11px; color: #6b7280; margin-top: 2px; }
        .invoice-meta { text-align: right; }
        .invoice-number { font-size: 18px; font-weight: 700; color: #111827; }
        .badge { display: inline-block; margin-top: 6px; padding: 3px 10px; border-radius: 99px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-draft     { background: #f3f4f6; color: #6b7280; }
        .badge-sent      { background: #dbeafe; color: #1d4ed8; }
        .badge-paid      { background: #d1fae5; color: #065f46; }
        .badge-overdue   { background: #fee2e2; color: #991b1b; }
        .badge-cancelled { background: #f3f4f6; color: #9ca3af; }

        /* Divider */
        .divider { border: none; border-top: 1px solid #e5e7eb; margin: 20px 0; }

        /* Parties */
        .parties { display: flex; justify-content: space-between; margin-bottom: 28px; }
        .party-block { width: 48%; }
        .party-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #9ca3af; margin-bottom: 6px; }
        .party-name { font-size: 14px; font-weight: 600; color: #111827; margin-bottom: 4px; }
        .party-detail { color: #6b7280; line-height: 1.6; }

        /* Dates */
        .dates { display: flex; gap: 24px; margin-bottom: 28px; }
        .date-block { }
        .date-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #9ca3af; margin-bottom: 3px; }
        .date-value { font-size: 13px; font-weight: 600; color: #111827; }

        /* Table */
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        thead tr { background: #f9fafb; }
        th { padding: 10px 12px; text-align: left; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: #6b7280; border-bottom: 2px solid #e5e7eb; }
        th.right, td.right { text-align: right; }
        td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; color: #374151; }
        tbody tr:last-child td { border-bottom: none; }

        /* Totals */
        .totals { display: flex; justify-content: flex-end; margin-top: 4px; }
        .totals-table { width: 260px; }
        .totals-row { display: flex; justify-content: space-between; padding: 5px 0; color: #6b7280; }
        .totals-row.grand { border-top: 2px solid #e5e7eb; margin-top: 6px; padding-top: 10px; font-size: 15px; font-weight: 700; color: #111827; }

        /* Notes */
        .notes { margin-top: 32px; padding: 14px 16px; background: #f9fafb; border-radius: 6px; border-left: 3px solid #4f46e5; }
        .notes-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #9ca3af; margin-bottom: 5px; }
        .notes-text { color: #4b5563; line-height: 1.6; }

        /* Footer */
        .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #9ca3af; }
    </style>
</head>
<body>
<div class="page">

    <!-- Header -->
    <div class="header">
        <div>
            <img class="brand-logo" src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(public_path('logo.jpg'))) }}" alt="Logo" />
            <div class="brand">{{ $tenant->name }}</div>
            <div class="brand-sub">{{ $tenant->type === 'ca_firm' ? 'CA Firm' : 'Business' }}</div>
        </div>
        <div class="invoice-meta">
            <div class="invoice-number">{{ $invoice->invoice_number }}</div>
            <span class="badge badge-{{ $invoice->status }}">{{ $invoice->status }}</span>
        </div>
    </div>

    <hr class="divider">

    <!-- Parties -->
    <div class="parties">
        <div class="party-block">
            <div class="party-label">From</div>
            <div class="party-name">{{ $tenant->name }}</div>
        </div>
        <div class="party-block" style="text-align:right;">
            <div class="party-label">Bill To</div>
            <div class="party-name">{{ $invoice->client->name }}</div>
            <div class="party-detail">
                @if($invoice->client->company){{ $invoice->client->company }}<br>@endif
                @if($invoice->client->email){{ $invoice->client->email }}<br>@endif
                @if($invoice->client->phone){{ $invoice->client->phone }}<br>@endif
                @if($invoice->client->address){{ $invoice->client->address }}@endif
            </div>
        </div>
    </div>

    <!-- Invoice Details -->
    <div class="dates" style="margin-bottom: 28px;">
        <div class="date-block">
            <div class="date-label">Issue Date</div>
            <div class="date-value">{{ $invoice->issue_date->format('M d, Y') }}</div>
        </div>
        <div class="date-block">
            <div class="date-label">Due Date</div>
            <div class="date-value">{{ $invoice->due_date ? $invoice->due_date->format('M d, Y') : 'N/A' }}</div>
        </div>
        <div class="date-block">
            <div class="date-label">Currency</div>
            <div class="date-value">{{ $invoice->currency }}</div>
        </div>
    </div>

    <!-- Line Items -->
    <table>
        <thead>
            <tr>
                <th style="width:40%">Description</th>
                <th class="right" style="width:12%">Qty</th>
                <th class="right" style="width:12%">Unit</th>
                <th class="right" style="width:16%">Unit Price</th>
                <th class="right" style="width:10%">Tax</th>
                <th class="right" style="width:16%">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="right">{{ number_format((float)$item->quantity, 2) }}</td>
                <td class="right">{{ $item->unit }}</td>
                <td class="right">{{ number_format((float)$item->unit_price, 2) }}</td>
                <td class="right">{{ $item->tax_rate }}%</td>
                <td class="right"><strong>{{ number_format((float)$item->total, 2) }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totals -->
    <div class="totals">
        <div class="totals-table">
            <div class="totals-row">
                <span>Subtotal</span>
                <span>{{ $invoice->currency }} {{ number_format((float)$invoice->subtotal, 2) }}</span>
            </div>
            <div class="totals-row">
                <span>Tax</span>
                <span>{{ $invoice->currency }} {{ number_format((float)$invoice->tax_amount, 2) }}</span>
            </div>
            <div class="totals-row grand">
                <span>Total</span>
                <span>{{ $invoice->currency }} {{ number_format((float)$invoice->total, 2) }}</span>
            </div>
        </div>
    </div>

    @if($invoice->notes)
    <!-- Notes -->
    <div class="notes">
        <div class="notes-label">Notes &amp; Payment Terms</div>
        <div class="notes-text">{{ $invoice->notes }}</div>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        {{ $tenant->name }} &bull; {{ now()->format('M d, Y') }}
    </div>

</div>
</body>
</html>
