<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProviderLedger extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'provider_id',
        'type',
        'distribution_id',
        'product_id',
        'car_number',
        'quantity',
        'buy_price',
        'amount',
        'transaction_date',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'buy_price' => 'decimal:4',
        'amount' => 'decimal:4',
        'transaction_date' => 'date',
    ];

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
}
