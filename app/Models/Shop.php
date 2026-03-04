<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected $fillable = [
        'client_id',
        'name',
        'address',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
