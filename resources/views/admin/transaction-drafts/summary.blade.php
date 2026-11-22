@php
    $distribution = $draft->kind === 'distribution';
    $provider = $draft->kind === 'provider_ledger';
    $dateField = $distribution ? 'distribution_date' : ($provider ? 'provider_received_at' : 'transaction_date');
    $summaryFields = $distribution
        ? ['client_id', 'product_id', 'quantity', 'quantity_unit']
        : [$provider ? 'provider_id' : 'client_id', 'type', 'amount', 'currency'];
    $date = $draft->current_fields[$dateField] ?? $source['sent_at'];
@endphp

<h2 class="font-semibold">
    {{ $kinds[$draft->kind] }}
    <span class="ms-2 text-sm font-normal text-gray-600 dark:text-gray-300"
        @if ($pending) x-text="saved.{{ $dateField }} ?? @js($source['sent_at'])" @endif>{{ $date }}</span>
</h2>
<p class="flex flex-wrap items-baseline gap-x-2 text-sm">
    @foreach ($summaryFields as $field)
        @php
            $value = $draft->current_fields[$field] ?? null;
            $catalogField = str_ends_with($field, '_id');
            $options = $choices[$draft->kind][$field] ?? collect();
            $name = $value === null ? '' : (
                $catalogField
                    ? ($catalogNames[$field][$value] ?? collect($options)->firstWhere('id', $value)['name'] ?? '#'.$value)
                    : __((string) $value)
            );
        @endphp
        <span @if ($pending) x-text="summaryValue(@js($field), @js($catalogField))" @endif>{{ $name }}</span>
    @endforeach
</p>
<p class="break-words text-sm text-gray-600 dark:text-gray-300">
    {{ $source['chat_key'] }} · {{ $source['export_id'] }} #{{ $source['line'] ?? $source['position'] }} · {{ $source['event_slot'] }}
</p>
