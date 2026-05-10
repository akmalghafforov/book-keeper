<div
    x-cloak
    x-show="rangeReportModalOpen"
    x-transition.opacity
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
>
    <div class="flex min-h-screen items-center justify-center px-4 py-6">
        <button
            type="button"
            class="fixed inset-0 bg-black/50"
            aria-label="{{ __('Close') }}"
            @click="rangeReportModalOpen = false"
        ></button>

        <div
            x-show="rangeReportModalOpen"
            x-transition
            @keydown.escape.window="rangeReportModalOpen = false"
            class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-[#161615] border border-gray-200 dark:border-[#3E3E3A]"
        >
            <form method="POST" :action="rangeReportAction" class="space-y-5">
                @csrf
                <input type="hidden" name="format" value="jpg">

                <div class="space-y-1">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('Date Range Report') }}</h3>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300" x-text="rangeReportClientName"></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Date Range') }}</label>
                    <div class="flex items-center space-x-2">
                        <input
                            type="text"
                            id="range_report_start_date"
                            name="start_date"
                            x-ref="rangeReportStartDate"
                            x-model="rangeReportStartDate"
                            required
                            placeholder="{{ __('From') }}"
                            class="block w-full border-gray-300 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm py-2 px-3"
                        >
                        <span class="text-gray-500">-</span>
                        <input
                            type="text"
                            id="range_report_end_date"
                            name="end_date"
                            x-ref="rangeReportEndDate"
                            x-model="rangeReportEndDate"
                            placeholder="{{ __('To') }}"
                            class="block w-full border-gray-300 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm py-2 px-3"
                        >
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3">
                    <button
                        type="button"
                        @click="rangeReportModalOpen = false"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-[#1C1C1A] border border-gray-300 dark:border-[#3E3E3A] rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-[#2C2C2A] focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center px-4 py-2 bg-teal-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-teal-700 focus:bg-teal-700 active:bg-teal-900 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-md shadow-teal-500/20"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M5 11h14M7 21h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        {{ __('Generate Report') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
