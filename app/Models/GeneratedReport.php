<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

class GeneratedReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'serial_number',
        'name',
        'type',
        'format',
        'parameters',
        'last_included_ledger_id',
        'status',
        'file_path',
        'error_message',
    ];

    protected $casts = [
        'parameters' => 'array',
        'serial_number' => 'integer',
        'last_included_ledger_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::created(function (GeneratedReport $report): void {
            if ($report->serial_number !== null) {
                return;
            }

            $report->forceFill([
                'serial_number' => $report->id,
            ])->saveQuietly();
        });
    }

    public function getFormattedSerialNumberAttribute(): string
    {
        return str_pad((string) ($this->serial_number ?? $this->id), 6, '0', STR_PAD_LEFT);
    }

    public function getReportGeneratedAtAttribute(): ?Carbon
    {
        $cutoff = ($this->parameters ?? [])['cutoff_at'] ?? null;

        if ($cutoff) {
            try {
                return Carbon::parse($cutoff);
            } catch (Throwable) {
                // Fall back to the original report timestamp for malformed legacy parameters.
            }
        }

        return $this->created_at;
    }
}
