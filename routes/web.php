<?php

use App\Livewire\Dashboard;
use App\Livewire\ImportCsv;
use App\Livewire\Patrimoine;
use App\Livewire\Transactions;
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

// Convenience: send the site root to the dashboard.
Route::redirect('/', '/budget');
