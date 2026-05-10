@extends('layouts.admin')

@section('title', __('Created WhatsApp Tasks'))
@section('header_title', __('Created WhatsApp Tasks'))

@section('content')
@php
    $statusLabels = [
        'pending' => __('Pending'),
        'completed' => __('Completed'),
        'cancelled' => __('Cancelled'),
    ];
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Created WhatsApp Tasks') }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Browse created tasks and narrow them down with filters.') }}</p>
        </div>
        <a href="{{ route('admin.whatsapp-tasks.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-[#3E3E3A] dark:bg-[#161615] dark:text-gray-300 dark:hover:bg-[#1C1C1A]">
            {{ __('Create task') }}
        </a>
    </div>

    @if (session('success'))
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-100">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-100">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET" action="{{ route('admin.whatsapp-tasks.created') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-[#3E3E3A] dark:bg-[#161615] sm:p-5">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="search" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Search') }}</label>
                <input
                    id="search"
                    name="search"
                    value="{{ request('search') }}"
                    type="search"
                    placeholder="{{ __('Notes, client, sender, message') }}"
                    class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white sm:text-sm"
                >
            </div>

            <div>
                <label for="status" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Status') }}</label>
                <select id="status" name="status" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white sm:text-sm">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach ($statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected((string) request('status') === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="task_type" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Task type') }}</label>
                <select id="task_type" name="task_type" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white sm:text-sm">
                    <option value="">{{ __('All types') }}</option>
                    @foreach ($taskTypeOptions as $taskType)
                        <option value="{{ $taskType }}" @selected((string) request('task_type') === (string) $taskType)>{{ __(\App\Models\WhatsAppTask::TYPE_LABELS[$taskType] ?? $taskType) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="client_id" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Client') }}</label>
                <select id="client_id" name="client_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-[#3E3E3A] dark:bg-[#0a0a0a] dark:text-white sm:text-sm">
                    <option value="">{{ __('All clients') }}</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) request('client_id') === (string) $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap justify-end gap-2">
            @if (request()->hasAny(['search', 'status', 'task_type', 'client_id']))
                <a href="{{ route('admin.whatsapp-tasks.created') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-[#3E3E3A] dark:bg-[#161615] dark:text-gray-300 dark:hover:bg-[#1C1C1A]">
                    {{ __('Clear filters') }}
                </a>
            @endif
            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-indigo-500/20 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                {{ __('Apply filters') }}
            </button>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-[#3E3E3A] dark:bg-[#161615]">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                <thead class="bg-gray-50 dark:bg-[#1C1C1A]">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Task') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Client') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Amount') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Task date') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Status') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Messages') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Created') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-[#3E3E3A] dark:bg-[#161615]">
                    @forelse ($tasks as $task)
                        <tr class="align-top">
                            <td class="px-6 py-4 text-sm">
                                <div class="font-semibold text-gray-900 dark:text-white">#{{ $task->id }} {{ __($task->taskTypeLabel()) }}</div>
                                @if ($task->notes)
                                    <div class="mt-1 max-w-xs whitespace-pre-line break-words text-gray-500 dark:text-gray-400">{{ $task->notes }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <div>{{ $task->client?->name ?: '-' }}</div>
                                @if ($task->creditClient)
                                    <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('Credit') }}: {{ $task->creditClient->name }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $task->amount !== null ? number_format((float) $task->amount, 2) : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $task->task_date?->format('Y-m-d') ?: '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $task->status === 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100' : ($task->status === 'cancelled' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100' : 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-100') }}">
                                    {{ __($task->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $task->messages_count }} {{ __('messages') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <div>{{ $task->created_at->format('Y-m-d H:i') }}</div>
                                <div class="mt-1 text-xs">{{ $task->creator?->name ?: '-' }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ __('No created tasks match these filters.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($tasks->hasPages())
            <div class="border-t border-gray-200 px-4 py-4 dark:border-[#3E3E3A] sm:px-6">
                {{ $tasks->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
