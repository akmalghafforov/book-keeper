<div x-show="type === 'payment'" x-cloak>
    <label for="payment_purpose" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Payment Purpose') }}</label>
    <select name="payment_purpose" id="payment_purpose" x-model="paymentPurpose"
        class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200">
        <option value="">{{ __('Select a payment purpose') }}</option>
        @foreach(\App\Enums\PaymentPurpose::cases() as $purpose)
            <option value="{{ $purpose->value }}" {{ $paymentPurpose === $purpose->value ? 'selected' : '' }}>{{ $purpose->label() }}</option>
        @endforeach
    </select>
    @error('payment_purpose')
        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>

<div x-show="type === 'payment' && paymentPurpose === 'on_behalf_of'" x-cloak>
    <label for="payer_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Payer Name') }}</label>
    <input type="text" name="payer_name" id="payer_name" value="{{ $payerName }}" :required="type === 'payment' && paymentPurpose === 'on_behalf_of'" :disabled="type !== 'payment' || paymentPurpose !== 'on_behalf_of'"
        class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-all duration-200">
    @error('payer_name')
        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
