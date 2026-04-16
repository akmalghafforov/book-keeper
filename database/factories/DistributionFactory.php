<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Distribution;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Distribution>
 */
class DistributionFactory extends Factory
{
    protected $model = Distribution::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 100);
        $price = fake()->randomFloat(4, 10, 1000);

        return [
            'supplier_id' => Supplier::factory(),
            'client_id' => Client::factory(),
            'credit_client_id' => null,
            'product_id' => Product::factory(),
            'quantity_unit' => fake()->randomElement(['per_ton', 'per_bag', 'per_piece']),
            'quantity' => $quantity,
            'price' => $price,
            'subtotal' => round($quantity * $price, 4),
            'distribution_date' => fake()->date(),
        ];
    }

    /**
     * Set a credit client for the distribution.
     */
    public function withCreditClient(?Client $client = null): static
    {
        return $this->state(fn (array $attributes) => [
            'credit_client_id' => $client?->id ?? Client::factory(),
        ]);
    }
}
