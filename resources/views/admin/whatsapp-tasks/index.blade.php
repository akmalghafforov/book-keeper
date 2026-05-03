@extends('layouts.admin')

@section('title', __('WhatsApp Tasks'))
@section('header_title', __('WhatsApp Tasks'))

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
@endpush

@section('content')
@php
    $selectedMessageIds = collect(old('message_ids', []))->map(fn ($id) => (string) $id)->all();
    $messageDates = [];
    $tabs = [
        'without_task' => __('Messages without task'),
        'with_task' => __('Messages with created task'),
        'deleted' => __('Deleted messages'),
    ];
    $tabQuery = request()->except('page', 'tab');
@endphp

<div class="space-y-6" x-data="{ taskType: '{{ old('task_type', \App\Models\WhatsAppTask::TYPE_GOODS_PIECES) }}', selectedCount: {{ count($selectedMessageIds) }} }">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Create WhatsApp Task') }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Select one or more parsed WhatsApp messages, then create a debt, debt with credits, or payment task.') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('admin.whatsapp-tasks.created') }}" class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                {{ __('Created tasks') }}
            </a>
            <a href="{{ route('admin.whatsapp-imports.index') }}" class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                {{ __('Import messages') }}
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="border-b border-gray-200 dark:border-[#3E3E3A]">
        <nav class="-mb-px flex flex-wrap gap-4" aria-label="{{ __('WhatsApp message tabs') }}">
            @foreach ($tabs as $tabKey => $tabLabel)
                <a
                    href="{{ route('admin.whatsapp-tasks.index', array_merge($tabQuery, ['tab' => $tabKey])) }}"
                    class="whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium {{ $activeTab === $tabKey ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
                >
                    {{ $tabLabel }}
                </a>
            @endforeach
        </nav>
    </div>

    <form method="GET" action="{{ route('admin.whatsapp-tasks.index') }}" class="bg-white dark:bg-[#161615] border border-gray-200 dark:border-[#3E3E3A] rounded-xl p-5 shadow-sm">
        <input type="hidden" name="tab" value="{{ $activeTab }}">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="sender" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Sender') }}</label>
                <input type="text" name="sender" id="sender" value="{{ request('sender') }}" class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Message search') }}</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}" class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-900 dark:bg-gray-100 border border-transparent rounded-lg font-semibold text-xs text-white dark:text-gray-900 uppercase tracking-widest hover:bg-gray-800 dark:hover:bg-white transition">
                    {{ __('Filter') }}
                </button>
                <a href="{{ route('admin.whatsapp-tasks.index', ['tab' => $activeTab]) }}" class="inline-flex items-center px-4 py-2 bg-gray-100 dark:bg-[#2A2A28] border border-transparent rounded-lg font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-[#3E3E3A] transition">
                    {{ __('Reset') }}
                </a>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.whatsapp-tasks.store') }}">
        @csrf
        <input type="hidden" name="tab" value="{{ $activeTab }}">
        <input type="hidden" name="sender" value="{{ request('sender') }}">
        <input type="hidden" name="search" value="{{ request('search') }}">

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 bg-white dark:bg-[#161615] border border-gray-200 dark:border-[#3E3E3A] rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-[#3E3E3A] flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $tabs[$activeTab] }}</h3>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-sm text-gray-500 dark:text-gray-400"><span x-text="selectedCount"></span> {{ __('selected') }}</span>
                        @if ($activeTab !== 'deleted')
                            <button
                                type="submit"
                                formaction="{{ route('admin.whatsapp-tasks.messages.destroy') }}"
                                formmethod="POST"
                                formnovalidate
                                onclick="return confirm('{{ __('Remove selected WhatsApp messages?') }}')"
                                class="inline-flex items-center px-3 py-2 bg-red-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition"
                            >
                                {{ __('Remove selected') }}
                            </button>
                        @endif
                    </div>
                </div>

                <div class="space-y-3 bg-[#efe7db] bg-[radial-gradient(circle_at_1px_1px,rgba(17,24,39,0.08)_1px,transparent_0)] bg-[length:18px_18px] p-3 dark:bg-[#101815] dark:bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.06)_1px,transparent_0)] sm:p-5">
                    @forelse ($messages as $message)
                        @php
                            $messageDateKey = $message->message_at->toDateString();
                            $showDate = ! in_array($messageDateKey, $messageDates, true);
                            $messageDates[] = $messageDateKey;
                            $attachmentUrl = $message->attachmentUrl();
                        @endphp

                        @if ($showDate)
                            <div class="flex justify-center">
                                <span class="rounded-md bg-white/80 px-3 py-1 text-xs font-medium text-gray-600 shadow-sm ring-1 ring-black/5 dark:bg-[#202c27]/90 dark:text-gray-300 dark:ring-white/10">
                                    {{ $message->message_at->format('Y-m-d') }}
                                </span>
                            </div>
                        @endif

                        <label class="group flex cursor-pointer items-end gap-2 sm:gap-3">
                            <div class="flex min-w-0 max-w-[92%] items-end gap-2 sm:max-w-[82%]">
                                @if ($activeTab === 'deleted')
                                    <div class="mb-2 h-4 w-4 shrink-0"></div>
                                @else
                                    <input
                                        type="checkbox"
                                        name="message_ids[]"
                                        value="{{ $message->id }}"
                                        @checked(in_array((string) $message->id, $selectedMessageIds, true))
                                        @change="selectedCount += $event.target.checked ? 1 : -1"
                                        class="mb-2 h-4 w-4 shrink-0 rounded border-gray-300 bg-white text-green-600 focus:ring-green-500"
                                    >
                                @endif

                                <div class="relative min-w-0 rounded-lg rounded-bl-sm bg-[#d9fdd3] px-3 py-2 text-gray-900 shadow-sm ring-1 ring-black/5 transition group-hover:ring-green-500/40 dark:bg-[#144d37] dark:text-gray-100 dark:ring-white/10">
                                    <div class="absolute bottom-0 left-[-7px] h-3 w-3 bg-[#d9fdd3] [clip-path:polygon(100%_0,100%_100%,0_100%)] dark:bg-[#144d37]"></div>

                                    <div class="flex min-w-0 items-baseline gap-2">
                                        <div class="truncate text-[13px] font-semibold text-[#128c7e] dark:text-[#7ee0c2]">{{ $message->sender ?: __('Unknown sender') }}</div>
                                        <div class="shrink-0 text-[11px] text-gray-500 dark:text-gray-300">{{ $message->message_at->format('H:i') }}</div>
                                    </div>

                                    @if ($attachmentUrl)
                                        <div class="mt-2 overflow-hidden rounded-md bg-black/5 dark:bg-black/20">
                                            <img
                                                src="{{ $attachmentUrl }}"
                                                alt="{{ $message->attachment_filename }}"
                                                loading="lazy"
                                                class="max-h-72 w-full max-w-sm object-contain"
                                            >
                                        </div>
                                    @endif

                                    <div class="mt-1 whitespace-pre-line break-words text-sm leading-relaxed">{{ $message->body ?: $message->attachment_filename }}</div>

                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-gray-600 dark:text-gray-300">
                                        <span class="rounded bg-black/5 px-1.5 py-0.5 dark:bg-white/10">#{{ $message->id }}</span>
                                        @if ($message->attachment_filename)
                                            <span class="rounded bg-black/5 px-1.5 py-0.5 dark:bg-white/10">{{ $message->attachment_filename }}</span>
                                        @endif
                                        @if ($message->tasks_count > 0)
                                            <span class="rounded bg-amber-100 px-2 py-0.5 text-amber-800 dark:bg-amber-900 dark:text-amber-100">{{ __('already used') }}</span>
                                        @endif
                                        @if ($activeTab === 'deleted')
                                            <span class="rounded bg-red-100 px-2 py-0.5 text-red-800 dark:bg-red-900 dark:text-red-100">{{ __('deleted') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </label>
                    @empty
                        <div class="rounded-lg bg-white/80 p-8 text-center text-sm text-gray-500 shadow-sm dark:bg-[#202c27]/90 dark:text-gray-400">{{ __('No parsed messages found.') }}</div>
                    @endforelse
                </div>

                @if ($messages->hasPages())
                    <div class="px-5 py-4 border-t border-gray-200 dark:border-[#3E3E3A]">
                        {{ $messages->links() }}
                    </div>
                @endif
            </div>

            <div>
                <div class="bg-white dark:bg-[#161615] border border-gray-200 dark:border-[#3E3E3A] rounded-xl shadow-sm p-5">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Task Details') }}</h3>

                    <div class="mt-5 space-y-5">
                        <div>
                            <label for="task_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Task type') }}</label>
                            <select name="task_type" id="task_type" x-model="taskType" required class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                @foreach (\App\Models\WhatsAppTask::TYPE_LABELS as $taskTypeValue => $taskTypeLabel)
                                    <option value="{{ $taskTypeValue }}">{{ __($taskTypeLabel) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="client_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Client') }}</label>
                            <select name="client_id" id="client_id" class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">{{ __('Select a client') }}</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div x-show="taskType === '{{ \App\Models\WhatsAppTask::TYPE_CLIENT_TRANSFER }}'" x-cloak>
                            <label for="credit_client_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Credit client') }}</label>
                            <select name="credit_client_id" id="credit_client_id" class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">{{ __('Select a credit client') }}</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected(old('credit_client_id') == $client->id)>{{ $client->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Amount') }}</label>
                            <input type="number" name="amount" id="amount" value="{{ old('amount') }}" step="0.01" min="0.01" class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="0.00">
                        </div>

                        <div x-data="{ init() { flatpickr($refs.taskDate, { dateFormat: 'd/m/Y', defaultDate: '{{ old('task_date', now()->format('d/m/Y')) }}', allowInput: true }); } }">
                            <label for="task_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Task date') }}</label>
                            <input type="text" name="task_date" id="task_date" x-ref="taskDate" class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="dd/mm/yyyy">
                        </div>

                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Notes') }}</label>
                            <textarea name="notes" id="notes" rows="4" class="block w-full px-3 py-2 bg-white dark:bg-[#0a0a0a] border border-gray-300 dark:border-[#3E3E3A] text-gray-900 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">{{ old('notes') }}</textarea>
                        </div>

                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition shadow-md shadow-indigo-500/20">
                            {{ __('Create task') }}
                        </button>
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
