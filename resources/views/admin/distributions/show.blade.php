@extends('layouts.admin')

@section('title', 'Distribution Details')
@section('header_title', 'Distribution Details')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Distribution Details</h2>
        <div class="flex space-x-3">
            <a href="{{ route('admin.distributions.index') }}" class="inline-flex items-center text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300 transition-colors duration-200">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to list
            </a>
            <a href="{{ route('admin.distributions.edit', $distribution) }}" class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition ease-in-out duration-150">
                Edit
            </a>
        </div>
    </div>

    <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A]">
        <div class="p-8">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-8">
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Distribution Date</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $distribution->distribution_date->format('d/m/Y') }}</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Supply (Car)</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                        @if($distribution->supply)
                            {{ $distribution->supply->car_number }} ({{ $distribution->supply->car_color }}) - {{ $distribution->supply->delivery_date->format('d/m/Y') }}
                        @else
                            <span class="text-gray-400 italic">No associated supply (Direct)</span>
                        @endif
                    </dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Client</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $distribution->client->name }}</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Product</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $distribution->product->name }}</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Quantity</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $distribution->quantity }} {{ str_replace('per_', '', $distribution->quantity_unit) }}</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Price</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">${{ number_format($distribution->price, 2) }}</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Subtotal</dt>
                    <dd class="mt-1 text-sm font-bold text-indigo-600 dark:text-indigo-400">${{ number_format($distribution->subtotal, 2) }}</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created At</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $distribution->created_at->format('d/m/Y H:i') }}</dd>
                </div>
            </dl>
        </div>
    </div>
</div>
@endsection
