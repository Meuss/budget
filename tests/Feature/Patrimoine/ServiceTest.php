<?php

namespace Tests\Feature\Patrimoine;

use App\Models\Avoir;
use App\Models\Releve;
use App\Services\PatrimoineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\PatrimoineFixtures;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use PatrimoineFixtures, RefreshDatabase;

    public function test_parse_amount_accepts_swiss_typing_and_rejects_the_rest(): void
    {
        $this->assertSame('12283.00', PatrimoineService::parseAmount("12'283"));
        $this->assertSame('12283.50', PatrimoineService::parseAmount('12 283,50'));
        $this->assertSame('12283.00', PatrimoineService::parseAmount("12\u{2019}283"));
        $this->assertSame('-1500.00', PatrimoineService::parseAmount('-1500'));
        $this->assertSame('0.00', PatrimoineService::parseAmount('0'));
        $this->assertNull(PatrimoineService::parseAmount(''));
        $this->assertNull(PatrimoineService::parseAmount('abc'));
        $this->assertNull(PatrimoineService::parseAmount('12,283')); // three decimals: ambiguous, refuse
    }

    public function test_months_between_counts_calendar_months(): void
    {
        $this->assertSame(2, PatrimoineService::monthsBetween(Carbon::parse('2026-09-01'), Carbon::parse('2026-11-20')));
        $this->assertSame(1, PatrimoineService::monthsBetween(Carbon::parse('2026-12-28'), Carbon::parse('2027-01-03')));
        $this->assertSame(0, PatrimoineService::monthsBetween(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30')));
        $this->assertSame(0, PatrimoineService::monthsBetween(Carbon::parse('2026-11-01'), Carbon::parse('2026-09-01')));
    }

    public function test_active_avoirs_are_ordered_by_classe_then_position(): void
    {
        ['liq' => $liq, 'courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $epargne = Avoir::create(['classe_id' => $liq->id, 'title' => 'Épargne', 'position' => 1]);

        $ids = app(PatrimoineService::class)->activeAvoirs('2026-01-01')->pluck('id')->all();

        $this->assertSame([$courant->id, $epargne->id, $titres->id], $ids);
    }

    public function test_first_draft_has_empty_balances_and_zero_versements(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();

        $draft = app(PatrimoineService::class)->draft('2026-01-15');

        $this->assertSame(['balance' => '', 'versement' => null], $draft[$courant->id]);
        $this->assertSame(['balance' => '', 'versement' => '0.00'], $draft[$titres->id]);
    }

    public function test_draft_prefills_from_the_closest_earlier_releve_and_multiplies_versement_mensuel(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $this->makeReleve('2026-06-01', [$courant->id => ['500', null], $titres->id => ['900', '300']]);
        $this->makeReleve('2026-09-01', [$courant->id => ['1000', null], $titres->id => ['2000', '300']]);
        $this->makeReleve('2026-12-01', [$courant->id => ['9999', null], $titres->id => ['9999', '300']]); // later: ignored

        $draft = app(PatrimoineService::class)->draft('2026-11-20');

        $this->assertSame('1000.00', $draft[$courant->id]['balance']);
        $this->assertSame('2000.00', $draft[$titres->id]['balance']);
        $this->assertSame('600.00', $draft[$titres->id]['versement']); // 2 months × 300
    }

    public function test_draft_of_an_existing_releve_shows_its_stored_values(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $releve = $this->makeReleve('2026-09-01', [$courant->id => ['1000', null], $titres->id => ['2000', '-50']]);

        $draft = app(PatrimoineService::class)->draft('2026-09-01', $releve);

        $this->assertSame(['balance' => '1000.00', 'versement' => null], $draft[$courant->id]);
        $this->assertSame(['balance' => '2000.00', 'versement' => '-50.00'], $draft[$titres->id]);
    }

    public function test_archived_avoir_is_left_out_from_its_archive_date_but_kept_before(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $titres->update(['archived_on' => '2026-10-01']);
        $service = app(PatrimoineService::class);

        $this->assertArrayNotHasKey($titres->id, $service->draft('2026-10-01'));
        $this->assertArrayHasKey($titres->id, $service->draft('2026-09-30'));
    }

    public function test_save_creates_then_updates_a_releve(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $service = app(PatrimoineService::class);

        $releve = $service->save('2026-09-01', [$courant->id => '1000.00', $titres->id => '2000.00'], [$titres->id => '300.00']);

        $this->assertSame('2026-09-01', $releve->date->toDateString());
        $this->assertSame(2, $releve->lignes()->count());
        $this->assertNull($releve->lignes()->where('avoir_id', $courant->id)->value('versement'));

        $titres->update(['archived_on' => '2026-09-01']);
        $service->save('2026-09-01', [$courant->id => '1100.00'], [], $releve);

        $this->assertSame(1, Releve::count());
        $this->assertSame([$courant->id], $releve->lignes()->pluck('avoir_id')->all()); // archived line dropped
        $this->assertSame('1100.00', (string) $releve->lignes()->first()->balance);
    }
}
