<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email copy of a workflow stage message, for stages with the Email channel.
 * Uses the header title and logo set in Email Templates.
 */
class WorkflowStageMail extends Mailable
{
    public function __construct(
        public string $heading,
        public string $text,
        public string $name,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->heading);
    }

    public function content(): Content
    {
        $brand = EmailTemplate::for(EmailTemplate::RESET_PASSWORD);

        return new Content(
            view: 'emails.workflow-notification',
            with: [
                'headerTitle' => $brand->headerTitle(),
                'logoPath' => $brand->logoFullPath(),
                'loginUrl' => url('/login'),
            ],
        );
    }
}
