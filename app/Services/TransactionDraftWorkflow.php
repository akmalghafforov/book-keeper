<?php

namespace App\Services;

use App\Models\TransactionDraft;
use App\Models\TransactionDraftEvent;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TransactionDraftWorkflow
{
    public function __construct(
        private TransactionDraftPosting $posting,
        private TransactionDraftDuplicates $duplicates,
    ) {}

    public function submit(string $kind, array $source, array $fields, int $actor): JsonResponse
    {
        $scope = Arr::only($source, ['chat_key', 'message_key', 'event_slot']);
        $id = (string) Str::uuid();
        $now = now();
        $draft = TransactionDraft::query()->firstOrCreate($scope, [
            'id' => $id,
            'kind' => $kind,
            'source' => $source,
            'original_fields' => $fields,
            'current_fields' => $fields,
            'submitted_by' => $actor,
            'created_at' => $now,
        ]);

        if ($draft->wasRecentlyCreated) {
            $draft->refresh();
            $this->event($draft, 'submitted', $actor, $this->changes([], $fields));

            return $this->response($draft, 201);
        }

        if ($draft->kind !== $kind || self::digest($draft->original_fields) !== self::digest($fields)) {
            Log::info('Transaction draft source conflict', ['draft_id' => $draft->id, 'kind' => $kind]);

            return response()->json(['message' => __('This source event already has a different suggestion.'), 'existing_draft_id' => $draft->id], 409);
        }

        return $this->response($draft);
    }

    public function response(TransactionDraft $draft, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $this->resource($draft)], $status)->header('ETag', $draft->etag());
    }

    public function resource(TransactionDraft $draft, bool $detail = true, ?bool $postedAvailable = null): array
    {
        $source = Arr::except($draft->source, ['message_text']);
        if (isset($source['additional_locators'])) {
            $source['additional_locators'] = array_map(
                fn (array $locator) => Arr::except($locator, ['message_text']),
                $source['additional_locators'],
            );
        }

        $attributes = [
            'kind' => $draft->kind,
            'status' => $draft->status,
            'revision' => $draft->revision,
            'corrected' => $draft->corrected,
            'source' => $source,
            'submitted_by' => $draft->submitted_by,
            'created_at' => $draft->created_at->toIso8601String(),
            'updated_at' => $draft->updated_at->toIso8601String(),
        ];

        if ($detail) {
            $prepared = $draft->status === 'pending' ? $this->posting->prepare($draft->kind, $draft->current_fields) : null;
            $events = $draft->events()->get();
            $attributes += [
                'original_fields' => (object) $draft->original_fields,
                'current_fields' => (object) $draft->current_fields,
                'validation_issues' => (object) ($prepared['issues'] ?? []),
                'events' => $events,
                'posted_record' => $this->postedRecord($draft, $postedAvailable),
                'approval' => $draft->approval,
            ];

            $matched = $events->firstWhere('type', 'dismissed')?->details['existing_record'] ?? null;
            if ($matched !== null) {
                $attributes['matched_posted_record'] = $this->postedRecordLink($draft->kind, $matched['id']);
            }
        }

        return ['type' => 'transaction-draft', 'id' => $draft->id, 'attributes' => $attributes];
    }

    public function preview(TransactionDraft $draft, array $changes): array
    {
        $fields = array_replace($draft->current_fields, $changes);
        $prepared = $this->posting->prepare($draft->kind, $fields);
        $payment = null;

        if (($fields['type'] ?? null) === 'payment' && $prepared['attributes']) {
            $payment = [
                'amount' => (string) $fields['amount'],
                'currency' => $fields['currency'],
                'exchange_rate' => $fields['currency'] === 'TJS' ? '1' : (string) $fields['exchange_rate'],
                'amount_tjs' => number_format($prepared['attributes']['amount'], 2, '.', ''),
            ];
        }

        return [
            'validation_issues' => (object) $prepared['issues'],
            'payment' => $payment,
            'effects' => array_map(fn (array $effect) => [
                'kind' => $effect['kind'],
                'type' => $effect['values']['type'],
                'party_id' => $effect['values']['client_id'] ?? $effect['values']['provider_id'],
                'amount' => (string) $effect['values']['amount'],
            ], $prepared['effects']),
            ...$this->duplicates->inspect($draft, $prepared),
        ];
    }

    public function edit(TransactionDraft $draft, int $actor, ?string $etag, Closure $changes): JsonResponse
    {
        return $this->locked($draft, function (TransactionDraft $locked) use ($actor, $etag, $changes): JsonResponse {
            if ($failure = $this->reviewFailure($locked, $etag)) {
                return $failure;
            }

            $this->recordEdit($locked, $actor, $changes($locked));

            return $this->response($locked);
        });
    }

    public function approve(TransactionDraft $draft, int $actor, ?string $etag, string $key, array $requestBody, Closure $validate): JsonResponse
    {
        $digest = self::digest([$etag, $requestBody]);

        try {
            return $this->locked($draft, function (TransactionDraft $locked) use ($actor, $etag, $key, $digest, $validate): JsonResponse {
                $saved = $locked->events()->where('type', 'approved')->first();
                if ($saved && $saved->actor_id === $actor && $saved->approval_key === $key) {
                    if ($saved->request_digest === $digest) {
                        return response()->json(['data' => $this->resource($locked, postedAvailable: true)])
                            ->header('ETag', $locked->etag());
                    }

                    return response()->json(['message' => __('Idempotency key was used with a different request.')], 409);
                }

                if ($failure = $this->reviewFailure($locked, $etag)) {
                    return $failure;
                }

                $input = $validate();
                $fields = array_replace($locked->current_fields, $input['fields']);
                $prepared = $this->posting->prepare($locked->kind, $fields);
                if ($prepared['issues']) {
                    Log::info('Transaction draft approval validation failed', ['draft_id' => $locked->id, 'kind' => $locked->kind]);
                    throw ValidationException::withMessages($prepared['issues']);
                }

                $duplicates = $this->duplicates->inspect($locked, $prepared);
                if ($duplicates['duplicate_confirmation'] !== ($input['duplicate_confirmation'] ?? null)) {
                    Log::info('Transaction draft duplicate confirmation required', ['draft_id' => $locked->id, 'kind' => $locked->kind]);

                    return response()->json(['message' => __('Review the current potential duplicates before approving.'), ...$duplicates], 409);
                }

                $this->recordEdit($locked, $actor, $input['fields']);
                $record = $this->posting->post($locked->kind, $prepared['attributes']);
                $locked->update([
                    'status' => 'approved',
                    'revision' => $locked->revision + 1,
                    'posted_record_id' => $record->id,
                    'approval' => $this->posting->snapshot($locked->kind, $record, $fields),
                ]);

                $this->event($locked, 'approved', $actor, [], [
                    'posted_record' => $this->posting->link($locked->kind, $record->id),
                    'confirmed_matches' => $duplicates['matches'],
                ], ['approval_key' => $key, 'request_digest' => $digest]);

                return response()->json(['data' => $this->resource($locked, postedAvailable: true)])
                    ->header('ETag', $locked->etag());
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            // Database exceptions can embed SQL bindings containing draft fields.
            Log::error('Transaction draft approval failed', ['draft_id' => $draft->id, 'kind' => $draft->kind, 'error_type' => $exception::class]);

            return response()->json(['message' => __('The approval failed. The draft is still available for review.')], 500);
        }
    }

    public function dismiss(TransactionDraft $draft, int $actor, ?string $etag, Closure $validate): JsonResponse
    {
        return $this->locked($draft, function (TransactionDraft $locked) use ($actor, $etag, $validate): JsonResponse {
            if ($failure = $this->reviewFailure($locked, $etag)) {
                return $failure;
            }

            $input = $validate();
            $existing = null;
            if ($input['reason'] === 'already_recorded') {
                $prepared = $this->posting->prepare($locked->kind, $locked->current_fields);
                $duplicates = $this->duplicates->inspect($locked, $prepared);
                $existing = collect($duplicates['matches'])->first(fn (array $match) => $match['source'] === 'posted'
                    && $match['kind'] === $locked->kind
                    && $match['id'] === (string) $input['posted_record_id']);
                if ($existing === null) {
                    return response()->json(['message' => __('Choose a currently matched posted record.'), ...$duplicates], 409);
                }
            }

            $locked->update(['status' => 'dismissed', 'revision' => $locked->revision + 1]);
            $this->event($locked, 'dismissed', $actor, [], ['reason' => $input['reason'], 'existing_record' => $existing]);

            return $this->response($locked);
        });
    }

    public static function digest(array $value): string
    {
        return hash('sha256', json_encode(self::sortKeys($value), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    private static function sortKeys(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(fn ($item) => is_array($item) ? self::sortKeys($item) : $item, $value);
    }

    private function locked(TransactionDraft $draft, Closure $operation): JsonResponse
    {
        return DB::transaction(function () use ($draft, $operation): JsonResponse {
            $query = TransactionDraft::whereKey($draft->id);
            if (DB::connection()->getDriverName() === 'sqlite') {
                // SQLite ignores FOR UPDATE. Take the writer lock before reading the revision.
                (clone $query)->toBase()->update(['revision' => DB::raw('revision')]);
            }

            return $operation($query->lockForUpdate()->firstOrFail());
        }, 5);
    }

    private function reviewFailure(TransactionDraft $draft, ?string $etag): ?JsonResponse
    {
        if ($draft->status !== 'pending') {
            return response()->json(['message' => __('This draft has already been reviewed.'), 'data' => $this->resource($draft)], 409);
        }

        if ($draft->etag() !== $etag) {
            return response()->json(['message' => __('This draft changed while you were reviewing it.'), 'data' => $this->resource($draft)], 412)
                ->header('ETag', $draft->etag());
        }

        return null;
    }

    private function recordEdit(TransactionDraft $draft, int $actor, array $submitted): void
    {
        $merged = array_replace($draft->current_fields, $submitted);
        $changes = $this->changes($draft->current_fields, $merged);
        if ($changes === []) {
            return;
        }
        $draft->update(['current_fields' => $merged, 'revision' => $draft->revision + 1, 'corrected' => true]);
        $this->event($draft, 'edited', $actor, $changes);
    }

    private function changes(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $field => $value) {
            $present = array_key_exists($field, $before);
            if (! $present || $before[$field] !== $value) {
                $changes[$field] = [
                    'before' => $present ? ['present' => true, 'value' => $before[$field]] : ['present' => false],
                    'after' => ['present' => true, 'value' => $value],
                ];
            }
        }

        return $changes;
    }

    private function event(TransactionDraft $draft, string $type, int $actor, array $changes, ?array $details = null, array $metadata = []): TransactionDraftEvent
    {
        return $draft->events()->create([
            ...$metadata,
            'actor_id' => $actor,
            'type' => $type,
            'revision' => $draft->revision,
            'occurred_at' => now('UTC')->format('Y-m-d\TH:i:s.u\Z'),
            'changes' => $changes,
            'details' => $details,
        ]);
    }

    private function postedRecord(TransactionDraft $draft, ?bool $postedAvailable): ?array
    {
        if ($draft->posted_record_id === null) {
            return null;
        }

        return $this->postedRecordLink($draft->kind, $draft->posted_record_id, $postedAvailable);
    }

    private function postedRecordLink(string $kind, int|string $id, ?bool $available = null): array
    {
        $model = TransactionDraft::KINDS[$kind];

        return [
            ...$this->posting->link($kind, $id),
            'available' => $available ?? $model::whereKey($id)->exists(),
        ];
    }
}
