<?php

namespace App\Models;

use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppMessage extends Model
{
    use SoftDeletes;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'message_at',
        'sender',
        'body',
        'attachment_filename',
        'is_system',
        'source_archive',
        'source_text_file',
        'source_line',
        'import_hash',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'message_at' => 'datetime',
            'is_system' => 'boolean',
            'imported_at' => 'datetime',
        ];
    }

    public function attachmentIsImage(): bool
    {
        if (! $this->attachment_filename) {
            return false;
        }

        return in_array(
            mb_strtolower(pathinfo($this->attachment_filename, PATHINFO_EXTENSION)),
            ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif'],
            true,
        );
    }

    public function attachmentStoragePath(): ?string
    {
        if (! $this->attachment_filename) {
            return null;
        }

        return self::attachmentStoragePathFor($this->source_archive, $this->attachment_filename);
    }

    public function attachmentUrl(): ?string
    {
        $path = $this->attachmentStoragePath();

        if (! $path || ! $this->attachmentIsImage() || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public static function attachmentStoragePathFor(string $sourceArchive, string $attachmentFilename): string
    {
        return 'whatsapp-attachments/'
            . sha1($sourceArchive)
            . '/'
            . self::safeAttachmentFilename($attachmentFilename);
    }

    public static function safeAttachmentFilename(string $attachmentFilename): string
    {
        $filename = basename(str_replace('\\', '/', $attachmentFilename));
        $filename = preg_replace('/[\x00-\x1F\x7F]+/u', '_', $filename) ?: 'attachment';

        return trim($filename) !== '' ? $filename : 'attachment';
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(
            WhatsAppTask::class,
            'whatsapp_message_task',
            'whatsapp_message_id',
            'whatsapp_task_id',
        )
            ->withTimestamps();
    }
}
