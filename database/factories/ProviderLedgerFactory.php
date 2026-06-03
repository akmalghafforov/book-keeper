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
            'type' => 'charge',
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

    public function payment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'payment',
            'distribution_id' => null,
            'product_id' => null,
            'car_number' => null,
            'quantity' => null,
            'buy_price' => null,
        ]);
    }

    public function manualCharge(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'charge',
            'distribution_id' => null,
            'product_id' => null,
            'quantity' => null,
            'buy_price' => null,
        ]);
    }
}
