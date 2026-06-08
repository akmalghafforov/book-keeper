<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Distribution extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'client_id',
        'shop_id',
        'credit_client_id',
        'product_id',
        'quantity_unit',
        'quantity',
        'price',
        'subtotal',
        'distribution_date',
        'provider_received_at',
    ];

    protected $casts = [
        'distribution_date' => 'date',
        'provider_received_at' => 'datetime',
        'quantity' => 'decimal:3',
        'price' => 'decimal:4',
        'subtotal' => 'decimal:4',
    ];

    protected static function booted()
    {
        static::created(function (Distribution $distribution) {
            $distribution->createDebtLedgerCharge();
            $distribution->syncProviderLedger();
        });

        static::updated(function (Distribution $distribution) {
            // Skip sync during restore — the `restored` event handles it
            if ($distribution->isDirty('deleted_at')) {
                return;
            }
            $distribution->syncDebtLedgerCharge();
            $distribution->syncProviderLedger();
        });

        static::deleted(function (Distribution $distribution) {
            $distribution->deleteDebtLedgerCharge();
            $distribution->deleteProviderLedger();
        });

        static::restored(function (Distribution $distribution) {
            $distribution->restoreDebtLedgerCharge();
            $distribution->syncProviderLedger();
        });
    }

    public function createDebtLedgerCharge()
    {
        DebtLedger::create([
            'client_id' => $this->client_id,
            'type' => 'charge',
            'amount' => $this->subtotal,
            'transaction_date' => $this->distribution_date,
            'reference_id' => $this->id,
            'notes' => "Auto-generated charge from Distribution #{$this->id} ({$this->distribution_date->format('d/m/Y')})",
        ]);

        if ($this->credit_client_id) {
            DebtLedger::create([
                'client_id' => $this->credit_client_id,
                'type' => 'credit_note',
                'amount' => $this->subtotal,
                'transaction_date' => $this->distribution_date,
                'reference_id' => $this->id,
                'notes' => "Auto-generated credit note from Distribution #{$this->id} ({$this->distribution_date->format('d/m/Y')})",
            ]);
        }
    }

    public function syncDebtLedgerCharge()
    {
        $ledger = DebtLedger::where('type', 'charge')
            ->where('reference_id', $this->id)
            ->first();

        if ($ledger) {
            $ledger->update([
                'client_id' => $this->client_id,
                'amount' => $this->subtotal,
                'transaction_date' => $this->distribution_date,
                'notes' => "Auto-generated charge from Distribution #{$this->id} ({$this->distribution_date->format('d/m/Y')})",
            ]);
        } else {
            DebtLedger::create([
                'client_id' => $this->client_id,
                'type' => 'charge',
                'amount' => $this->subtotal,
                'transaction_date' => $this->distribution_date,
                'reference_id' => $this->id,
                'notes' => "Auto-generated charge from Distribution #{$this->id} ({$this->distribution_date->format('d/m/Y')})",
            ]);
        }

        $creditLedger = DebtLedger::where('type', 'credit_note')
            ->where('reference_id', $this->id)
            ->first();

        if ($this->credit_client_id) {
            if ($creditLedger) {
                $creditLedger->update([
                    'client_id' => $this->credit_client_id,
                    'amount' => $this->subtotal,
                    'transaction_date' => $this->distribution_date,
                    'notes' => "Auto-generated credit note from Distribution #{$this->id} ({$this->distribution_date->format('d/m/Y')})",
                ]);
            } else {
                DebtLedger::create([
                    'client_id' => $this->credit_client_id,
                    'type' => 'credit_note',
                    'amount' => $this->subtotal,
                    'transaction_date' => $this->distribution_date,
                    'reference_id' => $this->id,
                    'notes' => "Auto-generated credit note from Distribution #{$this->id} ({$this->distribution_date->format('d/m/Y')})",
                ]);
            }
        } elseif ($creditLedger) {
            $creditLedger->delete();
        }
    }

    public function deleteDebtLedgerCharge()
    {
        DebtLedger::where('reference_id', $this->id)
            ->whereIn('type', ['charge', 'credit_note'])
            ->delete();
    }

    public function restoreDebtLedgerCharge()
    {
        DebtLedger::withTrashed()
            ->where('reference_id', $this->id)
            ->whereIn('type', ['charge', 'credit_note'])
            ->restore();
    }

    public function syncProviderLedger(): void
    {
        $product = Product::query()->find($this->product_id);
        $ledger = ProviderLedger::withTrashed()
            ->where('distribution_id', $this->id)
            ->first();

        if (! $product?->default_provider_id || $product->buy_price === null) {
            $ledger?->delete();

            return;
        }

        $amount = round((float) $this->quantity * (float) $product->buy_price, 4);
        $providerReceivedAt = $this->provider_received_at ?? $this->distribution_date?->copy()->startOfDay();
        $providerReceivedAtLabel = $providerReceivedAt?->format('d/m/Y H:i') ?? $this->distribution_date->format('d/m/Y');
        $data = [
            'provider_id' => $product->default_provider_id,
            'type' => 'charge',
            'distribution_id' => $this->id,
            'product_id' => $product->id,
            'car_number' => Supplier::withTrashed()
                ->whereKey($this->supplier_id)
                ->value('car_number'),
            'quantity' => $this->quantity,
            'buy_price' => $product->buy_price,
            'amount' => $amount,
            'transaction_date' => $providerReceivedAt?->toDateString() ?? $this->distribution_date,
            'provider_received_at' => $providerReceivedAt,
            'notes' => "Auto-generated provider balance from Distribution #{$this->id} ({$providerReceivedAtLabel})",
        ];

        if ($ledger) {
            if ($ledger->trashed()) {
                $ledger->restore();
            }

            $ledger->update($data);

            return;
        }

        ProviderLedger::create($data);
    }

    public function deleteProviderLedger(): void
    {
        ProviderLedger::where('distribution_id', $this->id)->delete();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creditClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'credit_client_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function providerLedger(): HasOne
    {
        return $this->hasOne(ProviderLedger::class);
    }
}
