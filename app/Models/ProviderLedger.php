<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class ProviderLedger extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'provider_id',
        'type',
        'payment_method',
        'payment_purpose',
        'payer_name',
        'distribution_id',
        'product_id',
        'car_number',
        'quantity',
        'buy_price',
        'amount',
        'transaction_date',
        'provider_received_at',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'buy_price' => 'decimal:4',
        'amount' => 'decimal:4',
        'payment_method' => PaymentMethod::class,
        'payment_purpose' => PaymentPurpose::class,
        'transaction_date' => 'date',
        'provider_received_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProviderLedger $ledger): void {
            if ((int) ($ledger->sort_order ?? 0) > 0 || ! $ledger->provider_id || ! $ledger->transaction_date) {
                return;
            }

            $ledger->sort_order = static::nextSortOrder($ledger->provider_id, $ledger->transaction_date);
        });

        static::updating(function (ProviderLedger $ledger): void {
            if (! $ledger->isDirty(['provider_id', 'transaction_date']) || $ledger->isDirty('sort_order')) {
                return;
            }

            if (! $ledger->provider_id || ! $ledger->transaction_date) {
                return;
            }

            $ledger->sort_order = static::nextSortOrder($ledger->provider_id, $ledger->transaction_date, $ledger->id);
        });
    }

    public function scopeInOperationOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw(static::operationDateTimeExpression().' asc')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeInReverseOperationOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw(static::operationDateTimeExpression().' desc')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public static function operationDateTimeExpression(): string
    {
        $table = (new static)->getTable();

        return "COALESCE({$table}.provider_received_at, {$table}.transaction_date)";
    }

    public function operationDateTime(): ?Carbon
    {
        return $this->provider_received_at
            ?? $this->transaction_date
            ?? $this->created_at;
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(Distribution::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function nextSortOrder(int $providerId, mixed $transactionDate, ?int $exceptId = null): int
    {
        $query = static::query()
            ->where('provider_id', $providerId)
            ->whereDate('transaction_date', static::normalizeTransactionDate($transactionDate));

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        return (int) $query->max('sort_order') + 1;
    }

    private static function normalizeTransactionDate(mixed $transactionDate): string
    {
        if ($transactionDate instanceof DateTimeInterface) {
            return $transactionDate->format('Y-m-d');
        }

        return Carbon::parse($transactionDate)->toDateString();
    }
}
