<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ENDPOINTS = ['transaction_drafts.submit' => 'POST', 'transaction_drafts.view' => 'GET'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('api_only')->default(false);
        });

        Schema::create('transaction_drafts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('kind', 30);
            $table->string('status', 12)->default('pending');
            $table->unsignedInteger('revision')->default(1);
            $table->boolean('corrected')->default(false);
            $table->string('chat_key', 128);
            $table->string('message_key', 128);
            $table->string('event_slot', 128);
            $table->json('source');
            $table->json('original_fields');
            $table->json('current_fields');
            $table->unsignedBigInteger('submitted_by');
            $table->unsignedBigInteger('posted_record_id')->nullable();
            $table->json('approval')->nullable();
            $table->timestamps();
            $table->unique(['chat_key', 'message_key', 'event_slot'], 'draft_source_unique');
            $table->unique(['kind', 'posted_record_id'], 'draft_posted_record_unique');
            $table->index(['kind', 'status', 'created_at']);
            $table->index(['status', 'corrected', 'created_at']);
        });

        Schema::create('transaction_draft_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('draft_id')->constrained('transaction_drafts')->restrictOnDelete();
            $table->unsignedBigInteger('actor_id');
            $table->string('type', 12);
            $table->unsignedInteger('revision');
            $table->json('changes');
            $table->json('details')->nullable();
            $table->string('occurred_at', 32);
            $table->string('approval_key')->nullable();
            $table->string('request_digest', 64)->nullable();
            $table->unique(['draft_id', 'revision']);
        });

        foreach ([...array_keys(self::ENDPOINTS), 'transaction_drafts.review', 'database.operate'] as $code) {
            DB::table('permissions')->insert(['code' => $code, 'name' => $code, 'created_at' => now(), 'updated_at' => now()]);
        }

        foreach (self::ENDPOINTS as $code => $method) {
            DB::table('api_endpoints')->insert([
                'endpoint_key' => $code,
                'display_name' => $code,
                'required_permission' => $code,
                'method' => $method,
                'route_template' => '/api/v1/transaction-drafts',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('api_endpoints')->whereIn('endpoint_key', array_keys(self::ENDPOINTS))->delete();
        DB::table('permissions')->whereIn('code', [...array_keys(self::ENDPOINTS), 'transaction_drafts.review', 'database.operate'])->delete();
        Schema::dropIfExists('transaction_draft_events');
        Schema::dropIfExists('transaction_drafts');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('api_only'));
    }
};
