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
    </style>

    <div class="mb-4">
        {{ $this->form }}
    </div>

    <div class="report-table-card">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Product</th>
                    <th class="report-num">Qty Sold</th>
                    <th class="report-num">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->getRows() as $row)
                    <tr>
                        <td>{{ $row['period'] }}</td>
                        <td>{{ $row['product_name'] }}</td>
                        <td class="report-num">{{ number_format($row['quantity'], 2) }}</td>
                        <td class="report-num">{{ number_format($row['revenue'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="report-empty">No data for this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
