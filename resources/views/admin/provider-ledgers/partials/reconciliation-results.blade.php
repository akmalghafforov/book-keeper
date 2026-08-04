@php
    $checks = $result->checks();
    $checkLabels = [
        'payment_count' => __('Payment record count'),
        'payment_amounts' => __('Individual payment amounts'),
        'payment_total' => __('Payment total'),
        'delivery_count' => __('Delivery record count'),
        'car_numbers' => __('Car numbers'),
        'weights' => __('Weights'),
        'duplicates' => __('Duplicate deliveries'),
        'buy_prices' => __('Buy prices'),
        'composite_records' => __('Composite delivery records'),
    ];
@endphp

<div class="bg-white dark:bg-[#161615] shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A] p-6">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Comparison Checks') }}</h3>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        {{ $provider->name }} &middot; {{ $dateFrom->format('d/m/Y') }} &ndash; {{ $dateTo->format('d/m/Y') }}
    </p>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        {{ __('Delivery dates within ±1 day are treated as matching.') }}
    </p>

    <div class="mt-5 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-[#3E3E3A]">
            <thead class="bg-gray-50 dark:bg-[#1C1C1A]">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Check') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Excel') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Ledger') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Result') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                @foreach ($checks as $key => $passed)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $checkLabels[$key] }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                            @if ($key === 'payment_count') {{ $result->excelPaymentCount }}
                            @elseif ($key === 'payment_total') {{ $result->excelPaymentTotal }}
                            @elseif ($key === 'delivery_count') {{ $result->excelDeliveryCount }}
                            @else &mdash;
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                            @if ($key === 'payment_count') {{ $result->ledgerPaymentCount }}
                            @elseif ($key === 'payment_total') {{ $result->ledgerPaymentTotal }}
                            @elseif ($key === 'delivery_count') {{ $result->ledgerDeliveryCount }}
                            @else &mdash;
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm font-semibold {{ $passed ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ $passed ? __('Pass') : __('Fail') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($result->missingCount() > 0)
        <span class="sr-only">{{ __('Missing in Database') }}</span>
    @endif
    @if ($result->extraCount() > 0)
        <span class="sr-only">{{ __('Extra in Database') }}</span>
    @endif
</div>

@if ($result->paymentAmountMismatches !== [] || $result->missingPayments !== [] || $result->extraPayments !== [])
    <div class="bg-white dark:bg-[#161615] shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A] overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-[#3E3E3A]">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Payment Mismatches') }}</h3>
        </div>
        @if ($result->paymentAmountMismatches !== [])
            @include('admin.provider-ledgers.partials.reconciliation-pairs', [
                'pairs' => $result->paymentAmountMismatches,
                'kind' => 'payment',
            ])
        @endif
        @if ($result->missingPayments !== [] || $result->extraPayments !== [])
            @include('admin.provider-ledgers.partials.reconciliation-table', [
                'deliveries' => [],
                'payments' => $result->missingPayments,
                'showSourceRow' => true,
                'heading' => __('Missing Excel Payments'),
            ])
            @include('admin.provider-ledgers.partials.reconciliation-table', [
                'deliveries' => [],
                'payments' => $result->extraPayments,
                'showSourceRow' => false,
                'heading' => __('Extra Ledger Payments'),
            ])
        @endif
    </div>
@endif

@if ($result->buyPriceMismatches !== [])
    <div class="bg-white dark:bg-[#161615] shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A] overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-[#3E3E3A]">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Buy Price Mismatches') }}</h3>
        </div>
        @include('admin.provider-ledgers.partials.reconciliation-pairs', [
            'pairs' => $result->buyPriceMismatches,
            'kind' => 'delivery',
        ])
    </div>
@endif

@if ($result->missingDeliveries !== [])
    <div class="bg-white dark:bg-[#161615] shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A] overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-[#3E3E3A]">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Missing Excel Deliveries and Closest Ledger Candidates') }}</h3>
        </div>
        <div class="divide-y divide-gray-200 dark:divide-[#3E3E3A]">
            @foreach ($result->missingDeliveries as $delivery)
                <div class="p-6">
                    <h4 class="font-semibold text-gray-900 dark:text-white">{{ __('Excel Row') }} {{ $delivery['source_row'] }}</h4>
                    <div class="mt-3 grid grid-cols-2 md:grid-cols-5 gap-3 text-sm">
                        <div><span class="text-gray-500">{{ __('Date') }}:</span> {{ date('d/m/Y', strtotime($delivery['date'])) }}</div>
                        <div><span class="text-gray-500">{{ __('Car Number') }}:</span> {{ $delivery['car_number'] ?: __('N/A') }}</div>
                        <div><span class="text-gray-500">{{ __('Weight') }}:</span> {{ number_format((float) $delivery['quantity'], 3) }}</div>
                        <div><span class="text-gray-500">{{ __('Buy Price') }}:</span> {{ number_format((float) $delivery['price'], 4) }}</div>
                        <div><span class="text-gray-500">{{ __('Amount') }}:</span> {{ number_format((float) $delivery['amount'], 4) }} <span class="text-xs">({{ __('Informational') }})</span></div>
                    </div>

                    @if ($delivery['candidates'] === [])
                        <p class="mt-4 text-sm text-gray-500">{{ __('No ledger delivery candidates are available in the selected range.') }}</p>
                    @else
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                                <thead class="bg-gray-50 dark:bg-[#1C1C1A]">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 uppercase">{{ __('Ledger ID') }}</th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 uppercase">{{ __('Date') }}</th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 uppercase">{{ __('Car Number') }}</th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 uppercase">{{ __('Weight') }}</th>
                                        <th class="px-3 py-2 text-left text-xs text-gray-500 uppercase">{{ __('Buy Price') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                                    @foreach ($delivery['candidates'] as $candidate)
                                        <tr>
                                            <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">{{ $candidate['ledger_id'] }}</td>
                                            @foreach ([
                                                ['date', date('d/m/Y', strtotime($candidate['date']))],
                                                ['car_number', $candidate['car_number'] ?: __('N/A')],
                                                ['quantity', number_format((float) $candidate['quantity'], 3)],
                                                ['price', number_format((float) $candidate['price'], 4)],
                                            ] as [$field, $value])
                                                <td class="px-3 py-2 text-sm font-medium {{ $candidate['field_matches'][$field] ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300' }}">
                                                    <span class="block text-xs font-normal opacity-75">{{ __('Excel') }}:
                                                        @if ($field === 'date') {{ date('d/m/Y', strtotime($delivery['date'])) }}
                                                        @elseif ($field === 'car_number') {{ $delivery['car_number'] ?: __('N/A') }}
                                                        @elseif ($field === 'quantity') {{ number_format((float) $delivery['quantity'], 3) }}
                                                        @else {{ number_format((float) $delivery['price'], 4) }}
                                                        @endif
                                                    </span>
                                                    <span class="block">{{ __('Ledger') }}: {{ $value }}</span>
                                                    <span class="sr-only">{{ $candidate['field_matches'][$field] ? __('Match') : __('Different') }}</span>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif

@foreach ([
    [__('Excel Duplicate Deliveries'), $result->excelDuplicateDeliveryGroups, __('Excel Rows')],
    [__('Ledger Duplicate Deliveries'), $result->ledgerDuplicateDeliveryGroups, __('Ledger IDs')],
] as [$title, $groups, $referenceLabel])
    @if ($groups !== [])
        <div class="bg-white dark:bg-[#161615] shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A] overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-[#3E3E3A]">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                @foreach ($groups as $group)
                    <div class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                        <span class="font-semibold">{{ date('d/m/Y', strtotime($group['date'])) }} &middot; {{ $group['car_number'] }}</span>
                        <span class="ml-3 text-gray-500">{{ $referenceLabel }}: {{ implode(', ', $group['references']) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endforeach

@if ($result->excelOnlyCarNumbers !== [] || $result->ledgerOnlyCarNumbers !== [] || $result->excelOnlyWeights !== [] || $result->ledgerOnlyWeights !== [])
    <div class="bg-white dark:bg-[#161615] shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A] p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Car and Weight Differences') }}</h3>
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div><span class="font-medium text-gray-800 dark:text-gray-200">{{ __('Only in Excel car numbers') }}:</span> <span class="text-gray-600 dark:text-gray-400">{{ implode(', ', $result->excelOnlyCarNumbers) ?: __('None') }}</span></div>
            <div><span class="font-medium text-gray-800 dark:text-gray-200">{{ __('Only in ledger car numbers') }}:</span> <span class="text-gray-600 dark:text-gray-400">{{ implode(', ', $result->ledgerOnlyCarNumbers) ?: __('None') }}</span></div>
            <div><span class="font-medium text-gray-800 dark:text-gray-200">{{ __('Only in Excel weights') }}:</span> <span class="text-gray-600 dark:text-gray-400">{{ implode(', ', $result->excelOnlyWeights) ?: __('None') }}</span></div>
            <div><span class="font-medium text-gray-800 dark:text-gray-200">{{ __('Only in ledger weights') }}:</span> <span class="text-gray-600 dark:text-gray-400">{{ implode(', ', $result->ledgerOnlyWeights) ?: __('None') }}</span></div>
        </div>
    </div>
@endif

@if ($result->extraDeliveries !== [])
    <div class="bg-white dark:bg-[#161615] shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A] overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-[#3E3E3A]">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Extra Ledger Deliveries') }}</h3>
        </div>
        @include('admin.provider-ledgers.partials.reconciliation-table', [
            'deliveries' => $result->extraDeliveries,
            'payments' => [],
            'showSourceRow' => false,
        ])
    </div>
@endif

@if ($result->invalidEntries !== [])
    <div class="bg-white dark:bg-[#161615] shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A] overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-[#3E3E3A]">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Invalid Excel Entries') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                <thead class="bg-gray-50 dark:bg-[#1C1C1A]">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Excel Row') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Type') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Date') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Errors') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                    @foreach ($result->invalidEntries as $entry)
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $entry['row'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ __($entry['entry_type']) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $entry['date'] ? date('d/m/Y', strtotime($entry['date'])) : ($entry['date_value'] ?: __('N/A')) }}</td>
                            <td class="px-6 py-4 text-sm text-red-600 dark:text-red-400">{{ implode(' ', array_map(fn ($error) => __($error), $entry['errors'])) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@if ($result->allChecksPass())
    <div class="bg-emerald-100 border border-emerald-400 text-emerald-800 px-4 py-3 rounded dark:bg-emerald-900 dark:text-emerald-100 dark:border-emerald-800">
        {{ __('The Excel file and database ledger match for the selected period.') }}
    </div>
@endif
