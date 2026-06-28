<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\TransactionImporter;
use Illuminate\Console\Command;

class ImportTransactions extends Command
{
    protected $signature = 'import:transactions {path? : CSV file or directory (defaults to storage/app/imports)}';

    protected $description = 'Import UBS transaction CSV export(s); dedupes by transaction number and auto-classifies new rows.';

    public function handle(TransactionImporter $importer): int
    {
        $files = $this->resolveFiles($this->argument('path'));

        if (empty($files)) {
            $this->error('No CSV files found.');

            return self::FAILURE;
        }

        $totalNew = 0;
        $totalDup = 0;

        foreach ($files as $file) {
            $this->line("Importing <info>{$file}</info> ...");
            $result = $importer->importFile($file);
            $this->line("  → {$result['new']} new, {$result['duplicates']} already present");
            $totalNew += $result['new'];
            $totalDup += $result['duplicates'];
        }

        $this->info("Done. {$totalNew} imported, {$totalDup} duplicates skipped. Total in DB: ".Transaction::count());

        return self::SUCCESS;
    }

    /** @return string[] */
    protected function resolveFiles(?string $path): array
    {
        $path ??= storage_path('app/imports');

        foreach ([$path, base_path($path)] as $candidate) {
            if (is_dir($candidate)) {
                return glob(rtrim($candidate, '/').'/*.csv') ?: [];
            }
            if (is_file($candidate)) {
                return [$candidate];
            }
        }

        return [];
    }
}
