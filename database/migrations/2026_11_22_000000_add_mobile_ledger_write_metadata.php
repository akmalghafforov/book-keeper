<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->string('response_etag')->nullable();
        });

        Schema::table('provider_ledgers', function (Blueprint $table): void {
            $table->unsignedBigInteger('api_revision')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('provider_ledgers', function (Blueprint $table): void {
            $table->dropColumn('api_revision');
        });

        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->dropColumn('response_etag');
        });
    }
};
