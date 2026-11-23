@php
    $primary = $source['primary'];
    $sentAt = $primary['sent_at'] ?? null;
@endphp

<header class="min-w-0 space-y-2 text-sm">
    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
        <h2 class="font-semibold">{{ __('WhatsApp message') }}</h2>
        <time datetime="{{ $sentAt }}" class="text-gray-600 dark:text-gray-300">
            {{ $sentAt ? Illuminate\Support\Facades\Date::parse($sentAt)->format('Y-m-d H:i:s P') : '—' }}
        </time>
    </div>

    @if (filled($primary['message_text']) || $primary['image'])
        @include('admin.transaction-drafts.message', ['text' => $primary['message_text'], 'image' => $primary['image']])
    @else
        <p class="wrap-anywhere text-gray-600 dark:text-gray-300">
            {{ $primary['chat_key'] }} · {{ $primary['export_id'] }} #{{ $primary['line'] ?? $primary['position'] }}
        </p>
    @endif

    @if ($source['related'])
        <details>
            <summary class="cursor-pointer font-medium">{{ __('Related source messages') }}</summary>
            <ul class="mt-2 space-y-2 ps-4">
                @foreach ($source['related'] as $locator)
                    <li class="space-y-1">
                        <p class="wrap-anywhere text-gray-600 dark:text-gray-300">
                            <time datetime="{{ $locator['sent_at'] }}">{{ $locator['sent_at'] }}</time> · {{ $locator['export_id'] }} #{{ $locator['line'] ?? $locator['position'] }}
                        </p>
                        @include('admin.transaction-drafts.message', ['text' => $locator['message_text'], 'image' => $locator['image']])
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</header>
