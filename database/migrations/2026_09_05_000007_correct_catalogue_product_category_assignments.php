<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SNAPSHOT_TABLE = 'product_category_assignment_correction_snapshots';

    /** @var array<string, list<string>> */
    private const CATALOGUE = [
        'Цемент' => [
            'Ёқут М500', 'Ёқут M400', 'Тоҷ Смент', 'Яксин М500', 'Яксин М400',
            'Уқоб M500', 'Уқоб M400', 'Хуаксин M500', 'Хуаксин M400', 'Мохир M400',
            'Мохир M500', 'Хатлон M400', 'Хатлон M500',
        ],
        'Рассыпной' => [
            'Тоҷиксемент Рассыпной М500', 'Рассипной Ганҷ M500', 'Рассипной Ганҷ M400',
            'Портланд Рассипной', 'Рассыпной Мохир M500', 'Рассыпной Хуаксин M500',
        ],
        'Гипсокартон' => [
            'Гипсокартон', 'Гипсакартон Кабуд', 'Гипс. стеновой',
            'Гипс потолочный - простой', 'Гипс потолочный - влагастойкий',
        ],
        'Другое' => ['Шифер', 'ОСБ 0.9', 'Оҳок', 'Арматур', 'ОСБ 0.6'],
    ];

    public function up(): void
    {
        $categoryIds = DB::table('product_categories')->whereIn('name', array_keys(self::CATALOGUE))->pluck('id', 'name')->all();
        $missingCategories = array_diff(array_keys(self::CATALOGUE), array_keys($categoryIds));

        if ($missingCategories !== []) {
            throw new RuntimeException('Cannot correct product categories: missing canonical categories '.implode(', ', $missingCategories).'.');
        }

        $assignments = $this->assignments($categoryIds);
        $duplicateNames = DB::table('products')
            ->select('name')
            ->whereIn('name', array_keys($assignments))
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name')
            ->all();

        if ($duplicateNames !== []) {
            throw new RuntimeException('Cannot correct product categories: duplicate catalogue product names: '.implode(', ', $duplicateNames).'.');
        }

        Schema::create(self::SNAPSHOT_TABLE, function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->primary();
            $table->unsignedBigInteger('original_category_id');
            $table->unsignedBigInteger('assigned_category_id');
            $table->timestamp('original_updated_at')->nullable();
            $table->timestamp('corrected_at');
        });

        DB::transaction(function () use ($assignments) {
            $correctedAt = now();
            $products = DB::table('products')
                ->whereIn('name', array_keys($assignments))
                ->get(['id', 'name', 'product_category_id', 'updated_at']);

            foreach ($products as $product) {
                $assignedCategoryId = $assignments[$product->name];

                if ((int) $product->product_category_id === (int) $assignedCategoryId) {
                    continue;
                }

                DB::table(self::SNAPSHOT_TABLE)->insert([
                    'product_id' => $product->id,
                    'original_category_id' => $product->product_category_id,
                    'assigned_category_id' => $assignedCategoryId,
                    'original_updated_at' => $product->updated_at,
                    'corrected_at' => $correctedAt,
                ]);

                DB::table('products')->where('id', $product->id)->update([
                    'product_category_id' => $assignedCategoryId,
                    'updated_at' => $correctedAt,
                ]);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return;
        }

        DB::transaction(function () {
            $changedProducts = DB::table(self::SNAPSHOT_TABLE.' as snapshot')
                ->leftJoin('products as product', 'product.id', '=', 'snapshot.product_id')
                ->where(function ($query) {
                    $query->whereNull('product.id')
                        ->orWhereColumn('product.product_category_id', '!=', 'snapshot.assigned_category_id')
                        ->orWhereColumn('product.updated_at', '!=', 'snapshot.corrected_at');
                })
                ->exists();

            if ($changedProducts) {
                throw new RuntimeException('Cannot roll back product category correction because a corrected product has changed since the migration.');
            }

            DB::table(self::SNAPSHOT_TABLE)->orderBy('product_id')->eachById(function ($snapshot) {
                DB::table('products')->where('id', $snapshot->product_id)->update([
                    'product_category_id' => $snapshot->original_category_id,
                    'updated_at' => $snapshot->original_updated_at,
                ]);
            }, 1000, 'product_id');
        });

        Schema::dropIfExists(self::SNAPSHOT_TABLE);
    }

    /** @param array<string, int|string> $categoryIds
     * @return array<string, int|string>
     */
    private function assignments(array $categoryIds): array
    {
        $assignments = [];

        foreach (self::CATALOGUE as $category => $products) {
            foreach ($products as $product) {
                $assignments[$product] = $categoryIds[$category];
            }
        }

        return $assignments;
    }
};
