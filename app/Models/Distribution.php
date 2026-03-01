<?php

namespace App\Models;

use App\Models\DebtLedger;
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
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::created(function (Distribution $distribution) {
            $distribution->createDebtLedgerCharge();
        });

        static::updated(function (Distribution $distribution) {
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
            $this->createDebtLedgerCharge();
        }
    }

    public function deleteDebtLedgerCharge()
    {
        DebtLedger::where('reference_id', $this->id)
            ->where('type', 'charge')
            ->delete();
    }

    public function restoreDebtLedgerCharge()
    {
        DebtLedger::withTrashed()
            ->where('reference_id', $this->id)
            ->where('type', 'charge')
            ->restore();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
