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
                <th>Orders</th>
                <th>Total Revenue</th>
                <th>Cash</th>
                <th>QRIS</th>
                <th>Transfer</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['period'] }}</td>
                    <td>{{ $row['order_count'] }}</td>
                    <td>{{ number_format($row['total_revenue'], 2) }}</td>
                    <td>{{ number_format($row['cash_revenue'], 2) }}</td>
                    <td>{{ number_format($row['qris_revenue'], 2) }}</td>
                    <td>{{ number_format($row['transfer_revenue'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No data for this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
