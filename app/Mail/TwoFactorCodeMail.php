<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TwoFactorCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $code,
        public int $ttlMinutes,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your NexHRIS verification code: {$this->code}",
        );
    }

    public function content(): Content
    {
        // Markdown, so it renders through the shared shell in
        // resources/views/vendor/mail and matches every other NexHRIS email.
        return new Content(markdown: 'emails.two-factor-code');
    }
}
