<div class="mt-2 space-y-2 rounded-lg border border-gray-300 p-3 dark:border-[#3E3E3A]">
    <p x-show="catalogEntry?.error" x-text="catalogEntry?.error" role="alert" class="text-red-700 dark:text-red-300"></p>
    @php($client = $field !== 'supplier_id')
    <label class="block">
        {{ $client ? __('Name') : __('Car Number') }}
        <input type="text" class="{{ $control }}" x-ref="catalogEntryInput"
            :value="catalogEntry?.values.{{ $client ? 'name' : 'car_number' }} ?? ''"
            @input="catalogEntry.values.{{ $client ? 'name' : 'car_number' }} = $el.value"
            @keydown.enter.prevent="createCatalogEntry()" :disabled="catalogBusy">
    </label>
    <label class="block">
        {{ $client ? __('Phone') : __('Car Color') }}
        <input type="{{ $client ? 'tel' : 'text' }}" class="{{ $control }}"
            :value="catalogEntry?.values.{{ $client ? 'phone' : 'car_color' }} ?? ''"
            @input="catalogEntry.values.{{ $client ? 'phone' : 'car_color' }} = $el.value"
            @keydown.enter.prevent="createCatalogEntry()" :disabled="catalogBusy">
    </label>
    <div class="flex flex-wrap gap-2">
        <button type="button" class="{{ $button }}" @click="createCatalogEntry()" :disabled="catalogBusy">
            {{ $client ? __('Save Client') : __('Save Supplier') }}
        </button>
        <button type="button" class="{{ $button }}" @click="closeCatalogEntry()" :disabled="catalogBusy">{{ __('Cancel') }}</button>
    </div>
</div>
