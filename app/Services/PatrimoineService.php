<?php

namespace App\Services;

use App\Models\Avoir;
use App\Models\Releve;
use App\Models\ReleveLigne;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Writing Relevés: which Avoirs belong in one, what the form proposes, and saving it.
 * Amounts travel as decimal strings ("12283.00") so nothing is lost to float rounding.
 */
class PatrimoineService
{
    /** "12'283", "12 283,50", "-1500" → "12283.00"; empty or anything else → null. */
    public static function parseAmount(?string $input): ?string
    {
        $s = str_replace(["'", "\u{2019}", ' ', "\u{00A0}", "\u{202F}"], '', trim((string) $input));
        $s = str_replace(',', '.', $s);

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $s)) {
            return null;
        }

        return number_format((float) $s, 2, '.', '');
    }

    /** Calendar months from one date to the next (01.09 → 20.11 = 2): standing orders run once a month. */
    public static function monthsBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        return max(0, ($to->year * 12 + $to->month) - ($from->year * 12 + $from->month));
    }

    /** The Avoirs a Relevé dated $date holds, in display order (Classe, then Avoir). */
    public function activeAvoirs(string $date): Collection
    {
        return Avoir::query()
            ->activeOn($date)
            ->join('patrimoine_classes', 'patrimoine_classes.id', '=', 'patrimoine_avoirs.classe_id')
            ->orderBy('patrimoine_classes.position')->orderBy('patrimoine_classes.id')
            ->orderBy('patrimoine_avoirs.position')->orderBy('patrimoine_avoirs.id')
            ->select('patrimoine_avoirs.*')
            ->with('classe')
            ->get();
    }

    /** The first Relevé after $date, if any (other than $except). */
    public function nextReleve(string $date, ?Releve $except = null): ?Releve
    {
        return Releve::query()
            ->where('date', '>', $date)
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->orderBy('date')
            ->first();
    }

    /**
     * What the form shows for a Relevé dated $date: stored values when editing $releve, otherwise
     * balances from the closest earlier Relevé and Versement mensuel × months since it. When a later
     * Relevé exists, its Versement already covers this period, so none is proposed here.
     * An Avoir with no balance in any earlier Relevé is optional: empty means it did not exist yet.
     *
     * @return array<int, array{balance: string, versement: ?string, optional: bool}>
     */
    public function draft(string $date, ?Releve $releve = null): array
    {
        $stored = $releve?->lignes()->get()->keyBy('avoir_id') ?? collect();

        $previous = Releve::query()
            ->where('date', '<', $date)
            ->when($releve, fn ($q) => $q->whereKeyNot($releve->id))
            ->orderByDesc('date')
            ->with('lignes')
            ->first();
        $previousLines = $previous?->lignes->keyBy('avoir_id') ?? collect();
        $months = $previous ? self::monthsBetween($previous->date, Carbon::parse($date)) : 0;
        if ($this->nextReleve($date, $releve)) {
            $months = 0;
        }
        $earlier = ReleveLigne::query()
            ->whereHas('releve', fn ($q) => $q->where('date', '<', $date))
            ->distinct()->pluck('avoir_id')->flip();

        $draft = [];
        foreach ($this->activeAvoirs($date) as $avoir) {
            $line = $stored->get($avoir->id);

            if ($line) {
                $balance = (string) $line->balance;
                $versement = $avoir->suitLesVersements() ? (string) ($line->versement ?? '0.00') : null;
            } else {
                $balance = (string) ($previousLines->get($avoir->id)?->balance ?? '');
                $versement = $avoir->suitLesVersements()
                    ? number_format((float) $avoir->versement_mensuel * $months, 2, '.', '')
                    : null;
            }

            $draft[$avoir->id] = ['balance' => $balance, 'versement' => $versement, 'optional' => ! $earlier->has($avoir->id)];
        }

        return $draft;
    }

    /**
     * Create or update a Relevé with one line per Avoir active on $date that has a balance; lines for
     * Avoirs left empty or no longer active on that date (date moved past an archive date) are removed.
     *
     * @param  array<int, ?string>  $balances  avoirId => parsed amount, null = no line
     * @param  array<int, string>  $versements  avoirId => parsed amount (tracked Avoirs only)
     */
    public function save(string $date, array $balances, array $versements, ?Releve $releve = null): Releve
    {
        return DB::transaction(function () use ($date, $balances, $versements, $releve) {
            $releve ??= new Releve;
            $releve->date = $date;
            $releve->save();

            $kept = [];
            foreach ($this->activeAvoirs($date) as $avoir) {
                if (($balances[$avoir->id] ?? null) === null) {
                    continue;
                }
                $releve->lignes()->updateOrCreate(['avoir_id' => $avoir->id], [
                    'balance' => $balances[$avoir->id],
                    'versement' => $avoir->suitLesVersements() ? ($versements[$avoir->id] ?? '0.00') : null,
                ]);
                $kept[] = $avoir->id;
            }
            $releve->lignes()->whereNotIn('avoir_id', $kept)->delete();

            return $releve->load('lignes');
        });
    }
}
