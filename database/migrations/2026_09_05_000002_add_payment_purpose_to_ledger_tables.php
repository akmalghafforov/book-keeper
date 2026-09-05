<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debt_ledgers', function (Blueprint $table) {
            $table->string('payment_purpose')->nullable()->after('payment_method');
            $table->string('payer_name')->nullable()->after('payment_purpose');
        });

        Schema::table('provider_ledgers', function (Blueprint $table) {
            $table->string('payment_purpose')->nullable()->after('payment_method');
            $table->string('payer_name')->nullable()->after('payment_purpose');
        });
    }

    public function down(): void
    {
        Schema::table('debt_ledgers', function (Blueprint $table) {
            $table->dropColumn(['payment_purpose', 'payer_name']);
        });

        Schema::table('provider_ledgers', function (Blueprint $table) {
            $table->dropColumn(['payment_purpose', 'payer_name']);
        });
    }
};
