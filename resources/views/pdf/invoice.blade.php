<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class="header">
        <h2>iTracker GPS Tracking</h2>
        <p>Invoice #{{ $invoice->invoice_number }}</p>
        <p>Issue Date: {{ $invoice->issued_date->format('d M Y') }}</p>
        <p>Due Date: {{ $invoice->due_date ? $invoice->due_date->format('d M Y') : 'N/A' }}</p>
    </div>

    <p><strong>Customer:</strong> {{ $invoice->vtsAccount->name }}</p>

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
            @foreach($invoice->invoiceItems as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td>{{ $item->period_start->format('d M Y') }} - {{ $item->period_end->format('d M Y') }}</td>
                <td>{{ number_format($item->quantity, 4) }}</td>
                <td>{{ number_format($item->unit_price, 2) }}</td>
                <td>{{ number_format($item->amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: 20px; text-align: right;">
        <strong>Subtotal:</strong> {{ number_format($invoice->subtotal, 2) }} ৳<br>
        <strong>Discount:</strong> {{ number_format($invoice->discount_amount, 2) }} ৳<br>
        <strong>Total:</strong> {{ number_format($invoice->total_amount, 2) }} ৳<br>
        <strong>Paid:</strong> {{ number_format($invoice->paid_amount, 2) }} ৳<br>
        <strong>Due:</strong> {{ number_format($invoice->due_amount, 2) }} ৳
    </div>

    <p style="margin-top: 30px;">Thank you for your business!</p>
</body>
</html>