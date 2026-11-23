@if ($distribution)
    <p x-cloak x-show="bulkWeightWarning" role="status"
        class="mb-2 rounded-lg border border-amber-400 px-3 py-2 text-sm">
        {{ __('Bulk weight exceeds 40 tonnes. Verify the ticket and document any exception before approving.') }}
    </p>
@endif
<div x-show="!closed" class="flex flex-wrap gap-2">
    <button type="button" class="{{ $button }} bg-indigo-600 text-white" @click="act('approve')" :disabled="busy">{{ __('Approve') }}</button>
    <button type="button" class="{{ $button }}" @click="openMissing($el)" :disabled="busy">{{ __('Add missing fields') }}</button>
    <button type="button" class="{{ $button }}" @click="toggleEditor()" :aria-expanded="editorOpen" :disabled="busy">{{ __('Quick edit') }}</button>
    <button type="button" class="{{ $button }}" @click="toggleDismissal()" :aria-expanded="dismissalOpen" :disabled="busy">{{ __('Dismiss') }}</button>
</div>
<p x-cloak x-show="!closed && message" class="mt-3 text-sm" x-text="message" role="status"></p>
<template x-if="editorReady">
    <div x-show="!closed && (editorOpen || dismissalOpen)">
        @include('admin.transaction-drafts.editor')
    </div>
</template>
<template x-if="closed">
    <p role="status" class="text-sm">
        <span x-text="closed.status === 'approved' ? @js(__('Draft approved.')) : @js(__('Draft dismissed.'))"></span>
        <a x-show="postedRecord?.available" :href="postedRecord?.url" class="underline">{{ __('Open posted record') }}</a>
    </p>
</template>

@include('admin.transaction-drafts.missing-fields')
