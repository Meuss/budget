<?php

return [
    /*
    | Your own savings-account IBAN (spaces optional). Outgoing transfers whose
    | details contain it are tagged as "savings" rather than spending. Leave the
    | BUDGET_SAVINGS_IBAN env var empty to rely on the "EPARGNE" text match only.
    */
    'savings_iban' => env('BUDGET_SAVINGS_IBAN'),

    /*
    | Monthly reminder to import new statements and enter a Relevé, sent on the
    | 2nd of every month at 08:00 (in this timezone) by `budget:remind`. Leave
    | BUDGET_REMINDER_EMAIL empty to send nothing.
    */
    'reminder_email' => env('BUDGET_REMINDER_EMAIL'),
    'reminder_timezone' => env('BUDGET_REMINDER_TIMEZONE', 'Europe/Zurich'),

    /*
    | For hosts whose scheduler can only call a URL: GET /cron/reminder with this
    | token as the HTTP Basic password (any username) runs `budget:remind`.
    | Leave it empty to keep the URL disabled (404).
    */
    'reminder_token' => env('BUDGET_REMINDER_TOKEN'),
];
