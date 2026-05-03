<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppZipMessageImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class WhatsAppZipImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_groups_multiline_messages_and_detects_media(): void
    {
        $text = implode("\n", [
            '01.03.2026, 15:01 - Сообщения и звонки защищены сквозным шифрованием.',
            '02.03.2026, 08:41 - Амаки Хайруллоҳ: ‎IMG-20260302-WA0001.jpg (файл добавлен)',
            '32.08',
            '02.03.2026, 08:42 - Амаки Хайруллоҳ: Шавкатбаа 1120с нарх',
        ]);

        $messages = app(WhatsAppZipMessageImporter::class)
            ->parseText($text, 'chat.zip', 'chat.txt');

        $this->assertCount(3, $messages);
        $this->assertTrue($messages[0]['is_system']);
        $this->assertNull($messages[0]['sender']);
        $this->assertFalse($messages[1]['is_system']);
        $this->assertSame('Амаки Хайруллоҳ', $messages[1]['sender']);
        $this->assertSame("IMG-20260302-WA0001.jpg (файл добавлен)\n32.08", $messages[1]['body']);
        $this->assertSame('IMG-20260302-WA0001.jpg', $messages[1]['attachment_filename']);
    }

    public function test_import_command_creates_one_database_row_per_message(): void
    {
        $zipPath = storage_path('framework/testing/whatsapp-export.zip');
        $this->createZip($zipPath, implode("\n", [
            '01.03.2026, 15:01 - ‎Амаки Хайруллоҳ создал(-а) группу "Цемет"',
            '01.03.2026, 22:17 - Амаки Хайруллоҳ: Мубинба 500 та мохир',
            '01.03.2026, 22:17 - Амаки Хайруллоҳ: Нах47,5',
        ]));

        $this->artisan('whatsapp:import-zip', ['path' => $zipPath])
            ->expectsOutput('Created: 3')
            ->expectsOutput('Skipped: 0')
            ->assertSuccessful();

        $this->assertDatabaseCount('whatsapp_messages', 3);
        $this->assertDatabaseHas('whatsapp_messages', [
            'sender' => 'Амаки Хайруллоҳ',
            'body' => 'Мубинба 500 та мохир',
            'source_archive' => 'whatsapp-export.zip',
            'source_text_file' => 'chat.txt',
            'source_line' => 2,
        ]);
        $firstImportedAt = WhatsAppMessage::query()->min('imported_at');

        $this->artisan('whatsapp:import-zip', ['path' => $zipPath])
            ->expectsOutput('Created: 0')
            ->expectsOutput('Skipped: 3')
            ->assertSuccessful();

        $this->assertDatabaseCount('whatsapp_messages', 3);
        $this->assertSame($firstImportedAt, WhatsAppMessage::query()->min('imported_at'));
        $this->assertSame(1, WhatsAppMessage::query()->where('is_system', true)->count());
    }

    public function test_import_skips_duplicates_when_zip_filename_changes(): void
    {
        $text = implode("\n", [
            '01.03.2026, 22:17 - Амаки Хайруллоҳ: Мубинба 500 та мохир',
            '01.03.2026, 22:17 - Амаки Хайруллоҳ: Нах47,5',
        ]);
        $firstZipPath = storage_path('framework/testing/first-export.zip');
        $secondZipPath = storage_path('framework/testing/second-export.zip');
        $this->createZip($firstZipPath, $text);
        $this->createZip($secondZipPath, $text);

        $importer = app(WhatsAppZipMessageImporter::class);

        $firstResult = $importer->import($firstZipPath);
        $secondResult = $importer->import($secondZipPath);

        $this->assertSame(2, $firstResult['created']);
        $this->assertSame(0, $firstResult['skipped']);
        $this->assertSame(0, $secondResult['created']);
        $this->assertSame(2, $secondResult['skipped']);
        $this->assertDatabaseCount('whatsapp_messages', 2);
    }

    public function test_import_extracts_attached_images_for_preview(): void
    {
        Storage::fake('public');

        $zipPath = storage_path('framework/testing/whatsapp-image-export.zip');
        $this->createZip($zipPath, implode("\n", [
            '02.03.2026, 08:41 - Амаки Хайруллоҳ: ‎IMG-20260302-WA0001.jpg (файл добавлен)',
            '32.08',
        ]), [
            'IMG-20260302-WA0001.jpg' => 'image-bytes',
        ]);

        app(WhatsAppZipMessageImporter::class)->import($zipPath);

        $message = WhatsAppMessage::query()->firstOrFail();

        Storage::disk('public')->assertExists($message->attachmentStoragePath());
        $this->assertSame('IMG-20260302-WA0001.jpg', $message->attachment_filename);
        $this->assertNotNull($message->attachmentUrl());
    }

    public function test_admin_can_upload_zip_and_run_import_from_ui(): void
    {
        $zipPath = storage_path('framework/testing/ui-export.zip');
        $this->createZip($zipPath, implode("\n", [
            '01.03.2026, 15:01 - ‎Амаки Хайруллоҳ создал(-а) группу "Цемет"',
            '01.03.2026, 22:17 - Амаки Хайруллоҳ: Мубинба 500 та мохир',
        ]));

        $uploadedFile = new UploadedFile(
            $zipPath,
            'whatsapp-chat.zip',
            'application/zip',
            null,
            true,
        );

        $response = $this
            ->actingAs(User::factory()->create())
            ->post(route('admin.whatsapp-imports.store'), [
                'zip_file' => $uploadedFile,
            ]);

        $response
            ->assertRedirect(route('admin.whatsapp-imports.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('import_result.created', 2)
            ->assertSessionHas('import_result.skipped', 0);

        $this->assertDatabaseCount('whatsapp_messages', 2);
    }

    /**
     * @param  array<string, string>  $files
     */
    private function createZip(string $path, string $text, array $files = []): void
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $this->assertTrue($zip->addFromString('chat.txt', $text));

        foreach ($files as $name => $contents) {
            $this->assertTrue($zip->addFromString($name, $contents));
        }

        $this->assertTrue($zip->close());
    }
}
