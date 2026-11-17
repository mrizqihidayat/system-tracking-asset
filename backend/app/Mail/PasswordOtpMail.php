<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email kode OTP lupa password (T40). Judul web dan logo dari Pengaturan; menyebut masa berlaku OTP.
 */
class PasswordOtpMail extends Mailable
{
    public function __construct(
        public string $otp,
        public int $minutes,
        public string $siteTitle,
        public ?string $logoUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Kode OTP reset password {$this->siteTitle}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.password-otp', text: 'mail.password-otp-text');
    }
}
