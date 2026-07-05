<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionImporter
{
    public function __construct(
        protected UbsCsvParser $parser,
        protected CreditCardCsvParser $cardParser,
        protected ClassifierService $classifier,
    ) {
    }

    /**
     * Import a single CSV file, auto-detecting whether it is a UBS bank-account
     * or credit-card export. Dedupes by transaction number and auto-classifies
     * new rows. Returns ['new' => int, 'duplicates' => int].
     */
    public function importFile(string $path): array
    {
        $existing = Transaction::pluck('transaction_no')->flip();
        $batch = [];
        $dup = 0;

        foreach ($this->parserFor($path)->parse($path) as $record) {
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

    /**
     * Pick the parser by sniffing the file's header. The credit-card export has
     * an unmistakable header row ("Date d'achat" / "Texte comptable"); everything
     * else is treated as a UBS bank-account export.
     */
    protected function parserFor(string $path): UbsCsvParser|CreditCardCsvParser
    {
        $head = (string) file_get_contents($path, false, null, 0, 4096);
        if (! mb_check_encoding($head, 'UTF-8')) {
            $head = mb_convert_encoding($head, 'UTF-8', 'Windows-1252');
        }

        return str_contains($head, "Date d'achat") || str_contains($head, 'Texte comptable')
            ? $this->cardParser
            : $this->parser;
    }
}
