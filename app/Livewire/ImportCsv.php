<?php

namespace App\Livewire;

use App\Models\Transaction;
use App\Services\ClassifierService;
use App\Services\TransactionImporter;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('budget.layout')]
class ImportCsv extends Component
{
    use WithFileUploads;

    /** @var array<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public $files = [];

    public ?array $result = null;

    public function import(TransactionImporter $importer): void
    {
        $this->validate([
            'files' => 'required|array',
            'files.*' => 'file|max:20480', // 20 MB each
        ]);

        $new = 0;
        $dup = 0;
        $names = [];

        foreach ($this->files as $file) {
            $r = $importer->importFile($file->getRealPath());
            $new += $r['new'];
            $dup += $r['duplicates'];
            $names[] = $file->getClientOriginalName();
        }

        $this->result = ['new' => $new, 'duplicates' => $dup, 'files' => $names];
        $this->files = [];
        $this->dispatch('notify', text: "{$new} importée(s), {$dup} doublon(s) ignoré(s).");
    }

    public function reapplyRules(ClassifierService $classifier): void
    {
        $rows = Transaction::where('source', '!=', 'manual')
            ->whereNull('category_id')->where('is_savings', false)->cursor();
        $changed = $classifier->reclassify($rows);
        $this->dispatch('notify', text: "Règles appliquées aux non classées : {$changed} modifiée(s).");
    }

    public function render()
    {
        return view('livewire.import-csv', [
            'total' => Transaction::count(),
            'classified' => Transaction::whereNotNull('category_id')->count(),
            'unclassified' => Transaction::whereNull('category_id')->where('is_savings', false)->count(),
            'savings' => Transaction::where('is_savings', true)->count(),
        ])->title('Import · Savings Budget');
    }
}
