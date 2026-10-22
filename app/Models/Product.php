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

    private const CATEGORY_RULES = [
        'Рассыпной' => ['рассыпной', 'рассипной'],
        'Гипсокартон' => ['гипсокартон', 'гипсакартон', 'гипс'],
        'Шифер' => ['шифер'],
        'Цемент' => ['цемент', 'смент', 'm500', 'м500', 'm400', 'м400'],
    ];

    protected $fillable = [
        'name',
        'product_category_id',
        'default_unit',
        'default_provider_id',
        'buy_price',
        'usage_priority',
    ];

    protected $casts = [
        'buy_price' => 'decimal:4',
        'usage_priority' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            if ($product->product_category_id) {
                return;
            }

            $product->product_category_id = ProductCategory::query()->firstOrCreate([
                'name' => self::categoryNameFor($product->name),
            ])->getKey();
        });
    }

    private static function categoryNameFor(string $name): string
    {
        $normalized = mb_strtolower($name);

        foreach (self::CATEGORY_RULES as $category => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($normalized, $needle)) {
                    return $category;
                }
            }
        }

        return 'Другое';
    }

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
