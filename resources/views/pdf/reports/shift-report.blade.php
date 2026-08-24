<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        h1 { font-size: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background-color: #f3f4f6; }
    </style>
</head>
<body>
    <h1>Shift Report ({{ ucfirst($periodType) }})</h1>
    <table>
        <thead>
            <tr>
                <th>Kasir</th>
                <th>Dibuka</th>
                <th>Ditutup</th>
                <th>Modal Awal</th>
                <th>Cash Sales</th>
                <th>Non-Cash Sales</th>
                <th>Cash In</th>
                <th>Cash Out</th>
                <th>Expected</th>
                <th>Actual</th>
                <th>Selisih</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['user'] }}</td>
                    <td>{{ $row['opened_at'] }}</td>
                    <td>{{ $row['closed_at'] ?? '-' }}</td>
                    <td>{{ number_format($row['opening_cash'], 2) }}</td>
                    <td>{{ number_format($row['cash_sales'], 2) }}</td>
                    <td>{{ number_format($row['non_cash_sales'], 2) }}</td>
                    <td>{{ number_format($row['cash_in'], 2) }}</td>
                    <td>{{ number_format($row['cash_out'], 2) }}</td>
                    <td>{{ $row['expected_cash'] !== null ? number_format($row['expected_cash'], 2) : '-' }}</td>
                    <td>{{ $row['actual_cash'] !== null ? number_format($row['actual_cash'], 2) : '-' }}</td>
                    <td>{{ $row['difference'] !== null ? number_format($row['difference'], 2) : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="11">No data for this period.</td></tr>
            @endforelse
            @if ($rows->isNotEmpty())
                <tr>
                    <td colspan="10" style="text-align: right; font-weight: bold;">Total</td>
                    <td style="font-weight: bold;">Rp {{ number_format($rows->sum('cash_sales') + $rows->sum('non_cash_sales'), 0, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
