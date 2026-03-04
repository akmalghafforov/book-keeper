<?php

namespace App\Models;

use App\Models\DebtLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];

    protected $casts = [
        'distribution_date' => 'date',
        'quantity' => 'decimal:3',
        'price' => 'decimal:4',
        'subtotal' => 'decimal:4',
    ];

    protected static function booted()
    {
        static::created(function (Distribution $distribution) {
            $distribution->createDebtLedgerCharge();
        });

        static::updated(function (Distribution $distribution) {
            // Skip sync during restore — the `restored` event handles it
            if ($distribution->isDirty('deleted_at')) {
                return;
            }
            $distribution->syncDebtLedgerCharge();
        });

        static::deleted(function (Distribution $distribution) {
            $distribution->deleteDebtLedgerCharge();
        });

        static::restored(function (Distribution $distribution) {
            $distribution->restoreDebtLedgerCharge();
        });
    }

    public function createDebtLedgerCharge()
    {
        DebtLedger::create([
            'client_id' => $this->client_id,
            'type' => 'charge',
            'amount' => $this->subtotal,
            'reference_id' => $this->id,
            'notes' => "Auto-generated charge from Distribution #{$this->id} ({$this->distribution_date->format('d/m/Y')})",
        ]);

        if ($this->credit_client_id) {
            DebtLedger::create([
                'client_id' => $this->credit_client_id,
                'type' => 'credit_note',
                'amount' => $this->subtotal,
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
                'notes' => "Auto-generated charge from Distribution #{$this->id} ({$this->distribution_date->format('d/m/Y')})",
            ]);
        } else {
            DebtLedger::create([
                'client_id' => $this->client_id,
                'type' => 'charge',
                'amount' => $this->subtotal,
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
                    'notes' => "Auto-generated credit note from Distribution #{$this->id} ({$this->distribution_date->format('d/m/Y')})",
                ]);
            } else {
                DebtLedger::create([
                    'client_id' => $this->credit_client_id,
                    'type' => 'credit_note',
                    'amount' => $this->subtotal,
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
}
