<?php

namespace App\Livewire;

use App\Models\Transaction;
use App\Services\CategoryService;
use App\Services\ClassifierService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('budget.layout')]
class Transactions extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = 'all';   // 'all' | 'unclassified' | <category id>

    #[Url]
    public string $year = 'all';

    #[Url]
    public string $direction = 'all';  // 'all' | 'debit' | 'credit'

    #[Url]
    public string $amountMin = '';     // filter on |amount| >=

    #[Url]
    public string $amountMax = '';     // filter on |amount| <=

    public string $bulkCategory = '';

    /** Ids of hand-picked rows (persist across pages). */
    public array $selected = [];

    public int $perPage = 50;

    public function updating($name): void
    {
        if (in_array($name, ['search', 'category', 'year', 'direction', 'amountMin', 'amountMax'])) {
            $this->resetPage();
            $this->selected = []; // a new filter means a fresh selection
        }
    }

    protected function baseQuery(): Builder
    {
        return Transaction::query()
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($w) => $w->where('merchant', 'like', $term)
                    ->orWhere('details', 'like', $term)
                    ->orWhere('type', 'like', $term));
            })
            ->when($this->category === 'unclassified',
                fn ($q) => $q->whereNull('category_id')->where('is_savings', false))
            ->when($this->category !== 'all' && $this->category !== 'unclassified',
                fn ($q) => $q->where('category_id', $this->category))
            ->when($this->year !== 'all',
                fn ($q) => $q->whereBetween('date', ["{$this->year}-01-01", "{$this->year}-12-31"]))
            ->when($this->direction !== 'all',
                fn ($q) => $q->where('direction', $this->direction))
            // cast(? as decimal) keeps the bound value numeric — Laravel binds floats
            // as strings, and SQLite then treats "numeric >= text" as always false.
            ->when(is_numeric($this->amountMin),
                fn ($q) => $q->whereRaw('abs(amount) >= cast(? as decimal(12,2))', [$this->amountMin]))
            ->when(is_numeric($this->amountMax),
                fn ($q) => $q->whereRaw('abs(amount) <= cast(? as decimal(12,2))', [$this->amountMax]));
    }

    /** Classify one row by hand (sticks; rules won't override it later). */
    public function assign(int $id, string $categoryId): void
    {
        $t = Transaction::findOrFail($id);

        if ($categoryId === '') {
            $t->forceFill(['category_id' => null, 'is_savings' => false, 'source' => 'manual'])->save();
        } else {
            $kind = app(CategoryService::class)->kindOf($categoryId);
            $t->forceFill([
                'category_id' => $categoryId,
                'is_savings' => $kind === 'savings',
                'source' => 'manual',
            ])->save();
        }
    }

    public function toggleSavings(int $id): void
    {
        $t = Transaction::findOrFail($id);
        $t->forceFill(['is_savings' => ! $t->is_savings, 'source' => 'manual'])->save();
    }

    /** Apply the chosen category to EVERY row matching the current filter. */
    public function bulkAssign(): void
    {
        if ($this->bulkCategory === '') {
            $this->dispatch('notify', text: "Choisissez d'abord une catégorie.");

            return;
        }

        $clearing = $this->bulkCategory === '__clear__';
        $kind = $clearing ? null : app(CategoryService::class)->kindOf($this->bulkCategory);

        $count = (clone $this->baseQuery())->update([
            'category_id' => $clearing ? null : $this->bulkCategory,
            'is_savings' => $kind === 'savings',
            'source' => 'manual',
        ]);

        $this->dispatch('notify', text: "{$count} transaction(s) mise(s) à jour.");
    }

    /** Re-run auto-rules over the filtered, non-manual rows. */
    public function bulkApplyRules(ClassifierService $classifier): void
    {
        $rows = (clone $this->baseQuery())->where('source', '!=', 'manual')->cursor();
        $changed = $classifier->reclassify($rows);
        $this->dispatch('notify', text: "Règles appliquées : {$changed} modifiée(s).");
    }

    protected function orderedQuery(): Builder
    {
        return $this->baseQuery()->orderByDesc('date')->orderByDesc('id');
    }

    /** Ids shown on the current page, as strings (checkbox values are strings). */
    protected function pageIds(): array
    {
        return (clone $this->orderedQuery())
            ->forPage($this->getPage(), $this->perPage)
            ->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /** Header checkbox: select / deselect every row on the current page. */
    public function toggleSelectPage(): void
    {
        $ids = $this->pageIds();
        $allOnPage = $ids && ! array_diff($ids, $this->selected);

        $this->selected = $allOnPage
            ? array_values(array_diff($this->selected, $ids))
            : array_values(array_unique([...$this->selected, ...$ids]));
    }

    /** Select every row matching the current filter (across all pages). */
    public function selectAllMatching(): void
    {
        $this->selected = (clone $this->baseQuery())
            ->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    /** Apply the chosen category to the hand-picked (checked) rows only. */
    public function assignSelected(): void
    {
        if (empty($this->selected)) {
            $this->dispatch('notify', text: 'Aucune ligne sélectionnée.');

            return;
        }
        if ($this->bulkCategory === '') {
            $this->dispatch('notify', text: "Choisissez d'abord une catégorie.");

            return;
        }

        $clearing = $this->bulkCategory === '__clear__';
        $kind = $clearing ? null : app(CategoryService::class)->kindOf($this->bulkCategory);

        $count = Transaction::whereIn('id', $this->selected)->update([
            'category_id' => $clearing ? null : $this->bulkCategory,
            'is_savings' => $kind === 'savings',
            'source' => 'manual',
        ]);

        $this->selected = [];
        $this->dispatch('notify', text: "{$count} transaction(s) sélectionnée(s) mise(s) à jour.");
    }

    public function render()
    {
        $categories = app(CategoryService::class);

        return view('livewire.transactions', [
            'rows' => $this->orderedQuery()->paginate($this->perPage),
            'matchCount' => (clone $this->baseQuery())->count(),
            'cats' => $categories->ordered(),
            'categoryService' => $categories,
        ])->title('Transactions · Savings Budget');
    }
}
