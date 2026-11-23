<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionDraftImage extends Model
{
    public const MIME_TYPES = ['image/jpeg', 'image/png'];

    public $timestamps = false;

    protected $hidden = ['content'];
}
