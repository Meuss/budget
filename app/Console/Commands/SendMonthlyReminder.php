<?php

namespace App\Console\Commands;

use App\Mail\MonthlyReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendMonthlyReminder extends Command
{
    protected $signature = 'budget:remind';

    protected $description = 'Email the monthly reminder to import statements and enter a Relevé (scheduled on the 2nd of each month).';

    public function handle(): int
    {
        $to = config('budget.reminder_email');

        if (blank($to)) {
            $this->warn('BUDGET_REMINDER_EMAIL is not set: no reminder sent.');

            return self::SUCCESS;
        }

        Mail::to($to)->send(new MonthlyReminder);
        $this->info("Reminder sent to {$to}.");

        return self::SUCCESS;
    }
}
