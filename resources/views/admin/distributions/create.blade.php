@extends('layouts.admin')

@section('title', 'Add Distribution')
@section('header_title', 'Add Distribution')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
<style>
    .flatpickr-input {
        background-color: transparent !important;
    }
</style>
@endpush

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="{ 
    quantity: {{ old('quantity', 0) }}, 
    price: {{ old('price', 0) }},
    get subtotal() {
        return (this.quantity * this.price).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
}">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Add Distribution</h2>
        <a href="{{ route('admin.distributions.index') }}" class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors duration-200">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to list
        </a>
    </div>

    <form action="{{ route('admin.distributions.store') }}" method="POST">
        @csrf
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: Distribution Info -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A]">
                    <div class="p-6 space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white border-b border-gray-100 dark:border-[#3E3E3A] pb-2">Distribution Details</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div x-data="{
                                init() {
                                    flatpickr($refs.datepicker, {
                                        dateFormat: 'd/m/Y',
                                        defaultDate: '{{ old('distribution_date', date('d/m/Y')) }}',
                                        allowInput: true,
                                    });
                                }
                            }">
                                <label for="distribution_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                    <input type="text" name="distribution_date" id="distribution_date" x-ref="datepicker" required
                                        class="block w-full pl-10 pr-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200"
                                        placeholder="dd/mm/yyyy">
                                </div>
                                @error('distribution_date')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="supply_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Supply (Arrival)</label>
                                <select name="supply_id" id="supply_id"
                                    class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200">
                                    <option value="">None (Direct Distribution)</option>
                                    @foreach($supplies as $supply)
                                        <option value="{{ $supply->id }}" {{ old('supply_id') == $supply->id ? 'selected' : '' }}>
                                            {{ $supply->car_number }} ({{ $supply->car_color }}) - {{ $supply->delivery_date->format('d/m/Y') }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('supply_id')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="client_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Client</label>
                                <select name="client_id" id="client_id" required
                                    class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200">
                                    <option value="">Select Client</option>
                                    @foreach($clients as $client)
                                        <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>
                                            {{ $client->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('client_id')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="product_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Product</label>
                                <select name="product_id" id="product_id" required
                                    class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200">
                                    <option value="">Select Product</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                            {{ $product->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('product_id')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A]">
                    <div class="p-6 space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white border-b border-gray-100 dark:border-[#3E3E3A] pb-2">Pricing & Quantity</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="quantity_unit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Unit</label>
                                <select name="quantity_unit" id="quantity_unit" required
                                    class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200">
                                    <option value="per_ton" {{ old('quantity_unit') == 'per_ton' ? 'selected' : '' }}>Per Ton</option>
                                    <option value="per_bag" {{ old('quantity_unit') == 'per_bag' ? 'selected' : '' }}>Per Bag</option>
                                    <option value="per_piece" {{ old('quantity_unit') == 'per_piece' ? 'selected' : '' }}>Per Piece</option>
                                </select>
                                @error('quantity_unit')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="quantity" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Quantity</label>
                                <input type="number" step="0.001" name="quantity" id="quantity" x-model.number="quantity" required
                                    class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200"
                                    placeholder="0.000">
                                @error('quantity')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Price</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">$</span>
                                    </div>
                                    <input type="number" step="0.01" name="price" id="price" x-model.number="price" required
                                        class="block w-full pl-7 pr-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200"
                                        placeholder="0.00">
                                </div>
                                @error('price')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Summary & Submit -->
            <div class="space-y-6">
                <div class="bg-indigo-600 dark:bg-indigo-900 overflow-hidden shadow-lg sm:rounded-xl text-white">
                    <div class="p-6 space-y-4">
                        <h3 class="text-lg font-bold">Summary</h3>
                        
                        <div class="flex justify-between items-center text-indigo-100">
                            <span>Quantity:</span>
                            <span class="font-medium" x-text="quantity"></span>
                        </div>
                        <div class="flex justify-between items-center text-indigo-100">
                            <span>Price:</span>
                            <span class="font-medium">$<span x-text="price.toFixed(2)"></span></span>
                        </div>
                        
                        <div class="pt-4 border-t border-indigo-500 flex justify-between items-end">
                            <span class="text-sm uppercase tracking-wider font-semibold">Total Subtotal</span>
                            <span class="text-3xl font-bold">$<span x-text="subtotal"></span></span>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A]">
                    <div class="p-6 space-y-3">
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-3 bg-indigo-600 border border-transparent rounded-lg font-bold text-sm text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-md shadow-indigo-500/20">
                            Save Distribution
                        </button>
                        <a href="{{ route('admin.distributions.index') }}" class="w-full inline-flex justify-center items-center px-4 py-2 bg-gray-100 dark:bg-[#2A2A28] border border-transparent rounded-lg font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-[#3E3E3A] focus:outline-none focus:ring-2 focus:ring-gray-300 dark:focus:ring-gray-700 transition ease-in-out duration-150">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
@endpush
