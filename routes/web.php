<?php

use App\Livewire\Dashboard;
use App\Livewire\ImportCsv;
use App\Livewire\Transactions;
use Illuminate\Support\Facades\Route;

// The Savings Budget app — gated behind the Statamic CP login (web "auth" guard).
Route::middleware('auth')->prefix('budget')->group(function () {
    Route::get('/', Dashboard::class)->name('budget.dashboard');
    Route::get('/transactions', Transactions::class)->name('budget.transactions');
    Route::get('/import', ImportCsv::class)->name('budget.import');
});

// Convenience: send the site root to the dashboard.
Route::redirect('/', '/budget');
