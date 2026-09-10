<?php

namespace App\Services;

use App\Models\Distribution;
use Illuminate\Support\Facades\DB;

class DistributionService
{
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

    private function prepare(array $attributes): array
    {
        $attributes['subtotal'] = round((float) $attributes['quantity'] * (float) $attributes['price'], 4);
        if (empty($attributes['credit_client_id'])) {
            $attributes['credit_client_price'] = null;
        }

return $attributes;
    }
}
