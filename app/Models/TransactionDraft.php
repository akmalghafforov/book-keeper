<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionDraft extends Model
{
    use HasUuids;

    public const KINDS = [
        'distribution' => Distribution::class,
        'provider_ledger' => ProviderLedger::class,
        'debt_ledger' => DebtLedger::class,
    ];

    public const ROUTES = [
        'distribution' => 'admin.distributions.show',
        'provider_ledger' => 'admin.provider-ledgers.show',
        'debt_ledger' => 'admin.debt-ledgers.show',
    ];

    protected $guarded = [];

    protected $casts = [
        'source' => 'array',
        'original_fields' => 'array',
        'current_fields' => 'array',
        'approval' => 'array',
        'corrected' => 'boolean',
        'revision' => 'integer',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(TransactionDraftEvent::class, 'draft_id')->orderBy('revision');
    }

    public function images(): HasMany
    {
        return $this->hasMany(TransactionDraftImage::class, 'draft_id');
    }

    public function etag(): string
    {
        return '"'.$this->revision.'"';
    }
}
