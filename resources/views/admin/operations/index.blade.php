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
                $($refs.selectClient).select2({ placeholder: '{{ __('All Clients') }}', allowClear: true, width: '100%' });
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
            <table class="min-w-full divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                <thead class="bg-gray-50 dark:bg-[#1C1C1A]">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Date') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Client') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Car') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Operation') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Product') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Qty') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Price') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Amount') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Notes') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-[#161615] divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                    @forelse ($operations as $operation)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ optional($operation->transaction_date)->format('Y-m-d') ?? $operation->created_at->format('Y-m-d') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                {{ $operation->client->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $operation->distribution?->supplier?->car_number ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $operation->type === 'charge' ? 'bg-red-100 text-red-800' : ($operation->type === 'payment' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800') }}">
                                    {{ __($operation->type) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $operation->distribution->product->name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $operation->distribution ? number_format($operation->distribution->quantity, 2) : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $operation->distribution ? number_format($operation->distribution->price, 2) : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold {{ $operation->type === 'charge' ? 'text-red-600' : 'text-green-600' }}">
                                {{ number_format($operation->amount, 2) }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 truncate max-w-xs" title="{{ $operation->notes }}">
                                {{ $operation->notes }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500 dark:text-gray-400">
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
