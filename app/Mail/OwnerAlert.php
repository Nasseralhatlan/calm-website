<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A plain internal alert to the owners — a subject and a few key-fact lines.
 * Queued so a slow mail server never blocks the booking/place flow.
 */
final class OwnerAlert extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public readonly string $subjectLine,
        public readonly array $lines,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(text: 'emails.owner-alert', with: ['lines' => $this->lines]);
    }
}
