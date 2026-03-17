@extends('layouts.admin')

@section('title', __('Debt Ledger Entry Details'))
@section('header_title', __('Debt Ledger Entry Details'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Entry Details') }}</h2>
        <div class="flex space-x-3">
            <a href="{{ route('admin.debt-ledgers.index') }}" class="inline-flex items-center text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-200">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                {{ __('Back to list') }}
            </a>
            <a href="{{ route('admin.debt-ledgers.edit', $debtLedger) }}" class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 focus:bg-yellow-700 active:bg-yellow-900 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition ease-in-out duration-150">
                {{ __('Edit') }}
            </a>
        </div>
    </div>

    <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A]">
        <div class="p-8">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2">
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Client') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white font-semibold">{{ $debtLedger->client->name }}</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Type') }}</dt>
                    <dd class="mt-1 text-sm">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                            {{ $debtLedger->type === 'charge' ? 'bg-red-100 text-red-800' : ($debtLedger->type === 'payment' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800') }}">
                            {{ __($debtLedger->type) }}
                        </span>
                    </dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Amount') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white font-bold text-lg">{{ number_format($debtLedger->amount, 2) }}</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Reference ID') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $debtLedger->reference_id ?? __('N/A') }}</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Transaction Date') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ optional($debtLedger->transaction_date)->format('F d, Y') ?? $debtLedger->created_at->format('F d, Y') }}</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Created At') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $debtLedger->created_at->format('F d, Y H:i') }}</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Last Updated') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $debtLedger->updated_at->format('F d, Y H:i') }}</dd>
                </div>
                <div class="sm:col-span-2 border-t border-gray-100 dark:border-[#3E3E3A] pt-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">{{ __('Notes') }}</dt>
                    <dd class="text-sm text-gray-900 dark:text-white bg-gray-50 dark:bg-[#0a0a0a] p-4 rounded-lg italic">
                        {{ $debtLedger->notes ?? __('No notes provided.') }}
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</div>
@endsection
