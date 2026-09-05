<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'product_category_id',
        'default_unit',
        'default_provider_id',
        'buy_price',
    ];

    protected $casts = [
        'buy_price' => 'decimal:4',
    ];

    public function defaultProvider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'default_provider_id');
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class);
    }
}
