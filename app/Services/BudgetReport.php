<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * Aggregates transactions for a period and builds ready-to-use Apache ECharts
 * option arrays (ranked bars / monthly bars / sankey) plus headline KPIs. All money figures are
 * positive CHF amounts unless noted.
 */
class BudgetReport
{
    // Series colours, bound to meaning (ninth-series franc notes; mirrored as CSS tokens in budget/layout).
    public const INCOME = '#3aa384';       // 50-franc green: money in
    public const SPENDING = '#e2573f';     // 20-franc red: money out
    public const SPENDING_SUB = '#b8493a'; // the same red, one step deeper, for sub-categories
    public const SAVINGS = '#5b8fe0';      // 100-franc blue: money kept
    public const UNALLOCATED = '#b8794a';  // 200-franc copper: left on the account
    public const ACT = '#e9b949';          // 10-franc yellow: needs your action (unclassified)

    public function __construct(protected CategoryService $categories)
    {
    }

    /** Resolve a period key ("all" | "2024" | "2025" ...) to [from, to] date strings or nulls. */
    public function bounds(string $period): array
    {
        if ($period === 'all' || ! ctype_digit($period)) {
            return [null, null];
        }

        return ["{$period}-01-01", "{$period}-12-31"];
    }

    /** SQLite (tests) and MySQL (prod) need different date functions. */
    protected function yearExpr(): string
    {
        return Transaction::query()->getConnection()->getDriverName() === 'sqlite'
            ? "strftime('%Y', date)"
            : 'YEAR(date)';
    }

    protected function yearMonthExpr(): string
    {
        return Transaction::query()->getConnection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', date)"
            : "DATE_FORMAT(date, '%Y-%m')";
    }

    public function availableYears(): array
    {
        return Transaction::selectRaw($this->yearExpr().' y')
            ->distinct()->orderByDesc('y')->pluck('y')->map(fn ($y) => (string) $y)->all();
    }

    protected function scope(?string $from, ?string $to)
    {
        $q = Transaction::query();
        if ($from) {
            $q->whereBetween('date', [$from, $to]);
        }

        return $q;
    }

    public function kpis(?string $from, ?string $to): array
    {
        $income = (float) (clone $this->scope($from, $to))
            ->where('direction', 'credit')->where('is_savings', false)->where('is_transfer', false)->sum('amount');

        $spending = -1 * (float) (clone $this->scope($from, $to))
            ->where('direction', 'debit')->where('is_savings', false)->where('is_transfer', false)->sum('amount');

        $savings = -1 * (float) (clone $this->scope($from, $to))
            ->where('is_savings', true)->sum('amount'); // net into savings

        $rate = $income > 0 ? round($savings / $income * 100, 1) : 0.0;

        return [
            'income' => round($income, 2),
            'spending' => round($spending, 2),
            'savings' => round($savings, 2),
            'net' => round($income - $spending - $savings, 2), // change in checking balance
            'rate' => $rate,
        ];
    }

    /** [topCategoryTitle => totalSpend], spending only, rolled up to top level. */
    public function spendingByTopCategory(?string $from, ?string $to): array
    {
        $totals = [];
        foreach ($this->spendingByTopCategoryId($from, $to) as $id => $total) {
            $totals[$this->categories->title($id ?: null)] = $total;
        }

        return $totals;
    }

    /** [topCategoryId ('' = unclassified) => totalSpend], largest first. */
    protected function spendingByTopCategoryId(?string $from, ?string $to): array
    {
        $rows = (clone $this->scope($from, $to))
            ->where('direction', 'debit')->where('is_savings', false)->where('is_transfer', false)
            ->selectRaw('category_id, sum(amount) total')
            ->groupBy('category_id')->get();

        $totals = [];
        foreach ($rows as $r) {
            $top = (string) $this->categories->topLevel($r->category_id);
            $totals[$top] = ($totals[$top] ?? 0) + (-1 * (float) $r->total);
        }
        arsort($totals);

        return $totals;
    }

    /** Link to the Transactions page, filtered to what a figure is made of. */
    public function transactionsUrl(?string $from, array $filters): string
    {
        if ($from) {
            $filters['year'] = substr($from, 0, 4);
        }

        return route('budget.transactions', $filters);
    }

    /** Filters for one category bucket ('' = unclassified). */
    protected function categoryFilters(string $id): array
    {
        return $id === ''
            ? ['category' => 'unclassified', 'direction' => 'debit']
            : ['branch' => $id, 'direction' => 'debit'];
    }

    /** Ranked horizontal bars: spending per top-level category. */
    public function spendingOption(?string $from, ?string $to): array
    {
        $names = [];
        $data = [];
        foreach ($this->spendingByTopCategoryId($from, $to) as $id => $total) {
            if ($total <= 0) {
                continue;
            }
            $id = (string) $id;
            $names[] = $this->categories->title($id ?: null);
            $data[] = [
                'value' => round($total, 2),
                'href' => $this->transactionsUrl($from, $this->categoryFilters($id)),
                // Unclassified spend is a to-do, not a category: it wears the "act here" yellow.
                'itemStyle' => ['color' => $id === '' ? self::ACT : self::SPENDING],
            ];
        }

        return [
            'tooltip' => ['trigger' => 'item'],
            'grid' => ['left' => 8, 'right' => 64, 'top' => 4, 'bottom' => 4, 'containLabel' => true],
            'xAxis' => [['type' => 'value', 'show' => false]],
            'yAxis' => [[
                'type' => 'category', 'inverse' => true, 'data' => $names,
                'axisLine' => ['show' => false], 'axisTick' => ['show' => false],
            ]],
            'series' => [[
                'name' => 'Dépenses',
                'type' => 'bar',
                'barMaxWidth' => 18,
                'itemStyle' => ['borderRadius' => [0, 3, 3, 0]],
                'label' => ['show' => true, 'position' => 'right'],
                'data' => $data,
            ]],
        ];
    }

    public function barOption(?string $from, ?string $to): array
    {
        $rows = (clone $this->scope($from, $to))
            ->selectRaw($this->yearMonthExpr().' ym')
            ->selectRaw("sum(case when direction='credit' and is_savings=0 and is_transfer=0 then amount else 0 end) income")
            ->selectRaw("sum(case when direction='debit' and is_savings=0 and is_transfer=0 then -amount else 0 end) spending")
            ->selectRaw('sum(case when is_savings=1 then -amount else 0 end) savings')
            ->groupBy('ym')->orderBy('ym')->get();

        $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        $labels = $rows->map(fn ($r) => $months[(int) substr($r->ym, 5, 2) - 1].' '.substr($r->ym, 2, 2))->all();

        $series = fn ($name, $field, $color, $filters) => [
            'name' => $name, 'type' => 'bar', 'barGap' => '15%', 'barMaxWidth' => 10,
            'itemStyle' => ['color' => $color, 'borderRadius' => [2, 2, 0, 0]],
            'data' => $rows->map(fn ($r) => round((float) $r->{$field}, 2))->all(),
            // Read by the chart's click handler: each bar opens that month's transactions.
            'hrefs' => $rows->map(fn ($r) => route('budget.transactions', $filters + ['month' => $r->ym]))->all(),
        ];

        return [
            'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow']],
            'legend' => ['top' => 0, 'left' => 0],
            'grid' => ['left' => 8, 'right' => 8, 'top' => 40, 'bottom' => 4, 'containLabel' => true],
            'xAxis' => [['type' => 'category', 'data' => $labels]],
            'yAxis' => [['type' => 'value']],
            'series' => [
                $series('Revenus', 'income', self::INCOME, ['direction' => 'credit']),
                $series('Dépenses', 'spending', self::SPENDING, ['direction' => 'debit']),
                $series('Épargne', 'savings', self::SAVINGS, ['category' => 'savings']),
            ],
        ];
    }

    /**
     * Sankey: Income → each top-level expense category (+ Savings + Unallocated),
     * then each top category → its subcategories (+ "(other)" remainder).
     */
    public function sankeyOption(?string $from, ?string $to): array
    {
        $kpis = $this->kpis($from, $to);

        // Spend grouped by exact category, rolled into top + sub buckets.
        $rows = (clone $this->scope($from, $to))
            ->where('direction', 'debit')->where('is_savings', false)->where('is_transfer', false)
            ->selectRaw('category_id, sum(-amount) total')
            ->groupBy('category_id')->get();

        $topTotals = [];   // topId => total ('' = unclassified)
        $subTotals = [];   // topId => [subId => total]
        foreach ($rows as $r) {
            $total = (float) $r->total;
            if ($total <= 0) {
                continue;
            }
            $topId = (string) $this->categories->topLevel($r->category_id);
            $topTotals[$topId] = ($topTotals[$topId] ?? 0) + $total;

            // Sub = the category directly under the top, if the txn category is deeper.
            $chain = $r->category_id
                ? [...array_reverse($this->categories->ancestorIds($r->category_id)), $r->category_id]
                : [];
            if ($subId = $chain[1] ?? null) {
                $subTotals[$topId][$subId] = ($subTotals[$topId][$subId] ?? 0) + $total;
            }
        }
        arsort($topTotals);

        $nodes = [];
        $links = [];
        $add = function (string $name, string $color, ?array $filters) use (&$nodes, $from) {
            $nodes[$name] = ['name' => $name, 'itemStyle' => ['color' => $color]]
                + ($filters !== null ? ['href' => $this->transactionsUrl($from, $filters)] : []);
        };

        $add('Revenus', self::INCOME, ['direction' => 'credit']);

        foreach ($topTotals as $topId => $total) {
            $top = $this->categories->title($topId ?: null);
            $total = round($total, 2);
            $add($top, $topId === '' ? self::ACT : self::SPENDING, $this->categoryFilters($topId));
            $links[] = ['source' => 'Revenus', 'target' => $top, 'value' => $total];

            $subSum = 0;
            foreach (($subTotals[$topId] ?? []) as $subId => $val) {
                $val = round($val, 2);
                $label = $this->categories->title($subId).' ';  // keep sub node names unique vs. top names
                $add($label, self::SPENDING_SUB, $this->categoryFilters($subId));
                $links[] = ['source' => $top, 'target' => $label, 'value' => $val];
                $subSum += $val;
            }
            $remainder = round($total - $subSum, 2);
            if ($subSum > 0 && $remainder > 0.01) {
                $label = "$top (autre)";
                // Spend booked on the top category itself, not on one of its subs.
                $add($label, self::SPENDING_SUB, ['category' => $topId, 'direction' => 'debit']);
                $links[] = ['source' => $top, 'target' => $label, 'value' => $remainder];
            }
        }

        if ($kpis['savings'] > 0) {
            $add('Épargne', self::SAVINGS, ['category' => 'savings']);
            $links[] = ['source' => 'Revenus', 'target' => 'Épargne', 'value' => $kpis['savings']];
        }
        if ($kpis['net'] > 0) {
            $add('Non alloué', self::UNALLOCATED, []);
            $links[] = ['source' => 'Revenus', 'target' => 'Non alloué', 'value' => $kpis['net']];
        }

        return [
            'tooltip' => ['trigger' => 'item', 'triggerOn' => 'mousemove'],
            'series' => [[
                'type' => 'sankey',
                'left' => 8, 'right' => 150, 'top' => 12, 'bottom' => 12,
                'nodeWidth' => 10,
                'nodeGap' => 12,
                'layoutIterations' => 64,
                'emphasis' => ['focus' => 'adjacency'],
                'lineStyle' => ['color' => 'gradient', 'curveness' => 0.5, 'opacity' => 0.28],
                'data' => array_values($nodes),
                'links' => $links,
            ]],
        ];
    }

    /** Everything the dashboard needs for one period. */
    public function forPeriod(string $period): array
    {
        [$from, $to] = $this->bounds($period);

        return [
            'kpis' => $this->kpis($from, $to),
            'spending' => $this->spendingOption($from, $to),
            'bar' => $this->barOption($from, $to),
            'sankey' => $this->sankeyOption($from, $to),
        ];
    }
}
