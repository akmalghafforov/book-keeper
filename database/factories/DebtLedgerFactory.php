<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\DebtLedger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DebtLedger>
 */
class DebtLedgerFactory extends Factory
{
    protected $model = DebtLedger::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'type' => fake()->randomElement(['charge', 'payment', 'credit_note']),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'transaction_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'reference_id' => null,
            'notes' => fake()->sentence(),
        ];
    }

    public function charge(): static
    {
        return $this->state(fn () => ['type' => 'charge']);
    }

    public function payment(): static
    {
        return $this->state(fn () => ['type' => 'payment']);
    }

    public function creditNote(): static
    {
        return $this->state(fn () => ['type' => 'credit_note']);
    }
}
