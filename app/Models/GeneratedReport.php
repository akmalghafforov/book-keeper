<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneratedReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'format',
        'parameters',
        'status',
        'file_path',
        'error_message',
    ];

    protected $casts = [
        'parameters' => 'array',
    ];
}
