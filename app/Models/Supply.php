<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supply extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'car_color',
        'car_number',
        'delivery_date',
    ];

    protected $casts = [
        'delivery_date' => 'date',
    ];

    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class);
    }
}
