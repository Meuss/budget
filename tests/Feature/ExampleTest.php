<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The site root redirects guests into the budget app (which itself sends
     * unauthenticated visitors to the Statamic CP login).
     */
    public function test_the_root_redirects_to_the_budget_app(): void
    {
        $this->get('/')->assertRedirect('/budget');
    }
}
