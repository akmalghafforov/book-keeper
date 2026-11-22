<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionDraftEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['draft_id', 'approval_key', 'request_digest'];

    protected $casts = [
        'actor_id' => 'integer',
        'changes' => 'array',
        'details' => 'array',
    ];
}
