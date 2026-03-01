<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
    ];

    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class);
    }

    public function debtLedgers(): HasMany
    {
        return $this->hasMany(DebtLedger::class);
    }

    public function getTotalDebtAttribute(): float
    {
        $charges = $this->debtLedgers()->where('type', 'charge')->sum('amount');
        $payments = $this->debtLedgers()->where('type', 'payment')->sum('amount');
        $creditNotes = $this->debtLedgers()->where('type', 'credit_note')->sum('amount');

        return (float) ($charges - $payments - $creditNotes);
    }
}
