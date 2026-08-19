<x-filament-panels::page>
    <div class="mb-4">
        {{ $this->form }}
    </div>

    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-x-auto">
        <table class="w-full text-start text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-white/10">
                    <th class="px-4 py-2 text-xs font-medium text-gray-500">Period</th>
                    <th class="px-4 py-2 text-xs font-medium text-gray-500">Product</th>
                    <th class="px-4 py-2 text-xs font-medium text-gray-500">Qty Sold</th>
                    <th class="px-4 py-2 text-xs font-medium text-gray-500">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->getRows() as $row)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="px-4 py-2">{{ $row['period'] }}</td>
                        <td class="px-4 py-2">{{ $row['product_name'] }}</td>
                        <td class="px-4 py-2">{{ number_format($row['quantity'], 2) }}</td>
                        <td class="px-4 py-2">{{ number_format($row['revenue'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">No data for this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
