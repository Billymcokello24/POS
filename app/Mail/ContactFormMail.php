<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactFormMail extends Mailable
{
    use Queueable, SerializesModels;

    public $senderEmail;
    public $subject;
    public $messageContent;

    /**
     * Create a new message instance.
     */
    public function __construct($senderEmail, $subject, $messageContent)
    {
        $this->senderEmail = $senderEmail;
        $this->subject = $subject;
        $this->messageContent = $messageContent;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Contact Form: ' . $this->subject)
                    ->replyTo($this->senderEmail)
                    ->view('emails.contact-form');
    }
}
