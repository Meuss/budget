<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\ClassifierService;
use Illuminate\Console\Command;

class ReclassifyTransactions extends Command
{
    protected $signature = 'classify:transactions {--all : Also re-evaluate rows already classified by a rule (never touches manual rows)}';

    protected $description = 'Re-run the auto-classifier over transactions (after editing category rules). Manual classifications are always preserved.';

    public function handle(ClassifierService $classifier): int
    {
        $query = Transaction::where('source', '!=', 'manual');

        if (! $this->option('all')) {
            $query->where(function ($q) {
                $q->whereNull('category_id')->where('is_savings', false);
            });
        }

        $changed = $classifier->reclassify($query->cursor());

        $this->info("Reclassified {$changed} transaction(s).");

        return self::SUCCESS;
    }
}
