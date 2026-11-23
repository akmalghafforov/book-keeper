<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_draft_images', function (Blueprint $table): void {
            $table->index(['draft_id', 'locator_index']);
            $table->dropUnique(['draft_id', 'locator_index']);
            $table->unsignedTinyInteger('locator_index')->nullable()->change();
        });
    }

    public function down(): void
    {
        // The old schema cannot represent draft-level images or multiple images at a locator.
        $images = DB::table('transaction_draft_images');
        if ((clone $images)->whereNull('locator_index')->exists()
            || $images->groupBy('draft_id', 'locator_index')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('The transaction draft image data requires the current schema.');
        }

        Schema::table('transaction_draft_images', function (Blueprint $table): void {
            $table->unsignedTinyInteger('locator_index')->change();
            $table->unique(['draft_id', 'locator_index']);
            $table->dropIndex(['draft_id', 'locator_index']);
        });
    }
};
