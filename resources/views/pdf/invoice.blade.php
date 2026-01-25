<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 15mm 10mm 30mm 10mm;
            footer: html_pageFooter;
        }

        body {
            font-family: 'solaimanlipi', sans-serif !important;
            font-size: 12pt;
            line-height: 1.6;
            direction: ltr;
            unicode-bidi: plaintext;
            margin: 0;
            padding: 0;
        }

        * { font-family: 'solaimanlipi' !important; }

        .container {
            width: 100%;
            max-width: 190mm;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        h2 {
            margin: 0;
            font-size: 18pt;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th, td {
            border: 1px solid #000;
            padding: 8px 10px;
            text-align: left;
        }

        th {
            background-color: #f8f8f8;
            font-weight: bold;
        }

        .summary p {
            margin: 5px 0;
            text-align: right;
        }

        /* Footer style */
        #pageFooter {
            font-size: 10pt;
            color: #555;
            text-align: center;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>iTracker GPS Tracking</h2>
            <p><strong>Invoice #{{ $invoice->invoice_number }}</strong></p>
            <p>Issue Date: {{ $invoice->issued_date ? $invoice->issued_date->format('d M Y') : 'N/A' }}</p>
            <p>Due Date: {{ $invoice->due_date ? $invoice->due_date->format('d M Y') : 'N/A' }}</p>
        </div>

        <p><strong>Customer:</strong> {{ $invoice->vtsAccount->name ?? 'N/A' }}</p>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Period</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoice->invoiceItems as $item)
                    <tr>
                        <td>{{ $item->description ?? 'N/A' }}</td>
                        <td>
                            {{ $item->period_start ? $item->period_start->format('d M Y') : 'N/A' }}
                            -
                            {{ $item->period_end ? $item->period_end->format('d M Y') : 'N/A' }}
                        </td>
                        <td>{{ number_format($item->quantity ?? 0, 4) }}</td>
                        <td>{{ number_format($item->unit_price ?? 0, 2) }} ৳</td>
                        <td>{{ number_format($item->amount ?? 0, 2) }} ৳</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;">No items found</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="summary">
            <p><strong>Subtotal:</strong> {{ number_format($invoice->subtotal ?? 0, 2) }} ৳</p>
            <p><strong>Discount:</strong> {{ number_format($invoice->discount_amount ?? 0, 2) }} ৳</p>
            <p><strong>Total:</strong> {{ number_format($invoice->total_amount ?? 0, 2) }} ৳</p>
            <p><strong>Paid:</strong> {{ number_format($invoice->paid_amount ?? 0, 2) }} ৳</p>
            <p><strong>Due:</strong> {{ number_format($invoice->due_amount ?? 0, 2) }} ৳</p>
        </div>
    </div>

    <!-- প্রতি পেজের নিচে দেখাবে -->
    <htmlpagefooter name="pageFooter">
        <div id="pageFooter">
            Thank you for your business!
        </div>
    </htmlpagefooter>
</body>
</html>