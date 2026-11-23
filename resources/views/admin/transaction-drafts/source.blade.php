@php($images = $draft->images->keyBy('locator_index'))

<div class="min-w-0 space-y-2 text-sm">
    @if (isset($source['message_text']) || $images->has(0))
        <div class="space-y-1">
            <p class="font-medium">{{ __('WhatsApp message') }}</p>
            @include('admin.transaction-drafts.message', ['text' => $source['message_text'] ?? null, 'image' => $images->get(0)])
        </div>
    @else
        <p class="wrap-anywhere text-gray-600 dark:text-gray-300">
            {{ $source['chat_key'] }} · {{ $source['export_id'] }} #{{ $source['line'] ?? $source['position'] }} · {{ $source['event_slot'] }}
        </p>
    @endif

    @if (!empty($source['additional_locators']))
        <details>
            <summary class="cursor-pointer font-medium">{{ __('Related source messages') }}</summary>
            <ul class="mt-2 space-y-2 ps-4">
                @foreach ($source['additional_locators'] as $locator)
                    <li class="space-y-1">
                        <p class="wrap-anywhere text-gray-600 dark:text-gray-300">
                            <time datetime="{{ $locator['sent_at'] }}">{{ $locator['sent_at'] }}</time> · {{ $locator['export_id'] }} #{{ $locator['line'] ?? $locator['position'] }}
                        </p>
                        @include('admin.transaction-drafts.message', ['text' => $locator['message_text'] ?? null, 'image' => $images->get($loop->iteration)])
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</div>
