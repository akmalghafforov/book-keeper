<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionDraftImage extends Model
{
    public const MIME_TYPES = ['image/jpeg', 'image/png'];

    public $timestamps = false;

    protected $fillable = ['locator_index', 'mime_type', 'content'];

    protected $casts = ['locator_index' => 'integer'];

    protected $hidden = ['content'];
}
