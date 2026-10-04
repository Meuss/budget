<?php

namespace Tests\Feature\Patrimoine;

use App\Models\Avoir;
use App\Services\PatrimoineReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PatrimoineFixtures;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use PatrimoineFixtures, RefreshDatabase;

    public function test_latest_is_null_without_releves(): void
    {
        $report = app(PatrimoineReport::class);

        $this->assertNull($report->latest($report->releves()));
    }

    public function test_latest_groups_by_classe_with_subtotals_total_and_change(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $this->makeReleve('2026-08-01', [$courant->id => ['1000', null], $titres->id => ['2000', '0']]);
        $this->makeReleve('2026-09-01', [$courant->id => ['1500', null], $titres->id => ['2300', '300']]);
        $report = app(PatrimoineReport::class);

        $latest = $report->latest($report->releves());

        $this->assertSame('2026-09-01', $latest['date']->toDateString());
        $this->assertSame('2026-08-01', $latest['previousDate']->toDateString());
        $this->assertSame(3800.0, $latest['total']);
        $this->assertSame(800.0, $latest['change']);
        $this->assertSame(['Liquidités', 'Prévoyance'], array_column($latest['groups'], 'title'));
        $this->assertSame(1500.0, $latest['groups'][0]['subtotal']);
        $this->assertSame(500.0, $latest['groups'][0]['avoirs'][0]['change']);
    }

    public function test_total_and_classes_options_use_a_time_axis(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $this->makeReleve('2026-08-01', [$courant->id => ['1000', null], $titres->id => ['2000', '0']]);
        $this->makeReleve('2026-09-15', [$courant->id => ['1500', null], $titres->id => ['2300', '300']]);
        $report = app(PatrimoineReport::class);
        $releves = $report->releves();

        $total = $report->totalOption($releves);
        $this->assertSame('time', $total['xAxis'][0]['type']);
        $this->assertSame([['2026-08-01', 3000.0], ['2026-09-15', 3800.0]], $total['series'][0]['data']);

        $classes = $report->classesOption($releves);
        $this->assertSame(['Liquidités', 'Prévoyance'], array_column($classes['series'], 'name'));
        $this->assertSame([['2026-08-01', 1000.0], ['2026-09-15', 1500.0]], $classes['series'][0]['data']);
        $this->assertSame('patrimoine', $classes['series'][1]['stack']);
    }

    public function test_return_only_counts_tracked_avoirs_present_in_both_releves(): void
    {
        ['prev' => $prev, 'courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $this->makeReleve('2026-01-15', [$courant->id => ['500', null], $titres->id => ['1000', '0']]);
        // A tracked Avoir that appears only from the second Relevé: its opening balance is not return.
        $nouveau = Avoir::create(['classe_id' => $prev->id, 'title' => 'Nouveau', 'position' => 1, 'versement_mensuel' => '100']);
        $this->makeReleve('2026-03-10', [$courant->id => ['800', null], $titres->id => ['1700', '600'], $nouveau->id => ['5000', '0']]);
        $report = app(PatrimoineReport::class);
        $releves = $report->releves();

        $this->assertSame(
            [['2026-01-15', 0.0, 0.0], ['2026-03-10', 600.0, 100.0]],
            $report->cumulativeVersements($releves),
        );
        $this->assertTrue($report->hasVersements($releves));
    }

    public function test_has_versements_is_false_when_no_line_tracks_them(): void
    {
        ['courant' => $courant] = $this->makeHoldings();
        $this->makeReleve('2026-01-15', [$courant->id => ['500', null]]);
        $report = app(PatrimoineReport::class);

        $this->assertFalse($report->hasVersements($report->releves()));
    }
}
