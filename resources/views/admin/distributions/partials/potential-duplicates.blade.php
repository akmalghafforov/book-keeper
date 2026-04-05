@if($potentialDuplicateGroups->isNotEmpty())
    <div class="bg-amber-50 dark:bg-[#261c08] border border-amber-200 dark:border-amber-900 rounded-xl p-6 space-y-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-amber-900 dark:text-amber-100">{{ __('Potential Duplicate Distributions') }}</h3>
                <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">
                    {{ __('These groups share the same client, product, quantity, price, and date. Review them before editing or deleting records.') }}
                </p>
            </div>
            <div class="inline-flex items-center rounded-full bg-white/70 dark:bg-black/20 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-100">
                {{ $potentialDuplicateGroups->sum('count') }} {{ __('records flagged') }}
            </div>
        </div>

        <div class="space-y-4">
            @foreach($potentialDuplicateGroups as $group)
                <div class="rounded-xl border border-amber-200 dark:border-amber-900 bg-white dark:bg-[#161615] overflow-hidden">
                    <div class="px-5 py-4 border-b border-amber-100 dark:border-amber-950 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $group['summary'] }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $group['confidence'] === 'high' ? 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200' }}">
                                    {{ $group['confidence_label'] }}
                                </span>
                                @foreach($group['reasons'] as $reason)
                                    <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-[#2A2A28] px-2.5 py-1 text-xs font-medium text-gray-700 dark:text-gray-200">
                                        {{ $reason }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex flex-col items-start gap-3 text-sm font-semibold text-amber-900 dark:text-amber-100 lg:items-end">
                            <div>{{ $group['count'] }} {{ __('entries') }}</div>
                            <form action="{{ route('admin.distributions.potential-duplicates.resolve') }}" method="POST">
                                @csrf
                                @foreach($group['record_ids'] as $recordId)
                                    <input type="hidden" name="record_ids[]" value="{{ $recordId }}">
                                @endforeach
                                <button type="submit" class="inline-flex items-center rounded-lg border border-amber-300 dark:border-amber-800 bg-amber-100 dark:bg-amber-950 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-amber-900 dark:text-amber-100 hover:bg-amber-200 dark:hover:bg-amber-900">
                                    {{ __('Mark as Not Duplicate') }}
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                            <thead class="bg-gray-50 dark:bg-[#1C1C1A]">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('ID') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Date') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Client') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Product') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Supplier') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Shop') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Quantity') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Price') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-[#3E3E3A] bg-white dark:bg-[#161615]">
                                @foreach($group['records'] as $distribution)
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">#{{ $distribution->id }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ optional($distribution->distribution_date)->format('d/m/Y') ?? 'N/A' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $distribution->client->name }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $distribution->product->name }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $distribution->supplier?->car_number ?? '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $distribution->shop?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ number_format($distribution->quantity, 3) }} {{ __($distribution->quantity_unit) }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ number_format($distribution->price, 4) }}</td>
                                        <td class="px-4 py-3 text-right text-sm font-medium whitespace-nowrap">
                                            <a href="{{ route('admin.distributions.show', $distribution) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">{{ __('View') }}</a>
                                            <span class="mx-1 text-gray-300 dark:text-gray-600">|</span>
                                            <a href="{{ route('admin.distributions.edit', $distribution) }}" class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300">{{ __('Edit') }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
