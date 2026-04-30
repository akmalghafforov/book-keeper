<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->timestamp('message_at');
            $table->string('sender')->nullable();
            $table->text('body')->nullable();
            $table->string('attachment_filename')->nullable();
            $table->boolean('is_system')->default(false);
            $table->string('source_archive');
            $table->string('source_text_file');
            $table->unsignedInteger('source_line');
            $table->string('import_hash')->unique();
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->index('message_at');
            $table->index('sender');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
