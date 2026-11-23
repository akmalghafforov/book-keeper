<?php

namespace App\Services;

use App\Models\TransactionDraft;
use Illuminate\Database\Eloquent\Model;

class TransactionDraftDuplicates
{
    public function __construct(
        private PotentialDuplicateDetector $keys,
        private TransactionDraftPosting $posting,
    ) {}

    public function inspect(TransactionDraft $draft, array $prepared): array
    {
        if ($prepared['issues']) {
            return ['matches' => [], 'duplicate_confirmation' => null];
        }

        $subjects = $this->entries($draft->kind, $prepared);
        $matches = [];
        foreach ($subjects as $subject) {
            foreach ($this->postedNeighbors($subject['kind'], $subject['entry']) as $record) {
                $match = $this->compare($subject, $record);
                if ($match !== null) {
                    $matches[] = [
                        ...$match,
                        ...$this->posting->link($subject['kind'], $record->id),
                        'source' => 'posted',
                        'origin' => $record->origin,
                    ];
                }
            }
        }

        $kinds = $draft->kind === 'distribution' ? array_keys(TransactionDraft::KINDS) : [$draft->kind, 'distribution'];
        $pending = TransactionDraft::where('status', 'pending')->whereIn('kind', $kinds)->whereKeyNot($draft->id)->orderBy('id')->cursor();
        foreach ($pending as $other) {
            $proposal = $this->posting->prepare($other, $other->current_fields);
            if ($proposal['issues']) {
                continue;
            }
            foreach ($this->entries($other->kind, $proposal) as $entry) {
                foreach ($subjects as $subject) {
                    if ($subject['kind'] !== $entry['kind']) {
                        continue;
                    }
                    $match = $this->compare($subject, $entry['entry']);
                    if ($match !== null) {
                        $matches[] = [
                            ...$match,
                            'source' => 'pending',
                            'kind' => $other->kind,
                            'id' => $other->id,
                            'revision' => $other->revision,
                        ];
                    }
                }
            }
        }

        $signature = $matches ? TransactionDraftWorkflow::digest([$draft->id, $draft->revision, $subjects, $matches]) : null;

        return ['matches' => $matches, 'duplicate_confirmation' => $signature];
    }

    private function entries(string $kind, array $prepared): array
    {
        return array_map(fn (array $entry) => [
            'kind' => $entry['kind'],
            'entry' => new (TransactionDraft::KINDS[$entry['kind']])($entry['values']),
        ], [['kind' => $kind, 'values' => $prepared['attributes']], ...$prepared['effects']]);
    }

    private function postedNeighbors(string $kind, Model $entry): iterable
    {
        $dateField = $this->dateField($kind);
        $date = $entry->{$dateField};
        $party = $kind === 'provider_ledger' ? 'provider_id' : 'client_id';
        $query = $entry->newQuery()
            ->where($party, $entry->{$party})
            ->where($dateField, '>=', $date->copy()->subDay()->toDateString())
            ->where($dateField, '<', $date->copy()->addDays(2)->toDateString());

        $kind === 'distribution'
            ? $query->where('product_id', $entry->product_id)
            : $query->where('type', $entry->type);

        return $query->orderBy('id')->cursor();
    }

    private function compare(array $subject, Model $record): ?array
    {
        $kind = $subject['kind'];
        $entry = $subject['entry'];
        $dateField = $this->dateField($kind);
        if (! $record->{$dateField} || abs($entry->{$dateField}->diffInDays($record->{$dateField})) > 1 || $this->key($kind, $entry) !== $this->key($kind, $record)) {
            return null;
        }

        $reasons = [match ($kind) {
            'distribution' => __('Same client, product, unit, quantity and prices'),
            'debt_ledger' => __('Same client, type and amount'),
            'provider_ledger' => __('Same provider, type and amount'),
        }];
        $reasons[] = $entry->{$dateField}->isSameDay($record->{$dateField}) ? __('Same transaction date') : __('Transaction date differs by one day');
        if ($kind === 'provider_ledger' && $entry->provider_received_at && $record->provider_received_at && $entry->provider_received_at->equalTo($record->provider_received_at)) {
            $reasons[] = __('Matching provider receipt time');
        }

        $supporting = match ($kind) {
            'distribution' => ['supplier_id' => 'Matching supplier', 'shop_id' => 'Matching shop', 'credit_client_id' => 'Matching credit client'],
            'debt_ledger' => ['reference_id' => 'Matching reference ID', 'payment_method' => 'Matching payment method', 'payment_purpose' => 'Matching payment purpose', 'payer_name' => 'Matching payer'],
            'provider_ledger' => ['car_number' => 'Matching vehicle', 'payment_method' => 'Matching payment method', 'payment_purpose' => 'Matching payment purpose', 'payer_name' => 'Matching payer'],
        };
        foreach ($supporting as $field => $reason) {
            $value = $this->normalized($entry, $field);
            if ($value !== '' && $value === $this->normalized($record, $field)) {
                $reasons[] = __($reason);
            }
        }

        return [
            'matched_kind' => $kind,
            'date' => $record->{$dateField}->toDateString(),
            'reasons' => $reasons,
            'record_digest' => TransactionDraftWorkflow::digest($record->getAttributes()),
        ];
    }

    private function key(string $kind, Model $entry): string
    {
        return match ($kind) {
            'distribution' => $this->keys->distributionDuplicateKey($entry, includeDate: false),
            'debt_ledger' => $this->keys->debtLedgerDuplicateKey($entry, includeDate: false),
            'provider_ledger' => implode('|', [$entry->provider_id, $entry->type, number_format((float) $entry->amount, 4, '.', '')]),
        };
    }

    private function dateField(string $kind): string
    {
        return $kind === 'distribution' ? 'distribution_date' : 'transaction_date';
    }

    private function normalized(Model $entry, string $field): string
    {
        return mb_strtolower(trim((string) ($entry->getAttributes()[$field] ?? '')));
    }
}
