@extends('layouts.admin')

@section('title', __('Edit Debt Ledger Entry'))
@section('header_title', __('Edit Debt Ledger Entry'))

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
@endpush

@section('content')
<div class="max-w-2xl mx-auto space-y-6" x-data="{ clientId: '{{ old('client_id') ?? $debtLedger->client_id }}' }">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Debt Ledger Entry</h2>
        <a href="{{ route('admin.debt-ledgers.index') }}" class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors duration-200">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            {{ __('Back to list') }}
        </a>
    </div>

    <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A]">
        <div class="p-8">
            <form action="{{ route('admin.debt-ledgers.update', $debtLedger) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')
                
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label for="client_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Client') }}</label>
                    </div>
                    <select name="client_id" id="client_id" x-ref="select"
                        x-init="
                            $($refs.select).select2({
                                placeholder: '{{ __('Select a client') }}',
                                allowClear: true,
                                width: '100%'
                            });
                            $($refs.select).on('change', () => { clientId = $($refs.select).val() });
                        "
                        x-effect="$($refs.select).val(clientId).trigger('change')"
                        required
                        class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200">
                        <option value=""></option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ (old('client_id') ?? $debtLedger->client_id) == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                        @endforeach
                    </select>
                    @error('client_id')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Type') }}</label>
                    <select name="type" id="type" required
                        class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200">
                        <option value="charge" {{ (old('type') ?? $debtLedger->type) == 'charge' ? 'selected' : '' }}>{{ __('charge') }}</option>
                        <option value="payment" {{ (old('type') ?? $debtLedger->type) == 'payment' ? 'selected' : '' }}>{{ __('payment') }}</option>
                        <option value="credit_note" {{ (old('type') ?? $debtLedger->type) == 'credit_note' ? 'selected' : '' }}>{{ __('credit_note') }}</option>
                    </select>
                    @error('type')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Amount') }}</label>
                    <input type="number" name="amount" id="amount" value="{{ old('amount') ?? $debtLedger->amount }}" step="0.01" min="0.01" required
                        class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200"
                        placeholder="0.00">
                    @error('amount')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div x-data="{
                    init() {
                        flatpickr($refs.datepicker, {
                            dateFormat: 'd/m/Y',
                            defaultDate: '{{ old('transaction_date', optional($debtLedger->transaction_date)?->format('d/m/Y')) }}',
                            allowInput: true,
                        });
                    }
                }">
                    <label for="transaction_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Transaction Date') }}</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </div>
                        <input type="text" name="transaction_date" id="transaction_date" x-ref="datepicker" required
                            class="block w-full pl-10 pr-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200"
                            placeholder="dd/mm/yyyy">
                    </div>
                    @error('transaction_date')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="reference_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Reference ID (Optional)') }}</label>
                    <input type="number" name="reference_id" id="reference_id" value="{{ old('reference_id') ?? $debtLedger->reference_id }}"
                        class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200">
                    @error('reference_id')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Notes (Optional)') }}</label>
                    <textarea name="notes" id="notes" rows="3"
                        class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200">{{ old('notes') ?? $debtLedger->notes }}</textarea>
                    @error('notes')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end pt-4 border-t border-gray-100 dark:border-[#3E3E3A] space-x-3">
                    <a href="{{ route('admin.debt-ledgers.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 dark:bg-[#2A2A28] border border-transparent rounded-lg font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-[#3E3E3A] focus:outline-none focus:ring-2 focus:ring-gray-300 dark:focus:ring-gray-700 transition ease-in-out duration-150">
                        {{ __('Cancel') }}
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-md shadow-indigo-500/20">
                        {{ __('Update Entry') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
@endpush
