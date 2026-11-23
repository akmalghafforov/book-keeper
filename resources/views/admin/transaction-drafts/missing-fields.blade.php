@php
    $locators = collect([$draft->source, ...($draft->source['additional_locators'] ?? [])])
        ->sortBy(fn ($locator) => isset($locator['sent_at']) ? Illuminate\Support\Facades\Date::parse($locator['sent_at'])->format('U.u') : PHP_INT_MIN);
@endphp

<dl x-show="filledDetails.length" class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
    <template x-for="field in filledDetails" :key="field">
        <div><dt class="inline font-medium" x-text="fieldLabels[field]"></dt> <dd class="inline whitespace-pre-wrap wrap-anywhere" x-text="missingValue(field, saved[field])"></dd></div>
    </template>
</dl>

<dialog x-ref="missingDialog" aria-labelledby="missing-title-{{ $draft->id }}"
    class="fixed inset-0 m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-6xl overflow-auto rounded-xl border border-gray-300 bg-white p-4 text-gray-900 backdrop:bg-black/60 sm:p-6 dark:border-[#3E3E3A] dark:bg-[#181817] dark:text-white"
    @cancel.prevent="closeMissing()" @close="missingTrigger?.focus()">
    <h2 id="missing-title-{{ $draft->id }}" tabindex="-1" autofocus class="mb-4 text-xl font-semibold">{{ __('Add missing fields') }}
        <span x-show="correctionField" x-text="correctionField ? ' · ' + fieldLabels[correctionField] : ''"></span>
    </h2>
    <template x-if="missingOpen">
        <div class="grid gap-6 md:grid-cols-2">
            <section class="min-w-0 space-y-3" aria-label="{{ __('WhatsApp photos') }}">
                <template x-if="photos.length">
                    <div class="space-y-2">
                        <div class="w-fit max-w-full overflow-hidden rounded-lg"
                            @pointermove="magnify($event)" @pointerleave="$el.firstElementChild.style.transform = ''">
                            <img :src="photos[photoIndex]" alt="{{ __('WhatsApp photo') }}" class="block max-h-[55dvh] max-w-full">
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <label x-show="photos.length > 1">{{ __('Photo') }}
                                <select aria-label="{{ __('Photo') }}" x-model.number="photoIndex" class="{{ $control }}">
                                    <template x-for="(_, position) in photos" :key="position">
                                        <option :value="position" x-text="position + 1"></option>
                                    </template>
                                </select>
                            </label>
                            <a :href="photos[photoIndex]" target="_blank" rel="noopener noreferrer" class="underline">{{ __('Open full-size image') }}</a>
                        </div>
                    </div>
                </template>
                <p x-show="photoStatus === 'loading'" role="status">{{ __('Loading WhatsApp images…') }}</p>
                <div x-show="photoStatus === 'failed'" role="status">
                    <p>{{ __('Could not load WhatsApp images.') }}</p>
                    <button type="button" class="{{ $button }}" @click="refreshPhotos()">{{ __('Retry') }}</button>
                </div>
                <p x-show="photoStatus === 'ready' && !photos.length">{{ __('No WhatsApp image available.') }}</p>
            </section>

            <div class="min-w-0 space-y-5">
                <section class="space-y-2" aria-label="{{ __('Original WhatsApp messages') }}">
                    <h3 class="font-semibold">{{ __('Original WhatsApp messages') }}</h3>
                    @foreach ($locators as $locator)
                        <article class="rounded-lg border border-gray-300 p-2 text-sm dark:border-[#3E3E3A]">
                            <time datetime="{{ $locator['sent_at'] ?? '' }}">{{ $locator['sent_at'] ?? '' }}</time>
                            <p class="whitespace-pre-wrap wrap-anywhere">{{ $locator['message_text'] ?? __('No message text available.') }}</p>
                        </article>
                    @endforeach
                </section>

                <form class="space-y-3" @submit.prevent="saveMissing()">
                    <div x-show="missingMessage || Object.keys(missingIssues).length" role="alert" class="text-sm text-red-700 dark:text-red-300">
                        <p x-text="missingMessage"></p>
                        <template x-for="(messages, key) in missingIssues" :key="key">
                            <p x-text="[fieldLabels[key.replace(/^fields\./, '')], ...messages].filter(Boolean).join(' · ')"></p>
                        </template>
                    </div>

                    <template x-if="missingStale">
                        <div class="rounded-lg border border-amber-400 p-3 text-sm">
                            <p>{{ __('Review the latest saved values before retrying.') }}</p>
                            <table class="mt-2 w-full text-start">
                                <thead><tr><th>{{ __('Field') }}</th><th>{{ __('Latest saved') }}</th><th>{{ __('Your entry') }}</th></tr></thead>
                                <tbody>
                                    <template x-for="(label, field) in fieldLabels" :key="field">
                                        <tr x-show="!isMissing(missingStale?.data.current_fields[field]) || !isMissing(missingEntries[field])">
                                            <th scope="row" class="text-start" x-text="label"></th>
                                            <td class="wrap-anywhere" x-text="missingValue(field, missingStale?.data.current_fields[field])"></td>
                                            <td class="wrap-anywhere" x-text="missingValue(field, missingEntries[field])"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                            <button type="button" class="{{ $button }} mt-2" @click="reviewMissingRevision()">{{ __('Use latest revision') }}</button>
                        </div>
                    </template>

                    <fieldset class="space-y-3" :disabled="busy || !!missingStale || missingUnavailable">
                        <legend class="font-semibold">{{ __('Draft fields') }}</legend>
                        @foreach ($draftFields as $field => [$label])
                            <div x-show="showMissingField(@js($field))" class="text-sm">
                                <div class="flex items-baseline justify-between gap-2">
                                    <label class="font-medium" for="missing-{{ $draft->id }}-{{ $field }}">{{ $label }}</label>
                                    <button type="button" x-show="!correctionField && canCorrect(@js($field)) && !Object.keys(missingEntries).length && !catalogEntry"
                                        class="text-xs text-indigo-700 hover:underline dark:text-indigo-400"
                                        @click="correctionField = @js($field)">{{ __('Edit :field', ['field' => $label]) }}</button>
                                    @if (in_array($field, ['client_id', 'credit_client_id', 'supplier_id']))
                                        <button type="button" x-show="canFill(@js($field))" @click="openCatalogEntry(@js($field), $el)"
                                            :aria-expanded="catalogEntry?.field === @js($field)" :disabled="!!catalogEntry"
                                            class="text-xs text-indigo-700 hover:underline disabled:opacity-50 dark:text-indigo-400">
                                            + {{ $field === 'supplier_id' ? __('New Car Number') : __('New Client') }}
                                        </button>
                                    @endif
                                </div>
                                <div x-show="!isMissing(original.{{ $field }})">
                                    {{ __('Original AI value') }} · <span class="whitespace-pre-wrap wrap-anywhere" x-text="missingValue(@js($field), original.{{ $field }})"></span>
                                </div>
                                <div x-show="!isMissing(saved.{{ $field }}) && String(saved.{{ $field }}) !== String(original.{{ $field }})">
                                    {{ __('Saved value') }} · <span class="whitespace-pre-wrap wrap-anywhere" x-text="missingValue(@js($field), saved.{{ $field }})"></span>
                                </div>
                                <template x-if="canFill(@js($field))">
                                    @php($inputId = 'missing-'.$draft->id.'-'.$field)
                                    @if (isset($draftFields[$field][1]))
                                        <select id="{{ $inputId }}" class="{{ $control }}" :aria-invalid="missingErrors(@js($field)).length > 0"
                                            x-data="missingSelect(@js($field))" :disabled="busy || !!missingStale || missingUnavailable">
                                            <option value=""></option>
                                            <template x-for="option in missingOptions(@js($field))" :key="option.id">
                                                <option :value="option.id" x-text="option.name"></option>
                                            </template>
                                        </select>
                                    @elseif ($field === 'notes')
                                        <textarea id="{{ $inputId }}" class="{{ $control }}" :value="missingInput('notes')"
                                            @input="setMissing('notes', $el.value)" :aria-invalid="missingErrors('notes').length > 0"></textarea>
                                    @elseif ($field === 'provider_received_at')
                                        <div class="relative draft-provider-date">
                                            <input id="{{ $inputId }}" class="{{ $control }} ps-10 focus:ring-2 focus:ring-indigo-500"
                                                type="datetime-local" placeholder="dd/mm/yyyy hh:mm" x-data="missingPicker(@js($field))"
                                                :aria-invalid="missingErrors(@js($field)).length > 0">
                                            <svg class="pointer-events-none absolute start-3 top-2.5 size-5 text-gray-400" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                    @else
                                        <input id="{{ $inputId }}" class="{{ $control }}"
                                            @if (in_array($field, ['distribution_date', 'transaction_date']))
                                                type="date" x-data="missingPicker(@js($field))"
                                            @elseif (in_array($field, ['amount', 'quantity', 'price', 'credit_client_price', 'provider_buy_price', 'exchange_rate']))
                                                type="text" inputmode="decimal" :value="formatDraftAmount(missingInput(@js($field))).display"
                                                @input="setMissingAmount(@js($field), $el)" :readonly="!correctionField && @js($field) === 'exchange_rate' && missingFields.currency === 'TJS'"
                                            @else
                                                type="text" :value="missingInput(@js($field))" @input="setMissing(@js($field), $el.value)"
                                            @endif
                                            :aria-invalid="missingErrors(@js($field)).length > 0">
                                    @endif
                                </template>
                                @if (in_array($field, ['client_id', 'credit_client_id', 'supplier_id']))
                                    <template x-if="catalogEntry?.field === @js($field)">
                                        @include('admin.transaction-drafts.catalog-entry')
                                    </template>
                                @endif
                                @if ($field === 'provider_buy_price')
                                    <p x-show="canFill('provider_buy_price')" class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ __("Defaults to the product's Buy Price. Override it for this distribution if needed.") }}
                                    </p>
                                @endif
                                <p class="text-red-700 dark:text-red-300" x-text="missingErrors(@js($field)).join(' ')"></p>
                            </div>
                        @endforeach
                    </fieldset>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="{{ $button }} bg-indigo-600 text-white" :disabled="busy || !!missingStale || missingUnavailable || !!catalogEntry">{{ __('Save draft') }}</button>
                        <button type="button" class="{{ $button }}" @click="closeMissing()" :disabled="busy || catalogBusy">{{ __('Cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</dialog>
