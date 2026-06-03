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
            ->selectRaw('COALESCE((SELECT SUM(amount) FROM provider_ledgers WHERE provider_id = providers.id AND deleted_at IS NULL), 0) as balance');
    }

    public function getBalanceAttribute($value): float
    {
        if ($value !== null) {
            return (float) $value;
        }

        return (float) $this->providerLedgers()->sum('amount');
    }
}
