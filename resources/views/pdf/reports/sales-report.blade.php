<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h1 { font-size: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 8px; text-align: left; }
        th { background-color: #f3f4f6; }
    </style>
</head>
<body>
    <h1>Sales Report ({{ ucfirst($periodType) }})</h1>
    <table>
        <thead>
            <tr>
                <th>Period</th>
                <th>Payment Method</th>
                <th>Orders</th>
                <th>Subtotal</th>
                <th>Tax</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['period'] }}</td>
                    <td>{{ $row['payment_method'] }}</td>
                    <td>{{ $row['order_count'] }}</td>
                    <td>{{ number_format($row['subtotal'], 2) }}</td>
                    <td>{{ number_format($row['tax'], 2) }}</td>
                    <td>{{ number_format($row['total'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No data for this period.</td></tr>
            @endforelse
            @if ($rows->isNotEmpty())
                <tr>
                    <td colspan="5" style="text-align: right; font-weight: bold;">Total</td>
                    <td style="font-weight: bold;">Rp {{ number_format($rows->sum('total'), 0, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
