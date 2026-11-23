<?php

namespace App\Services;

use App\Models\Distribution;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DistributionService
{
    public static function entryRules(): array
    {
        return [
            'credit_client_price' => 'nullable|required_with:credit_client_id|numeric|min:0',
            'product_category_id' => ['required', Rule::exists('product_categories', 'id')],
            'quantity_unit' => 'required|in:per_ton,per_bag,per_piece',
            'quantity' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'provider_buy_price' => 'nullable|numeric|min:0',
        ];
    }

    public function create(array $attributes): Distribution
    {
        return DB::transaction(fn () => Distribution::create($this->prepare($attributes)));
    }

    public function update(Distribution $distribution, array $attributes): Distribution
    {
        return DB::transaction(function () use ($distribution, $attributes) {
            $distribution->update($this->prepare($attributes));

            return $distribution->fresh();
        });
    }

    public function delete(Distribution $distribution): void
    {
        DB::transaction(fn () => $distribution->delete());
    }

    public function prepare(array $attributes): array
    {
        $attributes['subtotal'] = round((float) $attributes['quantity'] * (float) $attributes['price'], 4);
        if (empty($attributes['credit_client_id'])) {
            $attributes['credit_client_price'] = null;
        }

        return $attributes;
    }
}
