<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * Aggregates transactions for a period and builds ready-to-use Apache ECharts
 * option arrays (pie / bars / sankey) plus headline KPIs. All money figures are
 * positive CHF amounts unless noted.
 */
class BudgetReport
{
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
        $rows = (clone $this->scope($from, $to))
            ->where('direction', 'debit')->where('is_savings', false)->where('is_transfer', false)
            ->selectRaw('category_id, sum(amount) total')
            ->groupBy('category_id')->get();

        $totals = [];
        foreach ($rows as $r) {
            $top = $this->categories->topLevel($r->category_id);
            $title = $top ? $this->categories->title($top) : 'Non classé';
            $totals[$title] = ($totals[$title] ?? 0) + (-1 * (float) $r->total);
        }
        arsort($totals);

        return $totals;
    }

    public function pieOption(?string $from, ?string $to): array
    {
        $data = [];
        foreach ($this->spendingByTopCategory($from, $to) as $title => $total) {
            if ($total > 0) {
                $data[] = ['name' => $title, 'value' => round($total, 2)];
            }
        }

        return [
            'tooltip' => ['trigger' => 'item', 'valueFormatter' => null],
            'legend' => ['type' => 'scroll', 'orient' => 'vertical', 'right' => 0, 'top' => 'center', 'textStyle' => ['color' => '#cbd5e1']],
            'series' => [[
                'name' => 'Dépenses',
                'type' => 'pie',
                'radius' => ['45%', '72%'],
                'center' => ['38%', '50%'],
                'avoidLabelOverlap' => true,
                'itemStyle' => ['borderColor' => '#0f172a', 'borderWidth' => 2],
                'label' => ['show' => false],
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

        $months = $rows->pluck('ym')->all();

        return [
            'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow']],
            'legend' => ['textStyle' => ['color' => '#cbd5e1'], 'top' => 0],
            'grid' => ['left' => 50, 'right' => 16, 'top' => 36, 'bottom' => 40],
            'xAxis' => [[
                'type' => 'category', 'data' => $months,
                'axisLabel' => ['color' => '#94a3b8', 'rotate' => $months && count($months) > 14 ? 45 : 0],
                'axisLine' => ['lineStyle' => ['color' => '#334155']],
            ]],
            'yAxis' => [[
                'type' => 'value',
                'axisLabel' => ['color' => '#94a3b8'],
                'splitLine' => ['lineStyle' => ['color' => '#1e293b']],
            ]],
            'series' => [
                ['name' => 'Revenus', 'type' => 'bar', 'data' => $rows->map(fn ($r) => round((float) $r->income, 2))->all(), 'itemStyle' => ['color' => '#22c55e']],
                ['name' => 'Dépenses', 'type' => 'bar', 'data' => $rows->map(fn ($r) => round((float) $r->spending, 2))->all(), 'itemStyle' => ['color' => '#ef4444']],
                ['name' => 'Épargne', 'type' => 'bar', 'data' => $rows->map(fn ($r) => round((float) $r->savings, 2))->all(), 'itemStyle' => ['color' => '#3b82f6']],
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

        $topTotals = [];   // topTitle => total
        $subTotals = [];   // topTitle => [subTitle => total]
        foreach ($rows as $r) {
            $total = (float) $r->total;
            if ($total <= 0) {
                continue;
            }
            $topId = $this->categories->topLevel($r->category_id);
            $topTitle = $topId ? $this->categories->title($topId) : 'Non classé';
            $topTotals[$topTitle] = ($topTotals[$topTitle] ?? 0) + $total;

            // Sub = the category directly under the top, if the txn category is deeper.
            $path = $this->categories->path($r->category_id);
            $subTitle = $path[1] ?? null; // [top, sub, ...]
            if ($subTitle) {
                $subTotals[$topTitle][$subTitle] = ($subTotals[$topTitle][$subTitle] ?? 0) + $total;
            }
        }

        $nodes = [];
        $links = [];
        $add = function ($name) use (&$nodes) {
            $nodes[$name] = true;
        };

        $add('Revenus');

        foreach ($topTotals as $top => $total) {
            $total = round($total, 2);
            $add($top);
            $links[] = ['source' => 'Revenus', 'target' => $top, 'value' => $total];

            $subSum = 0;
            foreach (($subTotals[$top] ?? []) as $sub => $val) {
                $val = round($val, 2);
                $label = "$sub ";  // keep sub node names unique vs. top names
                $add($label);
                $links[] = ['source' => $top, 'target' => $label, 'value' => $val];
                $subSum += $val;
            }
            $remainder = round($total - $subSum, 2);
            if ($subSum > 0 && $remainder > 0.01) {
                $label = "$top (autre)";
                $add($label);
                $links[] = ['source' => $top, 'target' => $label, 'value' => $remainder];
            }
        }

        if ($kpis['savings'] > 0) {
            $add('Épargne');
            $links[] = ['source' => 'Revenus', 'target' => 'Épargne', 'value' => $kpis['savings']];
        }
        if ($kpis['net'] > 0) {
            $add('Non alloué');
            $links[] = ['source' => 'Revenus', 'target' => 'Non alloué', 'value' => $kpis['net']];
        }

        return [
            'tooltip' => ['trigger' => 'item', 'triggerOn' => 'mousemove'],
            'series' => [[
                'type' => 'sankey',
                'left' => 8, 'right' => 120, 'top' => 12, 'bottom' => 12,
                'nodeWidth' => 14,
                'nodeGap' => 10,
                'emphasis' => ['focus' => 'adjacency'],
                'lineStyle' => ['color' => 'gradient', 'curveness' => 0.5, 'opacity' => 0.45],
                'label' => ['color' => '#e2e8f0', 'fontSize' => 12],
                'data' => array_map(fn ($n) => ['name' => $n], array_keys($nodes)),
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
            'pie' => $this->pieOption($from, $to),
            'bar' => $this->barOption($from, $to),
            'sankey' => $this->sankeyOption($from, $to),
        ];
    }
}
