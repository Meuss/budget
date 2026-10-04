<?php

namespace App\Livewire;

use App\Models\Avoir;
use App\Models\Releve;
use App\Services\PatrimoineReport;
use App\Services\PatrimoineService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('budget.layout')]
class Patrimoine extends Component
{
    public bool $formOpen = false;

    public ?int $releveId = null;   // null = a new Relevé

    public string $date = '';       // Y-m-d

    /** Typed amounts, avoirId => string as typed ("12'283"). */
    public array $balances = [];

    public array $versements = [];

    public function newReleve(): void
    {
        $this->resetErrorBag();
        $this->releveId = null;
        $this->date = now()->toDateString();
        $this->prefill(keepTyped: false);
        $this->formOpen = true;
    }

    public function editReleve(int $id): void
    {
        $releve = Releve::findOrFail($id);
        $this->resetErrorBag();
        $this->releveId = $releve->id;
        $this->date = $releve->date->toDateString();
        $this->prefill(keepTyped: false);
        $this->formOpen = true;
    }

    /** A new date means a new previous Relevé and month count: propose fresh values. */
    public function updatedDate(): void
    {
        if ($this->validDate()) {
            $this->prefill(keepTyped: false);
        }
    }

    /** Avoirs were added, archived or reordered in the panel: keep what was typed, add the rest. */
    #[On('avoirs-changed')]
    public function avoirsChanged(): void
    {
        if ($this->formOpen && $this->validDate()) {
            $this->prefill(keepTyped: true);
        }
    }

    public function saveReleve(PatrimoineService $service): void
    {
        $this->resetErrorBag();
        $this->validate([
            'date' => ['required', 'date_format:Y-m-d', Rule::unique('patrimoine_releves', 'date')->ignore($this->releveId)],
        ], [
            'date.unique' => 'Il y a déjà un relevé à cette date.',
        ]);

        $avoirs = $service->activeAvoirs($this->date);
        if ($avoirs->isEmpty()) {
            $this->addError('date', 'Aucun avoir actif à cette date.');

            return;
        }

        $balances = [];
        $versements = [];
        foreach ($avoirs as $avoir) {
            $balances[$avoir->id] = PatrimoineService::parseAmount($this->balances[$avoir->id] ?? '');
            if ($balances[$avoir->id] === null) {
                $this->addError("balances.{$avoir->id}", 'Montant requis (ex. 12\'283 ou 0).');
            }
            if ($avoir->suitLesVersements()) {
                $versements[$avoir->id] = PatrimoineService::parseAmount($this->versements[$avoir->id] ?? '');
                if ($versements[$avoir->id] === null) {
                    $this->addError("versements.{$avoir->id}", 'Montant requis (0 si aucun).');
                }
            }
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $service->save($this->date, $balances, $versements, $this->releveId ? Releve::findOrFail($this->releveId) : null);

        $this->reset('formOpen', 'releveId', 'date', 'balances', 'versements');
        $this->dispatch('notify', text: 'Relevé enregistré.');
    }

    public function deleteReleve(int $id): void
    {
        Releve::findOrFail($id)->delete();
        if ($this->releveId === $id) {
            $this->reset('formOpen', 'releveId', 'date', 'balances', 'versements');
        }
        $this->dispatch('notify', text: 'Relevé supprimé.');
    }

    public function cancel(): void
    {
        $this->resetErrorBag();
        $this->reset('formOpen', 'releveId', 'date', 'balances', 'versements');
    }

    protected function validDate(): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) === 1;
    }

    protected function prefill(bool $keepTyped): void
    {
        $releve = $this->releveId ? Releve::find($this->releveId) : null;
        $balances = [];
        $versements = [];

        foreach (app(PatrimoineService::class)->draft($this->date, $releve) as $id => $line) {
            $balances[$id] = $keepTyped && array_key_exists($id, $this->balances) ? $this->balances[$id] : $line['balance'];
            if ($line['versement'] !== null) {
                $versements[$id] = $keepTyped && array_key_exists($id, $this->versements) ? $this->versements[$id] : $line['versement'];
            }
        }

        $this->balances = $balances;
        $this->versements = $versements;
    }

    public function render(PatrimoineService $service, PatrimoineReport $report)
    {
        $releves = $report->releves();
        $charts = [
            'total' => $report->totalOption($releves),
            'classes' => $report->classesOption($releves),
            'versements' => $report->versementsOption($releves),
        ];

        // Push fresh options to the (wire:ignore) ECharts instances.
        $this->dispatch('charts-updated', ...$charts);

        return view('livewire.patrimoine', [
            'latest' => $report->latest($releves),
            'charts' => $charts,
            'hasVersements' => $report->hasVersements($releves),
            'history' => $releves->reverse()->values(),
            'formAvoirs' => $this->formOpen && $this->validDate()
                ? $service->activeAvoirs($this->date)->groupBy('classe_id')
                : collect(),
            'needsSetup' => ! Avoir::exists(),
        ])->title('Patrimoine · Savings Budget');
    }
}
