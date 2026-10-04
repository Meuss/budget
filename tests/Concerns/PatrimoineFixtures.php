<?php

namespace Tests\Concerns;

use App\Models\Avoir;
use App\Models\Classe;
use App\Models\Releve;

/** Synthetic Patrimoine data: the repository is public, never use real figures. */
trait PatrimoineFixtures
{
    protected function makeHoldings(): array
    {
        $liq = Classe::create(['title' => 'Liquidités', 'position' => 0]);
        $prev = Classe::create(['title' => 'Prévoyance', 'description' => 'Bloqué', 'position' => 1]);
        $courant = Avoir::create(['classe_id' => $liq->id, 'title' => 'Compte courant', 'position' => 0]);
        $titres = Avoir::create(['classe_id' => $prev->id, 'title' => 'Compte titres', 'position' => 0, 'versement_mensuel' => '300.00']);

        return compact('liq', 'prev', 'courant', 'titres');
    }

    /** @param array<int, array{0: string|float, 1: string|float|null}> $lines avoirId => [balance, versement] */
    protected function makeReleve(string $date, array $lines): Releve
    {
        $releve = Releve::create(['date' => $date]);
        foreach ($lines as $avoirId => [$balance, $versement]) {
            $releve->lignes()->create(['avoir_id' => $avoirId, 'balance' => $balance, 'versement' => $versement]);
        }

        return $releve->load('lignes');
    }
}
