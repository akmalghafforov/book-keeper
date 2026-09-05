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
                flatpickr($refs.dateFrom, { dateFormat: 'd/n/Y', allowInput: true });
                flatpickr($refs.dateTo, { dateFormat: 'd/n/Y', allowInput: true });
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
                    <input type="text" name="date_from" id="date_from" x-ref="dateFrom" required value="{{ old('date_from', isset($dateFrom) ? $dateFrom->format('d/n/Y') : '') }}" placeholder="{{ __('DD/M/YYYY') }}" class="block w-full border-gray-300 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white rounded-lg shadow-sm">
                </div>

                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('End Date') }}</label>
                    <input type="text" name="date_to" id="date_to" x-ref="dateTo" required value="{{ old('date_to', isset($dateTo) ? $dateTo->format('d/n/Y') : '') }}" placeholder="{{ __('DD/M/YYYY') }}" class="block w-full border-gray-300 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white rounded-lg shadow-sm">
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
        @include('admin.provider-ledgers.partials.reconciliation-results')
    @endisset
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
@endpush
