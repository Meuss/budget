<?php

return [
    /*
    | Your own savings-account IBAN (spaces optional). Outgoing transfers whose
    | details contain it are tagged as "savings" rather than spending. Leave the
    | BUDGET_SAVINGS_IBAN env var empty to rely on the "EPARGNE" text match only.
    */
    'savings_iban' => env('BUDGET_SAVINGS_IBAN'),
];
