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

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function debtLedgers(): HasMany
    {
        return $this->hasMany(DebtLedger::class);
    }

    public function getLatestReportAttribute()
    {
        return GeneratedReport::where('parameters->client_id', $this->id)
            ->where('type', 'single_client_debt')
            ->where('status', 'completed')
            ->latest()
            ->first();
    }

    public function scopeWithBalance($query)
    {
        $charges = DebtLedger::whereColumn('debt_ledgers.client_id', 'clients.id')
            ->where('type', 'charge');

        $payments = DebtLedger::whereColumn('debt_ledgers.client_id', 'clients.id')
            ->where('type', 'payment');

        $creditNotes = DebtLedger::whereColumn('debt_ledgers.client_id', 'clients.id')
            ->where('type', 'credit_note');

        return $query->select('clients.*')
            ->selectSub($charges->selectRaw('SUM(amount)'), 'total_charges')
            ->selectSub($payments->selectRaw('SUM(amount)'), 'total_payments')
            ->selectSub($creditNotes->selectRaw('SUM(amount)'), 'total_credit_notes')
            ->selectRaw("COALESCE((SELECT SUM(amount) FROM debt_ledgers WHERE client_id = clients.id AND type = 'charge' AND deleted_at IS NULL), 0) - 
                         COALESCE((SELECT SUM(amount) FROM debt_ledgers WHERE client_id = clients.id AND type = 'payment' AND deleted_at IS NULL), 0) - 
                         COALESCE((SELECT SUM(amount) FROM debt_ledgers WHERE client_id = clients.id AND type = 'credit_note' AND deleted_at IS NULL), 0) as balance");
    }

    public function getTotalDebtAttribute(): float
    {
        $charges = $this->debtLedgers()->where('type', 'charge')->sum('amount');
        $payments = $this->debtLedgers()->where('type', 'payment')->sum('amount');
        $creditNotes = $this->debtLedgers()->where('type', 'credit_note')->sum('amount');

        return (float) ($charges - $payments - $creditNotes);
    }
}
