<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\DebtLedger;
use App\Models\Distribution;
use App\Models\GeneratedReport;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Provider;
use App\Models\ProviderLedger;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\TransactionDraft;
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
            ->assertOk()->assertSeeInOrder(['Price', 'Total', 'Per Ton', '>6</td>', '>12</td>', '2 USD', '1 TJS'], false);
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
        $this->get(self::WEB.'?tab=approved&kind=distribution')->assertOk()->assertSeeInOrder(['Price', 'Total', '>6</td>', '>12</td>', 'View details'], false);

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

    public function test_approval_defaults_to_cash_only_for_payments_without_a_method(): void
    {
        $client = Client::factory()->create();
        $provider = Provider::factory()->create();
        $payment = ['type' => 'payment', 'currency' => 'TJS', 'exchange_rate' => '1', 'amount' => 5];
        $debt = ['client_id' => $client->id, 'transaction_date' => '2026-11-19'];
        $providerFields = ['provider_id' => $provider->id, 'provider_received_at' => '2026-11-19T09:15:00+05:00'];
        $debts = $this->submit($this->suggestion('debt_ledger', $debt + $payment))->assertCreated()->json('data.id');
        $providers = $this->submit($this->suggestion('provider_ledger', $providerFields + $payment + ['payment_method' => null], 'provider'))
            ->assertCreated()->json('data.id');
        $charge = $this->submit($this->suggestion('debt_ledger', $debt + ['type' => 'charge', 'amount' => 7], 'charge'))
            ->assertCreated()->json('data.id');

        $this->actingAs($this->reviewer);
        $this->postJson(self::WEB.$debts.'/preview')->assertOk()->assertJsonPath('payment.amount_tjs', '5.00');
        foreach ([$debts, $providers] as $id) {
            $this->approve($id, 'cash-default')->assertOk()
                ->assertJsonPath('data.attributes.approval.posted_values.payment_method', 'cash')
                ->assertJsonPath('data.attributes.current_fields.payment_method', 'cash')
                ->assertJsonPath('data.attributes.events.1.changes.payment_method.after.value', 'cash');
        }
        $this->approve($charge, 'charge')->assertOk()
            ->assertJsonPath('data.attributes.approval.posted_values.payment_method', null)
            ->assertJsonMissingPath('data.attributes.current_fields.payment_method');
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
            ->assertUnprocessable()->assertJsonValidationErrors(['exchange_rate', 'payer_name']);
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

    public function test_reviewer_completes_distribution_with_zero_and_preserves_ai_and_manual_values(): void
    {
        $client = Client::factory()->create();
        $credit = Client::factory()->create();
        $provider = Provider::factory()->create();
        $product = Product::factory()->create(['default_provider_id' => $provider->id]);
        $original = [
            'client_id' => $client->id, 'credit_client_id' => $credit->id,
            'product_category_id' => $product->product_category_id, 'product_id' => $product->id,
            'quantity' => 0, 'quantity_unit' => 'per_ton', 'price' => null,
        ];
        $id = $this->submit($this->suggestion('distribution', $original))->assertCreated()->json('data.id');
        $url = self::WEB.$id.'/missing-fields';
        $this->actingAs($this->reviewer)->withHeader('If-Match', '"1"');
        $this->patchJson($url, ['fields' => []])->assertUnprocessable()->assertJsonValidationErrors('fields');
        $this->patchJson($url, ['fields' => (object) []])->assertUnprocessable()
            ->assertJsonValidationErrors(['distribution_date', 'price', 'credit_client_price']);
        $this->patchJson($url, ['fields' => ['quantity' => 1]])->assertUnprocessable()->assertJsonValidationErrors('fields.quantity');
        $this->patchJson(self::WEB.$id, ['fields' => ['price' => 5]])->assertOk()->assertHeader('ETag', '"2"');
        $this->patchJson($url, ['fields' => ['price' => 8]])->assertStatus(412)
            ->assertHeader('ETag', '"2"')->assertJsonPath('data.attributes.current_fields.price', 5);
        $this->withHeader('If-Match', '"2"')->patchJson($url, ['fields' => ['price' => 8]])
            ->assertUnprocessable()->assertJsonValidationErrors('fields.price');

        $this->patchJson($url, ['fields' => [
            'distribution_date' => '2026-11-19', 'credit_client_price' => 0, 'provider_buy_price' => 0,
        ]])->assertOk()->assertHeader('ETag', '"3"')
            ->assertJsonPath('data.attributes.status', 'pending')
            ->assertJsonPath('data.attributes.original_fields', $original)
            ->assertJsonPath('data.attributes.current_fields.price', 5)
            ->assertJsonPath('data.attributes.current_fields.credit_client_price', 0)
            ->assertJsonPath('data.attributes.current_fields.provider_buy_price', 0)
            ->assertJsonPath('data.attributes.events.2.type', 'edited');
        $this->assertDatabaseCount('distributions', 0);
        $this->assertDatabaseCount('debt_ledgers', 0);
        $this->assertDatabaseCount('provider_ledgers', 0);
    }

    public function test_reviewer_completes_provider_payment_using_application_time_and_tjs_rate(): void
    {
        $provider = Provider::factory()->create();
        $id = $this->submit($this->suggestion('provider_ledger', [
            'provider_id' => $provider->id, 'type' => 'payment', 'amount' => '20',
            'currency' => 'TJS', 'payment_purpose' => 'on_behalf_of',
        ]))->assertCreated()->json('data.id');
        $url = self::WEB.$id.'/missing-fields';
        $this->actingAs($this->reviewer)->withHeader('If-Match', '"1"');
        $this->patchJson($url, ['fields' => (object) []])->assertUnprocessable()
            ->assertJsonValidationErrors(['payer_name', 'payment_method', 'provider_received_at']);
        $this->patchJson($url, ['fields' => [
            'payer_name' => 'Payer', 'payment_method' => 'cash', 'provider_received_at' => '2026-11-19T09:15',
            'notes' => '  ',
        ]])->assertOk()->assertHeader('ETag', '"2"')
            ->assertJsonPath('data.attributes.status', 'pending')
            ->assertJsonPath('data.attributes.current_fields.exchange_rate', 1)
            ->assertJsonPath('data.attributes.current_fields.provider_received_at', '2026-11-19T09:15:00+05:00')
            ->assertJsonMissingPath('data.attributes.current_fields.notes');
        $this->assertDatabaseCount('provider_ledgers', 0);
    }

    public function test_debt_completion_requires_reviewer_and_respects_payment_only_reference(): void
    {
        $client = Client::factory()->create();
        $charge = $this->submit($this->suggestion('debt_ledger', [
            'client_id' => $client->id, 'type' => 'charge', 'amount' => '20',
        ]))->assertCreated()->json('data.id');
        $payment = $this->submit($this->suggestion('debt_ledger', [
            'client_id' => $client->id, 'type' => 'payment', 'amount' => '20', 'currency' => 'USD',
            'payment_method' => 'cash', 'exchange_rate' => '10',
        ], 'payment'))->assertCreated()->json('data.id');
        $fields = ['transaction_date' => '2026-11-19', 'reference_id' => 0];
        $this->withToken($this->token)->patchJson(self::WEB.$payment.'/missing-fields', ['fields' => $fields])->assertForbidden();
        $this->flushHeaders()->actingAs(User::factory()->create()->refresh())->patchJson(self::WEB.$payment.'/missing-fields', ['fields' => $fields])->assertForbidden();
        $this->actingAs($this->reviewer);
        $this->patchJson(self::WEB.$payment.'/missing-fields', ['fields' => $fields])->assertStatus(412);
        $this->withHeader('If-Match', '"1"')->patchJson(self::WEB.$charge.'/missing-fields', ['fields' => $fields])
            ->assertUnprocessable()->assertJsonValidationErrors('fields.reference_id');
        $this->patchJson(self::WEB.$payment.'/missing-fields', ['fields' => $fields])->assertOk()
            ->assertJsonPath('data.attributes.current_fields.reference_id', 0)
            ->assertJsonPath('data.attributes.status', 'pending');
        $this->assertDatabaseCount('debt_ledgers', 0);
    }

    public function test_request_replay_and_durable_source_uniqueness_ignore_changed_locators(): void
    {
        $payload = $this->suggestion('debt_ledger', []);
        $payload['source']['additional_locators'] = [['message_key' => 'extra', 'sent_at' => '2026-11-19T11:00:00+05:00', 'export_id' => 'test-export', 'position' => 2]];
        $payload['source']['message_text'] = 'Original message';
        $first = $this->submit($payload, 'first-key')->assertCreated();
        $this->submit($payload, 'first-key')->assertCreated()->assertContent($first->getContent());
        $id = $first->json('data.id');
        $payload['source']['message_text'] = 'Changed message';
        $this->submit($payload, 'first-key')->assertConflict();
        $payload['source']['line'] = 405;
        $payload['source']['export_id'] = 'another-export';
        $this->submit($payload)->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.attributes.source.line', 1);

        $this->assertSame('Original message', TransactionDraft::findOrFail($id)->source['message_text']);

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

    public function test_message_cards_show_saved_distribution_and_payment_values(): void
    {
        $client = Client::factory()->create(['name' => 'Example client']);
        $shop = Shop::create(['client_id' => $client->id, 'name' => 'Example shop']);
        $product = Product::factory()->create(['name' => 'Example product']);
        $supplier = Supplier::create(['car_number' => '1234 AB 01', 'car_color' => 'white']);
        $provider = Provider::factory()->create(['name' => 'Example provider']);
        $distribution = [
            'client_id' => $client->id, 'product_id' => $product->id, 'supplier_id' => $supplier->id,
            'quantity' => '49', 'price' => '500', 'quantity_unit' => 'per_bag',
        ];
        $shopDraft = $this->suggestion('distribution', $distribution + ['shop_id' => $shop->id]);
        $shopDraft['source']['message_text'] = "Shop distribution\n49 bags";
        $shopId = $this->submit($shopDraft)->assertCreated()->json('data.id');
        $payment = $this->suggestion('debt_ledger', [
            'client_id' => $client->id, 'type' => 'payment', 'amount' => '4650', 'currency' => 'TJS',
            'payment_method' => 'cash', 'payment_purpose' => 'vehicle_and_labor',
        ], 'vehicle-and-labor');
        $paymentId = $this->submit($payment)->assertCreated()->json('data.id');

        $bulk = $this->suggestion('distribution', array_replace($distribution, [
            'quantity' => '38.775', 'price' => '1000', 'quantity_unit' => 'per_ton',
        ]));
        $bulk['source']['message_key'] = 'bulk';
        $bulk['source']['message_text'] = 'Bulk distribution';
        $bulkId = $this->submit($bulk)->assertCreated()->json('data.id');
        $freight = $this->suggestion('provider_ledger', [
            'provider_id' => $provider->id, 'type' => 'payment', 'amount' => '9303', 'currency' => 'TJS',
            'payment_method' => 'cash', 'payment_purpose' => 'vehicle',
        ], 'freight');
        $freight['source']['message_key'] = 'bulk';
        $freightId = $this->submit($freight)->assertCreated()->json('data.id');

        $standalone = $this->suggestion('debt_ledger', [
            'client_id' => $client->id, 'type' => 'payment', 'amount' => '126.5', 'currency' => 'USD',
            'payment_method' => 'ds', 'notes' => 'Bank receipt',
        ]);
        $standalone['source']['message_key'] = 'standalone';
        $standalone['source']['message_text'] = 'Standalone payment';
        $this->submit($standalone)->assertCreated();

        $queue = $this->actingAs($this->reviewer)->get(self::WEB)->assertOk()
            ->assertSee('2026-11-19 11:00:00 +05:00')
            ->assertSeeInOrder(['Shop distribution', '49 bags', 'Example shop', '24,500', '1234 AB 01', '4,650 TJS', 'Money for vehicle and labor'])
            ->assertSeeInOrder(['Bulk distribution', '38.775 Per Ton', '38,775', '9,303 TJS'])
            ->assertSeeInOrder(['Standalone payment', '126.5 USD', 'ДС', 'Bank receipt']);
        $cards = $queue->viewData('messages')->getCollection()->keyBy('source.primary.message_key');
        $this->assertCount(3, $cards);
        $this->assertTrue($cards['fingerprint-a']['showShop']);
        $this->assertNull($cards['fingerprint-a']['freightId']);
        $this->assertSame('24,500', $cards['fingerprint-a']['values'][$shopId]['total']);
        $this->assertSame('4,650 TJS', $cards['fingerprint-a']['values'][$paymentId]['amount']);
        $this->assertFalse($cards['bulk']['showShop']);
        $this->assertSame('38,775', $cards['bulk']['values'][$bulkId]['total']);
        $this->assertSame($freightId, $cards['bulk']['freightId']);
        $this->assertSame('9,303 TJS', $cards['bulk']['values'][$freightId]['amount']);

        $this->withSession(['locale' => 'tj'])->get(self::WEB)->assertOk()
            ->assertSeeInOrder(['Паёми WhatsApp', 'Мизоҷ', 'Маҳсулот', 'Миқдор', 'Нарх', 'Ҷамъ', 'Рақами мошин', 'Нархи боркашонӣ'])
            ->assertSeeInOrder(['Амалиёт', 'Маблағ', 'Усул', 'Тавсиф', 'ДС']);

        $freight['source']['event_slot'] = 'second-vehicle';
        $this->submit($freight)->assertCreated();
        $this->get(self::WEB)->assertOk()->assertViewHas('messages', function ($messages) {
            $bulk = $messages->getCollection()->firstWhere('source.primary.message_key', 'bulk');

            return $bulk['freightId'] === null && $bulk['drafts']['provider_ledger']->count() === 2;
        });
    }

    public function test_cement_grade_choices_use_active_pairs_and_the_individual_drafts_source(): void
    {
        $bagged = ProductCategory::where('name', 'Цемент')->sole();
        $bulk = ProductCategory::where('name', 'Рассыпной')->sole();
        $product = fn ($category, $name) => Product::factory()->create(['product_category_id' => $category->id, 'name' => $name]);
        $product($bagged, 'Мохир M400');
        $m500 = $product($bagged, 'Мохир M500');
        $product($bagged, 'Яксин М400');
        $product($bagged, 'Яксин М500')->delete();
        $product($bulk, 'Яксин М500');
        $huaxinM400 = $product($bagged, 'Хуаксин M400');
        $huaxinM500 = $product($bagged, 'Хуаксин M500');

        $fields = ['product_category_id' => $bagged->id, 'quantity_unit' => 'per_bag', 'quantity' => '500'];
        $ids = [];
        foreach (['known' => 'мохир500шт', 'missing' => null, 'ambiguous' => 'хуак или Ясин', 'unavailable' => 'Яксин'] as $slot => $text) {
            $draft = $this->suggestion('distribution', $fields, $slot);
            if ($text !== null) {
                $draft['source']['additional_locators'] = [[
                    'message_key' => 'related-'.$slot, 'sent_at' => '2026-11-19T11:00:00+05:00',
                    'export_id' => 'test-export', 'line' => 2, 'message_text' => $text,
                ]];
            }
            $ids[$slot] = $this->submit($draft)->assertCreated()->json('data.id');
        }

        $queue = $this->actingAs($this->reviewer)->get(self::WEB)->assertOk();
        $card = $queue->viewData('messages')->first();
        $families = $card['review']['cementFamilies'];
        $this->assertSame('Мохир', $families[$ids['known']]);
        $this->assertNull($families[$ids['missing']]);
        $this->assertNull($families[$ids['ambiguous']]);
        $this->assertSame('Яксин', $families[$ids['unavailable']]);
        $pairs = (array) $queue->viewData('cementReview')['gradePairs'];
        $this->assertSame($m500->id, $pairs['Мохир']['M500']);
        $this->assertSame(['M400' => $huaxinM400->id, 'M500' => $huaxinM500->id], $pairs['Хуаксин']);
        $this->assertArrayNotHasKey('Яксин', $pairs);

        $url = self::WEB.$ids['known'];
        $this->withHeader('If-Match', '"1"')->patchJson($url, ['fields' => ['quantity' => '510']])->assertOk();
        $selection = ['fields' => ['product_id' => $m500->id]];
        $this->patchJson($url, $selection)->assertStatus(412)->assertHeader('ETag', '"2"');
        $this->withHeader('If-Match', '"2"')->patchJson($url, $selection)->assertOk()
            ->assertJsonPath('data.attributes.status', 'pending')
            ->assertJsonPath('data.attributes.current_fields.quantity', '510');
        $card = $this->get(self::WEB)->assertOk()->viewData('messages')->first();
        $this->assertSame('Мохир M500', $card['values'][$ids['known']]['product_id']);
        $this->assertNull($card['values'][$ids['missing']]['product_id']);
        $this->assertDatabaseCount('distributions', 0);
        $this->assertDatabaseCount('debt_ledgers', 0);
        $this->assertDatabaseCount('provider_ledgers', 0);
    }

    public function test_overweight_bulk_distribution_can_be_reviewed_and_approved_at_the_reported_weight(): void
    {
        $bulk = ProductCategory::where('name', 'Рассыпной')->sole();
        $product = Product::factory()->create([
            'name' => 'Рассыпной Хуаксин M500', 'default_unit' => 'per_ton', 'product_category_id' => $bulk->id,
        ]);
        $draft = $this->suggestion('distribution', [
            'client_id' => Client::factory()->create()->id,
            'product_category_id' => $product->product_category_id, 'product_id' => $product->id,
            'quantity_unit' => 'per_ton', 'quantity' => '40.01', 'price' => '1000',
            'distribution_date' => '2026-11-19',
        ]);
        $id = $this->submit($draft)->assertCreated()->json('data.id');
        $queue = $this->actingAs($this->reviewer)->get(self::WEB)->assertOk()->assertSee('40.01 Per Ton');
        $this->assertSame($product->product_category_id, $queue->viewData('cementReview')['bulkCategory']);
        $this->postJson(self::WEB.$id.'/preview')->assertOk()->assertJsonCount(0, 'validation_issues');
        $this->approve($id, 'overweight-approval')->assertOk()
            ->assertJsonPath('data.attributes.status', 'approved')
            ->assertJsonPath('data.attributes.approval.reviewed_fields.quantity', '40.01');
        $this->assertDatabaseHas('distributions', ['product_id' => $product->id, 'quantity' => 40.01]);
    }

    public function test_queue_paginates_filtered_messages_and_reviews_sibling_drafts_individually(): void
    {
        $this->freezeTime();
        for ($i = 0; $i < 9; $i++) {
            $payload = $this->suggestion('debt_ledger', []);
            $payload['source']['message_key'] = 'earlier-'.$i;
            $this->submit($payload)->assertCreated();
        }

        $client = Client::factory()->create();
        $this->travel(1)->seconds();
        $payment = $this->suggestion('debt_ledger', [
            'client_id' => $client->id, 'type' => 'payment', 'amount' => '10', 'currency' => 'TJS',
            'exchange_rate' => '1', 'payment_method' => 'ds', 'transaction_date' => '2026-11-19',
        ]);
        $payment['source']['message_text'] = 'First chat';
        $paymentId = $this->submit($payment)->assertCreated()->json('data.id');

        $this->travel(1)->seconds();
        $otherChat = $payment;
        $otherChat['source']['chat_key'] = 'other-chat';
        $otherChat['source']['message_text'] = 'Second chat';
        $otherChat['fields']['amount'] = '20';
        $this->submit($otherChat)->assertCreated();

        $this->travel(1)->seconds();
        $sibling = $this->suggestion('distribution', [], 'sibling');
        $siblingId = $this->submit($sibling)->assertCreated()->json('data.id');

        $this->actingAs($this->reviewer)->get(self::WEB)->assertOk()
            ->assertViewHas('pendingCount', 12)
            ->assertSee('First chat')->assertDontSee('Second chat')
            ->assertSee('data-draft-id="'.$paymentId.'"', false)->assertSee('data-draft-id="'.$siblingId.'"', false)
            ->assertViewHas('messages', fn ($messages) => $messages->total() === 11 && $messages->count() === 10);
        $this->get(self::WEB.'?page=2')->assertOk()->assertSee('Second chat')->assertDontSee('First chat')
            ->assertDontSee($siblingId);
        $this->get(self::WEB.'?kind=distribution')->assertOk()
            ->assertSee($siblingId)->assertDontSee($paymentId)->assertSee('>—</td>', false)
            ->assertViewHas('pendingCount', 12)
            ->assertViewHas('messages', fn ($messages) => $messages->total() === 1);

        $this->approve($paymentId, 'approve-sibling-payment')->assertOk();
        $this->get(self::WEB.'?kind=distribution')->assertOk()
            ->assertSee($siblingId)->assertSee('Quick edit')->assertViewHas('pendingCount', 11);
        $this->get(self::WEB.'?tab=approved')->assertOk()
            ->assertSee($paymentId)->assertSee('View details')->assertDontSee($siblingId);
    }

    public function test_draft_cards_sort_by_whatsapp_send_time_before_pagination(): void
    {
        $this->freezeTime();
        for ($i = 10; $i >= 0; $i--) {
            $payload = $this->suggestion('debt_ledger', []);
            $payload['source']['message_key'] = 'chronology-'.$i;
            $payload['source']['message_text'] = sprintf('Chronology card %02d', $i);
            $payload['source']['sent_at'] = $i === 10
                ? '2026-11-19T06:10:00Z'
                : sprintf('2026-11-19T11:%02d:00+0500', $i);
            $this->submit($payload)->assertCreated();
            $this->travel(1)->seconds();
        }

        $this->actingAs($this->reviewer)->get(self::WEB)->assertOk()
            ->assertSeeInOrder(['Chronology card 00', 'Chronology card 05', 'Chronology card 09'])
            ->assertDontSee('Chronology card 10');
        $this->get(self::WEB.'?page=2')->assertOk()
            ->assertSee('Chronology card 10')->assertDontSee('Chronology card 00');
    }

    public function test_original_messages_are_escaped_and_visible_to_reviewers_across_tabs(): void
    {
        $client = Client::factory()->create();
        $primary = "  Қабул шуд 👋\n<script>alert('receipt')</script>\n  ";
        $supporting = "Тасдиқ шуд\n<img src=x onerror=alert(1)>";
        $payload = $this->suggestion('debt_ledger', [
            'client_id' => $client->id, 'type' => 'payment', 'amount' => '10',
            'currency' => 'TJS', 'exchange_rate' => '1', 'payment_method' => 'cash', 'transaction_date' => '2026-11-19',
        ]);
        $references = $payload['source'] + ['additional_locators' => [[
            'message_key' => 'receipt-confirmation',
            'sent_at' => '2026-11-19T11:02:00+05:00',
            'export_id' => 'test-export',
            'position' => 7,
        ]]];
        $payload['source'] = $references + ['message_text' => $primary];
        $payload['source']['additional_locators'][0]['message_text'] = $supporting;

        $id = $this->submit($payload)->assertCreated()->assertJsonPath('data.attributes.source', $references)->json('data.id');
        $payload['source']['event_slot'] = 'dismiss-this';
        $dismissId = $this->submit($payload)->assertCreated()->json('data.id');
        $legacy = $this->suggestion('debt_ledger', [], 'legacy');
        $legacy['source']['message_key'] = 'legacy-message';
        $this->submit($legacy)->assertCreated();
        $this->assertSame($payload['fields'], TransactionDraft::findOrFail($id)->original_fields);
        $this->assertSame($primary, TransactionDraft::findOrFail($id)->source['message_text']);
        $this->assertSame($supporting, TransactionDraft::findOrFail($id)->source['additional_locators'][0]['message_text']);

        $this->withToken($this->token)->getJson(self::API.'/'.$id)
            ->assertOk()->assertJsonPath('data.attributes.source', $references);
        $this->getJson(self::API)->assertOk()->assertJsonMissingPath('data.0.attributes.source.message_text')
            ->assertJsonMissingPath('data.0.attributes.source.additional_locators.0.message_text')
            ->assertDontSee('Қабул шуд')->assertDontSee('Тасдиқ шуд');
        $this->getJson(self::WEB)->assertForbidden()->assertDontSee('Қабул шуд');

        $this->flushHeaders()->actingAs(User::factory()->create()->refresh())->get(self::WEB)
            ->assertForbidden()->assertDontSee('Қабул шуд');

        $this->actingAs($this->reviewer)->get(self::WEB)->assertOk()
            ->assertSeeInOrder(['WhatsApp message', $primary, 'Related source messages', '2026-11-19T11:02:00+05:00', 'test-export #7', $supporting])
            ->assertDontSee('<script>alert', false)->assertDontSee('<img src=x', false)
            ->assertSee('test-chat · test-export #1');
        $this->withHeader('If-Match', '"1"')->postJson(self::WEB.$dismissId.'/dismiss', ['reason' => 'Duplicate suggestion'])->assertOk();
        $posted = $this->approve($id, 'approve-message')->assertOk()
            ->assertJsonPath('data.attributes.source', $references);
        $this->assertNull(DebtLedger::findOrFail($posted->json('data.attributes.posted_record.id'))->notes);

        foreach (['approved', 'dismissed'] as $tab) {
            $this->get(self::WEB.'?tab='.$tab)->assertOk()->assertSee($primary)->assertSee($supporting);
        }
    }

    public function test_archived_photos_are_served_only_to_reviewers_on_their_draft(): void
    {
        $payload = $this->suggestion('distribution', []);
        $payload['source']['message_text'] = "<Без медиафайлов>\n31.04т";
        $payload['source']['additional_locators'] = [[
            'message_key' => 'photo-confirmation',
            'sent_at' => '2026-11-19T11:02:00+05:00',
            'export_id' => 'test-export',
            'line' => 7,
            'message_text' => '<Без медиафайлов>',
        ]];
        $id = $this->submit($payload)->assertCreated()->json('data.id');
        $payload['source']['event_slot'] = 'another-draft';
        $otherId = $this->submit($payload)->assertCreated()->json('data.id');

        $content = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/jS8AAAAASUVORK5CYII=';
        foreach ([0, 1] as $index) {
            $photos[$index] = DB::table('transaction_draft_images')->insertGetId([
                'draft_id' => $id,
                'locator_index' => $index,
                'mime_type' => 'image/png',
                'content' => $content,
            ]);
        }
        $primaryUrl = route('admin.transaction-drafts.image', [$id, $photos[0]]);
        $supportUrl = route('admin.transaction-drafts.image', [$id, $photos[1]]);

        $this->get($primaryUrl)->assertRedirect(route('login'));
        $this->withToken($this->token)->get($primaryUrl)->assertForbidden();
        $this->getJson(self::API.'/'.$id)->assertOk()->assertDontSee('/images/');
        $this->flushHeaders()->actingAs(User::factory()->create()->refresh())->get($primaryUrl)->assertForbidden();

        $this->actingAs($this->reviewer)->get(self::WEB)->assertOk()
            ->assertSeeInOrder(['31.04т', $primaryUrl, 'Related source messages', 'test-export #7', $supportUrl])
            ->assertSee('<Без медиафайлов>');
        foreach ([$primaryUrl, $supportUrl] as $url) {
            $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')
                ->assertHeader('X-Content-Type-Options', 'nosniff')->assertContent(base64_decode($content));
        }
        $this->get(route('admin.transaction-drafts.image', [$otherId, $photos[0]]))->assertNotFound();
        $this->withHeader('If-Match', '"1"')->postJson(self::WEB.$id.'/dismiss', ['reason' => 'Dismissed suggestion'])->assertOk();
        $this->get(self::WEB.'?tab=dismissed')->assertOk()->assertSee($primaryUrl)->assertSee($supportUrl)
            ->assertDontSee('<Без медиафайлов>');
    }

    public function test_message_limits_count_unicode_characters_for_each_locator(): void
    {
        $payload = $this->suggestion('debt_ledger', []);
        $payload['source']['message_text'] = str_repeat('🌿', 4096);
        $payload['source']['additional_locators'] = array_fill(0, 20, [
            'message_key' => 'extra',
            'sent_at' => '2026-11-19T11:02:00+05:00',
            'export_id' => 'test-export',
            'line' => 2,
            'message_text' => $payload['source']['message_text'],
        ]);
        $this->submit($payload)->assertCreated();

        $payload['source']['message_text'] .= '🌿';
        $payload['source']['additional_locators'][0]['message_text'] .= '🌿';
        $payload['source']['additional_locators'][1]['message_text'] = ['invalid'];
        $payload['source']['additional_locators'][] = $payload['source']['additional_locators'][0];
        $this->submit($payload)->assertUnprocessable()->assertJsonValidationErrors([
            'source.message_text', 'source.additional_locators', 'source.additional_locators.0.message_text', 'source.additional_locators.1.message_text',
        ]);
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
