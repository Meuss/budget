<?php

namespace App\Services;

use App\Models\Transaction;

/**
 * Assigns a category to a transaction based on the categories' "match_terms",
 * plus dedicated detection of internal savings transfers. Never overrides a
 * transaction the user classified by hand (source = "manual").
 */
class ClassifierService
{
    /** Own savings-account IBAN (from config, normalised). Empty disables the IBAN check. */
    protected string $savingsIban;

    public function __construct(protected CategoryService $categories)
    {
        $this->savingsIban = strtoupper(str_replace(' ', '', (string) config('budget.savings_iban')));
    }

    /**
     * Classify a single transaction. Returns the changed attributes
     * (category_id, is_savings, source) or null when nothing should change.
     */
    public function classify(Transaction $t): ?array
    {
        if ($t->source === 'manual') {
            return null; // respect manual decisions
        }

        $match = $this->matchCategory($t);

        if ($match) {
            return [
                'category_id' => $match['id'],
                'is_savings' => $match['kind'] === 'savings',
                'is_transfer' => $match['kind'] === 'transfer',
                'source' => 'rule',
            ];
        }

        if ($this->looksLikeSavings($t)) {
            return [
                'category_id' => $this->categories->savingsCategoryId(),
                'is_savings' => true,
                'is_transfer' => false,
                'source' => 'rule',
            ];
        }

        return [
            'category_id' => null,
            'is_savings' => false,
            'is_transfer' => false,
            'source' => 'unclassified',
        ];
    }

    /**
     * Find the best-matching category. Matches each category's terms against the
     * merchant (highest priority), then type, then details. Ties break toward the
     * higher-priority field, deeper (more specific) category, then longer term.
     */
    protected function matchCategory(Transaction $t): ?array
    {
        $fields = [
            3 => $this->normalize($t->merchant),
            2 => $this->normalize($t->type),
            1 => $this->normalize($t->details),
        ];

        $best = null;
        $bestScore = [-1, -1, -1];

        foreach ($this->categories->map() as $cat) {
            foreach ($cat['match_terms'] as $term) {
                $needle = $this->normalize($term);
                if ($needle === '') {
                    continue;
                }
                foreach ($fields as $priority => $haystack) {
                    if ($haystack !== '' && str_contains($haystack, $needle)) {
                        $score = [$priority, $cat['depth'], mb_strlen($needle)];
                        if ($score > $bestScore) {
                            $bestScore = $score;
                            $best = $cat;
                        }
                        break; // best field for this term found
                    }
                }
            }
        }

        return $best;
    }

    /**
     * Lower-case and flatten separators so a term works whether it was typed with
     * the raw ";" or the " · " shown in the transactions table. Both become a
     * single space, and runs of whitespace are collapsed.
     */
    protected function normalize(?string $value): string
    {
        $value = mb_strtolower((string) $value);
        $value = str_replace([';', '·'], ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }

    protected function looksLikeSavings(Transaction $t): bool
    {
        $type = mb_strtolower((string) $t->type);
        if (str_contains($type, 'epargne') || str_contains($type, 'épargne')) {
            return true;
        }

        if ($this->savingsIban === '') {
            return false; // no IBAN configured → rely on the EPARGNE text match only
        }

        $iban = strtoupper(str_replace(' ', '', (string) $t->details));

        return str_contains($iban, $this->savingsIban);
    }

    /**
     * Re-classify a set of transactions, persisting changes. Returns the count
     * of rows whose classification actually changed. Manual rows are skipped.
     */
    public function reclassify(iterable $transactions): int
    {
        $changed = 0;
        foreach ($transactions as $t) {
            $update = $this->classify($t);
            if ($update === null) {
                continue;
            }
            if ($t->category_id !== $update['category_id']
                || (bool) $t->is_savings !== $update['is_savings']
                || (bool) $t->is_transfer !== $update['is_transfer']
                || $t->source !== $update['source']) {
                $t->forceFill($update)->save();
                $changed++;
            }
        }

        return $changed;
    }
}
