<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
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
}
