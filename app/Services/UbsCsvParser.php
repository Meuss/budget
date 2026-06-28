<?php

namespace App\Services;

use Generator;

/**
 * Parses a UBS account-statement CSV export.
 *
 * Format: UTF-8 (with BOM), ";"-delimited, quoted fields may contain embedded
 * semicolons. The file starts with an account metadata block; the real data
 * begins at the row whose first cell is "Date de transaction".
 */
class UbsCsvParser
{
    /** Yields one associative array per transaction row. */
    public function parse(string $path): Generator
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open CSV: {$path}");
        }

        $headerSeen = false;

        try {
            while (($row = fgetcsv($handle, 0, ';', '"', '')) !== false) {
                if ($row === [null] || $row === false) {
                    continue; // blank line
                }

                $first = isset($row[0]) ? $this->stripBom((string) $row[0]) : '';

                if (! $headerSeen) {
                    if (trim($first) === 'Date de transaction') {
                        $headerSeen = true;
                    }
                    continue; // skip metadata + header line itself
                }

                $row[0] = $first; // BOM only ever appears on the very first cell

                $record = $this->mapRow($row);
                if ($record !== null) {
                    yield $record;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    protected function mapRow(array $row): ?array
    {
        $transactionNo = trim($row[9] ?? '');
        if ($transactionNo === '') {
            return null; // not a real transaction line
        }

        $debit = $this->amount($row[5] ?? '');
        $credit = $this->amount($row[6] ?? '');

        if ($debit !== null) {
            $amount = $debit;            // already negative in the export
            $direction = 'debit';
        } elseif ($credit !== null) {
            $amount = abs($credit);
            $direction = 'credit';
        } else {
            $amount = 0.0;
            $direction = 'debit';
        }

        return [
            'transaction_no' => $transactionNo,
            'date' => $this->date($row[0] ?? '') ?? now()->toDateString(),
            'value_date' => $this->date($row[3] ?? ''),
            'currency' => trim($row[4] ?? 'CHF') ?: 'CHF',
            'amount' => $amount,
            'direction' => $direction,
            'merchant' => $this->clean($row[10] ?? ''),
            'type' => $this->clean($row[11] ?? ''),
            'details' => $this->clean($row[12] ?? ''),
            'balance' => $this->amount($row[8] ?? ''),
            'raw' => array_slice($row, 0, 14),
        ];
    }

    protected function stripBom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }

    /** Parse a Swiss-formatted number; returns null for empty cells. */
    protected function amount(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        // Strip apostrophe/space thousands separators; keep sign and decimal point.
        $value = str_replace(["'", ' ', "\u{2019}"], '', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    protected function date(string $value): ?string
    {
        $value = trim($value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    protected function clean(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
