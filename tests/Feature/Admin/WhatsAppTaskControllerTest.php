<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsAppTaskControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_whatsapp_tasks_page(): void
    {
        $message = $this->createMessage([
            'sender' => 'Амаки Хайруллоҳ',
            'body' => 'Мубинба 500 та мохир',
        ]);

        $response = $this
            ->actingAs(User::factory()->create())
            ->get(route('admin.whatsapp-tasks.index'));

        $response
            ->assertOk()
            ->assertSee('Create WhatsApp Task')
            ->assertSee($message->sender)
            ->assertSee($message->body);
    }

    public function test_admin_can_open_created_whatsapp_tasks_page(): void
    {
        $client = Client::factory()->create(['name' => 'Created Task Client']);
        $firstMessage = $this->createMessage(['body' => 'first attached task message']);
        $secondMessage = $this->createMessage(['body' => 'second attached task message']);

        $task = WhatsAppTask::query()->create([
            'task_type' => WhatsAppTask::TYPE_PAYMENT,
            'status' => 'pending',
            'client_id' => $client->id,
            'amount' => 250,
            'task_date' => '2026-04-30',
            'notes' => 'Created page note.',
        ]);
        $task->messages()->attach([$firstMessage->id, $secondMessage->id]);

        $response = $this
            ->actingAs(User::factory()->create())
            ->get(route('admin.whatsapp-tasks.created'));

        $response
            ->assertOk()
            ->assertSee(__('Created WhatsApp Tasks'))
            ->assertSee('name="search"', false)
            ->assertSee('name="status"', false)
            ->assertSee('name="task_type"', false)
            ->assertSee('name="client_id"', false)
            ->assertSee('#' . $task->id)
            ->assertSee('Created Task Client')
            ->assertSee('250.00')
            ->assertSee('2026-04-30')
            ->assertSee('Created page note.')
            ->assertSee($task->messages_count . ' ' . __('messages'))
            ->assertSee('pending');
    }

    public function test_created_whatsapp_tasks_page_filters_by_status_type_and_client(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();

        $matchingTask = WhatsAppTask::query()->create([
            'task_type' => WhatsAppTask::TYPE_PAYMENT,
            'status' => 'pending',
            'client_id' => $client->id,
            'amount' => 120,
            'task_date' => '2026-05-04',
            'notes' => 'Match me',
        ]);

        $excludedByStatus = WhatsAppTask::query()->create([
            'task_type' => WhatsAppTask::TYPE_PAYMENT,
            'status' => 'completed',
            'client_id' => $client->id,
            'amount' => 220,
            'task_date' => '2026-05-03',
            'notes' => 'Completed task',
        ]);

        $excludedByType = WhatsAppTask::query()->create([
            'task_type' => WhatsAppTask::TYPE_CLIENT_TRANSFER,
            'status' => 'pending',
            'client_id' => $otherClient->id,
            'credit_client_id' => $client->id,
            'amount' => 320,
            'task_date' => '2026-05-02',
            'notes' => 'Different type',
        ]);

        $this
            ->actingAs(User::factory()->create())
            ->get(route('admin.whatsapp-tasks.created', [
                'status' => 'pending',
                'task_type' => WhatsAppTask::TYPE_PAYMENT,
                'client_id' => $client->id,
            ]))
            ->assertOk()
            ->assertSee($matchingTask->notes)
            ->assertSee('#' . $matchingTask->id)
            ->assertDontSee($excludedByStatus->notes)
            ->assertDontSee($excludedByType->notes);
    }

    public function test_created_whatsapp_tasks_page_filters_by_search(): void
    {
        $matchingTask = WhatsAppTask::query()->create([
            'task_type' => WhatsAppTask::TYPE_PAYMENT,
            'status' => 'pending',
            'amount' => 75,
            'notes' => 'Searchable note',
        ]);

        $otherTask = WhatsAppTask::query()->create([
            'task_type' => WhatsAppTask::TYPE_PAYMENT,
            'status' => 'pending',
            'amount' => 150,
            'notes' => 'Unrelated note',
        ]);

        $message = $this->createMessage(['body' => 'Search this message']);
        $matchingTask->messages()->attach($message);

        $this
            ->actingAs(User::factory()->create())
            ->get(route('admin.whatsapp-tasks.created', ['search' => 'Searchable']))
            ->assertOk()
            ->assertSee($matchingTask->notes)
            ->assertDontSee($otherTask->notes);
    }

    public function test_admin_can_save_extracted_goods_pieces_values(): void
    {
        $client = Client::factory()->create();
        $product = Product::factory()->create(['default_unit' => 'per_piece']);
        $supplier = Supplier::factory()->create();
        $task = WhatsAppTask::query()->create([
            'task_type' => WhatsAppTask::TYPE_GOODS_PIECES,
            'status' => 'pending',
            'task_date' => '2026-05-04',
        ]);

        $response = $this
            ->actingAs(User::factory()->create())
            ->post(route('admin.whatsapp-tasks.extracted-goods-pieces.store', $task), [
                'supplier_id' => $supplier->id,
                'client_id' => $client->id,
                'product_id' => $product->id,
                'quantity' => '500',
                'price' => '12.50',
            ]);

        $response
            ->assertRedirect(route('admin.whatsapp-tasks.created'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('distributions', [
            'supplier_id' => $supplier->id,
            'client_id' => $client->id,
            'product_id' => $product->id,
            'quantity_unit' => 'per_piece',
            'quantity' => 500,
            'price' => 12.50,
            'subtotal' => 6250,
            'distribution_date' => '2026-05-04 00:00:00',
        ]);

        $this->assertDatabaseHas('whatsapp_tasks', [
            'id' => $task->id,
            'status' => 'completed',
            'client_id' => $client->id,
            'amount' => 6250,
        ]);
    }

    public function test_admin_cannot_save_extracted_goods_pieces_with_per_ton_product(): void
    {
        $client = Client::factory()->create();
        $product = Product::factory()->create(['default_unit' => 'per_ton']);
        $task = WhatsAppTask::query()->create([
            'task_type' => WhatsAppTask::TYPE_GOODS_PIECES,
            'status' => 'pending',
        ]);

        $response = $this
            ->actingAs(User::factory()->create())
            ->from(route('admin.whatsapp-tasks.created'))
            ->post(route('admin.whatsapp-tasks.extracted-goods-pieces.store', $task), [
                'client_id' => $client->id,
                'product_id' => $product->id,
                'quantity' => '500',
                'price' => '12.50',
            ]);

        $response
            ->assertRedirect(route('admin.whatsapp-tasks.created'))
            ->assertSessionHasErrors('product_id');

        $this->assertDatabaseCount('distributions', 0);
    }

    public function test_created_payment_task_shows_extracted_client_and_editable_amount(): void
    {
        $client = Client::factory()->create(['name' => 'Rahim Store']);
        $message = $this->createMessage([
            'body' => 'Rahim magazin oplata 750 somoni',
        ]);
        $task = WhatsAppTask::query()->create([
            'task_type' => WhatsAppTask::TYPE_PAYMENT,
            'status' => 'pending',
        ]);
        $task->messages()->attach($message);

        $response = $this
            ->actingAs(User::factory()->create())
            ->get(route('admin.whatsapp-tasks.created'));

        $response
            ->assertOk()
            ->assertSee($client->name)
            ->assertSee('name="search"', false)
            ->assertSee('name="status"', false)
            ->assertSee('name="task_type"', false)
            ->assertSee('name="client_id"', false);
    }

    public function test_created_payment_task_extracts_amount_when_number_touches_text(): void
    {
        $client = Client::factory()->create(['name' => 'Rahim Store']);
        $message = $this->createMessage([
            'body' => 'Rahim 70хаз',
        ]);
        $task = WhatsAppTask::query()->create([
            'task_type' => WhatsAppTask::TYPE_PAYMENT,
            'status' => 'pending',
        ]);
        $task->messages()->attach($message);

        $response = $this
            ->actingAs(User::factory()->create())
            ->get(route('admin.whatsapp-tasks.created'));

        $response
            ->assertOk()
            ->assertSee($client->name);
    }

    public function test_admin_can_save_extracted_payment_values(): void
    {
        $client = Client::factory()->create();
        $task = WhatsAppTask::query()->create([
            'task_type' => WhatsAppTask::TYPE_PAYMENT,
            'status' => 'pending',
            'task_date' => '2026-05-04',
        ]);

        $response = $this
            ->actingAs(User::factory()->create())
            ->post(route('admin.whatsapp-tasks.extracted-payment.store', $task), [
                'client_id' => $client->id,
                'amount' => '750.25',
            ]);

        $response
            ->assertRedirect(route('admin.whatsapp-tasks.created'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('debt_ledgers', [
            'client_id' => $client->id,
            'type' => 'payment',
            'amount' => 750.25,
            'transaction_date' => '2026-05-04 00:00:00',
            'notes' => 'Auto-generated payment from WhatsApp Task #' . $task->id,
        ]);

        $this->assertDatabaseHas('whatsapp_tasks', [
            'id' => $task->id,
            'status' => 'completed',
            'client_id' => $client->id,
            'amount' => 750.25,
        ]);
    }

    public function test_review_page_shows_one_pending_task_and_previews_distribution_without_creating_it(): void
    {
        $client = Client::factory()->create(['name' => 'Review Client']);
        $product = Product::factory()->create([
            'name' => 'Review Cement',
            'default_unit' => 'per_piece',
        ]);
        $supplier = Supplier::factory()->create([
            'car_number' => 'RV-100',
            'car_color' => 'blue',
        ]);
        $message = $this->createMessage([
            'body' => 'Review Client Review Cement 10 ta narx 25 RV-100',
        ]);
        $task = WhatsAppTask::query()->create([
            'task_type' => WhatsAppTask::TYPE_GOODS_PIECES,
            'status' => 'pending',
            'task_date' => '2026-05-04',
        ]);
        $task->messages()->attach($message);

        $this
            ->actingAs(User::factory()->create())
            ->get(route('admin.whatsapp-tasks.review'))
            ->assertOk()
            ->assertSee('#' . $task->id)
            ->assertSee($message->body)
            ->assertSeeInOrder([
                'name="client_id"',
                'data-review-candidate="clientId"',
                'name="product_id"',
                'data-review-candidate="productId"',
                'name="supplier_id"',
                'data-review-candidate="supplierId"',
                'name="quantity"',
                'data-review-candidate="quantity"',
                'name="price"',
                'data-review-candidate="price"',
            ], false)
            ->assertSee('@click="quantity = \'10\'"', false)
            ->assertSee('@click="price = \'25\'"', false);

        $this
            ->actingAs(User::factory()->create())
            ->post(route('admin.whatsapp-tasks.review.preview', $task), [
                'task_type' => WhatsAppTask::TYPE_GOODS_PIECES,
                'supplier_id' => $supplier->id,
                'client_id' => $client->id,
                'product_id' => $product->id,
                'quantity' => '10',
                'price' => '25',
                'record_date' => '04/05/2026',
            ])
            ->assertOk()
            ->assertSee(__('Distribution preview'))
            ->assertSee(__('Original message'))
            ->assertSee($message->body)
            ->assertSee('250.00');

        $this->assertDatabaseCount('distributions', 0);
        $this->assertDatabaseMissing('whatsapp_tasks', [
            'id' => $task->id,
            'status' => 'completed',
        ]);
    }

    public function test_review_page_allows_changing_task_type_before_confirming(): void
    {
        $client = Client::factory()->create(['name' => 'Type Switch Client']);
        $message = $this->createMessage([
            'body' => 'Type Switch Client payment 75',
        ]);
        $task = WhatsAppTask::query()->create([
            'task_type' => WhatsAppTask::TYPE_GOODS_PIECES,
            'status' => 'pending',
            'task_date' => '2026-05-04',
        ]);
        $task->messages()->attach($message);

        $this
            ->actingAs(User::factory()->create())
            ->get(route('admin.whatsapp-tasks.review'))
            ->assertOk()
            ->assertSee('x-model="taskType"', false)
            ->assertSee('x-show="taskType ===', false)
            ->assertSee('paymentAmountCandidates', false)
            ->assertSee('name="task_type"', false)
            ->assertSee('value="' . WhatsAppTask::TYPE_GOODS_PIECES . '"', false)
            ->assertSee('value="' . WhatsAppTask::TYPE_PAYMENT . '"', false);

        $this
            ->actingAs(User::factory()->create())
            ->post(route('admin.whatsapp-tasks.review.preview', $task), [
                'task_type' => WhatsAppTask::TYPE_PAYMENT,
                'client_id' => $client->id,
                'amount' => '75',
                'record_date' => '04/05/2026',
            ])
            ->assertOk()
            ->assertSee(__('Debt payment preview'))
            ->assertSee('name="amount"', false)
            ->assertSee('x-show="taskType === \'' . WhatsAppTask::TYPE_PAYMENT . '\'"', false);

        $this
            ->actingAs(User::factory()->create())
            ->post(route('admin.whatsapp-tasks.review.confirm', $task), [
                'task_type' => WhatsAppTask::TYPE_PAYMENT,
                'client_id' => $client->id,
                'amount' => '75',
                'record_date' => '04/05/2026',
            ])
            ->assertRedirect(route('admin.whatsapp-tasks.review'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('whatsapp_tasks', [
            'id' => $task->id,
            'status' => 'completed',
            'task_type' => WhatsAppTask::TYPE_PAYMENT,
            'client_id' => $client->id,
            'amount' => 75,
        ]);

        $this->assertDatabaseHas('debt_ledgers', [
            'client_id' => $client->id,
            'type' => 'payment',
            'amount' => 75,
            'transaction_date' => '2026-05-04 00:00:00',
            'notes' => 'Auto-generated payment from WhatsApp Task #' . $task->id,
        ]);
    }

    public function test_admin_can_save_extracted_client_transfer_values(): void
    {
        $fromClient = Client::factory()->create();
        $toClient = Client::factory()->create();
        $task = WhatsAppTask::query()->create([
            'task_type' => WhatsAppTask::TYPE_CLIENT_TRANSFER,
            'status' => 'pending',
            'task_date' => '2026-05-04',
        ]);

        $response = $this
            ->actingAs(User::factory()->create())
            ->post(route('admin.whatsapp-tasks.extracted-client-transfer.store', $task), [
                'client_id' => $fromClient->id,
                'credit_client_id' => $toClient->id,
                'quantity' => '20',
                'price' => '35',
            ]);

        $response
            ->assertRedirect(route('admin.whatsapp-tasks.created'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('debt_ledgers', [
            'client_id' => $fromClient->id,
            'type' => 'charge',
            'amount' => 700,
            'transaction_date' => '2026-05-04 00:00:00',
            'notes' => 'Auto-generated client transfer charge from WhatsApp Task #' . $task->id,
        ]);

        $this->assertDatabaseHas('debt_ledgers', [
            'client_id' => $toClient->id,
            'type' => 'credit_note',
            'amount' => 700,
            'transaction_date' => '2026-05-04 00:00:00',
            'notes' => 'Auto-generated client transfer credit from WhatsApp Task #' . $task->id,
        ]);

        $this->assertDatabaseHas('whatsapp_tasks', [
            'id' => $task->id,
            'status' => 'completed',
            'client_id' => $fromClient->id,
            'credit_client_id' => $toClient->id,
            'amount' => 700,
        ]);
    }

    public function test_admin_whatsapp_tasks_page_shows_recent_messages_first(): void
    {
        $oldestMessage = $this->createMessage([
            'message_at' => now()->subDays(2),
            'body' => 'oldest task message',
        ]);
        $middleMessage = $this->createMessage([
            'message_at' => now()->subDay(),
            'body' => 'middle task message',
        ]);
        $newestMessage = $this->createMessage([
            'message_at' => now(),
            'body' => 'newest task message',
        ]);

        $response = $this
            ->actingAs(User::factory()->create())
            ->get(route('admin.whatsapp-tasks.index'));

        $response
            ->assertOk()
            ->assertSeeInOrder([
                $newestMessage->body,
                $middleMessage->body,
                $oldestMessage->body,
            ]);
    }

    public function test_admin_whatsapp_tasks_page_groups_messages_by_task_and_deleted_tabs(): void
    {
        $unusedMessage = $this->createMessage(['body' => 'message without created task']);
        $usedMessage = $this->createMessage(['body' => 'message with created task']);
        $deletedMessage = $this->createMessage(['body' => 'deleted whatsapp message']);

        $task = WhatsAppTask::query()->create([
            'task_type' => WhatsAppTask::TYPE_GOODS_PIECES,
            'status' => 'pending',
        ]);
        $task->messages()->attach($usedMessage);
        $deletedMessage->delete();

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('admin.whatsapp-tasks.index'))
            ->assertOk()
            ->assertSee('Messages without task')
            ->assertSee($unusedMessage->body)
            ->assertDontSee($usedMessage->body)
            ->assertDontSee($deletedMessage->body);

        $this
            ->actingAs($user)
            ->get(route('admin.whatsapp-tasks.index', ['tab' => 'with_task']))
            ->assertOk()
            ->assertSee($usedMessage->body)
            ->assertDontSee($unusedMessage->body)
            ->assertDontSee($deletedMessage->body);

        $this
            ->actingAs($user)
            ->get(route('admin.whatsapp-tasks.index', ['tab' => 'deleted']))
            ->assertOk()
            ->assertSee($deletedMessage->body)
            ->assertDontSee($unusedMessage->body)
            ->assertDontSee($usedMessage->body);
    }

    public function test_admin_can_remove_selected_whatsapp_messages(): void
    {
        $removedMessage = $this->createMessage(['body' => 'message selected for removal']);
        $keptMessage = $this->createMessage(['body' => 'message kept in inbox']);

        $response = $this
            ->actingAs(User::factory()->create())
            ->post(route('admin.whatsapp-tasks.messages.destroy'), [
                'tab' => 'without_task',
                'message_ids' => [$removedMessage->id],
            ]);

        $response
            ->assertRedirect(route('admin.whatsapp-tasks.index', ['tab' => 'without_task']))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('whatsapp_messages', [
            'id' => $removedMessage->id,
        ]);
        $this->assertDatabaseHas('whatsapp_messages', [
            'id' => $keptMessage->id,
            'deleted_at' => null,
        ]);

        $this
            ->actingAs(User::factory()->create())
            ->get(route('admin.whatsapp-tasks.index', ['tab' => 'deleted']))
            ->assertOk()
            ->assertSee($removedMessage->body)
            ->assertDontSee($keptMessage->body);
    }

    public function test_admin_can_create_payment_task_from_multiple_messages(): void
    {
        $client = Client::factory()->create();
        $firstMessage = $this->createMessage(['body' => 'payment 100']);
        $secondMessage = $this->createMessage(['body' => 'receipt photo']);

        $response = $this
            ->actingAs(User::factory()->create())
            ->post(route('admin.whatsapp-tasks.store'), [
                'task_type' => WhatsAppTask::TYPE_PAYMENT,
                'message_ids' => [$firstMessage->id, $secondMessage->id],
                'client_id' => $client->id,
                'amount' => '100',
                'task_date' => '30/04/2026',
                'notes' => 'Imported from chat.',
            ]);

        $response
            ->assertRedirect(route('admin.whatsapp-tasks.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('whatsapp_tasks', [
            'task_type' => WhatsAppTask::TYPE_PAYMENT,
            'client_id' => $client->id,
            'amount' => 100,
            'task_date' => '2026-04-30 00:00:00',
            'notes' => 'Imported from chat.',
        ]);

        $this->assertDatabaseHas('whatsapp_message_task', [
            'whatsapp_message_id' => $firstMessage->id,
        ]);
        $this->assertDatabaseHas('whatsapp_message_task', [
            'whatsapp_message_id' => $secondMessage->id,
        ]);
    }

    public function test_admin_can_create_task_without_optional_details(): void
    {
        $message = $this->createMessage();

        $response = $this
            ->actingAs(User::factory()->create())
            ->post(route('admin.whatsapp-tasks.store'), [
                'task_type' => WhatsAppTask::TYPE_CLIENT_TRANSFER,
                'message_ids' => [$message->id],
            ]);

        $response
            ->assertRedirect(route('admin.whatsapp-tasks.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('whatsapp_tasks', [
            'task_type' => WhatsAppTask::TYPE_CLIENT_TRANSFER,
            'client_id' => null,
            'credit_client_id' => null,
            'amount' => null,
            'task_date' => null,
            'notes' => null,
        ]);
    }

    public function test_admin_whatsapp_tasks_page_shows_attached_image_preview(): void
    {
        Storage::fake('public');

        $message = $this->createMessage([
            'body' => 'IMG-20260302-WA0001.jpg (файл добавлен)',
            'attachment_filename' => 'IMG-20260302-WA0001.jpg',
        ]);

        Storage::disk('public')->put($message->attachmentStoragePath(), 'image-bytes');

        $this
            ->actingAs(User::factory()->create())
            ->get(route('admin.whatsapp-tasks.index'))
            ->assertOk()
            ->assertSee('<img', false)
            ->assertSee($message->attachmentUrl(), false)
            ->assertSee('IMG-20260302-WA0001.jpg');
    }

    private function createMessage(array $overrides = []): WhatsAppMessage
    {
        return WhatsAppMessage::query()->create(array_merge([
            'message_at' => now(),
            'sender' => 'Sender',
            'body' => 'Message body',
            'attachment_filename' => null,
            'is_system' => false,
            'source_archive' => 'chat.zip',
            'source_text_file' => 'chat.txt',
            'source_line' => random_int(1, 100000),
            'import_hash' => fake()->unique()->sha256(),
            'imported_at' => now(),
        ], $overrides));
    }
}
