<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $url)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Chore Charts sign-in link',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.magic-link',
            with: ['url' => $this->url],
        );
    }
}
