@extends('layouts.admin')

@section('title', __('Edit Debt Ledger Entry'))
@section('header_title', __('Edit Debt Ledger Entry'))

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Debt Ledger Entry</h2>
        <a href="{{ route('admin.debt-ledgers.index') }}" class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors duration-200">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to list
        </a>
    </div>

    <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A]">
        <div class="p-8">
            <form action="{{ route('admin.debt-ledgers.update', $debtLedger) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')
                
                <div>
                    <label for="client_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Client') }}</label>
                    <select name="client_id" id="client_id" required
                        class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200">
                        <option value="">Select a client</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ (old('client_id') ?? $debtLedger->client_id) == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
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

                <div>
                    <label for="reference_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reference ID (Optional)</label>
                    <input type="number" name="reference_id" id="reference_id" value="{{ old('reference_id') ?? $debtLedger->reference_id }}"
                        class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200">
                    @error('reference_id')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes (Optional)</label>
                    <textarea name="notes" id="notes" rows="3"
                        class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200">{{ old('notes') ?? $debtLedger->notes }}</textarea>
                    @error('notes')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end pt-4 border-t border-gray-100 dark:border-[#3E3E3A] space-x-3">
                    <a href="{{ route('admin.debt-ledgers.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 dark:bg-[#2A2A28] border border-transparent rounded-lg font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-[#3E3E3A] focus:outline-none focus:ring-2 focus:ring-gray-300 dark:focus:ring-gray-700 transition ease-in-out duration-150">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-md shadow-indigo-500/20">
                        Update Entry
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
        background-color: white !important;
        border-color: #D1D5DB !important;
        height: 38px !important;
        border-radius: 0.5rem !important;
        display: flex !important;
        align-items: center !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #111827 !important;
        line-height: 36px !important;
        padding-left: 0.75rem !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #6B7280 !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
    }
    .select2-dropdown {
        background-color: white !important;
        border-color: #D1D5DB !important;
        border-radius: 0.5rem !important;
    }
    .select2-container--default .select2-search--dropdown .select2-search__field {
        background-color: white !important;
        border-color: #D1D5DB !important;
        border-radius: 0.375rem !important;
        color: #111827 !important;
    }
    .select2-results__option {
        color: #111827 !important;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #4F46E5 !important;
        color: white !important;
    }
    .select2-container--default .select2-results__option[aria-selected=true] {
        background-color: #E0E7FF !important;
        color: #4338CA !important;
    }

    /* Dark mode support */
    @media (prefers-color-scheme: dark) {
        .select2-container--default .select2-selection--single {
            background-color: #0a0a0a !important;
            border-color: #3E3E3A !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: white !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #9CA3AF !important;
        }
        .select2-dropdown {
            background-color: #161615 !important;
            border-color: #3E3E3A !important;
            color: white !important;
        }
        .select2-container--default .select2-search--dropdown .select2-search__field {
            background-color: #0a0a0a !important;
            border-color: #3E3E3A !important;
            color: white !important;
        }
        .select2-results__option {
            color: #EDEDEC !important;
        }
        .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: #312E81 !important;
            color: #E0E7FF !important;
        }
    }

    /* Fallback for .dark class if used */
    .dark .select2-container--default .select2-selection--single {
        background-color: #0a0a0a !important;
        border-color: #3E3E3A !important;
    }
    .dark .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: white !important;
    }
    .dark .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #9CA3AF !important;
    }
    .dark .select2-dropdown {
        background-color: #161615 !important;
        border-color: #3E3E3A !important;
        color: white !important;
    }
    .dark .select2-container--default .select2-search--dropdown .select2-search__field {
        background-color: #0a0a0a !important;
        border-color: #3E3E3A !important;
        color: white !important;
    }
    .dark .select2-results__option {
        color: #EDEDEC !important;
    }
    .dark .select2-container--default .select2-results__option[aria-selected=true] {
        background-color: #312E81 !important;
        color: #E0E7FF !important;
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
