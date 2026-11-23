<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_draft_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('draft_id')->constrained('transaction_drafts')->cascadeOnDelete();
            // Zero is the primary message. Supporting messages use their array index plus one.
            $table->unsignedTinyInteger('locator_index');
            $table->string('mime_type', 16);
            $table->mediumText('content');
            $table->unique(['draft_id', 'locator_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_draft_images');
    }
};
