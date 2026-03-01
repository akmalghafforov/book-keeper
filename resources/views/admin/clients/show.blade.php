@extends('layouts.admin')

@section('title', 'Client Details')
@section('header_title', 'Client Details: ' . $client->name)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Client Details</h2>
        <div class="flex items-center space-x-3">
            <a href="{{ route('admin.clients.index') }}" class="inline-flex items-center text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-200">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to list
            </a>
            <div class="h-4 w-px bg-gray-300 dark:bg-[#3E3E3A]"></div>
            <a href="{{ route('admin.clients.edit', $client) }}" class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 active:bg-yellow-900 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-md shadow-yellow-500/20">
                Edit
            </a>
            <form action="{{ route('admin.clients.destroy', $client) }}" method="POST" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-md shadow-red-500/20" onclick="return confirm('Are you sure?')">
                    Delete
                </button>
            </form>
        </div>
    </div>

    <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A]">
        <div class="p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Client Name</h3>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $client->name }}</p>
                </div>
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Phone Number</h3>
                    <p class="text-xl text-gray-900 dark:text-white">{{ $client->phone ?? 'N/A' }}</p>
                </div>
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created At</h3>
                    <p class="text-gray-900 dark:text-white">{{ $client->created_at->format('F d, Y H:i') }}</p>
                </div>
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Updated</h3>
                    <p class="text-gray-900 dark:text-white">{{ $client->updated_at->format('F d, Y H:i') }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Placeholder for related data like Distributions and Debt Ledgers -->
        <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A] p-8">
            <h3 class="text-lg font-bold mb-4 text-gray-900 dark:text-white">Recent Distributions</h3>
            <p class="text-gray-500 dark:text-gray-400 italic">No recent distributions.</p>
        </div>
        
        <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A] p-8">
            <h3 class="text-lg font-bold mb-4 text-gray-900 dark:text-white">Debt Ledger</h3>
            <p class="text-gray-500 dark:text-gray-400 italic">No debt records found.</p>
        </div>
    </div>
</div>
@endsection
