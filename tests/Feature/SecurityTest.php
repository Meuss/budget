<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Statamic\Facades\User;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private const BUDGET_URLS = ['/budget', '/budget/transactions', '/budget/import'];

    protected function tearDown(): void
    {
        // Users are flat files in users/, not DB rows, so RefreshDatabase doesn't clean them up.
        User::findByEmail('sec@example.test')?->delete();

        parent::tearDown();
    }

    protected function user(bool $withTwoFactor)
    {
        $user = User::make()->email('sec@example.test')->makeSuper();
        if ($withTwoFactor) {
            $user->set('two_factor_secret', encrypt('secret'));
            $user->set('two_factor_confirmed_at', now()->timestamp);
        }
        $user->save();

        return $user;
    }

    public function test_guests_cannot_reach_any_budget_page(): void
    {
        foreach (self::BUDGET_URLS as $url) {
            $this->get($url)->assertRedirect('/cp');
        }
    }

    public function test_users_without_two_factor_are_sent_to_setup(): void
    {
        $this->actingAs($this->user(withTwoFactor: false));

        foreach (self::BUDGET_URLS as $url) {
            $this->get($url)->assertRedirect(route('statamic.cp.two-factor-setup'));
        }
    }

    public function test_users_with_two_factor_can_use_the_app(): void
    {
        $this->artisan('seed:budget');
        $this->actingAs($this->user(withTwoFactor: true));

        foreach (self::BUDGET_URLS as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_statamic_front_end_is_disabled(): void
    {
        $this->get('/home')->assertNotFound();
        $this->get('/some-page')->assertNotFound();
    }

    public function test_responses_ask_crawlers_not_to_index(): void
    {
        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/cp/auth/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
