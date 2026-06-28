<?php

namespace App\Actions;

use App\Models\Transaction;
use App\Services\ClassifierService;
use Statamic\Actions\Action;
use Statamic\Contracts\Entries\Entry;

/**
 * CP action shown on category entries. After editing a category's auto-match
 * terms, run this to re-apply the rules across existing transactions — newly
 * matching rows get classified, and rows whose term was removed are revisited.
 * Manual classifications are always preserved.
 */
class ReclassifyTransactions extends Action
{
    public static function title()
    {
        return 'Reclasser les transactions';
    }

    public function visibleTo($item)
    {
        return $item instanceof Entry && $item->collection()?->handle() === 'categories';
    }

    public function visibleToBulk($items)
    {
        return $items->every(fn ($item) => $this->visibleTo($item));
    }

    public function authorize($user, $item)
    {
        return true; // any CP user editing categories may reclassify
    }

    public function buttonText()
    {
        return 'Reclasser les transactions';
    }

    public function confirmationText()
    {
        return 'Réappliquer les règles automatiques à toutes les transactions (les classements manuels sont conservés) ?';
    }

    public function run($items, $values)
    {
        $changed = app(ClassifierService::class)->reclassify(
            Transaction::where('source', '!=', 'manual')->cursor()
        );

        return $changed === 0
            ? 'Aucune transaction modifiée.'
            : "{$changed} transaction(s) reclassée(s).";
    }
}
