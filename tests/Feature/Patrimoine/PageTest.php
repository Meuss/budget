<?php

namespace Tests\Feature\Patrimoine;

use App\Livewire\Patrimoine;
use App\Models\Releve;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PatrimoineFixtures;
use Tests\TestCase;

class PageTest extends TestCase
{
    use PatrimoineFixtures, RefreshDatabase;

    public function test_empty_page_invites_to_create_avoirs(): void
    {
        Livewire::test(Patrimoine::class)
            ->assertOk()
            ->assertSee('Patrimoine')
            ->assertSee('Gérer les avoirs')
            ->assertSee('Aucun relevé');
    }

    public function test_new_releve_is_prefilled_and_saved(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $this->makeReleve('2026-09-01', [$courant->id => ['1000', null], $titres->id => ['2000', '300']]);

        Livewire::test(Patrimoine::class)
            ->call('newReleve')
            ->assertSet('formOpen', true)
            ->set('date', '2026-11-20')
            ->assertSet("balances.{$courant->id}", '1000.00')
            ->assertSet("versements.{$titres->id}", '600.00')
            ->set("balances.{$courant->id}", "1'250")
            ->call('saveReleve')
            ->assertHasNoErrors()
            ->assertSet('formOpen', false)
            ->assertDispatched('charts-updated')
            ->assertSee('3 250'); // latest total: 1 250 + 2 000

        $this->assertSame('1250.00', (string) Releve::where('date', '2026-11-20')->first()->lignes->firstWhere('avoir_id', $courant->id)->balance);
    }

    public function test_invalid_or_missing_amounts_block_saving(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $this->makeReleve('2026-08-01', [$courant->id => ['1000', null], $titres->id => ['2000', '0']]);

        Livewire::test(Patrimoine::class)
            ->call('newReleve')
            ->set('date', '2026-09-01')
            ->set("balances.{$courant->id}", '12,283')
            ->set("balances.{$titres->id}", '')
            ->call('saveReleve')
            ->assertHasErrors(["balances.{$courant->id}", "balances.{$titres->id}"]);

        $this->assertSame(1, Releve::count());
    }

    public function test_duplicate_date_is_a_validation_error(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $this->makeReleve('2026-09-01', [$courant->id => ['1000', null], $titres->id => ['2000', '0']]);
        $other = $this->makeReleve('2026-10-01', [$courant->id => ['1100', null], $titres->id => ['2100', '0']]);

        Livewire::test(Patrimoine::class)
            ->call('newReleve')->set('date', '2026-09-01')->call('saveReleve')
            ->assertHasErrors(['date'])
            ->call('editReleve', $other->id)->set('date', '2026-09-01')->call('saveReleve')
            ->assertHasErrors(['date']);

        $this->assertSame(2, Releve::count());
    }

    public function test_edit_and_delete_a_past_releve(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $releve = $this->makeReleve('2026-09-01', [$courant->id => ['1000', null], $titres->id => ['2000', '300']]);

        Livewire::test(Patrimoine::class)
            ->call('editReleve', $releve->id)
            ->assertSet('date', '2026-09-01')
            ->assertSet("versements.{$titres->id}", '300.00')
            ->set("balances.{$titres->id}", '2500')
            ->call('saveReleve')
            ->assertHasNoErrors();

        $this->assertSame('2500.00', (string) $releve->fresh()->lignes->firstWhere('avoir_id', $titres->id)->balance);

        Livewire::test(Patrimoine::class)->call('deleteReleve', $releve->id);
        $this->assertSame(0, Releve::count());
    }

    public function test_route_is_registered_in_the_budget_group(): void
    {
        $this->assertSame(url('/budget/patrimoine'), route('budget.patrimoine'));
    }

    public function test_editing_an_old_releve_can_leave_a_newer_avoir_empty(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $january = $this->makeReleve('2026-01-15', [$courant->id => ['500', null]]);
        $this->makeReleve('2026-03-15', [$courant->id => ['800', null], $titres->id => ['50000', '600']]);

        Livewire::test(Patrimoine::class)
            ->call('editReleve', $january->id)
            ->assertSet("balances.{$titres->id}", '')
            ->call('saveReleve')
            ->assertHasNoErrors();

        $this->assertSame([$courant->id], $january->fresh()->lignes->pluck('avoir_id')->all());
        $report = app(\App\Services\PatrimoineReport::class);
        $this->assertSame(['2026-03-15', 0.0, 0.0], $report->cumulativeVersements($report->releves())[1]);
    }

    public function test_a_releve_needs_at_least_one_balance(): void
    {
        $this->makeHoldings();

        Livewire::test(Patrimoine::class)
            ->call('newReleve')
            ->set('date', '2026-01-15')
            ->call('saveReleve')
            ->assertHasErrors(['date']);

        $this->assertSame(0, Releve::count());
    }
}
