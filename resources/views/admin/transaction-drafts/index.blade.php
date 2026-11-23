@extends('layouts.admin')

@section('title', __('Drafts Records'))
@section('header_title', __('Drafts Records'))

@section('content')
@php
    $pending = $tab === 'pending';
    $control = 'block w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg bg-white dark:bg-[#0a0a0a] dark:border-[#3E3E3A]';
    $button = 'rounded-lg border border-gray-300 dark:border-[#3E3E3A] px-3 py-2 text-sm font-medium disabled:opacity-50';
@endphp

<section x-data="{ remaining: { count: {{ $pendingCount }} }, draftChoices: @js($choices), draftCatalog: @js($catalog), cementReview: @js($cementReview) }"
    class="space-y-4 text-gray-900 dark:text-white">
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
        @forelse ($messages as $card)
            <li class="@container min-w-0 space-y-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-[#3E3E3A] dark:bg-[#161615]"
                @if ($pending) x-data="draftMessage(@js($card['review']))" @endif>
                @include('admin.transaction-drafts.source', ['source' => $card['source']])
                @foreach ($kinds as $draftKind => $heading)
                    @isset($card['drafts'][$draftKind])
                        @include('admin.transaction-drafts.table', ['rows' => $card['drafts'][$draftKind]])
                    @endisset
                @endforeach
            </li>
        @empty
            <li class="rounded-xl border border-gray-200 bg-white p-4 text-sm dark:border-[#3E3E3A] dark:bg-[#161615]">
                {{ __('No drafts match this view.') }}
            </li>
        @endforelse
    </ul>

    {{ $messages->links() }}
</section>
@endsection
