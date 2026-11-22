<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\DebtLedger;
use App\Models\Distribution;
use App\Models\GeneratedReport;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderLedger;
use App\Models\Shop;
use App\Models\User;
use App\Services\GeneratedReportLedgerBoundaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionDraftTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/v1/transaction-drafts';

    private const WEB = '/admin/transaction-drafts/';

    private User $reviewer;

    private User $codex;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reviewer = User::factory()->create()->refresh();
        $this->codex = User::factory()->create(['email' => 'importer@example.test'])->refresh();
        $this->codex->forceFill(['api_only' => true])->save();
        $this->artisan('drafts:configure-access', ['--reviewer' => [$this->reviewer->email], '--codex' => $this->codex->email])->assertSuccessful();

        $this->token = Str::random(32);
        DB::table('personal_access_tokens')->insert(['user_id' => $this->codex->id, 'name' => 'Test', 'token' => hash('sha256', $this->token)]);
        $this->withoutVite();
        $this->withSession(['locale' => 'en']);
    }

    public function test_all_kinds_stay_out_of_posted_ledgers_until_review_and_distribution_approval_freezes_effects(): void
    {
        $this->freezeTime();
        $client = Client::factory()->create();
        $credit = Client::factory()->create();
        $provider = Provider::factory()->create();
        $product = Product::factory()->create(['default_provider_id' => $provider->id, 'buy_price' => 4]);
        $draft = $this->submit($this->suggestion('distribution', [
            'client_id' => (string) $client->id,
            'credit_client_id' => (string) $credit->id,
            'credit_client_price' => '5',
            'product_id' => (string) $product->id,
            'product_category_id' => (string) $product->product_category_id,
            'quantity' => '2',
            'quantity_unit' => 'per_ton',
            'price' => '6',
            'distribution_date' => '2026-11-19',
        ]))->assertCreated()->json('data.id');
        $this->travel(1)->seconds();
        $providerDraft = $this->submit($this->suggestion('provider_ledger', [
            'provider_id' => (string) $provider->id,
            'type' => 'payment',
            'amount' => '2.00',
            'payment_method' => 'cash',
            'currency' => 'USD',
            'exchange_rate' => '10',
            'provider_received_at' => '2026-11-19T22:15:00Z',
        ], 'provider-payment'))->assertCreated()->json('data.id');
        $this->travel(1)->seconds();
        $debtDraft = $this->submit($this->suggestion('debt_ledger', [
            'client_id' => (string) $client->id,
            'type' => 'payment',
            'amount' => '1.00',
            'payment_method' => 'card',
            'payment_purpose' => 'other',
            'currency' => 'TJS',
            'exchange_rate' => '1',
            'reference_id' => '999',
            'transaction_date' => '2026-11-20',
        ], 'customer-receipt'))->assertCreated()->json('data.id');

        $this->assertDatabaseCount('distributions', 0);
        $this->assertDatabaseCount('debt_ledgers', 0);
        $this->assertDatabaseCount('provider_ledgers', 0);
        $this->assertSame(0.0, $client->getTotalDebtAttribute());
        $this->assertSame(0.0, Provider::withBalance()->findOrFail($provider->id)->balance);
        $boundary = app(GeneratedReportLedgerBoundaryService::class);
        $report = new GeneratedReport(['type' => 'client_debt', 'last_included_ledger_id' => $boundary->snapshotLastIncludedLedgerId('client_debt')]);
        $this->assertSame(0, $report->last_included_ledger_id);

        $this->actingAs($this->reviewer)->get(route('admin.transaction-drafts.index'))
            ->assertOk()->assertSeeInOrder(['goods', 'provider-payment', 'customer-receipt']);
        foreach (['distributions' => 'goods', 'provider-ledgers' => 'provider-payment', 'debt-ledgers' => 'customer-receipt'] as $page => $slot) {
            $this->get('/admin/'.$page)->assertOk()->assertSee('Drafts Records')->assertDontSee($slot)->assertDontSee('Quick edit');
        }
        $this->get('/admin/operations')->assertOk();

        $preview = $this->postJson(self::WEB.$draft.'/preview')->assertOk();
        $preview->assertJsonCount(3, 'effects')->assertJsonPath('effects.0.amount', '12.0000');
        $approval = $this->approve($draft, 'approve-distribution')->assertOk()
            ->assertJsonPath('data.attributes.status', 'approved')
            ->assertJsonPath('data.attributes.approval.posted_values.subtotal', '12.0000')
            ->assertJsonCount(3, 'data.attributes.approval.generated_records');

        $providerApproval = $this->approve($providerDraft, 'approve-provider')->assertOk()
            ->assertJsonPath('data.attributes.approval.posted_values.amount', '20.0000')
            ->assertJsonPath('data.attributes.approval.posted_values.origin', 'manual');
        $providerRecord = ProviderLedger::findOrFail($providerApproval->json('data.attributes.posted_record.id'));
        $this->assertSame('2026-11-20', $providerRecord->transaction_date->toDateString());
        $this->assertNotNull($providerRecord->sort_order);

        $this->approve($debtDraft, 'approve-debt')->assertOk()
            ->assertJsonPath('data.attributes.approval.posted_values.payment_purpose', 'other')
            ->assertJsonPath('data.attributes.approval.posted_values.origin', 'manual');
        $this->assertDatabaseCount('debt_ledgers', 3);
        $this->assertDatabaseCount('provider_ledgers', 2);
        $this->assertSame(11.0, $client->getTotalDebtAttribute());
        $this->assertSame(0, $boundary->resolveReportLastIncludedLedgerId($report));

        $record = Distribution::findOrFail($approval->json('data.attributes.posted_record.id'));
        $record->update(['subtotal' => 15]);
        $record->delete();
        $this->withToken($this->token)->getJson(self::API.'/'.$draft)->assertOk()
            ->assertJsonPath('data.attributes.posted_record.available', false)
            ->assertJsonPath('data.attributes.approval', $approval->json('data.attributes.approval'));
        $this->flushHeaders()->approve($draft, 'approve-distribution')->assertOk()->assertContent($approval->getContent());
        $this->approve($draft, 'different-key')->assertConflict();
    }

    public function test_incomplete_edits_audit_explicit_null_and_atomic_edit_and_approve_with_stale_revisions(): void
    {
        $client = Client::factory()->create();
        $suggestion = $this->suggestion('debt_ledger', ['type' => 'payment', 'amount' => '100.00']);
        $submission = $this->submit($suggestion)->assertCreated()
            ->assertJsonValidationErrorFor('client_id', 'data.attributes.validation_issues');
        $id = $submission->json('data.id');

        $this->actingAs($this->reviewer)->withHeader('If-Match', '"1"')->patchJson(self::WEB.$id, ['fields' => ['notes' => null]])
            ->assertOk()->assertHeader('ETag', '"2"')
            ->assertJsonPath('data.attributes.events.1.changes.notes.before.present', false)
            ->assertJsonPath('data.attributes.events.1.changes.notes.after.present', true)
            ->assertJsonPath('data.attributes.events.1.changes.notes.after.value', null);
        $this->withHeader('If-Match', '"1"')->patchJson(self::WEB.$id, ['fields' => ['amount' => '200']])
            ->assertStatus(412)->assertHeader('ETag', '"2"')->assertJsonPath('data.attributes.current_fields.amount', '100.00');

        $this->approve($id, 'retry', '"2"', ['fields' => ['client_id' => $client->id, 'currency' => 'USD', 'exchange_rate' => '0', 'payment_purpose' => 'on_behalf_of']])
            ->assertUnprocessable()->assertJsonValidationErrors(['exchange_rate', 'payment_method', 'payer_name']);
        $this->assertDatabaseCount('transaction_draft_events', 2);

        $final = ['client_id' => $client->id, 'payment_method' => 'card', 'transaction_date' => '2026-11-19', 'currency' => 'USD', 'exchange_rate' => '10.25'];
        $this->approve($id, 'retry', '"2"', ['fields' => $final])->assertOk()->assertHeader('ETag', '"4"')
            ->assertJsonPath('data.attributes.original_fields', $suggestion['fields'])
            ->assertJsonPath('data.attributes.approval.reviewed_fields.payment_method', 'card')
            ->assertJsonPath('data.attributes.approval.posted_values.amount', '1025.00')
            ->assertJsonPath('data.attributes.events.2.actor_id', $this->reviewer->id)
            ->assertJsonPath('data.attributes.events.2.type', 'edited')
            ->assertJsonPath('data.attributes.events.3.type', 'approved');
        $this->withToken($this->token)->getJson(self::API.'?status=approved&corrected=true&kind=debt_ledger')->assertOk()
            ->assertJsonPath('data.0.id', $id)->assertJsonPath('meta.pagination.total', 1);
    }

    public function test_request_replay_and_durable_source_uniqueness_ignore_changed_locators(): void
    {
        $payload = $this->suggestion('debt_ledger', []);
        $payload['source']['additional_locators'] = [['message_key' => 'extra', 'sent_at' => '2026-11-19T11:00:00+05:00', 'export_id' => 'test-export', 'position' => 2]];
        $first = $this->submit($payload, 'first-key')->assertCreated();
        $this->submit($payload, 'first-key')->assertCreated()->assertContent($first->getContent());
        $id = $first->json('data.id');
        $payload['source']['line'] = 405;
        $payload['source']['export_id'] = 'another-export';
        $this->submit($payload)->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.attributes.source.line', 1);

        $this->travel(25)->hours();
        $this->submit($payload, 'first-key')->assertOk()->assertJsonPath('data.id', $id);
        $payload['fields'] = ['amount' => '12'];
        $this->submit($payload)->assertConflict()->assertJsonPath('existing_draft_id', $id);
        $payload['source']['event_slot'] = 'second-leg';
        $this->submit($payload)->assertCreated();
        $this->assertDatabaseCount('transaction_drafts', 2);
        $this->assertDatabaseCount('transaction_draft_events', 2);

        $payload['source']['excerpt'] = 'raw text must be rejected';
        $this->submit($payload)->assertUnprocessable()->assertJsonValidationErrors('source');
    }

    public function test_catalog_mismatches_and_invalid_dates_are_field_errors(): void
    {
        $client = Client::factory()->create();
        $shop = Shop::create(['client_id' => $client->id, 'name' => 'Store']);
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        $client->delete();
        $payload = $this->suggestion('distribution', [
            'client_id' => $client->id,
            'shop_id' => $shop->id,
            'credit_client_id' => $client->id,
            'product_category_id' => $other->product_category_id,
            'product_id' => $product->id,
            'quantity_unit' => 'per_bag',
            'quantity' => 1,
            'price' => 100,
            'distribution_date' => '2026-11-31',
        ]);
        $this->submit($payload)->assertUnprocessable()->assertJsonValidationErrors('fields.distribution_date');
        $payload['fields']['distribution_date'] = '2026-11-20';
        $draft = $this->submit($payload)->assertCreated()->json('data.id');

        $this->actingAs($this->reviewer);
        $this->approve($draft, 'invalid-catalog', '"1"', ['fields' => ['client_id' => Client::factory()->create()->id]])
            ->assertUnprocessable()->assertJsonValidationErrors(['shop_id', 'product_id', 'credit_client_id', 'credit_client_price']);
    }

    public function test_duplicate_confirmation_changes_after_a_pending_distribution_posts_and_dismissal_checks_the_match(): void
    {
        $client = Client::factory()->create();
        $product = Product::factory()->create();
        $distribution = $this->submit($this->suggestion('distribution', [
            'client_id' => $client->id, 'product_id' => $product->id, 'product_category_id' => $product->product_category_id,
            'quantity' => 2, 'price' => 5, 'quantity_unit' => 'per_bag', 'distribution_date' => '2026-11-19',
        ]))->assertCreated()->json('data.id');
        $debt = $this->submit($this->suggestion('debt_ledger', [
            'client_id' => $client->id, 'type' => 'charge', 'amount' => '10', 'transaction_date' => '2026-11-20',
        ], 'debt'))->assertCreated()->json('data.id');

        $this->actingAs($this->reviewer);
        $pending = $this->approve($debt, 'charge')->assertConflict()->assertJsonPath('matches.0.id', $distribution)->json('duplicate_confirmation');
        $distributionMatch = $this->approve($distribution, 'sale')->assertConflict()->json('duplicate_confirmation');
        $posted = $this->approve($distribution, 'sale', '"1"', ['duplicate_confirmation' => $distributionMatch])->assertOk();
        $ledgerId = $posted->json('data.attributes.approval.generated_records.0.id');
        $this->approve($debt, 'charge', '"1"', ['duplicate_confirmation' => $pending])
            ->assertConflict()->assertJsonPath('matches.0.id', $ledgerId)->assertJsonPath('matches.0.origin', 'distribution');
        $this->withHeader('If-Match', '"1"')->postJson(self::WEB.$debt.'/dismiss', ['reason' => 'already_recorded', 'posted_record_id' => '999'])->assertConflict();
        $this->postJson(self::WEB.$debt.'/dismiss', ['reason' => 'already_recorded', 'posted_record_id' => $ledgerId])->assertOk()
            ->assertJsonPath('data.attributes.events.1.details.existing_record.id', $ledgerId)
            ->assertJsonPath('data.attributes.matched_posted_record.id', $ledgerId)
            ->assertJsonPath('data.attributes.matched_posted_record.available', true);
        $this->assertDatabaseCount('debt_ledgers', 1);
    }

    public function test_api_only_permissions_block_live_writes_and_all_human_rules_are_explicit(): void
    {
        $draft = $this->submit($this->suggestion('debt_ledger', []))->assertCreated()->json('data.id');
        $this->withToken($this->token)->getJson('/api/v1/clients')->assertOk();
        $this->postJson('/api/v1/debt-ledgers')->assertForbidden();
        $this->postJson('/api/v1/provider-ledgers')->assertForbidden();
        $this->postJson('/api/v1/distributions')->assertForbidden();
        $this->postJson(self::WEB.$draft.'/approve')->assertForbidden();
        $this->getJson(route('admin.transaction-drafts.index'))->assertForbidden();
        $this->post('/login', ['email' => $this->codex->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->flushHeaders()->actingAs($this->codex)->postJson(self::WEB.$draft.'/approve')->assertForbidden();
        $this->get('/admin/distributions')->assertForbidden();
        $this->actingAs($this->reviewer)->get('/admin/database/download')->assertForbidden();

        $this->actingAs(User::factory()->create()->refresh())->patchJson(self::WEB.$draft)->assertForbidden();
        $this->get(route('admin.transaction-drafts.index'))->assertForbidden();
        $this->get('/admin/debt-ledgers')->assertOk()->assertDontSee('Drafts Records');
    }

    public function test_failure_after_ledger_creation_rolls_back_all_rows_and_events(): void
    {
        $client = Client::factory()->create();
        $product = Product::factory()->create();
        $draft = $this->submit($this->suggestion('distribution', [
            'client_id' => $client->id, 'product_id' => $product->id, 'product_category_id' => $product->product_category_id,
            'quantity' => 2, 'price' => 5, 'quantity_unit' => 'per_bag', 'distribution_date' => '2026-11-19',
        ]))->assertCreated()->json('data.id');
        DebtLedger::created(fn () => throw new \RuntimeException('Injected ledger failure'));
        $this->actingAs($this->reviewer);
        $this->approve($draft, 'rollback', '"1"', ['fields' => ['price' => '7']])->assertServerError();
        $this->assertDatabaseCount('debt_ledgers', 0);
        $this->assertDatabaseCount('distributions', 0);
        $this->assertDatabaseCount('transaction_draft_events', 1);
        $this->assertDatabaseHas('transaction_drafts', ['id' => $draft, 'status' => 'pending', 'revision' => 1]);
    }

    private function submit(array $payload, ?string $key = null)
    {
        return $this->postJson(self::API, $payload, ['Authorization' => 'Bearer '.$this->token, 'Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    private function approve(string $id, string $key, string $etag = '"1"', array $payload = [])
    {
        return $this->postJson(self::WEB.$id.'/approve', $payload, ['Idempotency-Key' => $key, 'If-Match' => $etag]);
    }

    private function suggestion(string $kind, array $fields, string $slot = 'goods'): array
    {
        return [
            'kind' => $kind,
            'source' => ['chat_key' => 'test-chat', 'message_key' => 'fingerprint-a', 'sent_at' => '2026-11-19T11:00:00+05:00', 'export_id' => 'test-export', 'line' => 1, 'event_slot' => $slot],
            'fields' => $fields,
        ];
    }
}
