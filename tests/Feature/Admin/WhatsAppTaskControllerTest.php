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
            ->assertSee('#' . $task->id)
            ->assertSee('Created Task Client')
            ->assertSee('250.00')
            ->assertSee('2026-04-30')
            ->assertSee('Created page note.')
            ->assertSee('first attached task message')
            ->assertSee('second attached task message');
    }

    public function test_created_goods_pieces_task_shows_extracted_value_selection_form(): void
    {
        $client = Client::factory()->create(['name' => 'Ahmad Market']);
        $product = Product::factory()->create([
            'name' => 'Mohir Cement',
            'default_unit' => 'per_piece',
        ]);
        $tonProduct = Product::factory()->create([
            'name' => 'Mohir Tons',
            'default_unit' => 'per_ton',
        ]);
        $supplier = Supplier::factory()->create([
            'car_number' => 'AA-1234',
            'car_color' => 'white',
        ]);
        $message = $this->createMessage([
            'body' => 'Ahmad bozori Mohir 500 ta narx 12.50 moshin AA-1234',
        ]);

        $task = WhatsAppTask::query()->create([
            'task_type' => WhatsAppTask::TYPE_GOODS_PIECES,
            'status' => 'pending',
        ]);
        $task->messages()->attach($message);

        $response = $this
            ->actingAs(User::factory()->create())
            ->get(route('admin.whatsapp-tasks.created'));

        $response
            ->assertOk()
            ->assertSee('name="client_id"', false)
            ->assertSee('name="product_id"', false)
            ->assertSee('name="quantity"', false)
            ->assertSee('name="price"', false)
            ->assertSee('name="supplier_id"', false)
            ->assertSee($client->name)
            ->assertSee($product->name)
            ->assertDontSee($tonProduct->name)
            ->assertSee('<option value="500">500</option>', false)
            ->assertSee('<option value="12.50">12.50</option>', false)
            ->assertSee($supplier->car_number);
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
            ->assertSee(route('admin.whatsapp-tasks.extracted-payment.store', $task), false)
            ->assertSee('name="client_id"', false)
            ->assertSee('name="amount"', false)
            ->assertSee('value="750"', false)
            ->assertSee($client->name);
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
            ->assertSee(route('admin.whatsapp-tasks.extracted-payment.store', $task), false)
            ->assertSee('name="amount"', false)
            ->assertSee('value="70"', false)
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

    public function test_created_client_transfer_task_shows_two_clients_and_editable_quantity_price(): void
    {
        $fromClient = Client::factory()->create(['name' => 'Said Shop']);
        $toClient = Client::factory()->create(['name' => 'Karim Market']);
        $message = $this->createMessage([
            'body' => 'Said qarz Karim ga 20 ta narx 35',
        ]);
        $task = WhatsAppTask::query()->create([
            'task_type' => WhatsAppTask::TYPE_CLIENT_TRANSFER,
            'status' => 'pending',
        ]);
        $task->messages()->attach($message);

        $response = $this
            ->actingAs(User::factory()->create())
            ->get(route('admin.whatsapp-tasks.created'));

        $response
            ->assertOk()
            ->assertSee(route('admin.whatsapp-tasks.extracted-client-transfer.store', $task), false)
            ->assertSee('name="client_id"', false)
            ->assertSee('name="credit_client_id"', false)
            ->assertSee('name="quantity"', false)
            ->assertSee('name="price"', false)
            ->assertSeeInOrder([
                'name="quantity"',
                'value="20"',
                'name="price"',
                'value="35"',
            ], false)
            ->assertSee($fromClient->name)
            ->assertSee($toClient->name);
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
