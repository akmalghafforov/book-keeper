@extends('layouts.admin')

@section('title', __('Client Debt Report'))
@section('header_title', __('Reports'))

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Client Debt Report') }}</h2>
            <div class="flex gap-2">
                <form action="{{ route('admin.reports.export') }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="format" value="pdf">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        {{ __('Export PDF') }}
                    </button>
                </form>
                <form action="{{ route('admin.reports.export') }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="format" value="jpg">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        {{ __('Export JPG') }}
                    </button>
                </form>
            </div>
        </div>
        <div class="flex items-center gap-4 text-sm font-medium text-gray-600 dark:text-gray-400">
            <span>{{ __('Total Clients with Debt') }}: {{ $clients->count() }}</span>
            <span class="text-lg font-bold text-red-600 dark:text-red-400">
                {{ __('Total Debt') }}: {{ number_format($clients->sum('calculated_total_debt'), 2) }}
            </span>
        </div>
    </div>

    <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-[#3E3E3A]">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                <thead class="bg-gray-50 dark:bg-[#1C1C1A]">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Client') }}</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Current Debt') }}</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-[#161615] divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                    @forelse ($clients as $client)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                <a href="{{ route('admin.clients.show', $client) }}" class="hover:underline">
                                    {{ $client->name }}
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold {{ $client->calculated_total_debt > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                {{ number_format($client->calculated_total_debt, 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="{{ route('admin.debt-ledgers.index', ['client_id' => $client->id]) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300" title="{{ __('View Ledger') }}">
                                    <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500 dark:text-gray-400">
                                {{ __('No clients with active debt found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($clients->isNotEmpty())
                <tfoot class="bg-gray-50 dark:bg-[#1C1C1A] font-bold">
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white text-right">{{ __('Total') }}:</td>
                        <td class="px-6 py-4 text-sm text-right text-red-600 dark:text-red-400">
                            {{ number_format($clients->sum('calculated_total_debt'), 2) }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
