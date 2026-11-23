@php
    if ($image && $text !== null) {
        $text = preg_replace('/^[ \t\x{200E}\x{200F}]*<Без медиафайлов>[ \t]*(?:\R|\z)/mu', '', $text);
    }
@endphp

@if ($text !== null && $text !== '')
    <p class="whitespace-pre-wrap wrap-anywhere">{{ $text }}</p>
@endif

@if ($image)
    @php($url = route('admin.transaction-drafts.image', [$draft, $image]))
    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="block w-fit max-w-full rounded-lg focus-visible:outline-2 focus-visible:outline-indigo-500">
        <img src="{{ $url }}" alt="{{ __('WhatsApp photo') }}" loading="lazy" decoding="async" class="max-h-64 max-w-full rounded-lg object-contain">
    </a>
@endif
