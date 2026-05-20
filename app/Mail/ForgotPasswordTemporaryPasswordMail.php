<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ForgotPasswordTemporaryPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $temporaryPassword
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                'Зарядка кофе'
            ),
            subject: 'Временный пароль'
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.auth.forgot-password-temp-password-text',
            with: [
                'temporaryPassword' => $this->temporaryPassword,
            ]
        );
    }
}
