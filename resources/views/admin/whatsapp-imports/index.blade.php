@extends('layouts.admin')

@section('title', __('WhatsApp Import'))
@section('header_title', __('WhatsApp Import'))

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('WhatsApp Import') }}</h2>
    </div>

    @if (session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('import_result'))
        @php($result = session('import_result'))
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-[#161615] border border-gray-200 dark:border-[#3E3E3A] rounded-xl p-5 shadow-sm">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Parsed') }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($result['total']) }}</div>
            </div>
            <div class="bg-white dark:bg-[#161615] border border-gray-200 dark:border-[#3E3E3A] rounded-xl p-5 shadow-sm">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Inserted') }}</div>
                <div class="mt-1 text-2xl font-semibold text-green-600">{{ number_format($result['created']) }}</div>
            </div>
            <div class="bg-white dark:bg-[#161615] border border-gray-200 dark:border-[#3E3E3A] rounded-xl p-5 shadow-sm">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Skipped') }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($result['skipped']) }}</div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A] p-6">
            <form action="{{ route('admin.whatsapp-imports.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <div>
                    <label for="zip_file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('WhatsApp ZIP file') }}</label>
                    <input type="file" name="zip_file" id="zip_file" accept=".zip,application/zip" required class="block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-[#2A2A28] dark:file:text-gray-200 dark:hover:file:bg-[#343431]">
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Upload an exported WhatsApp chat ZIP. Existing messages are skipped automatically.') }}</p>
                </div>

                <div class="flex items-center justify-end">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 shadow-md shadow-indigo-500/20 transition ease-in-out duration-150">
                        {{ __('Import ZIP') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A] p-6">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Current Messages') }}</h3>
            <div class="mt-4 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($messageCount) }}</div>
            <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Latest message') }}:
                <span class="font-medium text-gray-700 dark:text-gray-300">
                    {{ $latestMessageAt ? \Illuminate\Support\Carbon::parse($latestMessageAt)->format('Y-m-d H:i') : '-' }}
                </span>
            </div>
        </div>
    </div>
</div>
@endsection
