@extends('layouts.admin')

@section('title', __('Dashboard'))
@section('header_title', __('Dashboard'))

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <!-- Stat Card 1 -->
    <div class="bg-white dark:bg-[#161615] p-6 rounded-lg border border-gray-200 dark:border-[#3E3E3A] shadow-sm">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300 mr-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Total Clients') }}</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">128</p>
            </div>
        </div>
    </div>

    <!-- Stat Card 2 -->
    <div class="bg-white dark:bg-[#161615] p-6 rounded-lg border border-gray-200 dark:border-[#3E3E3A] shadow-sm">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 dark:bg-green-900 text-green-600 dark:text-green-300 mr-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Revenue') }}</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">$24,500</p>
            </div>
        </div>
    </div>

    <!-- Stat Card 3 -->
    <div class="bg-white dark:bg-[#161615] p-6 rounded-lg border border-gray-200 dark:border-[#3E3E3A] shadow-sm">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-600 dark:text-yellow-300 mr-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Open Orders') }}</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">12</p>
            </div>
        </div>
    </div>

    <!-- Stat Card 4 -->
    <div class="bg-white dark:bg-[#161615] p-6 rounded-lg border border-gray-200 dark:border-[#3E3E3A] shadow-sm">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-600 dark:text-purple-300 mr-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Products') }}</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">45</p>
            </div>
        </div>
    </div>
</div>

<div class="bg-white dark:bg-[#161615] rounded-lg border border-gray-200 dark:border-[#3E3E3A] shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-[#3E3E3A] flex justify-between items-center">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ __('Recent Activities') }}</h3>
        <a href="#" class="text-sm font-medium text-blue-600 hover:text-blue-500">{{ __('View all') }}</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-gray-50 dark:bg-[#1C1C1A]">
                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Date') }}</th>
                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('User') }}</th>
                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Action') }}</th>
                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                <tr>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">Mar 1, 2026</td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white whitespace-nowrap">John Doe</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ __('Created new client') }}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs font-semibold text-green-600 bg-green-100 rounded-full">{{ __('Success') }}</span>
                    </td>
                </tr>
                <tr>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">Feb 28, 2026</td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white whitespace-nowrap">Jane Smith</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ __('Updated product stock') }}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs font-semibold text-blue-600 bg-blue-100 rounded-full">{{ __('Info') }}</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
