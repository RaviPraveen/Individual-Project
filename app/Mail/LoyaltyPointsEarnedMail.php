<?php

namespace App\Mail;

use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoyaltyPointsEarnedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Sale $sale) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your receipt and star points from '.config('app.name').' — '.$this->sale->invoice_no,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.loyalty-points-earned',
        );
    }
}
