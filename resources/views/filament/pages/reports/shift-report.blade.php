<x-filament-panels::page>
    <style>
        .report-table-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            overflow-x: auto;
        }
        .report-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .report-table thead { background: #f9fafb; }
        .report-table th {
            text-align: left;
            padding: 0.75rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }
        .report-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f3f4f6;
            color: #111827;
            white-space: nowrap;
        }
        .report-table tbody tr:last-child td { border-bottom: none; }
        .report-table tbody tr:hover { background: #f9fafb; }
        .report-table th.report-num, .report-table td.report-num { text-align: right; }
        .report-table .report-empty { text-align: center; padding: 2rem 1rem; color: #9ca3af; white-space: normal; }

        .dark .report-table-card { background: #18181b; border-color: #3f3f46; }
        .dark .report-table thead { background: #27272a; }
        .dark .report-table th { color: #a1a1aa; border-bottom-color: #3f3f46; }
        .dark .report-table td { color: #f4f4f5; border-bottom-color: #27272a; }
        .dark .report-table tbody tr:hover { background: #27272a; }
        .dark .report-table .report-empty { color: #71717a; }
    </style>

    <div class="mb-4">
        {{ $this->form }}
    </div>

    <div class="report-table-card">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Kasir</th>
                    <th>Dibuka</th>
                    <th>Ditutup</th>
                    <th class="report-num">Modal Awal</th>
                    <th class="report-num">Cash Sales</th>
                    <th class="report-num">Non-Cash Sales</th>
                    <th class="report-num">Cash In</th>
                    <th class="report-num">Cash Out</th>
                    <th class="report-num">Expected</th>
                    <th class="report-num">Actual</th>
                    <th class="report-num">Selisih</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->getRows() as $row)
                    <tr>
                        <td>{{ $row['user'] }}</td>
                        <td>{{ $row['opened_at'] }}</td>
                        <td>{{ $row['closed_at'] ?? '-' }}</td>
                        <td class="report-num">{{ number_format($row['opening_cash'], 2) }}</td>
                        <td class="report-num">{{ number_format($row['cash_sales'], 2) }}</td>
                        <td class="report-num">{{ number_format($row['non_cash_sales'], 2) }}</td>
                        <td class="report-num">{{ number_format($row['cash_in'], 2) }}</td>
                        <td class="report-num">{{ number_format($row['cash_out'], 2) }}</td>
                        <td class="report-num">{{ $row['expected_cash'] !== null ? number_format($row['expected_cash'], 2) : '-' }}</td>
                        <td class="report-num">{{ $row['actual_cash'] !== null ? number_format($row['actual_cash'], 2) : '-' }}</td>
                        <td class="report-num">{{ $row['difference'] !== null ? number_format($row['difference'], 2) : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="report-empty">No data for this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
