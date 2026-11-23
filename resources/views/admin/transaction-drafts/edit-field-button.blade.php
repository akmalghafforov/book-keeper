<button type="button" x-cloak x-show="!closed && canCorrect(@js($editField))" :disabled="busy"
    @click="openMissing($el, @js($editField))"
    aria-label="{{ __('Edit :field', ['field' => $editLabel]) }}" title="{{ __('Edit :field', ['field' => $editLabel]) }}"
    class="inline-flex shrink-0 items-center rounded p-1 text-gray-500 hover:bg-gray-200 hover:text-indigo-700 focus-visible:outline-2 focus-visible:outline-indigo-500 disabled:opacity-50 dark:text-gray-400 dark:hover:bg-[#292927] dark:hover:text-indigo-300">
    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="m16 3 5 5M4 15 16.5 2.5a2.1 2.1 0 0 1 3 0l2 2a2.1 2.1 0 0 1 0 3L9 20l-6 1z"/>
    </svg>
</button>
