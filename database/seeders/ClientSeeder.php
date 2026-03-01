<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Client::create(['name' => 'John Doe', 'phone' => '123-456-7890']);
        Client::create(['name' => 'Jane Smith', 'phone' => '098-765-4321']);
        Client::create(['name' => 'Acme Corp', 'phone' => '555-555-5555']);
    }
}
