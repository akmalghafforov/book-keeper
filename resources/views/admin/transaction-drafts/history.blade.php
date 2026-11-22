<details class="mt-3 text-sm"
    x-data="draftHistory({{ Illuminate\Support\Js::from(route('admin.transaction-drafts.show', $draft)) }})" @toggle="if ($el.open) load()">
    <summary class="cursor-pointer font-medium">{{ __('View details') }}</summary>
    <p x-cloak x-show="failed" role="status">{{ __('The request could not be completed. You can retry.') }}</p>
    <template x-if="draft">
        <div class="mt-2 space-y-2">
            <a x-show="postedRecord?.available" :href="postedRecord?.url" class="underline">{{ __('Open posted record') }}</a>
            <p x-show="postedRecord && !postedRecord.available">{{ __('The posted record is unavailable.') }}</p>
            <details>
                <summary class="cursor-pointer">{{ __('Original suggestion') }}</summary>
                <pre class="overflow-auto" x-text="JSON.stringify(draft.original_fields, null, 2)"></pre>
            </details>
            <details x-show="draft.approval">
                <summary class="cursor-pointer">{{ __('Approval snapshot') }}</summary>
                <pre class="overflow-auto" x-text="JSON.stringify(draft.approval, null, 2)"></pre>
            </details>
            <details>
                <summary class="cursor-pointer">{{ __('Audit events') }}</summary>
                <template x-for="event in draft.events" :key="event.id">
                    <div class="my-2">
                        <p x-text="event.type + ' · ' + event.occurred_at + ' · #' + event.actor_id"></p>
                        <pre class="overflow-auto" x-text="JSON.stringify(event.details ?? event.changes, null, 2)"></pre>
                    </div>
                </template>
            </details>
        </div>
    </template>
</details>
