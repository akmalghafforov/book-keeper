<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debt_ledgers', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('type');
        });

        Schema::table('provider_ledgers', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('debt_ledgers', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });

        Schema::table('provider_ledgers', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
