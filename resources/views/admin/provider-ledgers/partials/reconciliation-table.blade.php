<div class="overflow-x-auto">
    @isset($heading)
        <h4 class="px-6 pt-4 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $heading }}</h4>
    @endisset
    <table class="min-w-full divide-y divide-gray-200 dark:divide-[#3E3E3A]">
        <thead class="bg-gray-50 dark:bg-[#1C1C1A]">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $showSourceRow ? __('Excel Row') : __('Ledger ID') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Type') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Date') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Quantity') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Price') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Amount') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Car Number') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-[#3E3E3A]">
            @foreach ($deliveries as $entry)
                <tr>
                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $showSourceRow ? $entry['source_row'] : $entry['ledger_id'] }}</td>
                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ __('Delivery') }}</td>
                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ date('d/m/Y', strtotime($entry['date'])) }}</td>
                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $entry['quantity'] === null ? __('N/A') : number_format((float) $entry['quantity'], 3) }}</td>
                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $entry['price'] === null ? __('N/A') : number_format((float) $entry['price'], 4) }}</td>
                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ number_format((float) $entry['amount'], 4) }}</td>
                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $entry['car_number'] ?: __('N/A') }}</td>
                </tr>
            @endforeach
            @foreach ($payments as $entry)
                <tr>
                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $showSourceRow ? $entry['source_row'] : $entry['ledger_id'] }}</td>
                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ __('Payment') }}</td>
                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ date('d/m/Y', strtotime($entry['date'])) }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">&mdash;</td>
                    <td class="px-6 py-4 text-sm text-gray-500">&mdash;</td>
                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ number_format((float) $entry['amount'], 4) }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">&mdash;</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
