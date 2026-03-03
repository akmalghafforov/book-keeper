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

    public function scopeWithBalance($query)
    {
        $charges = DebtLedger::selectRaw('SUM(amount)')
            ->whereColumn('client_id', 'clients.id')
            ->where('type', 'charge')
            ->getQuery();

        $payments = DebtLedger::selectRaw('SUM(amount)')
            ->whereColumn('client_id', 'clients.id')
            ->where('type', 'payment')
            ->getQuery();

        $creditNotes = DebtLedger::selectRaw('SUM(amount)')
            ->whereColumn('client_id', 'clients.id')
            ->where('type', 'credit_note')
            ->getQuery();

        return $query->select('clients.*')
            ->selectSub($charges, 'total_charges')
            ->selectSub($payments, 'total_payments')
            ->selectSub($creditNotes, 'total_credit_notes')
            ->selectRaw("COALESCE((SELECT SUM(amount) FROM debt_ledgers WHERE client_id = clients.id AND type = 'charge'), 0) - 
                         COALESCE((SELECT SUM(amount) FROM debt_ledgers WHERE client_id = clients.id AND type = 'payment'), 0) - 
                         COALESCE((SELECT SUM(amount) FROM debt_ledgers WHERE client_id = clients.id AND type = 'credit_note'), 0) as balance");
    }

    public function getTotalDebtAttribute(): float
    {
        $charges = $this->debtLedgers()->where('type', 'charge')->sum('amount');
        $payments = $this->debtLedgers()->where('type', 'payment')->sum('amount');
        $creditNotes = $this->debtLedgers()->where('type', 'credit_note')->sum('amount');

        return (float) ($charges - $payments - $creditNotes);
    }
}
