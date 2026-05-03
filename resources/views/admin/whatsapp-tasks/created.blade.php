@extends('layouts.admin')

@section('title', __('Created WhatsApp Tasks'))
@section('header_title', __('Created WhatsApp Tasks'))

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Created WhatsApp Tasks') }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('All tasks created from imported WhatsApp messages.') }}</p>
        </div>
        <a href="{{ route('admin.whatsapp-tasks.index') }}" class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
            {{ __('Create task') }}
        </a>
    </div>

    @if (session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    <div class="bg-white dark:bg-[#161615] overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-[#3E3E3A]">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-[#3E3E3A]">
                <thead class="bg-gray-50 dark:bg-[#1C1C1A]">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Task') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Client') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Amount') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Task date') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Messages') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Created') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-[#161615] divide-y divide-gray-200 dark:divide-[#3E3E3A]">
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
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <div class="mb-2 text-xs font-medium text-gray-400 dark:text-gray-500">{{ $task->messages_count }} {{ __('messages') }}</div>
                                <div class="space-y-2">
                                    @foreach ($task->messages as $message)
                                        <div class="max-w-md rounded-lg bg-gray-50 p-2 dark:bg-[#1C1C1A]">
                                            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-400 dark:text-gray-500">
                                                <span>#{{ $message->id }}</span>
                                                <span>{{ $message->message_at->format('Y-m-d H:i') }}</span>
                                                <span>{{ $message->sender ?: __('Unknown sender') }}</span>
                                            </div>
                                            <div class="mt-1 break-words text-gray-700 dark:text-gray-300">
                                                {{ $message->body ?: $message->attachment_filename ?: '-' }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <div>{{ $task->created_at->format('Y-m-d H:i') }}</div>
                                <div class="mt-1 text-xs">{{ $task->creator?->name ?: '-' }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 whitespace-nowrap text-sm text-center text-gray-500 dark:text-gray-400">
                                {{ __('No tasks created yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($tasks->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-[#3E3E3A]">
                {{ $tasks->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
