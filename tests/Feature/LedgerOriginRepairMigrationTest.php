<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LedgerOriginRepairMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('distributions', fn (Blueprint $table) => $table->id());
        Schema::create('debt_ledgers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('type');
            $table->decimal('amount', 15, 2);
        });
        Schema::create('provider_ledgers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('distribution_id')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('provider_ledgers');
        Schema::dropIfExists('debt_ledgers');
        Schema::dropIfExists('distributions');

        parent::tearDown();
    }

    public function test_it_repairs_missing_columns_and_preserves_ledger_data(): void
    {
        DB::table('distributions')->insert(['id' => 1]);
        DB::table('debt_ledgers')->insert([
            ['id' => 1, 'reference_id' => 1, 'type' => 'charge', 'amount' => 25],
            ['id' => 2, 'reference_id' => 1, 'type' => 'payment', 'amount' => 10],
            ['id' => 3, 'reference_id' => 99, 'type' => 'credit_note', 'amount' => 5],
        ]);
        DB::table('provider_ledgers')->insert([
            ['id' => 1, 'distribution_id' => 1],
            ['id' => 2, 'distribution_id' => null],
        ]);

        $migration = require database_path('migrations/2026_10_22_000000_repair_missing_ledger_origin_columns.php');
        $migration->up();
        $migration->up();
        $migration->down();

        $this->assertDatabaseHas('debt_ledgers', ['id' => 1, 'origin' => 'distribution', 'amount' => 25]);
        $this->assertDatabaseHas('debt_ledgers', ['id' => 2, 'origin' => 'manual', 'amount' => 10]);
        $this->assertDatabaseHas('debt_ledgers', ['id' => 3, 'origin' => 'manual', 'amount' => 5]);
        $this->assertDatabaseHas('provider_ledgers', ['id' => 1, 'origin' => 'distribution']);
        $this->assertDatabaseHas('provider_ledgers', ['id' => 2, 'origin' => 'manual']);
    }

    public function test_it_preserves_existing_origins_when_only_one_table_needs_repair(): void
    {
        Schema::table('debt_ledgers', fn (Blueprint $table) => $table->string('origin')->default('manual'));
        DB::table('debt_ledgers')->insert(['type' => 'charge', 'amount' => 25, 'origin' => 'manual']);

        $migration = require database_path('migrations/2026_10_22_000000_repair_missing_ledger_origin_columns.php');
        $migration->up();

        $this->assertDatabaseHas('debt_ledgers', ['origin' => 'manual', 'amount' => 25]);
        $this->assertTrue(Schema::hasColumn('provider_ledgers', 'origin'));
    }
}
