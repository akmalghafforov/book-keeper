<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\DebtLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = database_path('data/clients.csv');

        if (!file_exists($filePath)) {
            $this->command->error("CSV file not found at: {$filePath}");
            return;
        }

        $file = fopen($filePath, 'r');

        // Read header
        $header = fgetcsv($file);

        if ($header === false) {
            $this->command->error("CSV file is empty or invalid.");
            fclose($file);
            return;
        }

        DB::beginTransaction();

        try {
            // Clear existing clients and their debt ledger entries
            DebtLedger::query()->delete();
            Client::query()->delete();

            while (($row = fgetcsv($file)) !== false) {
                // Expecting structure: ID, Name/Description, Amount
                if (count($row) < 3) {
                    continue;
                }

                $name = trim($row[1]);
                $amount = (float) str_replace(',', '', $row[2]);

                if (empty($name)) {
                    continue;
                }

                $client = Client::create([
                    'name' => $name,
                ]);

                if ($amount != 0) {
                    DebtLedger::create([
                        'client_id' => $client->id,
                        'type' => 'charge',
                        'amount' => $amount,
                        'notes' => 'Баланси аввал (Initial balance)',
                    ]);
                }
            }

            DB::commit();
            $this->command->info("Successfully imported clients and their initial debt ledger charges.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("Error importing data: " . $e->getMessage());
        }

        fclose($file);
    }
}
