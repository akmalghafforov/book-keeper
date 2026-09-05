<?php

namespace App\Console\Commands;

use App\Services\UsagePriorityCalculator;
use Illuminate\Console\Command;

class RecalculateUsagePriorities extends Command
{
    protected $signature = 'priorities:recalculate-usage';

    protected $description = 'Recalculate product and product category priorities from recent distributions';

    public function handle(UsagePriorityCalculator $calculator): int
    {
        $calculator->recalculate();

        $this->info('Usage priorities recalculated.');

        return self::SUCCESS;
    }
}
