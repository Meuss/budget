<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\Releve;
use Illuminate\Support\Collection;

/**
 * Reads Relevés back as the latest-Relevé table and ready-to-use Apache ECharts options.
 * Patrimoine is money kept, so its series are drawn in the 100-franc blue (DESIGN.md, Denomination Rule).
 */
class PatrimoineReport
{
    /** One shade of the 100-franc blue per Classe, cycling when there are more Classes than shades. */
    public const CLASS_SHADES = ['#5b8fe0', '#8fb2ec', '#3f6fb8', '#b9cff4', '#2d5290', '#7f9cc9'];

    public function releves(): Collection
    {
        return Releve::with('lignes.avoir')->orderBy('date')->get();
    }

    protected function total(Releve $releve): float
    {
        return round((float) $releve->lignes->sum('balance'), 2);
    }

    public function latest(Collection $releves): ?array
    {
        $last = $releves->last();
        if (! $last) {
            return null;
        }
        $previous = $releves->count() > 1 ? $releves[$releves->count() - 2] : null;
        $previousLines = $previous?->lignes->keyBy('avoir_id') ?? collect();

        $groups = [];
        foreach (Classe::orderBy('position')->orderBy('id')->get() as $classe) {
            $lines = $last->lignes
                ->filter(fn ($l) => $l->avoir->classe_id === $classe->id)
                ->sortBy(fn ($l) => sprintf('%08d-%08d', $l->avoir->position, $l->avoir_id));
            if ($lines->isEmpty()) {
                continue;
            }

            $groups[] = [
                'title' => $classe->title,
                'description' => $classe->description,
                'subtotal' => round((float) $lines->sum('balance'), 2),
                'avoirs' => $lines->map(fn ($l) => [
                    'title' => $l->avoir->title,
                    'description' => $l->avoir->description,
                    'balance' => (float) $l->balance,
                    'change' => $previousLines->has($l->avoir_id)
                        ? round((float) $l->balance - (float) $previousLines[$l->avoir_id]->balance, 2)
                        : null,
                ])->values()->all(),
            ];
        }

        return [
            'date' => $last->date,
            'total' => $this->total($last),
            'previousDate' => $previous?->date,
            'change' => $previous ? round($this->total($last) - $this->total($previous), 2) : null,
            'groups' => $groups,
        ];
    }

    protected function frame(array $series, bool $legend = false): array
    {
        return [
            'grid' => ['left' => 8, 'right' => 16, 'top' => $legend ? 40 : 16, 'bottom' => 8, 'containLabel' => true],
            'legend' => ['show' => $legend, 'top' => 0, 'left' => 0],
            'tooltip' => ['trigger' => 'axis'],
            'xAxis' => [['type' => 'time']],
            'yAxis' => [['type' => 'value', 'scale' => true]],
            'series' => $series,
        ];
    }

    public function totalOption(Collection $releves): array
    {
        return $this->frame([[
            'name' => 'Patrimoine', 'type' => 'line', 'symbolSize' => 6,
            'color' => BudgetReport::SAVINGS, 'areaStyle' => ['opacity' => 0.12],
            'data' => $releves->map(fn ($r) => [$r->date->toDateString(), $this->total($r)])->values()->all(),
        ]]);
    }

    public function classesOption(Collection $releves): array
    {
        $usedClasses = $releves->flatMap(fn ($r) => $r->lignes->map(fn ($l) => $l->avoir->classe_id))->unique();
        $classes = Classe::whereIn('id', $usedClasses)->orderBy('position')->orderBy('id')->get()->values();

        $series = $classes->map(fn ($classe, $i) => [
            'name' => $classe->title, 'type' => 'line', 'stack' => 'patrimoine', 'showSymbol' => false,
            'color' => self::CLASS_SHADES[$i % count(self::CLASS_SHADES)],
            'lineStyle' => ['width' => 0], 'areaStyle' => ['opacity' => 0.85],
            'data' => $releves->map(fn ($r) => [
                $r->date->toDateString(),
                round((float) $r->lignes->filter(fn ($l) => $l->avoir->classe_id === $classe->id)->sum('balance'), 2),
            ])->values()->all(),
        ])->all();

        $option = $this->frame($series, legend: true);
        $option['yAxis'][0]['scale'] = false; // stacked areas must start at 0

        return $option;
    }

    /**
     * Running totals per Relevé: [date, Versements, return]. For each period, only lines that track
     * Versements and also exist in the previous Relevé count; return = Δ balance − Versement.
     */
    public function cumulativeVersements(Collection $releves): array
    {
        $points = [];
        $versements = 0.0;
        $return = 0.0;
        $previous = null;

        foreach ($releves as $releve) {
            if ($previous) {
                $before = $previous->lignes->keyBy('avoir_id');
                foreach ($releve->lignes as $line) {
                    if ($line->versement === null || ! $before->has($line->avoir_id)) {
                        continue;
                    }
                    $versements += (float) $line->versement;
                    $return += (float) $line->balance - (float) $before[$line->avoir_id]->balance - (float) $line->versement;
                }
            }
            $points[] = [$releve->date->toDateString(), round($versements, 2), round($return, 2)];
            $previous = $releve;
        }

        return $points;
    }

    public function versementsOption(Collection $releves): array
    {
        $points = $this->cumulativeVersements($releves);

        return $this->frame([
            ['name' => 'Versements cumulés', 'type' => 'line', 'symbolSize' => 6, 'color' => BudgetReport::SAVINGS,
                'data' => array_map(fn ($p) => [$p[0], $p[1]], $points)],
            ['name' => 'Rendement cumulé', 'type' => 'line', 'symbolSize' => 6, 'color' => BudgetReport::INCOME,
                'data' => array_map(fn ($p) => [$p[0], $p[2]], $points)],
        ], legend: true);
    }

    public function hasVersements(Collection $releves): bool
    {
        return $releves->contains(fn ($r) => $r->lignes->contains(fn ($l) => $l->versement !== null));
    }
}
