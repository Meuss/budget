<?php

namespace App\Services;

use Generator;

/**
 * Parses a UBS credit-card statement CSV export.
 *
 * This differs from the bank account export ({@see UbsCsvParser}) in almost
 * every way: it is Windows-1252 (not UTF-8), starts with a "sep=;" hint then
 * jumps straight to the header (no metadata block), uses DD.MM.YYYY dates,
 * unsigned Débit/Crédit columns holding the CHF amount, and — crucially — has
 * NO transaction id. We synthesise a stable dedupe key per row so re-imports of
 * an overlapping export still dedupe, while two genuinely-identical purchases on
 * the same day are both kept.
 *
 * Columns (0-indexed):
 *   0 Numéro de compte   1 Numéro de carte   2 Titulaire        3 Date d'achat
 *   4 Texte comptable    5 Secteur           6 Montant          7 Monnaie originale
 *   8 Cours              9 Monnaie          10 Débit           11 Crédit
 *  12 Ecriture (booking date)
 */
class CreditCardCsvParser
{
    /** Yields one associative array per booked transaction row. */
    public function parse(string $path): Generator
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException("Cannot open CSV: {$path}");
        }

        // The export is Latin-1/Windows-1252; normalise to UTF-8 for the rest of the app.
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $raw = $this->stripBom($raw);

        $seen = []; // content-hash => times seen, to disambiguate identical rows

        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            if (trim($line) === '' || strcasecmp(trim($line), 'sep=;') === 0) {
                continue;
            }

            $row = str_getcsv($line, ';', '"', '');

            $record = $this->mapRow($row, $seen);
            if ($record !== null) {
                yield $record;
            }
        }
    }

    protected function mapRow(array $row, array &$seen): ?array
    {
        $purchaseDate = $this->date($row[3] ?? '');
        if ($purchaseDate === null) {
            return null; // header ("Date d'achat"), footer totals, or blank line
        }

        $debit = $this->amount($row[10] ?? '');
        $credit = $this->amount($row[11] ?? '');

        if ($debit !== null) {
            $amount = -abs($debit);
            $direction = 'debit';
        } elseif ($credit !== null) {
            $amount = abs($credit);
            $direction = 'credit';
        } else {
            return null; // pending authorisation — not yet booked, no CHF amount
        }

        $bookingDate = $this->date($row[12] ?? '') ?? $purchaseDate;

        return [
            'transaction_no' => $this->dedupeKey($row, $seen),
            'date' => $bookingDate,
            'value_date' => $purchaseDate,
            'currency' => trim($row[9] ?? 'CHF') ?: 'CHF',
            'amount' => $amount,
            'direction' => $direction,
            'merchant' => $this->clean($row[4] ?? ''),
            'type' => null,   // Secteur (col 5) is intentionally NOT classified on; kept in raw
            'details' => null,
            'balance' => null, // card statements carry no running balance
            'raw' => array_slice($row, 0, 13),
        ];
    }

    /**
     * A stable per-row key. Hashes the identifying fields, then appends how many
     * times this exact content has been seen in the file so identical same-day
     * rows (e.g. two identical Uber trips) each get a distinct — yet reproducible —
     * key. Prefixed "CC-" so it can never collide with a bank "No de transaction".
     */
    protected function dedupeKey(array $row, array &$seen): string
    {
        $parts = [$row[0] ?? '', $row[1] ?? '', $row[3] ?? '', $row[12] ?? '',
            $row[4] ?? '', $row[6] ?? '', $row[7] ?? '', $row[10] ?? '', $row[11] ?? ''];
        $hash = sha1(implode('|', $parts));
        $n = ($seen[$hash] = ($seen[$hash] ?? 0) + 1);

        return "CC-{$hash}-{$n}";
    }

    protected function stripBom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }

    /** Parse a card amount; returns null for empty cells. */
    protected function amount(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $value = str_replace(["'", ' ', "\u{2019}"], '', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    /** Convert a DD.MM.YYYY cell to YYYY-MM-DD; null when not a valid date. */
    protected function date(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return null;
    }

    protected function clean(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
