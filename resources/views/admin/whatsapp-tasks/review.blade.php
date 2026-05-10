@extends('layouts.admin')

@section('title', __('Review WhatsApp Task'))
@section('header_title', __('Review WhatsApp Task'))

@section('content')
@php
    $dateValue = old('record_date', $formValues['record_date'] ?? ($task?->task_date?->format('d/m/Y') ?? now()->format('d/m/Y')));
    $reviewTaskType = old('task_type', $formValues['task_type'] ?? ($reviewTaskType ?? $task?->task_type ?? \App\Models\WhatsAppTask::TYPE_GOODS_PIECES));
    $selectedClientId = old('client_id', $formValues['client_id'] ?? ($task?->client_id ?? data_get($extraction, 'clients.0.id')));
    $selectedProductId = old('product_id', $formValues['product_id'] ?? data_get($extraction, 'products.0.id'));
    $selectedSupplierId = old('supplier_id', $formValues['supplier_id'] ?? data_get($extraction, 'suppliers.0.id'));
    $quantityValue = old('quantity', $formValues['quantity'] ?? data_get($extraction, 'quantities.0.value'));
    $priceValue = old('price', $formValues['price'] ?? data_get($extraction, 'prices.0.value'));
    $amountValue = old('amount', $formValues['amount'] ?? ($task?->amount ?? data_get($extraction, 'amounts.0.value')));
    $clientCandidates = data_get($extraction, 'clients', collect());
    $productCandidates = data_get($extraction, 'products', collect());
    $supplierCandidates = data_get($extraction, 'suppliers', collect());
    $quantityCandidates = data_get($extraction, 'quantities', collect());
    $priceCandidates = data_get($extraction, 'prices', collect());
    $amountCandidates = data_get($extraction, 'amounts', collect());
@endphp

<div class="mx-auto max-w-5xl space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Review WhatsApp Task') }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Pending review tasks') }}: {{ $pendingCount }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.whatsapp-tasks.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-[#3E3E3A] dark:bg-[#161615] dark:text-gray-300 dark:hover:bg-[#1C1C1A]">
                {{ __('Create task') }}
            </a>
            <a href="{{ route('admin.whatsapp-tasks.created') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-[#3E3E3A] dark:bg-[#161615] dark:text-gray-300 dark:hover:bg-[#1C1C1A]">
                {{ __('All created tasks') }}
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-100">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-100">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @unless ($task)
        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-[#3E3E3A] dark:bg-[#161615]">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('No pending review tasks.') }}</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Create WhatsApp tasks first, then return here to process them one by one.') }}</p>
        </div>
    @else
        <div
            class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_360px]"
            x-data="{
                taskType: @js((string) $reviewTaskType),
                clientId: @js((string) ($selectedClientId ?? '')),
                productId: @js((string) ($selectedProductId ?? '')),
                supplierId: @js((string) ($selectedSupplierId ?? '')),
                quantity: @js((string) ($quantityValue ?? '')),
                price: @js((string) ($priceValue ?? '')),
                amount: @js((string) ($amountValue ?? '')),
                paymentAmountCandidates: @js($paymentAmountCandidates->pluck('value')->values()),
                get subtotal() {
                    return (Number(this.quantity || 0) * Number(this.price || 0)).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 });
                },
            }"
            x-effect="if (taskType === @js(\App\Models\WhatsAppTask::TYPE_PAYMENT) && !amount && paymentAmountCandidates.length) { amount = paymentAmountCandidates[0]; }"
        >
            <section class="space-y-5">
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-[#3E3E3A] dark:bg-[#161615]">
                    <div class="border-b border-gray-200 px-4 py-4 dark:border-[#3E3E3A] sm:px-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                    #{{ $task->id }} {{ __($task->taskTypeLabel()) }}
                                </div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ __('Task date') }}: {{ $task->task_date?->format('Y-m-d') ?: '-' }}
                                </div>
                            </div>
                            <span class="inline-flex w-fit rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-900 dark:text-amber-100">
                                {{ __($task->status) }}
                            </span>
                        </div>
                        @if ($task->notes)
                            <p class="mt-3 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $task->notes }}</p>
                        @endif
                    </div>

                    <div class="space-y-3 p-4 sm:p-5">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('WhatsApp messages') }}</h3>
                        @foreach ($task->messages as $message)
                            <article class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-[#3E3E3A] dark:bg-[#10100f]">
                                <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                    <span>#{{ $message->id }}</span>
                                    <span>{{ $message->message_at->format('Y-m-d H:i') }}</span>
                                    <span>{{ $message->sender ?: __('Unknown sender') }}</span>
                                </div>
                                <p class="mt-2 whitespace-pre-line break-words text-sm leading-6 text-gray-800 dark:text-gray-200">
                                    {{ $message->body ?: $message->attachment_filename ?: '-' }}
                                </p>
                                @if ($message->attachmentUrl())
                                    <img src="{{ $message->attachmentUrl() }}" alt="{{ $message->attachment_filename }}" class="mt-3 max-h-72 w-full rounded-lg object-contain sm:w-auto">
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.whatsapp-tasks.review.preview', $task) }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-[#3E3E3A] dark:bg-[#161615] sm:p-5">
                    @csrf
                    <div class="mb-4">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Extracted data') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Edit the values, then preview the record before it is created.') }}</p>
                    </div>

                    <div class="mb-4">
                        <label for="task_type" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Task type') }}</label>
                        <select id="task_type" name="task_type" x-model="taskType" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white sm:text-sm">
                            @foreach ($reviewableTaskTypes as $taskType)
                                <option value="{{ $taskType }}" @selected((string) $reviewTaskType === (string) $taskType)>{{ __(\App\Models\WhatsAppTask::TYPE_LABELS[$taskType] ?? $taskType) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-cloak x-show="taskType === @js(\App\Models\WhatsAppTask::TYPE_GOODS_PIECES)" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="record_date" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Date') }}</label>
                                <input id="record_date" name="record_date" value="{{ $dateValue }}" inputmode="numeric" :disabled="taskType !== @js(\App\Models\WhatsAppTask::TYPE_GOODS_PIECES)" required class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white sm:text-sm" placeholder="dd/mm/yyyy">
                            </div>

                            <div>
                                <label for="client_id" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Client') }}</label>
                                <select id="client_id" name="client_id" x-model="clientId" :disabled="taskType !== @js(\App\Models\WhatsAppTask::TYPE_GOODS_PIECES)" required class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white sm:text-sm">
                                    <option value="">{{ __('Select a client') }}</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}" @selected((string) $selectedClientId === (string) $client->id)>{{ $client->name }}</option>
                                    @endforeach
                                </select>
                                @if ($clientCandidates->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($clientCandidates as $candidate)
                                            @php $candidateValue = (string) ($candidate['id'] ?? $candidate['value']); @endphp
                                            <button
                                                type="button"
                                                data-review-candidate="clientId"
                                                @click="clientId = @js($candidateValue)"
                                                :class="clientId === @js($candidateValue) ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-500 dark:bg-indigo-950 dark:text-indigo-100' : 'border-transparent bg-gray-100 text-gray-700 dark:bg-[#2A2A28] dark:text-gray-300'"
                                                class="rounded-full border px-2.5 py-1 text-left text-xs transition hover:border-indigo-400 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:hover:text-indigo-200"
                                            >
                                                {{ $candidate['label'] ?? $candidate['value'] }}
                                                @isset($candidate['score'])
                                                    {{ number_format($candidate['score'], 0) }}%
                                                @endisset
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div>
                                <label for="product_id" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Product') }}</label>
                                <select id="product_id" name="product_id" x-model="productId" :disabled="taskType !== @js(\App\Models\WhatsAppTask::TYPE_GOODS_PIECES)" required class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white sm:text-sm">
                                    <option value="">{{ __('Select a product') }}</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" @selected((string) $selectedProductId === (string) $product->id)>{{ $product->name }}</option>
                                    @endforeach
                                </select>
                                @if ($productCandidates->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($productCandidates as $candidate)
                                            @php $candidateValue = (string) ($candidate['id'] ?? $candidate['value']); @endphp
                                            <button
                                                type="button"
                                                data-review-candidate="productId"
                                                @click="productId = @js($candidateValue)"
                                                :class="productId === @js($candidateValue) ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-500 dark:bg-indigo-950 dark:text-indigo-100' : 'border-transparent bg-gray-100 text-gray-700 dark:bg-[#2A2A28] dark:text-gray-300'"
                                                class="rounded-full border px-2.5 py-1 text-left text-xs transition hover:border-indigo-400 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:hover:text-indigo-200"
                                            >
                                                {{ $candidate['label'] ?? $candidate['value'] }}
                                                @isset($candidate['score'])
                                                    {{ number_format($candidate['score'], 0) }}%
                                                @endisset
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div>
                                <label for="supplier_id" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Supplier car') }}</label>
                                <select id="supplier_id" name="supplier_id" x-model="supplierId" :disabled="taskType !== @js(\App\Models\WhatsAppTask::TYPE_GOODS_PIECES)" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white sm:text-sm">
                                    <option value="">{{ __('None') }}</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" @selected((string) $selectedSupplierId === (string) $supplier->id)>{{ $supplier->car_number }}{{ $supplier->car_color ? ' (' . $supplier->car_color . ')' : '' }}</option>
                                    @endforeach
                                </select>
                                @if ($supplierCandidates->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($supplierCandidates as $candidate)
                                            @php $candidateValue = (string) ($candidate['id'] ?? $candidate['value']); @endphp
                                            <button
                                                type="button"
                                                data-review-candidate="supplierId"
                                                @click="supplierId = @js($candidateValue)"
                                                :class="supplierId === @js($candidateValue) ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-500 dark:bg-indigo-950 dark:text-indigo-100' : 'border-transparent bg-gray-100 text-gray-700 dark:bg-[#2A2A28] dark:text-gray-300'"
                                                class="rounded-full border px-2.5 py-1 text-left text-xs transition hover:border-indigo-400 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:hover:text-indigo-200"
                                            >
                                                {{ $candidate['label'] ?? $candidate['value'] }}
                                                @isset($candidate['score'])
                                                    {{ number_format($candidate['score'], 0) }}%
                                                @endisset
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div>
                                <label for="quantity" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Quantity') }}</label>
                                <input id="quantity" name="quantity" type="number" step="0.001" min="0" x-model="quantity" :disabled="taskType !== @js(\App\Models\WhatsAppTask::TYPE_GOODS_PIECES)" required class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white sm:text-sm">
                                @if ($quantityCandidates->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($quantityCandidates as $candidate)
                                            @php $candidateValue = (string) ($candidate['id'] ?? $candidate['value']); @endphp
                                            <button
                                                type="button"
                                                data-review-candidate="quantity"
                                                @click="quantity = @js($candidateValue)"
                                                :class="quantity === @js($candidateValue) ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-500 dark:bg-indigo-950 dark:text-indigo-100' : 'border-transparent bg-gray-100 text-gray-700 dark:bg-[#2A2A28] dark:text-gray-300'"
                                                class="rounded-full border px-2.5 py-1 text-left text-xs transition hover:border-indigo-400 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:hover:text-indigo-200"
                                            >
                                                {{ $candidate['label'] ?? $candidate['value'] }}
                                                @isset($candidate['score'])
                                                    {{ number_format($candidate['score'], 0) }}%
                                                @endisset
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div>
                                <label for="price" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Price') }}</label>
                                <input id="price" name="price" type="number" step="0.01" min="0" x-model="price" :disabled="taskType !== @js(\App\Models\WhatsAppTask::TYPE_GOODS_PIECES)" required class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white sm:text-sm">
                                @if ($priceCandidates->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($priceCandidates as $candidate)
                                            @php $candidateValue = (string) ($candidate['id'] ?? $candidate['value']); @endphp
                                            <button
                                                type="button"
                                                data-review-candidate="price"
                                                @click="price = @js($candidateValue)"
                                                :class="price === @js($candidateValue) ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-500 dark:bg-indigo-950 dark:text-indigo-100' : 'border-transparent bg-gray-100 text-gray-700 dark:bg-[#2A2A28] dark:text-gray-300'"
                                                class="rounded-full border px-2.5 py-1 text-left text-xs transition hover:border-indigo-400 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:hover:text-indigo-200"
                                            >
                                                {{ $candidate['label'] ?? $candidate['value'] }}
                                                @isset($candidate['score'])
                                                    {{ number_format($candidate['score'], 0) }}%
                                                @endisset
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="mt-4 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:bg-[#10100f] dark:text-gray-300">
                            {{ __('Subtotal') }}: <span class="font-semibold" x-text="subtotal"></span>
                        </div>
                    </div>

                    <div x-cloak x-show="taskType === @js(\App\Models\WhatsAppTask::TYPE_PAYMENT)" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="record_date" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Date') }}</label>
                                <input id="record_date" name="record_date" value="{{ $dateValue }}" inputmode="numeric" :disabled="taskType !== @js(\App\Models\WhatsAppTask::TYPE_PAYMENT)" required class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white sm:text-sm" placeholder="dd/mm/yyyy">
                            </div>

                            <div>
                                <label for="client_id" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Client') }}</label>
                                <select id="client_id" name="client_id" x-model="clientId" :disabled="taskType !== @js(\App\Models\WhatsAppTask::TYPE_PAYMENT)" required class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white sm:text-sm">
                                    <option value="">{{ __('Select a client') }}</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}" @selected((string) $selectedClientId === (string) $client->id)>{{ $client->name }}</option>
                                    @endforeach
                                </select>
                                @if ($clientCandidates->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($clientCandidates as $candidate)
                                            @php $candidateValue = (string) ($candidate['id'] ?? $candidate['value']); @endphp
                                            <button
                                                type="button"
                                                data-review-candidate="clientId"
                                                @click="clientId = @js($candidateValue)"
                                                :class="clientId === @js($candidateValue) ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-500 dark:bg-indigo-950 dark:text-indigo-100' : 'border-transparent bg-gray-100 text-gray-700 dark:bg-[#2A2A28] dark:text-gray-300'"
                                                class="rounded-full border px-2.5 py-1 text-left text-xs transition hover:border-indigo-400 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:hover:text-indigo-200"
                                            >
                                                {{ $candidate['label'] ?? $candidate['value'] }}
                                                @isset($candidate['score'])
                                                    {{ number_format($candidate['score'], 0) }}%
                                                @endisset
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="sm:col-span-2">
                                <label for="amount" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Amount') }}</label>
                                <input id="amount" name="amount" type="number" step="0.01" min="0.01" x-model="amount" :disabled="taskType !== @js(\App\Models\WhatsAppTask::TYPE_PAYMENT)" required class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white sm:text-sm">
                                @if ($amountCandidates->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($amountCandidates as $candidate)
                                            @php $candidateValue = (string) ($candidate['id'] ?? $candidate['value']); @endphp
                                            <button
                                                type="button"
                                                data-review-candidate="amount"
                                                @click="amount = @js($candidateValue)"
                                                :class="amount === @js($candidateValue) ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-500 dark:bg-indigo-950 dark:text-indigo-100' : 'border-transparent bg-gray-100 text-gray-700 dark:bg-[#2A2A28] dark:text-gray-300'"
                                                class="rounded-full border px-2.5 py-1 text-left text-xs transition hover:border-indigo-400 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:hover:text-indigo-200"
                                            >
                                                {{ $candidate['label'] ?? $candidate['value'] }}
                                                @isset($candidate['score'])
                                                    {{ number_format($candidate['score'], 0) }}%
                                                @endisset
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end">
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-indigo-500/20 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto">
                            {{ __('Preview record') }}
                        </button>
                    </div>
                </form>
            </section>

            <aside class="space-y-5">
                @if ($preview)
                    <div class="rounded-xl border border-indigo-200 bg-white p-4 shadow-sm dark:border-indigo-900 dark:bg-[#161615]">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $preview['title'] }}</h3>
                        <dl class="mt-4 space-y-3 text-sm">
                            @foreach ($preview as $key => $value)
                                @continue(in_array($key, ['kind', 'title'], true))
                                @if ($key === 'source_message')
                                    <div class="border-b border-gray-100 pb-2 last:border-0 dark:border-[#2A2A28]">
                                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Original message') }}</dt>
                                        <dd class="mt-1 whitespace-pre-line rounded-lg bg-gray-50 px-3 py-2 font-medium text-gray-900 dark:bg-[#10100f] dark:text-gray-100">
                                            {{ $value }}
                                        </dd>
                                    </div>
                                @else
                                    <div class="flex justify-between gap-4 border-b border-gray-100 pb-2 last:border-0 dark:border-[#2A2A28]">
                                        <dt class="text-gray-500 dark:text-gray-400">{{ __(str_replace('_', ' ', ucfirst($key))) }}</dt>
                                        <dd class="text-right font-medium text-gray-900 dark:text-white">
                                            @if (is_numeric($value))
                                                {{ number_format((float) $value, 2) }}
                                            @else
                                                {{ $value }}
                                            @endif
                                        </dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>

                        <form method="POST" action="{{ route('admin.whatsapp-tasks.review.confirm', $task) }}" class="mt-5">
                            @csrf
                            @foreach ($formValues as $name => $value)
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endforeach
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                {{ __('Confirm and create') }}
                            </button>
                        </form>
                    </div>
                @endif

            </aside>
        </div>
    @endunless
</div>
@endsection
