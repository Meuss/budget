<?php

namespace App\Livewire;

use App\Models\Avoir;
use App\Models\Classe;
use App\Services\PatrimoineService;
use Illuminate\Support\Collection;
use Livewire\Component;

/** The "Gérer les avoirs" panel: Classes and Avoirs, their order, archiving. */
class PatrimoineAvoirs extends Component
{
    public ?int $classeId = null;   // null = creating a new Classe

    public string $classeTitle = '';

    public string $classeDescription = '';

    public ?int $avoirId = null;    // null = creating a new Avoir

    public string $avoirTitle = '';

    public string $avoirDescription = '';

    public string $avoirClasse = '';

    public string $avoirVersementMensuel = '';

    /** Archive date per Avoir id, "Y-m-d" (defaults to today). */
    public array $archiveOn = [];

    public function saveClasse(): void
    {
        $data = $this->validate([
            'classeTitle' => 'required|string|max:60',
            'classeDescription' => 'nullable|string|max:160',
        ]);
        $fields = ['title' => trim($data['classeTitle']), 'description' => trim((string) $data['classeDescription']) ?: null];

        if ($this->classeId) {
            Classe::findOrFail($this->classeId)->update($fields);
        } else {
            Classe::create($fields + ['position' => (Classe::max('position') ?? -1) + 1]);
        }

        $this->cancel();
        $this->changed();
    }

    public function editClasse(int $id): void
    {
        $classe = Classe::findOrFail($id);
        $this->cancel();
        $this->classeId = $classe->id;
        $this->classeTitle = $classe->title;
        $this->classeDescription = (string) $classe->description;
    }

    public function deleteClasse(int $id): void
    {
        $classe = Classe::findOrFail($id);
        if ($classe->avoirs()->exists()) {
            $this->dispatch('notify', text: 'Cette classe contient des avoirs : déplacez-les ou supprimez-les d\'abord.');

            return;
        }
        $classe->delete();
        $this->changed();
    }

    public function moveClasse(int $id, int $dir): void
    {
        $this->move(Classe::orderBy('position')->orderBy('id')->get(), $id, $dir);
    }

    public function saveAvoir(): void
    {
        $data = $this->validate([
            'avoirTitle' => 'required|string|max:60',
            'avoirDescription' => 'nullable|string|max:160',
            'avoirClasse' => 'required|exists:patrimoine_classes,id',
            'avoirVersementMensuel' => ['nullable', function ($attribute, $value, $fail) {
                if (trim((string) $value) !== '' && PatrimoineService::parseAmount($value) === null) {
                    $fail('Montant invalide.');
                }
            }],
        ]);
        // Empty = this Avoir does not track Versements.
        $mensuel = trim($this->avoirVersementMensuel) === '' ? null : PatrimoineService::parseAmount($this->avoirVersementMensuel);

        $fields = [
            'title' => trim($data['avoirTitle']),
            'description' => trim((string) $data['avoirDescription']) ?: null,
            'classe_id' => (int) $data['avoirClasse'],
            'versement_mensuel' => $mensuel,
        ];
        $endOfClasse = fn () => (Avoir::where('classe_id', $fields['classe_id'])->max('position') ?? -1) + 1;

        if ($this->avoirId) {
            $avoir = Avoir::findOrFail($this->avoirId);
            if ($avoir->classe_id !== $fields['classe_id']) {
                $fields['position'] = $endOfClasse();
            }
            $avoir->update($fields);
        } else {
            Avoir::create($fields + ['position' => $endOfClasse()]);
        }

        $this->cancel();
        $this->changed();
    }

    public function editAvoir(int $id): void
    {
        $avoir = Avoir::findOrFail($id);
        $this->cancel();
        $this->avoirId = $avoir->id;
        $this->avoirTitle = $avoir->title;
        $this->avoirDescription = (string) $avoir->description;
        $this->avoirClasse = (string) $avoir->classe_id;
        $this->avoirVersementMensuel = $avoir->versement_mensuel === null ? '' : (string) $avoir->versement_mensuel;
    }

    public function deleteAvoir(int $id): void
    {
        $avoir = Avoir::findOrFail($id);
        if ($avoir->lignes()->exists()) {
            $this->dispatch('notify', text: 'Cet avoir a des soldes enregistrés : archivez-le plutôt.');

            return;
        }
        $avoir->delete();
        $this->changed();
    }

    public function moveAvoir(int $id, int $dir): void
    {
        $avoir = Avoir::findOrFail($id);
        $this->move(Avoir::where('classe_id', $avoir->classe_id)->orderBy('position')->orderBy('id')->get(), $id, $dir);
    }

    public function archiveAvoir(int $id): void
    {
        $this->validate(["archiveOn.$id" => 'nullable|date_format:Y-m-d']);
        Avoir::findOrFail($id)->update(['archived_on' => $this->archiveOn[$id] ?? now()->toDateString()]);
        unset($this->archiveOn[$id]);
        $this->changed();
    }

    public function unarchiveAvoir(int $id): void
    {
        Avoir::findOrFail($id)->update(['archived_on' => null]);
        $this->changed();
    }

    public function cancel(): void
    {
        $this->resetErrorBag();
        $this->reset('classeId', 'classeTitle', 'classeDescription', 'avoirId', 'avoirTitle', 'avoirDescription', 'avoirClasse', 'avoirVersementMensuel');
    }

    /** Swap $id with its neighbour ($dir = -1 up, +1 down) and renumber the siblings 0..n. */
    protected function move(Collection $siblings, int $id, int $dir): void
    {
        $ids = $siblings->pluck('id')->all();
        $i = array_search($id, $ids, true);
        $j = $i === false ? -1 : $i + $dir;
        if ($j < 0 || $j >= count($ids)) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];

        foreach ($ids as $position => $siblingId) {
            $siblings->firstWhere('id', $siblingId)->update(['position' => $position]);
        }
        $this->changed();
    }

    protected function changed(): void
    {
        $this->dispatch('avoirs-changed');
    }

    public function render()
    {
        return view('livewire.patrimoine-avoirs', [
            'classes' => Classe::with('avoirs')->orderBy('position')->orderBy('id')->get(),
        ]);
    }
}
