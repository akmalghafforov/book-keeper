<?php

namespace App\Console\Commands;

use App\Models\TransactionDraft;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

class TransactionDraftStatus extends Command
{
    protected $signature = 'drafts:status';

    protected $description = 'Show pending draft counts and oldest ages without exposing draft contents';

    public function handle(): int
    {
        $counts = TransactionDraft::where('status', 'pending')
            ->selectRaw('kind, COUNT(*) AS pending_count, MIN(created_at) AS oldest')
            ->groupBy('kind')->get()->keyBy('kind');

        $rows = [];
        foreach (array_keys(TransactionDraft::KINDS) as $kind) {
            $row = $counts->get($kind);
            $rows[] = [$kind, $row?->pending_count ?? 0, $row ? (int) Date::parse($row->oldest)->diffInHours(now()) : '-'];
        }
        $this->table(['Kind', 'Pending', 'Oldest age (hours)'], $rows);

        return self::SUCCESS;
    }
}
