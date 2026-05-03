<?php

namespace App\Services;

use App\Helpers\Transliterator;
use App\Models\Client;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\WhatsAppTask;
use Illuminate\Support\Collection;

class WhatsAppTaskDataExtractor
{
    private const MAX_SUGGESTIONS = 8;

    private ?Collection $clients = null;

    private ?Collection $products = null;

    private ?Collection $suppliers = null;

    public function extract(WhatsAppTask $task): array
    {
        if (! in_array($task->task_type, [WhatsAppTask::TYPE_GOODS_PIECES, WhatsAppTask::TYPE_PAYMENT, WhatsAppTask::TYPE_CLIENT_TRANSFER], true)) {
            return [
                'supported' => false,
                'task_type' => $task->task_type,
                'clients' => collect(),
                'products' => collect(),
                'quantities' => collect(),
                'prices' => collect(),
                'amounts' => collect(),
                'suppliers' => collect(),
            ];
        }

        $text = $this->taskText($task);
        $words = $this->words($text);
        $phrases = $this->phrases($words);

        $extraction = [
            'supported' => true,
            'task_type' => $task->task_type,
            'clients' => $this->matchNamedRecords($this->clients(), 'name', $words, $phrases),
            'products' => collect(),
            'quantities' => collect(),
            'prices' => collect(),
            'amounts' => collect(),
            'suppliers' => collect(),
        ];

        if ($task->task_type === WhatsAppTask::TYPE_GOODS_PIECES) {
            $extraction['products'] = $this->matchNamedRecords($this->productsForGoodsPieces(), 'name', $words, $phrases);
            $extraction['quantities'] = $this->extractNumbers($text, 'quantity');
            $extraction['prices'] = $this->extractNumbers($text, 'price');
            $extraction['suppliers'] = $this->matchSuppliers($words, $phrases);
        }

        if ($task->task_type === WhatsAppTask::TYPE_PAYMENT) {
            $extraction['amounts'] = $this->extractNumbers($text, 'amount');
        }

        if ($task->task_type === WhatsAppTask::TYPE_CLIENT_TRANSFER) {
            [$extraction['quantities'], $extraction['prices']] = $this->extractClientTransferQuantityAndPrice($text);
        }

        return $extraction;
    }

    private function taskText(WhatsAppTask $task): string
    {
        return $task->messages
            ->map(fn ($message) => trim((string) ($message->body ?: $message->attachment_filename)))
            ->filter()
            ->implode("\n");
    }

    /**
     * @return array<int, string>
     */
    private function words(string $text): array
    {
        $text = $this->normalize($text, true);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($words, fn (string $word) => mb_strlen($word) >= 2)));
    }

    /**
     * @param  array<int, string>  $words
     * @return array<int, string>
     */
    private function phrases(array $words): array
    {
        $phrases = $words;

        for ($size = 2; $size <= 4; $size++) {
            for ($index = 0; $index <= count($words) - $size; $index++) {
                $phrases[] = implode(' ', array_slice($words, $index, $size));
            }
        }

        return array_values(array_unique($phrases));
    }

    private function matchNamedRecords(Collection $records, string $attribute, array $words, array $phrases): Collection
    {
        return $records
            ->map(function ($record) use ($attribute, $words, $phrases) {
                $name = (string) $record->{$attribute};
                $score = $this->bestNameScore($name, $words, $phrases);

                if ($score < 72) {
                    return null;
                }

                return [
                    'id' => $record->id,
                    'label' => $name,
                    'score' => $score,
                ];
            })
            ->filter()
            ->sortByDesc('score')
            ->take(self::MAX_SUGGESTIONS)
            ->values();
    }

    private function bestNameScore(string $name, array $words, array $phrases): float
    {
        $normalizedName = $this->normalize($name, true);
        $nameWords = preg_split('/\s+/', $normalizedName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $candidates = array_merge($phrases, $words);
        $bestScore = 0.0;

        foreach ($candidates as $candidate) {
            $bestScore = max($bestScore, $this->similarity($candidate, $normalizedName));

            foreach ($nameWords as $nameWord) {
                $bestScore = max($bestScore, $this->similarity($candidate, $nameWord));
            }
        }

        return round($bestScore, 2);
    }

    private function extractNumbers(string $text, string $field): Collection
    {
        return $this->numberCandidates($text, $field)
            ->unique('value')
            ->sortByDesc('score')
            ->take(self::MAX_SUGGESTIONS)
            ->values()
            ->map(fn (array $candidate) => [
                'value' => $candidate['value'],
                'label' => $candidate['label'],
                'score' => $candidate['score'],
            ]);
    }

    private function extractClientTransferQuantityAndPrice(string $text): array
    {
        $numbers = $this->numberCandidates($text, 'quantity')
            ->unique('value')
            ->sortBy('offset')
            ->values();

        if ($numbers->isEmpty()) {
            return [collect(), collect()];
        }

        $quantity = $numbers->firstWhere('score', 95) ?? $numbers->first();
        $price = $numbers
            ->first(fn (array $candidate) => $candidate['offset'] > $quantity['offset'])
            ?? $numbers->skip(1)->first()
            ?? $quantity;

        $quantityCandidates = $this->prioritizeNumberCandidate($numbers, $quantity);
        $priceCandidates = $this->prioritizeNumberCandidate($numbers, $price);

        return [$quantityCandidates, $priceCandidates];
    }

    private function prioritizeNumberCandidate(Collection $numbers, array $primary): Collection
    {
        return $numbers
            ->sortBy(fn (array $candidate) => $candidate['value'] === $primary['value'] ? 0 : 1)
            ->take(self::MAX_SUGGESTIONS)
            ->values()
            ->map(fn (array $candidate) => [
                'value' => $candidate['value'],
                'label' => $candidate['label'],
                'score' => $candidate['score'],
            ]);
    }

    private function numberCandidates(string $text, string $field): Collection
    {
        preg_match_all('/(?<![\pL\pN])\d+(?:[.,]\d+)?/u', $text, $matches, PREG_OFFSET_CAPTURE);

        return collect($matches[0] ?? [])
            ->map(function (array $match) use ($text, $field) {
                [$rawValue, $offset] = $match;
                $value = str_replace(',', '.', $rawValue);
                $context = $this->numberContext($text, (int) $offset, mb_strlen($rawValue));

                return [
                    'value' => $value,
                    'label' => $value,
                    'score' => $this->numberScore($context, $field),
                    'offset' => (int) $offset,
                ];
            });
    }

    private function numberContext(string $text, int $offset, int $length): string
    {
        $start = max(0, $offset - 24);
        $context = mb_substr($text, $start, $length + 48);
        $context = preg_replace('/\s+/u', ' ', $context) ?: '';

        return trim($context);
    }

    private function numberScore(string $context, string $field): int
    {
        $context = $this->normalize($context, true);
        $quantityWords = ['ta', 'dona', 'sht', 'shtuk', 'piece', 'pieces', 'pcs'];
        $priceWords = ['narx', 'narkh', 'price', 'som', 'somoni', 'sum', 'suma', 'usd', 'dollar'];
        $amountWords = ['oplata', 'oplat', 'payment', 'paid', 'pay', 'pardokht', 'parokht', 'dod', 'som', 'somoni', 'sum', 'suma', 'usd', 'dollar'];
        $words = match ($field) {
            'quantity' => $quantityWords,
            'price' => $priceWords,
            default => $amountWords,
        };

        foreach ($words as $word) {
            if (str_contains($context, $word)) {
                return 95;
            }
        }

        return 50;
    }

    private function matchSuppliers(array $words, array $phrases): Collection
    {
        $compactCandidates = collect(array_merge($phrases, $words))
            ->map(fn (string $candidate) => $this->normalize($candidate, false))
            ->filter(fn (string $candidate) => mb_strlen($candidate) >= 3)
            ->unique()
            ->values();

        return $this->suppliers()
            ->map(function (Supplier $supplier) use ($compactCandidates) {
                $carNumber = (string) $supplier->car_number;
                $normalizedCarNumber = $this->normalize($carNumber, false);
                $score = $compactCandidates
                    ->map(fn (string $candidate) => $this->similarity($candidate, $normalizedCarNumber))
                    ->max() ?? 0;

                if ($score < 72) {
                    return null;
                }

                return [
                    'id' => $supplier->id,
                    'label' => $supplier->car_color
                        ? $carNumber . ' - ' . $supplier->car_color
                        : $carNumber,
                    'score' => round($score, 2),
                ];
            })
            ->filter()
            ->sortByDesc('score')
            ->take(self::MAX_SUGGESTIONS)
            ->values();
    }

    private function clients(): Collection
    {
        return $this->clients ??= Client::query()->orderBy('name')->get();
    }

    private function productsForGoodsPieces(): Collection
    {
        return $this->products ??= Product::query()
            ->where(function ($query) {
                $query->whereNull('default_unit')
                    ->orWhere('default_unit', '!=', 'per_ton');
            })
            ->orderBy('name')
            ->get();
    }

    private function suppliers(): Collection
    {
        return $this->suppliers ??= Supplier::query()
            ->whereNotNull('car_number')
            ->orderBy('car_number')
            ->get();
    }

    private function similarity(string $left, string $right): float
    {
        if ($left === '' || $right === '') {
            return 0.0;
        }

        if ($left === $right) {
            return 100.0;
        }

        if ((mb_strlen($left) >= 4 && str_contains($right, $left)) || (mb_strlen($right) >= 4 && str_contains($left, $right))) {
            return 92.0;
        }

        similar_text($left, $right, $percent);

        return $percent;
    }

    private function normalize(string $value, bool $keepSpaces): string
    {
        $value = mb_strtolower(Transliterator::transliterate($value));
        $pattern = $keepSpaces ? '/[^a-z0-9.]+/' : '/[^a-z0-9]+/';
        $value = preg_replace($pattern, $keepSpaces ? ' ' : '', $value) ?: '';

        return trim(preg_replace('/\s+/', ' ', $value) ?: '');
    }
}
