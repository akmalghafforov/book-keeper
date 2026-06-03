<?php

namespace Database\Factories;

use App\Models\Distribution;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderLedger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProviderLedger>
 */
class ProviderLedgerFactory extends Factory
{
    protected $model = ProviderLedger::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 100);
        $buyPrice = fake()->randomFloat(4, 10, 1000);

        return [
            'provider_id' => Provider::factory(),
            'distribution_id' => Distribution::factory(),
            'product_id' => Product::factory(),
            'car_number' => strtoupper(fake()->bothify('??-####')),
            'quantity' => $quantity,
            'buy_price' => $buyPrice,
            'amount' => round($quantity * $buyPrice, 4),
            'transaction_date' => fake()->date(),
            'notes' => fake()->sentence(),
        ];
    }
}
