<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provider extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'address',
        'notes',
    ];

    public function defaultProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'default_provider_id');
    }

    public function providerLedgers(): HasMany
    {
        return $this->hasMany(ProviderLedger::class);
    }

    public function scopeWithBalance($query)
    {
        return $query->select('providers.*')
            ->selectRaw("COALESCE((SELECT SUM(CASE WHEN type = 'payment' THEN -amount ELSE amount END) FROM provider_ledgers WHERE provider_id = providers.id AND deleted_at IS NULL), 0) as balance");
    }

    public function getBalanceAttribute($value): float
    {
        if ($value !== null) {
            return (float) $value;
        }

        $charges = $this->providerLedgers()->where('type', 'charge')->sum('amount');
        $payments = $this->providerLedgers()->where('type', 'payment')->sum('amount');

        return (float) ($charges - $payments);
    }
}
