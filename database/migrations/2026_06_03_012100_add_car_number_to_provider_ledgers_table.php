<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('provider_ledgers', function (Blueprint $table) {
            $table->string('car_number', 50)
                ->nullable()
                ->after('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_ledgers', function (Blueprint $table) {
            $table->dropColumn('car_number');
        });
    }
};
