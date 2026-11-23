@php
    $distribution = $draftKind === 'distribution';
    $party = $draftKind === 'provider_ledger' ? ['provider_id' => __('Provider')] : ['client_id' => __('Client')];
    $columns = $party + ($distribution ? [
        'shop_id' => __('Shop'),
        'product_id' => __('Product'),
        'quantity' => __('Quantity'),
        'price' => __('Price'),
        'total' => __('Total'),
        'supplier_id' => __('Car Number'),
        'freight' => __('Freight Price'),
    ] : [
        'type' => __('Operation'),
        'amount' => __('Amount'),
        'payment_method' => __('Method'),
        'description' => __('Description'),
    ]);
    $freightId = $card['freightId'];
    $showPaymentHead = $distribution || $rows->contains(fn ($draft) => $draft->id !== $freightId);
    $visibleColumns = $distribution
        ? array_diff_key($columns, array_filter(['shop_id' => !$card['showShop'], 'freight' => !$freightId]))
        : $columns;
    $renderedColumns = $pending ? $columns : $visibleColumns;
    $cellVisibility = ['shop_id' => 'showShop', 'freight' => 'freightId'];
    $cellClass = 'px-3 py-2 align-top';
@endphp

<div class="overflow-x-auto rounded-lg focus-visible:outline-2 focus-visible:outline-indigo-500" role="region" aria-label="{{ $heading }}" tabindex="0">
    <table class="w-max text-sm">
        <thead class="bg-gray-50 text-gray-700 dark:bg-[#20201e] dark:text-gray-200"
            @if (!$showPaymentHead) style="display: none" @endif
            @if ($pending && !$distribution) x-show="hasPaymentRows(@js($rows->pluck('id')->values()))" @endif>
            <tr>
                @foreach ($renderedColumns as $field => $label)
                    <th scope="col" class="{{ $cellClass }} whitespace-nowrap text-start font-semibold"
                        @if ($pending && isset($cellVisibility[$field])) x-show="{{ $cellVisibility[$field] }}" @endif
                        @if (!isset($visibleColumns[$field])) style="display: none" @endif>
                        {{ $label }}
                    </th>
                @endforeach
            </tr>
        </thead>
        @foreach ($rows as $draft)
            @php
                $folded = $draft->id === $freightId;
                $values = $card['values'][$draft->id];
                if ($pending) {
                    $draftFields = $fields[$draft->kind];
                    $config = [
                        'id' => $draft->id,
                        'kind' => $draft->kind,
                        'etag' => $draft->etag(),
                        'csrf' => csrf_token(),
                        'timezone' => config('app.timezone'),
                        'catalogUrls' => ['client' => route('admin.clients.store'), 'supplier' => route('admin.suppliers.store')],
                        'original' => (object) $draft->original_fields,
                        'fieldLabels' => array_map(fn ($definition) => $definition[0], $draftFields),
                        'columns' => array_keys($columns),
                        'urls' => collect(['show', 'preview', 'update', 'missing-fields', 'approve', 'dismiss'])->mapWithKeys(fn ($action) => [$action => route('admin.transaction-drafts.'.$action, $draft)]),
                        'messages' => [
                            'saved' => __('Draft saved.'),
                            'refreshed' => __('This draft changed while you were reviewing it. Check the refreshed values before trying again.'),
                            'checkFields' => __('Check the highlighted fields.'),
                            'failed' => __('The request could not be completed. You can retry.'),
                        ],
                    ];
                }
            @endphp
            <tbody class="border-b border-gray-200 last:border-b-0 dark:border-[#3E3E3A]" data-draft-id="{{ $draft->id }}"
                @if ($pending) x-data="draftReview(@js($config))" @endif>
                @if ($pending || !$folded)
                    <tr @if ($folded) style="display: none" @endif
                        @if ($pending && !$distribution) x-show="freightId !== id" @endif>
                        @foreach ($renderedColumns as $field => $label)
                            @php
                                $value = $field === 'freight' ? ($card['values'][$freightId]['amount'] ?? null) : $values[$field];
                                $editField = $field === 'description' ? 'notes' : $field;
                                $editable = $pending && isset($draftFields[$editField]);
                            @endphp
                            <td @class([$cellClass, 'max-w-80 whitespace-pre-wrap wrap-anywhere' => $field === 'description', 'whitespace-nowrap' => $field !== 'description'])
                                @if ($pending && isset($cellVisibility[$field])) x-show="{{ $cellVisibility[$field] }}" @endif
                                @if (!isset($visibleColumns[$field])) style="display: none" @endif
                                @if ($pending && !$editable) x-text="cellValue(@js($field))" @endif>@if ($pending && $field === 'product_id')
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span x-text="cellValue('product_id')">{{ filled($value) ? $value : '—' }}</span>
                                        @include('admin.transaction-drafts.edit-field-button', ['editLabel' => $label])
                                        <div x-cloak x-show="gradeFamilies.length" class="flex items-center gap-2">
                                            <span x-show="cementFamilies[id]" x-text="gradeFamily"></span>
                                            <select aria-label="{{ __('Cement family') }}" x-show="!cementFamilies[id]"
                                                x-model="gradeFamily" :disabled="gradeDisabled"
                                                class="rounded-lg border border-gray-300 bg-white px-2 py-1 text-sm disabled:opacity-50 dark:border-[#3E3E3A] dark:bg-[#0a0a0a]">
                                                <option value="">{{ __('Select cement family') }}</option>
                                                <template x-for="family in gradeFamilies" :key="family">
                                                    <option :value="family" x-text="family"></option>
                                                </template>
                                            </select>
                                            <div x-show="gradePair" class="flex gap-1">
                                                <button type="button" class="{{ $button }} !px-2 !py-1" @click="saveGrade('M400')" :disabled="gradeDisabled">M400</button>
                                                <button type="button" class="{{ $button }} !px-2 !py-1" @click="saveGrade('M500')" :disabled="gradeDisabled">M500</button>
                                            </div>
                                        </div>
                                    </div>
                                @elseif ($editable)
                                    <div class="flex items-baseline gap-1">
                                        <span x-text="cellValue(@js($field))">{{ filled($value) ? $value : '—' }}</span>
                                        @include('admin.transaction-drafts.edit-field-button', ['editLabel' => $label])
                                    </div>
                                @else{{ filled($value) ? $value : '—' }}@endif</td>
                        @endforeach
                    </tr>
                @endif
                <tr>
                    <td colspan="{{ count($visibleColumns) }}" @if ($pending && $distribution) :colspan="6 + Number(showShop) + Number(!!freightId)" @endif>
                        {{-- Keep the controls sticky without letting their width stretch the columns. --}}
                        <div class="sticky start-0 w-0">
                            <div class="w-[100cqw] px-3 pb-3 pt-1">
                                @unless ($distribution)
                                    <p class="mb-2 text-sm font-medium"
                                        @if (!$folded) style="display: none" @endif
                                        @if ($pending) x-show="freightId === id" @endif>
                                        {{ __('Freight Price') }}
                                        @if ($pending)
                                            @include('admin.transaction-drafts.edit-field-button', ['editField' => 'amount', 'editLabel' => __('Freight Price')])
                                        @endif
                                    </p>
                                @endunless
                                @if ($pending)
                                    @include('admin.transaction-drafts.controls')
                                @else
                                    @include('admin.transaction-drafts.history')
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        @endforeach
    </table>
</div>
