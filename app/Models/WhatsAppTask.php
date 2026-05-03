<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WhatsAppTask extends Model
{
    public const TYPE_GOODS_PIECES = 'goods_debt_pieces';
    public const TYPE_GOODS_TONS = 'goods_debt_tons';
    public const TYPE_CLIENT_TRANSFER = 'client_debt_transfer';
    public const TYPE_PAYMENT = 'client_payment';

    public const TYPES = [
        self::TYPE_GOODS_PIECES,
        self::TYPE_GOODS_TONS,
        self::TYPE_CLIENT_TRANSFER,
        self::TYPE_PAYMENT,
    ];

    public const TYPE_LABELS = [
        self::TYPE_GOODS_PIECES => 'Debt for goods paid by pieces',
        self::TYPE_GOODS_TONS => 'Debt for goods paid by tons',
        self::TYPE_CLIENT_TRANSFER => 'Debt transfer between clients',
        self::TYPE_PAYMENT => 'Debt payment of a client',
    ];

    protected $table = 'whatsapp_tasks';

    protected $fillable = [
        'task_type',
        'status',
        'client_id',
        'credit_client_id',
        'amount',
        'task_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'task_date' => 'date',
        ];
    }

    public function taskTypeLabel(): string
    {
        return self::TYPE_LABELS[$this->task_type] ?? str_replace('_', ' ', $this->task_type);
    }

    public function messages(): BelongsToMany
    {
        return $this->belongsToMany(
            WhatsAppMessage::class,
            'whatsapp_message_task',
            'whatsapp_task_id',
            'whatsapp_message_id',
        )
            ->withTimestamps();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creditClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'credit_client_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
