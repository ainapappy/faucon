<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Plain-text transactional email sent by the `action.email` node.
 *
 * The subject and body are interpolated workflow values; the recipient
 * and the sender are set by the caller (EmailSender / mail config).
 */
final class WorkflowActionEmail extends Mailable
{
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $body,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    /**
     * Get the message content definition (plain text only).
     */
    public function content(): Content
    {
        return new Content(
            text: 'mail.workflow-action',
        );
    }
}
