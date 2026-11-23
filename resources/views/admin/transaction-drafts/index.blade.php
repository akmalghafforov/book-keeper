@extends('layouts.admin')

@section('title', __('Drafts Records'))
@section('header_title', __('Drafts Records'))

@section('content')
@php
    $pending = $tab === 'pending';
    $control = 'block w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg bg-white dark:bg-[#0a0a0a] dark:border-[#3E3E3A]';
    $button = 'rounded-lg border border-gray-300 dark:border-[#3E3E3A] px-3 py-2 text-sm font-medium disabled:opacity-50';
@endphp

<section x-data="{ remaining: { count: {{ $pendingCount }} }, draftChoices: @js($choices) }"
    class="@container space-y-4 text-gray-900 dark:text-white">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <nav aria-label="{{ __('Draft status') }}" class="flex flex-wrap gap-2">
            @foreach ($tabs as $status => $label)
                <a href="{{ route('admin.transaction-drafts.index', array_filter(['tab' => $status, 'kind' => $kind])) }}"
                    @if ($tab === $status) aria-current="page" @endif
                    @class([$button, 'bg-indigo-600 text-white' => $tab === $status, 'bg-white dark:bg-[#161615]' => $tab !== $status])>
                    {{ $label }}
                    @if ($status === 'pending')
                        <span class="ms-1 tabular-nums" x-text="remaining.count">{{ $pendingCount }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <form action="{{ route('admin.transaction-drafts.index') }}" method="GET" class="flex items-end gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label class="text-sm font-medium">
                {{ __('Transaction type') }}
                <select name="kind" class="{{ $control }} text-sm" @change="$el.form.submit()">
                    <option value="">{{ __('All Types') }}</option>
                    @foreach ($kinds as $value => $label)
                        <option value="{{ $value }}" @selected($kind === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <noscript><button class="{{ $button }}">{{ __('Filter') }}</button></noscript>
        </form>
    </div>

    <ul class="space-y-3">
        @forelse ($drafts as $draft)
            @php
                $source = $draft->source;
                if ($pending) {
                    $draftFields = $fields[$draft->kind] ?? [];
                    $config = [
                        'kind' => $draft->kind,
                        'saved' => (object) $draft->current_fields,
                        'etag' => $draft->etag(),
                        'csrf' => csrf_token(),
                        'urls' => collect(['show', 'preview', 'update', 'approve', 'dismiss'])->mapWithKeys(fn ($action) => [$action => route('admin.transaction-drafts.'.$action, $draft)]),
                        'messages' => [
                            'saved' => __('Draft saved.'),
                            'checkFields' => __('Check the highlighted fields.'),
                            'failed' => __('The request could not be completed. You can retry.'),
                        ],
                    ];
                }
            @endphp
            <li class="rounded-xl border border-gray-200 bg-white p-4 dark:border-[#3E3E3A] dark:bg-[#161615]"
                @if ($pending) x-data="draftReview(@js($config))" @endif>
                <div @class([
                    'grid items-start gap-x-4 gap-y-3 @2xl:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]',
                    '@4xl:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_auto]' => $pending,
                ])>
                    <div class="min-w-0 space-y-1 wrap-anywhere">
                        @include('admin.transaction-drafts.summary')
                    </div>
                    @include('admin.transaction-drafts.source')
                    @if ($pending)
                        <div x-show="!closed" class="flex flex-wrap gap-2 @2xl:col-span-2 @4xl:col-span-1">
                            <button type="button" class="{{ $button }}" @click="toggleEditor()" :aria-expanded="editorOpen" :disabled="busy || !!stale">{{ __('Quick edit') }}</button>
                            <button type="button" class="{{ $button }} bg-indigo-600 text-white" @click="act('approve')" :disabled="busy || !!stale">{{ __('Approve') }}</button>
                            <button type="button" class="{{ $button }}" @click="toggleDismissal()" :aria-expanded="dismissalOpen" :disabled="busy || !!stale">{{ __('Dismiss') }}</button>
                        </div>
                    @endif
                </div>
                @if ($pending)
                    <p x-cloak x-show="!closed && message" class="mt-3 text-sm" x-text="message" role="status"></p>
                    <template x-if="editorReady">
                        <div x-show="!closed && (editorOpen || dismissalOpen)">
                            @include('admin.transaction-drafts.editor')
                        </div>
                    </template>
                    <template x-if="closed">
                        <p role="status" class="mt-3 text-sm">
                            <span x-text="closed.status === 'approved' ? @js(__('Draft approved.')) : @js(__('Draft dismissed.'))"></span>
                            <a x-show="postedRecord?.available" :href="postedRecord?.url" class="underline">{{ __('Open posted record') }}</a>
                        </p>
                    </template>
                @else
                    @include('admin.transaction-drafts.history')
                @endif
            </li>
        @empty
            <li class="rounded-xl border border-gray-200 bg-white p-4 text-sm dark:border-[#3E3E3A] dark:bg-[#161615]">
                {{ __('No drafts match this view.') }}
            </li>
        @endforelse
    </ul>

    {{ $drafts->links() }}
</section>
@endsection
