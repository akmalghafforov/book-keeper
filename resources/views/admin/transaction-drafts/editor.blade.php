<form class="mt-4 space-y-3 border-t border-gray-200 pt-4 dark:border-[#3E3E3A]" @submit.prevent="editorOpen && !dismissalOpen && act('update')">
    <fieldset x-bind:disabled="busy" class="space-y-3">
        <div x-show="editorOpen" class="space-y-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($draftFields as $field => $definition)
                    @php([$label, $choices, $placeholder] = array_pad($definition, 3, null))
                    <label x-show="visible('{{ $field }}')" class="text-sm font-medium">
                        {{ $label }}
                        @if ($choices !== null)
                            <select name="{{ $field }}" class="{{ $control }}" :value="fields.{{ $field }} ?? ''"
                                @change="setField('{{ $field }}', $event.target.value); check()"
                                :aria-invalid="errors('{{ $field }}').length > 0">
                                <option value=""></option>
                                <template x-for="option in options('{{ $field }}', draftChoices.{{ $draft->kind }}.{{ $field }})" :key="option.id">
                                    <option :value="option.id" :selected="String(fields.{{ $field }} ?? '') === String(option.id)" x-text="option.name"></option>
                                </template>
                            </select>
                        @elseif ($field === 'notes')
                            <textarea name="{{ $field }}" class="{{ $control }}" :value="fields.notes ?? ''" @input="setField('notes', $event.target.value)" @input.debounce.350ms="check()" :aria-invalid="errors('notes').length > 0"></textarea>
                        @else
                            <input name="{{ $field }}" type="text" class="{{ $control }}" placeholder="{{ $placeholder }}"
                                :value="fields.{{ $field }} ?? ''" @input="setField('{{ $field }}', $event.target.value)" @input.debounce.350ms="check()"
                                :aria-invalid="errors('{{ $field }}').length > 0">
                        @endif
                        <span class="block text-red-700 dark:text-red-300" x-text="errors('{{ $field }}').join(' ')"></span>
                    </label>
                @endforeach
            </div>

            <template x-if="preview.payment">
                <p class="text-sm">{{ __('Payment') }} <span x-text="preview.payment.amount + ' ' + preview.payment.currency + ' × ' + preview.payment.exchange_rate + ' = ' + preview.payment.amount_tjs + ' TJS'"></span></p>
            </template>
            <div x-show="preview.effects.length" class="text-sm">
                <h4 class="font-medium">{{ __('Projected ledger entries') }}</h4>
                <template x-for="(effect, index) in preview.effects" :key="index">
                    <p x-text="({ charge: @js(__('charge')), credit_note: @js(__('credit_note')) })[effect.type] + ' · ' + (effect.kind === 'provider_ledger' ? @js(__('Provider')) : @js(__('Client'))) + ' #' + effect.party_id + ' · ' + effect.amount + ' TJS'"></p>
                </template>
            </div>

            <div x-show="preview.matches.length" class="rounded-lg border border-amber-400 p-3 text-sm">
                <h4 class="font-semibold">{{ __('Potential duplicates') }}</h4>
                <ul class="my-2 list-disc pl-5">
                    <template x-for="(match, index) in preview.matches" :key="index">
                        <li>
                            <a x-show="match.url" :href="match.url" x-text="match.kind.replaceAll('_', ' ') + ' #' + match.id" class="underline"></a>
                            <span x-show="!match.url" x-text="@js(__('Pending draft')) + ' ' + match.id"></span>
                            <span x-text="' · ' + match.date + ' · ' + match.reasons.join(', ')"></span>
                        </li>
                    </template>
                </ul>
                <label x-show="preview.duplicate_confirmation && !dismissalOpen"><input type="checkbox" x-model="confirmed"> {{ __('I confirm this is a separate transaction.') }}</label>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="submit" class="{{ $button }}">{{ __('Save draft') }}</button>
                <button type="button" x-show="approvalRetry === 'approve'" class="{{ $button }} bg-indigo-600 text-white" @click="act('approve')">{{ __('Approve saved values') }}</button>
                <button type="button" class="{{ $button }} bg-indigo-600 text-white" @click="act('editApprove')" :disabled="!Object.keys(changes).length">{{ __('Edit and approve') }}</button>
            </div>
        </div>

        <div x-show="dismissalOpen" class="text-sm">
            <div class="mt-2 flex flex-wrap items-start gap-2">
                <div>
                    <select aria-label="{{ __('Dismissal reason') }}" class="{{ $control }}" x-model="reason" @change="postedId = ''; if (reason === 'already_recorded') check(true)">
                        <option value="">{{ __('Dismissal reason') }}</option>
                        <option value="already_recorded">{{ __('Already recorded') }}</option>
                        <option value="incorrect_suggestion">{{ __('Incorrect suggestion') }}</option>
                        <option value="outside_scope">{{ __('Outside scope') }}</option>
                    </select>
                    <span class="text-red-700 dark:text-red-300" x-text="errors('reason').join(' ')"></span>
                </div>
                <div x-show="reason === 'already_recorded'">
                    <select aria-label="{{ __('Posted record') }}" class="{{ $control }}" x-model="postedId">
                        <option value="">{{ __('Select a matched posted record') }}</option>
                        <template x-for="match in postedMatches" :key="match.id">
                            <option :value="match.id" x-text="'#' + match.id + ' · ' + match.date"></option>
                        </template>
                    </select>
                    <span class="block text-red-700 dark:text-red-300" x-text="errors('posted_record_id').join(' ')"></span>
                    <p x-show="previewSaved && !postedMatches.length" class="mt-1 text-gray-600 dark:text-gray-300">{{ __('No matching posted records.') }}</p>
                </div>
                <button type="button" class="{{ $button }}" @click="act('dismiss')" :disabled="!reason || (reason === 'already_recorded' && !postedMatches.some(match => String(match.id) === String(postedId)))">{{ __('Dismiss draft') }}</button>
            </div>
        </div>
    </fieldset>

    <button type="button" x-show="editorOpen" class="{{ $button }}" @click="cancelEditor()" :disabled="busy">{{ __('Cancel') }}</button>
</form>
