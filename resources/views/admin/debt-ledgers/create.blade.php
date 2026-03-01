@extends('layouts.admin')

@section('title', __('Add Debt Ledger Entry'))
@section('header_title', __('Add Debt Ledger Entry'))

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Add Debt Ledger Entry</h2>
        <a href="{{ route('admin.debt-ledgers.index') }}" class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors duration-200">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to list
        </a>
    </div>

    <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A]">
        <div class="p-8">
            <form action="{{ route('admin.debt-ledgers.store') }}" method="POST" class="space-y-6">
                @csrf
                
                <div>
                    <label for="client_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Client') }}</label>
                    <select name="client_id" id="client_id" required
                        class="select2 block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200">
                        <option value="">Select a client</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                        @endforeach
                    </select>
                    @error('client_id')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Type') }}</label>
                    <select name="type" id="type" required
                        class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200">
                        <option value="charge" {{ old('type') == 'charge' ? 'selected' : '' }}>Charge</option>
                        <option value="payment" {{ old('type') == 'payment' ? 'selected' : '' }}>Payment</option>
                        <option value="credit_note" {{ old('type') == 'credit_note' ? 'selected' : '' }}>Credit Note</option>
                    </select>
                    @error('type')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Amount') }}</label>
                    <input type="number" name="amount" id="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required
                        class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200"
                        placeholder="0.00">
                    @error('amount')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="reference_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reference ID (Optional)</label>
                    <input type="number" name="reference_id" id="reference_id" value="{{ old('reference_id') }}"
                        class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200">
                    @error('reference_id')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes (Optional)</label>
                    <textarea name="notes" id="notes" rows="3"
                        class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200">{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end pt-4 border-t border-gray-100 dark:border-[#3E3E3A] space-x-3">
                    <a href="{{ route('admin.debt-ledgers.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 dark:bg-[#2A2A28] border border-transparent rounded-lg font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-[#3E3E3A] focus:outline-none focus:ring-2 focus:ring-gray-300 dark:focus:ring-gray-700 transition ease-in-out duration-150">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-md shadow-indigo-500/20">
                        Create Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .select2-container--default .select2-selection--single {
        background-color: transparent;
        border-color: #D1D5DB;
        height: 38px;
        border-radius: 0.5rem;
    }
    .dark .select2-container--default .select2-selection--single {
        background-color: #0a0a0a;
        border-color: #3E3E3A;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #111827;
        line-height: 36px;
        padding-left: 0.75rem;
    }
    .dark .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: white;
    }
    .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #6B7280;
    }
    .dark .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #9CA3AF;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }
    .select2-dropdown {
        background-color: white;
        border-color: #D1D5DB;
        border-radius: 0.5rem;
    }
    .dark .select2-dropdown {
        background-color: #161615;
        border-color: #3E3E3A;
        color: white;
    }
    .select2-container--default .select2-search--dropdown .select2-search__field {
        background-color: white;
        border-color: #D1D5DB;
        border-radius: 0.375rem;
    }
    .dark .select2-container--default .select2-search--dropdown .select2-search__field {
        background-color: #0a0a0a;
        border-color: #3E3E3A;
        color: white;
    }
    .select2-results__option {
        color: #111827;
    }
    .dark .select2-results__option {
        color: #EDEDEC;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #4F46E5;
        color: white;
    }
    .select2-container--default .select2-results__option[aria-selected=true] {
        background-color: #E0E7FF;
        color: #4338CA;
    }
    .dark .select2-container--default .select2-results__option[aria-selected=true] {
        background-color: #312E81;
        color: #E0E7FF;
    }
</style>
@endpush

@push('scripts')
<script>
    $(document).ready(function() {
        $('#client_id').select2({
            placeholder: "{{ __('Select a client') }}",
            allowClear: true,
            width: '100%'
        });
    });
</script>
@endpush
