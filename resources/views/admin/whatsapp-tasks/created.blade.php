@extends('layouts.admin')

@section('title', __('Created WhatsApp Tasks'))
@section('header_title', __('Created WhatsApp Tasks'))

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Created WhatsApp Tasks') }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('All tasks created from imported WhatsApp messages.') }}</p>
        </div>
        <a href="{{ route('admin.whatsapp-tasks.index') }}" class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
            {{ __('Create task') }}
        </a>
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

    <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A]">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                <thead class="bg-gray-50 dark:bg-[#1C1C1A]">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Task') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Client') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Amount') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Task date') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Messages') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Created') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-[#161615] divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                    @forelse ($tasks as $task)
                        @php
                            $extraction = $task->extractedData ?? null;
                        @endphp
                        <tr class="align-top">
                            <td class="px-6 py-4 text-sm">
                                <div class="font-semibold text-gray-900 dark:text-white">#{{ $task->id }} {{ __($task->taskTypeLabel()) }}</div>
                                @if ($task->notes)
                                    <div class="mt-1 max-w-xs whitespace-pre-line break-words text-gray-500 dark:text-gray-400">{{ $task->notes }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <div>{{ $task->client?->name ?: '-' }}</div>
                                @if ($task->creditClient)
                                    <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('Credit') }}: {{ $task->creditClient->name }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $task->amount !== null ? number_format((float) $task->amount, 2) : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $task->task_date?->format('Y-m-d') ?: '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $task->status === 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100' : ($task->status === 'cancelled' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100' : 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-100') }}">
                                    {{ __($task->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <div class="mb-2 text-xs font-medium text-gray-400 dark:text-gray-500">{{ $task->messages_count }} {{ __('messages') }}</div>
                                <div class="space-y-2">
                                    @foreach ($task->messages as $message)
                                        <div class="max-w-md rounded-lg bg-gray-50 p-2 dark:bg-[#1C1C1A]">
                                            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-400 dark:text-gray-500">
                                                <span>#{{ $message->id }}</span>
                                                <span>{{ $message->message_at->format('Y-m-d H:i') }}</span>
                                                <span>{{ $message->sender ?: __('Unknown sender') }}</span>
                                            </div>
                                            <div class="mt-1 break-words text-gray-700 dark:text-gray-300">
                                                {{ $message->body ?: $message->attachment_filename ?: '-' }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <div>{{ $task->created_at->format('Y-m-d H:i') }}</div>
                                <div class="mt-1 text-xs">{{ $task->creator?->name ?: '-' }}</div>
                            </td>
                        </tr>
                        @if (($extraction['supported'] ?? false) === true && ($extraction['task_type'] ?? null) === \App\Models\WhatsAppTask::TYPE_GOODS_PIECES)
                            <tr>
                                <td colspan="7" class="bg-gray-50 px-6 py-4 dark:bg-[#10100f]">
                                    <form method="POST" action="{{ route('admin.whatsapp-tasks.extracted-goods-pieces.store', $task) }}" class="rounded-lg border border-gray-200 bg-white p-4 dark:border-[#3E3E3A] dark:bg-[#161615]">
                                        @csrf
                                        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Extracted task values') }}</h3>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Review and select the values detected from WhatsApp messages.') }}</span>
                                        </div>

                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
                                            <div>
                                                <label for="extracted_client_{{ $task->id }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Client') }}</label>
                                                <select id="extracted_client_{{ $task->id }}" name="client_id" class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                    <option value="">{{ __('Select a client') }}</option>
                                                    @foreach ($extraction['clients'] as $candidate)
                                                        <option value="{{ $candidate['id'] }}">{{ $candidate['label'] }} ({{ number_format($candidate['score'], 0) }}%)</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label for="extracted_product_{{ $task->id }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Product') }}</label>
                                                <select id="extracted_product_{{ $task->id }}" name="product_id" class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                    <option value="">{{ __('Select a product') }}</option>
                                                    @foreach ($extraction['products'] as $candidate)
                                                        <option value="{{ $candidate['id'] }}">{{ $candidate['label'] }} ({{ number_format($candidate['score'], 0) }}%)</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label for="extracted_quantity_{{ $task->id }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Quantity') }}</label>
                                                <select id="extracted_quantity_{{ $task->id }}" name="quantity" class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                    <option value="">{{ __('Select quantity') }}</option>
                                                    @foreach ($extraction['quantities'] as $candidate)
                                                        <option value="{{ $candidate['value'] }}">{{ $candidate['label'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label for="extracted_price_{{ $task->id }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Price') }}</label>
                                                <select id="extracted_price_{{ $task->id }}" name="price" class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                    <option value="">{{ __('Select price') }}</option>
                                                    @foreach ($extraction['prices'] as $candidate)
                                                        <option value="{{ $candidate['value'] }}">{{ $candidate['label'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label for="extracted_supplier_{{ $task->id }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Supplier car') }}</label>
                                                <select id="extracted_supplier_{{ $task->id }}" name="supplier_id" class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                    <option value="">{{ __('Select supplier car') }}</option>
                                                    @foreach ($extraction['suppliers'] as $candidate)
                                                        <option value="{{ $candidate['id'] }}">{{ $candidate['label'] }} ({{ number_format($candidate['score'], 0) }}%)</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mt-4 flex justify-end">
                                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition shadow-md shadow-indigo-500/20">
                                                {{ __('Save selected values') }}
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                        @if (($extraction['supported'] ?? false) === true && ($extraction['task_type'] ?? null) === \App\Models\WhatsAppTask::TYPE_PAYMENT)
                            @php
                                $amountCandidate = $extraction['amounts']->first();
                            @endphp
                            <tr>
                                <td colspan="7" class="bg-gray-50 px-6 py-4 dark:bg-[#10100f]">
                                    <form method="POST" action="{{ route('admin.whatsapp-tasks.extracted-payment.store', $task) }}" class="rounded-lg border border-gray-200 bg-white p-4 dark:border-[#3E3E3A] dark:bg-[#161615]">
                                        @csrf
                                        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Extracted payment values') }}</h3>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Review and edit the values detected from WhatsApp messages.') }}</span>
                                        </div>

                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div>
                                                <label for="extracted_payment_client_{{ $task->id }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Client') }}</label>
                                                <select id="extracted_payment_client_{{ $task->id }}" name="client_id" class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                    <option value="">{{ __('Select a client') }}</option>
                                                    @foreach ($extraction['clients'] as $candidate)
                                                        <option value="{{ $candidate['id'] }}">{{ $candidate['label'] }} ({{ number_format($candidate['score'], 0) }}%)</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label for="extracted_payment_amount_{{ $task->id }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Amount') }}</label>
                                                <input
                                                    type="number"
                                                    id="extracted_payment_amount_{{ $task->id }}"
                                                    name="amount"
                                                    value="{{ $amountCandidate['value'] ?? '' }}"
                                                    step="0.01"
                                                    min="0.01"
                                                    class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                >
                                            </div>
                                        </div>

                                        <div class="mt-4 flex justify-end">
                                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition shadow-md shadow-indigo-500/20">
                                                {{ __('Save selected values') }}
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                        @if (($extraction['supported'] ?? false) === true && ($extraction['task_type'] ?? null) === \App\Models\WhatsAppTask::TYPE_CLIENT_TRANSFER)
                            @php
                                $quantityCandidate = $extraction['quantities']->first();
                                $priceCandidate = $extraction['prices']->first();
                            @endphp
                            <tr>
                                <td colspan="7" class="bg-gray-50 px-6 py-4 dark:bg-[#10100f]">
                                    <form method="POST" action="{{ route('admin.whatsapp-tasks.extracted-client-transfer.store', $task) }}" class="rounded-lg border border-gray-200 bg-white p-4 dark:border-[#3E3E3A] dark:bg-[#161615]">
                                        @csrf
                                        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Extracted client transfer values') }}</h3>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Review and edit the values detected from WhatsApp messages.') }}</span>
                                        </div>

                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                                            <div>
                                                <label for="extracted_transfer_client_{{ $task->id }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Client') }}</label>
                                                <select id="extracted_transfer_client_{{ $task->id }}" name="client_id" class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                    <option value="">{{ __('Select a client') }}</option>
                                                    @foreach ($extraction['clients'] as $candidate)
                                                        <option value="{{ $candidate['id'] }}">{{ $candidate['label'] }} ({{ number_format($candidate['score'], 0) }}%)</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label for="extracted_transfer_credit_client_{{ $task->id }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Credit client') }}</label>
                                                <select id="extracted_transfer_credit_client_{{ $task->id }}" name="credit_client_id" class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                    <option value="">{{ __('Select a credit client') }}</option>
                                                    @foreach ($extraction['clients'] as $candidate)
                                                        <option value="{{ $candidate['id'] }}">{{ $candidate['label'] }} ({{ number_format($candidate['score'], 0) }}%)</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label for="extracted_transfer_quantity_{{ $task->id }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Quantity') }}</label>
                                                <input
                                                    type="number"
                                                    id="extracted_transfer_quantity_{{ $task->id }}"
                                                    name="quantity"
                                                    value="{{ $quantityCandidate['value'] ?? '' }}"
                                                    list="extracted_transfer_quantities_{{ $task->id }}"
                                                    step="0.001"
                                                    min="0"
                                                    class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                >
                                                <datalist id="extracted_transfer_quantities_{{ $task->id }}">
                                                    @foreach ($extraction['quantities'] as $candidate)
                                                        <option value="{{ $candidate['value'] }}"></option>
                                                    @endforeach
                                                </datalist>
                                            </div>

                                            <div>
                                                <label for="extracted_transfer_price_{{ $task->id }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Price') }}</label>
                                                <input
                                                    type="number"
                                                    id="extracted_transfer_price_{{ $task->id }}"
                                                    name="price"
                                                    value="{{ $priceCandidate['value'] ?? '' }}"
                                                    list="extracted_transfer_prices_{{ $task->id }}"
                                                    step="0.01"
                                                    min="0"
                                                    class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                >
                                                <datalist id="extracted_transfer_prices_{{ $task->id }}">
                                                    @foreach ($extraction['prices'] as $candidate)
                                                        <option value="{{ $candidate['value'] }}"></option>
                                                    @endforeach
                                                </datalist>
                                            </div>
                                        </div>

                                        <div class="mt-4 flex justify-end">
                                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition shadow-md shadow-indigo-500/20">
                                                {{ __('Save selected values') }}
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 whitespace-nowrap text-sm text-center text-gray-500 dark:text-gray-400">
                                {{ __('No tasks created yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($tasks->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-[#3E3E3A]">
                {{ $tasks->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
