@extends('layouts.admin')

@section('title', __('All Operations'))
@section('header_title', __('All Operations'))

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
@endpush

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('All Operations') }}</h2>
    </div>

    <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A] p-6 mb-6">
        <form action="{{ route('admin.operations.index') }}" method="GET" x-data="{
            init() {
                flatpickr($refs.dateFrom, { dateFormat: 'Y-m-d', allowInput: true });
                flatpickr($refs.dateTo, { dateFormat: 'Y-m-d', allowInput: true });
                $($refs.selectClient).select2({ placeholder: '{{ __('All Clients') }}', allowClear: true, width: '100%', matcher: window.clientSelect2Matcher });
                $($refs.selectType).select2({ placeholder: '{{ __('All Types') }}', allowClear: true, width: '100%' });
            }
        }">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Search') }}</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="{{ __('Client, Product, Notes...') }}" class="block w-full border-gray-300 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm py-2 px-3">
                </div>

                <div>
                    <label for="car_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Car Number') }}</label>
                    <input type="text" name="car_number" id="car_number" value="{{ request('car_number') }}" placeholder="{{ __('Car Number') }}" class="block w-full border-gray-300 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm py-2 px-3">
                </div>

                <div>
                    <label for="client_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Client') }}</label>
                    <select name="client_id" id="client_id" x-ref="selectClient" class="block w-full">
                        <option value=""></option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Type') }}</label>
                    <select name="type" id="type" x-ref="selectType" class="block w-full">
                        <option value=""></option>
                        <option value="charge" {{ request('type') === 'charge' ? 'selected' : '' }}>{{ __('Charge') }}</option>
                        <option value="payment" {{ request('type') === 'payment' ? 'selected' : '' }}>{{ __('Payment') }}</option>
                        <option value="credit_note" {{ request('type') === 'credit_note' ? 'selected' : '' }}>{{ __('Credit Note') }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Date Range') }}</label>
                    <div class="flex items-center space-x-2">
                        <input type="text" name="date_from" x-ref="dateFrom" value="{{ request('date_from') }}" placeholder="{{ __('From') }}" class="block w-full border-gray-300 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm py-2 px-3">
                        <span class="text-gray-500">-</span>
                        <input type="text" name="date_to" x-ref="dateTo" value="{{ request('date_to') }}" placeholder="{{ __('To') }}" class="block w-full border-gray-300 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm py-2 px-3">
                    </div>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end space-x-3">
                <a href="{{ route('admin.operations.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-[#1C1C1A] border border-gray-300 dark:border-[#3E3E3A] rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-[#2C2C2A] transition ease-in-out duration-150">
                    {{ __('Clear') }}
                </a>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 shadow-md shadow-indigo-500/20 transition ease-in-out duration-150">
                    {{ __('Filter') }}
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A]">
        <div class="overflow-x-auto">
            <table class="w-max divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                <thead class="bg-gray-50 dark:bg-[#1C1C1A]">
                    <tr>
                        <th scope="col" class="sticky left-0 z-30 w-16 min-w-16 bg-gray-50 px-2 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:bg-[#1C1C1A] dark:text-gray-400 sm:px-3">{{ __('Actions') }}</th>
                        <th scope="col" class="sticky left-16 z-30 w-14 min-w-14 bg-gray-50 px-2 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:bg-[#1C1C1A] dark:text-gray-400 sm:px-3">{{ __('Date') }}</th>
                        <th scope="col" class="sticky left-[7.5rem] z-30 bg-gray-50 px-2 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 shadow-[4px_0_6px_-4px_rgb(0_0_0_/_0.35)] dark:bg-[#1C1C1A] dark:text-gray-400 sm:px-3">{{ __('Client') }}</th>
                        <th scope="col" class="px-2 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 sm:px-3">{{ __('Product') }}</th>
                        <th scope="col" class="px-2 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 sm:px-3">{{ __('Qty × Price') }}</th>
                        <th scope="col" class="px-2 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 sm:px-3">{{ __('Amount') }}</th>
                        <th scope="col" class="px-2 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 sm:px-3">{{ __('Balance') }}</th>
                        <th scope="col" class="px-2 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 sm:px-3">{{ __('Notes') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-[#161615] divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                    @forelse ($operations as $operation)
                        @php($includedInRecentDebtReport = (bool) $operation->getAttribute('included_in_recent_debt_report'))
                        <tr>
                            <td class="sticky left-0 z-20 w-16 min-w-16 bg-white px-2 py-4 text-left text-sm dark:bg-[#161615] sm:px-3">
                                <form action="{{ route('admin.reports.export-operation-debt', $operation) }}" method="POST" class="inline-block">
                                    @csrf
                                    <input type="hidden" name="format" value="jpg">
                                    @php($reportActionLabel = $includedInRecentDebtReport ? __('Included in the latest completed debt report for this client') : __('Generate debt report from this operation'))
                                    <button type="submit" aria-label="{{ $reportActionLabel }}" class="inline-flex items-center justify-center rounded-md border border-transparent p-2 {{ $includedInRecentDebtReport ? 'bg-red-600 hover:bg-red-700 active:bg-red-900 focus:ring-red-500' : 'bg-green-600 hover:bg-green-700 active:bg-green-900 focus:ring-green-500' }} text-white shadow-sm transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2" title="{{ $reportActionLabel }}">
                                        @if($includedInRecentDebtReport)
                                            <svg aria-hidden="true" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                                            </svg>
                                        @else
                                            <svg aria-hidden="true" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5V6.75A2.25 2.25 0 0 0 12.375 4.5h-4.5a2.25 2.25 0 0 0-2.25 2.25v10.5a2.25 2.25 0 0 0 2.25 2.25h4.5a2.25 2.25 0 0 0 2.25-2.25V15.75h1.5a3.375 3.375 0 0 0 3.375-3.375Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 9.75h3.75M9 12.75h3.75" />
                                            </svg>
                                        @endif
                                    </button>
                                </form>
                            </td>
                            <td class="sticky left-16 z-20 w-14 min-w-14 whitespace-nowrap bg-white px-2 py-4 text-sm text-gray-500 dark:bg-[#161615] dark:text-gray-400 sm:px-3">
                                <span class="inline-flex items-center gap-2">
                                    <span role="img" class="h-2.5 w-2.5 rounded-full {{ $operation->type === 'charge' ? 'bg-red-500' : ($operation->type === 'payment' ? 'bg-green-500' : 'bg-blue-500') }}" aria-label="{{ __($operation->type) }}" title="{{ __($operation->type) }}"></span>
                                    {{ optional($operation->transaction_date)->format('j/n') ?? $operation->created_at->format('j/n') }}
                                </span>
                            </td>
                            <td class="sticky left-[7.5rem] z-20 whitespace-nowrap bg-white px-2 py-4 text-sm font-medium text-gray-900 shadow-[4px_0_6px_-4px_rgb(0_0_0_/_0.35)] dark:bg-[#161615] dark:text-white sm:px-3">
                                {{ $operation->client->name }}
                            </td>
                            <td class="whitespace-nowrap px-2 py-4 text-sm text-gray-500 dark:text-gray-400 sm:px-3">
                                {{ $operation->distribution->product->name ?? '-' }}
                            </td>
                            <td class="whitespace-nowrap px-2 py-4 text-sm text-gray-500 dark:text-gray-400 sm:px-3">
                                <div>{{ $operation->distribution ? number_format($operation->distribution->quantity, 2).' × '.number_format($operation->type === 'credit_note' ? ($operation->distribution->credit_client_price ?? $operation->distribution->price) : $operation->distribution->price, 2) : '-' }}</div>
                                <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $operation->distribution?->supplier?->car_number ?? '-' }}</div>
                            </td>
                            <td class="whitespace-nowrap px-2 py-4 text-sm font-semibold {{ $operation->type === 'charge' ? 'text-red-600' : 'text-green-600' }} sm:px-3">
                                {{ number_format($operation->amount, 2) }}
                            </td>
                            <td class="whitespace-nowrap px-2 py-4 text-sm font-semibold {{ $operation->balance_after_operation > 0 ? 'text-red-600' : 'text-green-600' }} sm:px-3">
                                {{ number_format($operation->balance_after_operation, 2) }}
                            </td>
                            <td class="max-w-xs truncate px-2 py-4 text-sm text-gray-500 dark:text-gray-400 sm:px-3" title="{{ $operation->notes }}">
                                {{ $operation->notes }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-2 py-4 whitespace-nowrap text-center text-sm text-gray-500 dark:text-gray-400 sm:px-3">
                                {{ __('No operations found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($operations->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-[#3E3E3A]">
                {{ $operations->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
@endpush
