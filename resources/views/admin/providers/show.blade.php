@extends('layouts.admin')

@section('title', __('Provider Details'))
@section('header_title', __('Provider Details') . ': ' . $provider->name)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Provider Details') }}</h2>
        <div class="flex items-center space-x-3">
            <a href="{{ route('admin.providers.index') }}" class="inline-flex items-center text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-200">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                {{ __('Back to list') }}
            </a>
            <div class="h-4 w-px bg-gray-300 dark:bg-[#3E3E3A]"></div>
            <a href="{{ route('admin.provider-ledgers.create', ['provider_id' => $provider->id]) }}" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-md shadow-green-500/20">
                {{ __('Record Payment') }}
            </a>
            <a href="{{ route('admin.providers.edit', $provider) }}" class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 active:bg-yellow-900 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-md shadow-yellow-500/20">
                {{ __('Edit') }}
            </a>
            <form action="{{ route('admin.providers.destroy', $provider) }}" method="POST" onsubmit="return confirm('{{ __('Are you sure?') }}')">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-md shadow-red-500/20">
                    {{ __('Delete') }}
                </button>
            </form>
        </div>
    </div>

    @if (session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative dark:bg-green-900 dark:text-green-100 dark:border-green-800" role="alert">
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A]">
        <div class="p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Provider Name') }}</h3>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $provider->name }}</p>
                </div>
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Phone') }}</h3>
                    <p class="text-gray-900 dark:text-white">{{ $provider->phone ?? __('N/A') }}</p>
                </div>
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Balance') }}</h3>
                    <p class="text-gray-900 dark:text-white">{{ number_format($provider->balance, 4) }}</p>
                </div>
                <div class="space-y-1 md:col-span-2">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Address') }}</h3>
                    <p class="text-gray-900 dark:text-white">{{ $provider->address ?? __('N/A') }}</p>
                </div>
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Created At') }}</h3>
                    <p class="text-gray-900 dark:text-white">{{ $provider->created_at->format('F d, Y H:i') }}</p>
                </div>
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Last Updated') }}</h3>
                    <p class="text-gray-900 dark:text-white">{{ $provider->updated_at->format('F d, Y H:i') }}</p>
                </div>
                <div class="space-y-1 md:col-span-2">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Notes') }}</h3>
                    <p class="text-gray-900 dark:text-white whitespace-pre-line">{{ $provider->notes ?? __('N/A') }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A]">
        <div class="p-8">
            <h3 class="text-lg font-bold mb-4 text-gray-900 dark:text-white">{{ __('Provider Ledger') }}</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                    <thead class="bg-gray-50 dark:bg-[#1C1C1A]">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Date') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Type') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Product') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Car Number') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Quantity') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Buy Price') }}</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Amount') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Notes') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-[#161615] divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                        @forelse ($providerLedgers as $ledger)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ optional($ledger->transaction_date)->format('M d, Y') }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $ledger->type === 'payment' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ __($ledger->type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $ledger->product?->name ?? __('N/A') }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $ledger->car_number ?? __('N/A') }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $ledger->quantity === null ? __('N/A') : number_format((float) $ledger->quantity, 3) }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $ledger->buy_price === null ? __('N/A') : number_format((float) $ledger->buy_price, 4) }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-medium {{ $ledger->type === 'payment' ? 'text-green-600 dark:text-green-400' : 'text-gray-900 dark:text-white' }}">
                                    {{ $ledger->type === 'payment' ? '-' : '' }}{{ number_format((float) $ledger->amount, 4) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $ledger->notes ?? __('N/A') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-500 dark:text-gray-400">
                                    {{ __('No provider ledger entries found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($providerLedgers->hasPages())
                <div class="pt-4 border-t border-gray-200 dark:border-[#3E3E3A]">
                    {{ $providerLedgers->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
