<?php

use App\Livewire\Dashboard;
use App\Livewire\ImportCsv;
use App\Livewire\Patrimoine;
use App\Livewire\Transactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Statamic\Http\Middleware\CP\RedirectIfTwoFactorSetupIncomplete;

// The Savings Budget app — gated behind the Statamic CP login (web "auth" guard), and 2FA:
// users who haven't set it up yet are sent to the CP setup screen (2FA is enforced for all roles).
Route::middleware(['auth', RedirectIfTwoFactorSetupIncomplete::class])->prefix('budget')->group(function () {
    Route::get('/', Dashboard::class)->name('budget.dashboard');
    Route::get('/transactions', Transactions::class)->name('budget.transactions');
    Route::get('/import', ImportCsv::class)->name('budget.import');
    Route::get('/patrimoine', Patrimoine::class)->name('budget.patrimoine');
});

// Hosts whose scheduler can only call a URL (e.g. Infomaniak's "Tâches planifiées") trigger the
// monthly reminder here, with BUDGET_REMINDER_TOKEN as the HTTP Basic password.
Route::get('/cron/reminder', function (Request $request) {
    $token = config('budget.reminder_token');
    abort_if(blank($token), 404);
    abort_unless(hash_equals($token, (string) $request->getPassword()), 401, headers: ['WWW-Authenticate' => 'Basic']);

    Artisan::call('budget:remind');

    return response(Artisan::output(), 200, ['Content-Type' => 'text/plain']);
})->middleware('throttle:5,60');

// Convenience: send the site root to the dashboard.
Route::redirect('/', '/budget');
