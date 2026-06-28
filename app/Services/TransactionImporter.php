<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionImporter
{
    public function __construct(
        protected UbsCsvParser $parser,
        protected ClassifierService $classifier,
    ) {
    }

    /**
     * Import a single CSV file. Dedupes by transaction number and auto-classifies
     * new rows. Returns ['new' => int, 'duplicates' => int].
     */
    public function importFile(string $path): array
    {
        $existing = Transaction::pluck('transaction_no')->flip();
        $batch = [];
        $dup = 0;

        foreach ($this->parser->parse($path) as $record) {
            if ($existing->has($record['transaction_no'])) {
                $dup++;

                continue;
            }
            $existing->put($record['transaction_no'], true); // guard intra-file dupes
            $batch[] = $record;
        }

        $new = 0;
        DB::transaction(function () use ($batch, &$new) {
            foreach ($batch as $record) {
                $t = new Transaction($record); // raw[] encoded by the model's array cast
                $t->save();

                if ($update = $this->classifier->classify($t)) {
                    $t->forceFill($update)->save();
                }
                $new++;
            }
        });

        return ['new' => $new, 'duplicates' => $dup];
    }
}
