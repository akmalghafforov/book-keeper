<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DebtLedger extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'type',
        'amount',
        'reference_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(Distribution::class, 'reference_id');
    }
}
