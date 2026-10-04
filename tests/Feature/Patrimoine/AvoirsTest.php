<?php

namespace Tests\Feature\Patrimoine;

use App\Livewire\PatrimoineAvoirs;
use App\Models\Avoir;
use App\Models\Classe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PatrimoineFixtures;
use Tests\TestCase;

class AvoirsTest extends TestCase
{
    use PatrimoineFixtures, RefreshDatabase;

    public function test_creates_classes_and_avoirs_in_order(): void
    {
        $component = Livewire::test(PatrimoineAvoirs::class)
            ->set('classeTitle', 'Liquidités')->call('saveClasse')
            ->set('classeTitle', 'Prévoyance')->set('classeDescription', 'Bloqué')->call('saveClasse')
            ->assertDispatched('avoirs-changed');

        $prev = Classe::where('title', 'Prévoyance')->first();
        $this->assertSame([0, 1], Classe::orderBy('position')->pluck('position')->all());

        $component
            ->set('avoirClasse', (string) $prev->id)->set('avoirTitle', 'Compte titres')
            ->set('avoirVersementMensuel', "1'000")->call('saveAvoir')
            ->assertHasNoErrors();

        $avoir = Avoir::first();
        $this->assertSame('Compte titres', $avoir->title);
        $this->assertSame('1000.00', (string) $avoir->versement_mensuel);
    }

    public function test_validates_titles_classe_and_versement_mensuel(): void
    {
        Livewire::test(PatrimoineAvoirs::class)
            ->set('classeTitle', str_repeat('x', 61))->call('saveClasse')
            ->assertHasErrors(['classeTitle'])
            ->set('avoirTitle', '')->set('avoirClasse', '')->set('avoirVersementMensuel', 'abc')->call('saveAvoir')
            ->assertHasErrors(['avoirTitle', 'avoirClasse', 'avoirVersementMensuel']);

        $this->assertSame(0, Classe::count());
    }

    public function test_edit_and_clear_versement_mensuel_stops_tracking(): void
    {
        ['titres' => $titres] = $this->makeHoldings();

        Livewire::test(PatrimoineAvoirs::class)
            ->call('editAvoir', $titres->id)
            ->assertSet('avoirTitle', 'Compte titres')
            ->set('avoirVersementMensuel', '')
            ->call('saveAvoir');

        $this->assertFalse($titres->fresh()->suitLesVersements());
    }

    public function test_move_swaps_positions_within_siblings(): void
    {
        ['liq' => $liq, 'prev' => $prev] = $this->makeHoldings();

        Livewire::test(PatrimoineAvoirs::class)->call('moveClasse', $prev->id, -1);

        $this->assertSame([$prev->id, $liq->id], Classe::orderBy('position')->pluck('id')->all());
    }

    public function test_archive_and_unarchive(): void
    {
        ['titres' => $titres] = $this->makeHoldings();

        Livewire::test(PatrimoineAvoirs::class)
            ->set("archiveOn.{$titres->id}", '2026-10-01')->call('archiveAvoir', $titres->id);
        $this->assertSame('2026-10-01', $titres->fresh()->archived_on->toDateString());

        Livewire::test(PatrimoineAvoirs::class)->call('unarchiveAvoir', $titres->id);
        $this->assertNull($titres->fresh()->archived_on);
    }

    public function test_refuses_to_delete_a_classe_with_avoirs_or_an_avoir_with_balances(): void
    {
        ['liq' => $liq, 'courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $this->makeReleve('2026-09-01', [$courant->id => ['1000', null]]);

        Livewire::test(PatrimoineAvoirs::class)
            ->call('deleteClasse', $liq->id)->assertDispatched('notify')
            ->call('deleteAvoir', $courant->id)->assertDispatched('notify')
            ->call('deleteAvoir', $titres->id); // no balances: allowed

        $this->assertNotNull($liq->fresh());
        $this->assertNotNull($courant->fresh());
        $this->assertNull($titres->fresh());
    }
}
