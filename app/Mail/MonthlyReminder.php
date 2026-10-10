<?php

namespace App\Mail;

use App\Models\Releve;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Monthly nudge to import the new statements and enter a Relevé. */
class MonthlyReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Budget : nouveau mois, nouvelles données');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.monthly-reminder',
            with: [
                'lastTransaction' => Transaction::max('date'),
                'lastReleve' => Releve::max('date'),
            ],
        );
    }
}
