<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;

class SeedBudget extends Command
{
    protected $signature = 'seed:budget';

    protected $description = 'Seed a starter category tree (with Swiss merchant auto-rules) into the Statamic categories collection.';

    /**
     * Tree definition. Slugs are STABLE (kept in English) so re-seeding updates the
     * existing entries in place — preserving their ids, which transactions reference.
     * Titles are the French labels shown everywhere. Terms are case-insensitive substrings.
     *
     * Shape: ['slug', 'Titre', 'kind', [terms], [children...]]
     */
    protected function tree(): array
    {
        return [
            ['income', 'Revenus', 'income', ['salaire', 'salary', 'bonus', 'remboursement', 'virement en votre faveur'], []],
            ['savings', 'Épargne', 'savings', ['epargne', 'épargne'], []],

            ['housing', 'Logement', 'expense', ['loyer'], [
                ['housing-rent', 'Loyer', 'expense', ['loyer'], []],
                ['housing-utilities', 'Charges & énergie', 'expense', ['groupe e', 'romande energie', 'sig ', 'services industriels'], []],
            ]],

            ['food', 'Alimentation', 'expense', [], [
                ['food-groceries', 'Courses', 'expense', ['migros', 'denner', 'coop', 'aldi', 'lidl', 'manor food', 'volg'], []],
                ['food-restaurants-cafes', 'Restaurants & cafés', 'expense', ['restaurant', 'café', 'cafe', 'mcdonald', 'starbucks', 'kebab', 'pizz', 'uber eats', 'just eat'], []],
            ]],

            ['transport', 'Transport', 'expense', [], [
                ['transport-public-transport', 'Transports publics', 'expense', ['cff', 'sbb', 'tpf', 'tpg', 'transports publics', 'mobility'], []],
                ['transport-car-fuel', 'Voiture & carburant', 'expense', ['essence', 'socar', 'shell', 'migrol', 'tamoil', 'parking', 'autoroute', 'garage', 'paybyphone'], []],
            ]],

            ['insurance', 'Assurances', 'expense', ['assurance', 'zurich', 'axa', 'helvetia', 'css ', 'assura', 'visana', 'swica', 'sympany', 'groupe mutuel', 'helsana', 'sanitas', 'concordia', 'mutuel'], []],

            ['health', 'Santé', 'expense', ['pharmacie', 'pharmacy', 'médecin', 'medecin', 'hopital', 'hôpital', 'dentiste', 'permanence'], []],

            ['telecom-subscriptions', 'Télécom & abonnements', 'expense', ['swisscom', 'salt', 'sunrise', 'wingo', 'infomaniak', 'netflix', 'spotify', 'disney', 'youtube', 'apple.com', 'microsoft', 'adobe', 'openai', 'anthropic'], []],

            ['shopping', 'Achats', 'expense', ['galaxus', 'digitec', 'zalando', 'amazon', 'ikea', 'h&m', 'zara', 'manor', 'fnac', 'interio'], []],

            ['leisure', 'Loisirs', 'expense', ['cinema', 'cinéma', 'pathé', 'pathe', 'fitness', 'steam', 'playstation', 'nintendo', 'spotify', 'booking.com', 'airbnb'], []],

            ['taxes', 'Impôts', 'expense', ['impôt', 'impot', 'administration fiscale', 'afc ', 'tva'], []],

            ['cash-atm', 'Retraits & cash', 'expense', ['retrait', 'bancomat', 'distributeur', 'cash'], []],

            ['bank-fees', 'Frais bancaires', 'expense', ['décompte des prix', 'decompte des prix', 'prestations', 'frais bancaires', 'frais de tenue'], []],
        ];
    }

    public function handle(): int
    {
        $created = 0;
        $updated = 0;

        foreach ($this->tree() as $node) {
            $this->seedNode($node, null, $created, $updated);
        }

        $this->info("Categories seeded: {$created} created, {$updated} updated.");

        return self::SUCCESS;
    }

    protected function seedNode(array $node, ?string $parentId, int &$created, int &$updated): void
    {
        [$slug, $title, $kind, $terms, $children] = $node;

        $entry = Entry::query()
            ->where('collection', 'categories')
            ->where('slug', $slug)
            ->first();

        $data = [
            'title' => $title,
            'kind' => $kind,
            'match_terms' => $terms,
        ];
        if ($parentId) {
            $data['parent'] = [$parentId];
        }

        if ($entry) {
            $entry->merge($data)->save();
            $updated++;
        } else {
            $entry = Entry::make()
                ->collection('categories')
                ->blueprint('category')
                ->slug($slug)
                ->data($data);
            $entry->save();
            $created++;
        }

        foreach ($children as $child) {
            $this->seedNode($child, $entry->id(), $created, $updated);
        }
    }
}
