@extends('layouts.admin')

@section('title', __('Compare Provider Ledger with Excel'))
@section('header_title', __('Compare Provider Ledger with Excel'))

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
@endpush

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Compare Provider Ledger with Excel') }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('The comparison is read-only. The uploaded file is not stored.') }}</p>
        </div>
        <a href="{{ route('admin.provider-ledgers.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-[#1C1C1A] border border-gray-300 dark:border-[#3E3E3A] rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-[#2C2C2A]">
            {{ __('Back to list') }}
        </a>
    </div>

    <div class="bg-white dark:bg-[#161615] shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A] p-6">
        <form action="{{ route('admin.provider-ledgers.compare') }}" method="POST" enctype="multipart/form-data" x-data="{
            init() {
                flatpickr($refs.dateFrom, { dateFormat: 'd/m/Y', allowInput: true });
                flatpickr($refs.dateTo, { dateFormat: 'd/m/Y', allowInput: true });
                $($refs.provider).select2({ placeholder: '{{ __('Select a provider') }}', width: '100%' });
            }
        }">
            @csrf

            @if ($errors->any())
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded dark:bg-red-900 dark:text-red-100 dark:border-red-800" role="alert">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
                <div>
                    <label for="provider_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Provider') }}</label>
                    <select name="provider_id" id="provider_id" x-ref="provider" required class="block w-full border-gray-300 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white rounded-lg shadow-sm">
                        <option value=""></option>
                        @foreach ($providers as $item)
                            <option value="{{ $item->id }}" @selected((string) old('provider_id', $provider->id ?? '') === (string) $item->id)>{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Start Date') }}</label>
                    <input type="text" name="date_from" id="date_from" x-ref="dateFrom" required value="{{ old('date_from', isset($dateFrom) ? $dateFrom->format('d/m/Y') : '') }}" placeholder="{{ __('DD/MM/YYYY') }}" class="block w-full border-gray-300 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white rounded-lg shadow-sm">
                </div>

                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('End Date') }}</label>
                    <input type="text" name="date_to" id="date_to" x-ref="dateTo" required value="{{ old('date_to', isset($dateTo) ? $dateTo->format('d/m/Y') : '') }}" placeholder="{{ __('DD/MM/YYYY') }}" class="block w-full border-gray-300 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white rounded-lg shadow-sm">
                </div>

                <div>
                    <label for="excel_file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Excel File') }}</label>
                    <input type="file" name="excel_file" id="excel_file" required accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="block w-full text-sm text-gray-600 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900 dark:file:text-indigo-200">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('XLSX only, maximum 10 MB.') }}</p>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    {{ __('Compare Excel') }}
                </button>
            </div>
        </form>
    </div>

    @isset($result)
        <div class="bg-white dark:bg-[#161615] shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A] p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Comparison Summary') }}</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $provider->name }} &middot; {{ $dateFrom->format('d/m/Y') }} &ndash; {{ $dateTo->format('d/m/Y') }}
            </p>
            <div class="mt-5 grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">
                @foreach ([
                    [__('Matched Deliveries'), $result->matchedDeliveries, 'text-emerald-600'],
                    [__('Matched Payments'), $result->matchedPayments, 'text-emerald-600'],
                    [__('Missing in Database'), $result->missingCount(), 'text-amber-600'],
                    [__('Extra in Database'), $result->extraCount(), 'text-red-600'],
                    [__('Invalid Entries'), count($result->invalidEntries), 'text-gray-700 dark:text-gray-200'],
                ] as [$label, $count, $color])
                    <div class="rounded-lg border border-gray-200 dark:border-[#3E3E3A] p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</div>
                        <div class="mt-1 text-2xl font-bold {{ $color }}">{{ $count }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        @if ($result->missingCount() > 0)
            <div class="bg-white dark:bg-[#161615] shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A] overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-[#3E3E3A]">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Missing in Database') }}</h3>
                </div>
                @include('admin.provider-ledgers.partials.reconciliation-table', [
                    'deliveries' => $result->missingDeliveries,
                    'payments' => $result->missingPayments,
                    'showSourceRow' => true,
                ])
            </div>
        @endif

        @if ($result->extraCount() > 0)
            <div class="bg-white dark:bg-[#161615] shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A] overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-[#3E3E3A]">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Extra in Database') }}</h3>
                </div>
                @include('admin.provider-ledgers.partials.reconciliation-table', [
                    'deliveries' => $result->extraDeliveries,
                    'payments' => $result->extraPayments,
                    'showSourceRow' => false,
                ])
            </div>
        @endif

        @if (count($result->invalidEntries) > 0)
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
                                    <td class="px-6 py-4 text-sm text-red-600 dark:text-red-400">
                                        {{ implode(' ', array_map(fn ($error) => __($error), $entry['errors'])) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($result->missingCount() === 0 && $result->extraCount() === 0 && count($result->invalidEntries) === 0)
            <div class="bg-emerald-100 border border-emerald-400 text-emerald-800 px-4 py-3 rounded dark:bg-emerald-900 dark:text-emerald-100 dark:border-emerald-800">
                {{ __('The Excel file and database ledger match for the selected period.') }}
            </div>
        @endif
    @endisset
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
@endpush
