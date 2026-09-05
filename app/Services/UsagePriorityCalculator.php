<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class UsagePriorityCalculator
{
    /**
     * Recalculate priorities from active distributions in the inclusive three-month window.
     */
    public function recalculate(?CarbonInterface $today = null): void
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $windowStart = $today->copy()->subMonthsNoOverflow(3);

        DB::transaction(function () use ($today, $windowStart): void {
            DB::table('products')->whereNull('deleted_at')->update(['usage_priority' => 0]);
            DB::table('product_categories')->update(['usage_priority' => 0]);

            $usage = DB::table('distributions')
                ->join('products', 'products.id', '=', 'distributions.product_id')
                ->whereNull('distributions.deleted_at')
                ->whereNull('products.deleted_at')
                ->whereBetween('distributions.distribution_date', [
                    $windowStart->toDateString(),
                    $today->copy()->endOfDay()->toDateTimeString(),
                ]);

            (clone $usage)
                ->select('distributions.product_id', DB::raw('count(*) as usage_priority'))
                ->groupBy('distributions.product_id')
                ->get()
                ->each(fn ($row) => DB::table('products')
                    ->where('id', $row->product_id)
                    ->update(['usage_priority' => $row->usage_priority]));

            $usage
                ->select('products.product_category_id', DB::raw('count(*) as usage_priority'))
                ->groupBy('products.product_category_id')
                ->get()
                ->each(fn ($row) => DB::table('product_categories')
                    ->where('id', $row->product_category_id)
                    ->update(['usage_priority' => $row->usage_priority]));
        });
    }
}
