<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
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
