<?php

namespace Tests\Feature;

use App\Mail\MonthlyReminder;
use App\Models\Releve;
use App\Models\Transaction;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MonthlyReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_the_reminder_to_the_configured_address(): void
    {
        Mail::fake();
        config(['budget.reminder_email' => 'me@example.test']);

        $this->artisan('budget:remind')->assertSuccessful();

        Mail::assertSent(MonthlyReminder::class, fn ($mail) => $mail->hasTo('me@example.test'));
    }

    public function test_it_sends_nothing_without_an_address(): void
    {
        Mail::fake();
        config(['budget.reminder_email' => null]);

        $this->artisan('budget:remind')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_the_mail_shows_the_latest_transaction_and_releve_dates(): void
    {
        Transaction::create(['transaction_no' => 'T1', 'date' => '2026-09-28', 'amount' => -10, 'direction' => 'debit', 'merchant' => 'Shop']);
        Releve::create(['date' => '2026-09-01']);

        $mail = new MonthlyReminder;

        $mail->assertSeeInHtml('28.09.2026');
        $mail->assertSeeInHtml('01.09.2026');
        $mail->assertSeeInHtml(route('budget.import'));
        $mail->assertSeeInHtml(route('budget.patrimoine'));
    }

    public function test_the_mail_handles_an_empty_database(): void
    {
        (new MonthlyReminder)->assertSeeInHtml('jamais');
    }

    public function test_it_is_scheduled_on_the_2nd_of_every_month_at_8(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'budget:remind'));

        $this->assertNotNull($event);
        $this->assertSame('0 8 2 * *', $event->expression);
        $this->assertSame('Europe/Zurich', $event->timezone);
    }

    public function test_the_cron_url_is_disabled_without_a_token(): void
    {
        config(['budget.reminder_token' => null]);

        $this->get('/cron/reminder')->assertNotFound();
    }

    public function test_the_cron_url_rejects_a_wrong_password(): void
    {
        Mail::fake();
        config(['budget.reminder_token' => 'secret-token', 'budget.reminder_email' => 'me@example.test']);

        $this->get('/cron/reminder')->assertUnauthorized();
        $this->withBasicAuth('cron', 'wrong')->get('/cron/reminder')->assertUnauthorized();

        Mail::assertNothingSent();
    }

    public function test_the_cron_url_sends_the_reminder_with_the_token(): void
    {
        Mail::fake();
        config(['budget.reminder_token' => 'secret-token', 'budget.reminder_email' => 'me@example.test']);

        $this->withBasicAuth('cron', 'secret-token')->get('/cron/reminder')->assertOk();

        Mail::assertSent(MonthlyReminder::class, fn ($mail) => $mail->hasTo('me@example.test'));
    }
}
