@extends('layouts.admin')

@section('title', __('Client Details'))
@section('header_title', __('Client Details') . ': ' . $client->name)

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Client Details') }}</h2>
        <div class="flex items-center space-x-3">
            <a href="{{ route('admin.clients.index') }}" class="inline-flex items-center text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-200">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                {{ __('Back to list') }}
            </a>
            <div class="h-4 w-px bg-gray-300 dark:bg-[#3E3E3A]"></div>
            
            <form action="{{ route('admin.reports.export-client-debt', $client) }}" method="POST" class="inline-block" target="_blank">
                @csrf
                <input type="hidden" name="format" value="png">
                <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm" title="{{ __('Export PNG') }}">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h14a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    PNG
                </button>
            </form>

            @if($client->latestReport)
                <div class="h-4 w-px bg-gray-300 dark:bg-[#3E3E3A]"></div>
                <button
                    onclick="copyReportToClipboard(this, '{{ $client->latestReport->name }}', '{{ Storage::url($client->latestReport->file_path) }}')"
                    class="inline-flex items-center px-3 py-1.5 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm"
                    title="{{ __('Copy Last Report') }}"
                >
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                    </svg>
                    <span class="btn-text">{{ __('Copy') }}</span>
                </button>
            @endif

            <div class="h-4 w-px bg-gray-300 dark:bg-[#3E3E3A]"></div>
            <a href="{{ route('admin.clients.edit', $client) }}" class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 active:bg-yellow-900 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-md shadow-yellow-500/20">
                {{ __('Edit') }}
            </a>
        </div>
    </div>

    <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A]">
        <div class="p-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Client Name') }}</h3>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $client->name }}</p>
                </div>
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Phone Number') }}</h3>
                    <p class="text-xl text-gray-900 dark:text-white">{{ $client->phone ?? __('N/A') }}</p>
                </div>
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Total Debt') }}</h3>
                    <p class="text-2xl font-black {{ $client->total_debt > 0 ? 'text-red-600' : 'text-green-600' }}">
                        {{ number_format($client->total_debt, 2) }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Distributions -->
        <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A]">
            <div class="p-6 border-b border-gray-200 dark:border-[#3E3E3A] flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('Recent Distributions') }}</h3>
            </div>
            <div class="p-0 overflow-x-auto">
                @if($client->distributions->count() > 0)
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-[#1C1C1B]">
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Date') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Product') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-right">{{ __('Qty') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-right">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                            @foreach($client->distributions->sortByDesc('created_at')->take(10) as $dist)
                                <tr class="hover:bg-gray-50 dark:hover:bg-[#1C1C1B] transition-colors duration-150">
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-300">{{ $dist->created_at->format('M d, Y') }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-300">{{ $dist->product->name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-300 text-right">{{ $dist->quantity }}</td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white text-right">{{ number_format($dist->subtotal, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="p-8 text-center">
                        <p class="text-gray-500 dark:text-gray-400 italic">No distributions recorded yet.</p>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Debt Ledger -->
        <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A]">
            <div class="p-6 border-b border-gray-200 dark:border-[#3E3E3A] flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('Debt Ledger') }}</h3>
            </div>
            <div class="p-0 overflow-x-auto">
                @if($client->debtLedgers->count() > 0)
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-[#1C1C1B]">
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Date') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Type') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Notes') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-right">{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                            @foreach($client->debtLedgers->sortByDesc('transaction_date')->take(10) as $ledger)
                                <tr class="hover:bg-gray-50 dark:hover:bg-[#1C1C1B] transition-colors duration-150">
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-300">{{ $ledger->transaction_date?->format('M d, Y') ?? $ledger->created_at->format('M d, Y') }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            {{ $ledger->type === 'charge' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                            {{ __($ledger->type) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $ledger->notes ?: '-' }}</td>
                                    <td class="px-6 py-4 text-sm font-medium text-right {{ $ledger->type === 'charge' ? 'text-red-600' : 'text-green-600' }}">
                                        {{ $ledger->type === 'charge' ? '+' : '-' }}{{ number_format($ledger->amount, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="p-8 text-center">
                        <p class="text-gray-500 dark:text-gray-400 italic">No debt records found.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@include('partials.copy-report-js')
@endsection
