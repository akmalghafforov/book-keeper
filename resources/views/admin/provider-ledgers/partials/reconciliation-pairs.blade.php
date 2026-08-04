<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-[#3E3E3A]">
        <thead class="bg-gray-50 dark:bg-[#1C1C1A]">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Excel Row') }}</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Ledger ID') }}</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Date') }}</th>
                @if ($kind === 'delivery')
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Car Number') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Weight') }}</th>
                @endif
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $kind === 'payment' ? __('Excel Payment') : __('Excel Buy Price') }}</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $kind === 'payment' ? __('Ledger Payment') : __('Ledger Buy Price') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-[#3E3E3A]">
            @foreach ($pairs as $pair)
                <tr>
                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $pair['excel']['source_row'] }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $pair['ledger']['ledger_id'] }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                        {{ date('d/m/Y', strtotime($pair['excel']['date'])) }}
                        @if ($pair['excel']['date'] !== $pair['ledger']['date'])
                            &rarr; {{ date('d/m/Y', strtotime($pair['ledger']['date'])) }}
                        @endif
                    </td>
                    @if ($kind === 'delivery')
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $pair['excel']['car_number'] ?: __('N/A') }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ number_format((float) $pair['excel']['quantity'], 3) }}</td>
                    @endif
                    <td class="px-4 py-3 text-sm font-medium text-red-600 dark:text-red-400">{{ number_format((float) ($kind === 'payment' ? $pair['excel']['amount'] : $pair['excel']['price']), 4) }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-red-600 dark:text-red-400">{{ number_format((float) ($kind === 'payment' ? $pair['ledger']['amount'] : $pair['ledger']['price']), 4) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
