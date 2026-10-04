<?php

namespace Tests\Feature\Patrimoine;

use App\Models\Avoir;
use App\Models\ReleveLigne;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PatrimoineFixtures;
use Tests\TestCase;

class ModelsTest extends TestCase
{
    use PatrimoineFixtures, RefreshDatabase;

    public function test_relations_and_versement_tracking(): void
    {
        ['liq' => $liq, 'courant' => $courant, 'titres' => $titres] = $this->makeHoldings();

        $this->assertSame(['Compte courant'], $liq->avoirs->pluck('title')->all());
        $this->assertSame('Liquidités', $courant->classe->title);
        $this->assertFalse($courant->suitLesVersements());
        $this->assertTrue($titres->suitLesVersements());
    }

    public function test_active_on_excludes_avoirs_archived_on_or_before_the_date(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $titres->update(['archived_on' => '2026-03-01']);

        $this->assertEqualsCanonicalizing([$courant->id, $titres->id], Avoir::activeOn('2026-02-28')->pluck('id')->all());
        $this->assertSame([$courant->id], Avoir::activeOn('2026-03-01')->pluck('id')->all());
    }

    public function test_deleting_a_releve_deletes_its_lines_but_an_avoir_with_lines_cannot_be_deleted(): void
    {
        ['courant' => $courant] = $this->makeHoldings();
        $releve = $this->makeReleve('2026-01-15', [$courant->id => ['1000', null]]);

        try {
            $courant->delete();
            $this->fail('An Avoir with balances must not be deletable.');
        } catch (QueryException) {
            $this->assertNotNull($courant->fresh());
        }

        $releve->delete();
        $this->assertSame(0, ReleveLigne::count());
    }

    public function test_releve_dates_are_unique(): void
    {
        $this->makeReleve('2026-01-15', []);

        $this->expectException(QueryException::class);
        $this->makeReleve('2026-01-15', []);
    }
}
