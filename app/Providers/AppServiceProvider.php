<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Actions\ReclassifyTransactions::register();

        // CP tweaks (e.g. hides the password-reset link); custom_css_url would need Statamic Pro.
        \Statamic\Statamic::externalStyle('/css/cp.css');

        // Re-apply the 2FA gate to Livewire update requests made from the /budget pages.
        \Livewire\Livewire::addPersistentMiddleware([
            \Statamic\Http\Middleware\CP\RedirectIfTwoFactorSetupIncomplete::class,
        ]);
    }
}
