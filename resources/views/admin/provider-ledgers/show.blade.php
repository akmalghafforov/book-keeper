@extends('layouts.admin')

@section('title', __('Provider Ledger Entry Details'))
@section('header_title', __('Provider Ledger Entry Details'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Provider Ledger Entry') }}</h2>
        <div class="flex space-x-3">
            <a href="{{ route('admin.provider-ledgers.index') }}" class="inline-flex items-center text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-200">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                {{ __('Back to list') }}
            </a>
            @if($providerLedger->distribution_id === null)
                <a href="{{ route('admin.provider-ledgers.edit', $providerLedger) }}" class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 focus:bg-yellow-700 active:bg-yellow-900 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    {{ __('Edit') }}
                </a>
            @endif
        </div>
    </div>

    <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A]">
        <div class="p-8">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2">
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Provider') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white font-semibold">{{ $providerLedger->provider->name }}</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Type') }}</dt>
                    <dd class="mt-1 text-sm">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $providerLedger->type === 'payment' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ __($providerLedger->type) }}
                        </span>
                    </dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Amount') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white font-bold text-lg">{{ $providerLedger->type === 'payment' ? '-' : '' }}{{ number_format((float) $providerLedger->amount, 4) }}</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Provider Date & Time') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $providerLedger->operationDateTime()?->format('F d, Y H:i') }}</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Product') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $providerLedger->product?->name ?? __('N/A') }}</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Distribution ID') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $providerLedger->distribution_id ?? __('N/A') }}</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Car Number') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $providerLedger->car_number ?? __('N/A') }}</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Quantity') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $providerLedger->quantity === null ? __('N/A') : number_format((float) $providerLedger->quantity, 3) }}</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Buy Price') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $providerLedger->buy_price === null ? __('N/A') : number_format((float) $providerLedger->buy_price, 4) }}</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Created At') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $providerLedger->created_at->format('F d, Y H:i') }}</dd>
                </div>
                <div class="sm:col-span-2 border-t border-gray-100 dark:border-[#3E3E3A] pt-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">{{ __('Notes') }}</dt>
                    <dd class="text-sm text-gray-900 dark:text-white bg-gray-50 dark:bg-[#0a0a0a] p-4 rounded-lg italic">
                        {{ $providerLedger->notes ?? __('No notes provided.') }}
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</div>
@endsection
