<?php

namespace App\Services\Integration;

use App\Mail\WorkflowActionEmail;
use App\Models\Integration;
use Closure;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;

/**
 * Single boundary for sending the workflow action email (D12).
 *
 * The default path (no SMTP integration) goes through the Laravel
 * application mailer — the `Mail` facade — which `Mail::fake()` can
 * intercept in tests. The boundary exists because `Mail::fake()` cannot
 * intercept a hand-built Symfony transport (symfony/mailer engine built
 * from per-team credentials): the SMTP path is therefore exercised in
 * tests through the injected sender closure instead.
 *
 * @param  Closure(string, string, string, array<string, mixed>): void|null  $smtpSender  Tests only — intercepts the SMTP path.
 */
final class EmailSender
{
    public function __construct(
        private readonly SmtpTransportFactory $smtp,
        private readonly ?Closure $smtpSender = null,
    ) {}

    /**
     * Send the workflow email through the application mailer or the
     * referenced SMTP integration.
     *
     * @throws \Throwable transport failures — the handler maps them to `email_send_failed`.
     */
    public function send(string $to, string $subject, string $body, ?Integration $smtpIntegration): void
    {
        if ($smtpIntegration === null) {
            Mail::to($to)->send(new WorkflowActionEmail($subject, $body));

            return;
        }

        $credentials = $smtpIntegration->credentials;

        if ($this->smtpSender !== null) {
            ($this->smtpSender)($to, $subject, $body, $credentials);

            return;
        }

        $this->sendThroughSmtp($to, $subject, $body, $credentials);
    }

    /**
     * Send through the per-team SMTP server (Symfony Mailer built from
     * the credentials — never through the shared application mailer).
     *
     * @param  array<string, mixed>  $credentials
     */
    private function sendThroughSmtp(string $to, string $subject, string $body, array $credentials): void
    {
        $from = $credentials['from'] ?? null;

        if (! is_string($from) || $from === '') {
            $from = (string) config('mail.from.address');
        }

        $mailer = $this->smtp->mailerFor($credentials);

        $mailer->send(
            (new Email)
                ->from($from, (string) config('mail.from.name'))
                ->to($to)
                ->subject($subject)
                ->text($body),
        );
    }
}
