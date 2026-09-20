<?php

namespace App\Services\Integration;

use LogicException;
use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

/**
 * Builds Symfony Mailer transports/mailer from the `smtp` integration
 * credentials. dsnFor is pure (unit-testable without any connection).
 *
 * Why a raw Symfony Mailer instead of the `Mail` facade: Laravel already
 * ships `Illuminate\Mail\Mailer` instances through the `Mail` facade /
 * `MailManager`, built from the named mailers of `config/mail.php`, with
 * symfony/mailer as the underlying engine (no extra dependency involved
 * here — it is the engine Laravel itself depends on). But workflow SMTP
 * credentials are DYNAMIC: they come from a per-team `Integration` row,
 * encrypted in the database, not from static `config/mail.php` entries.
 * Building the transport per call keeps the factory stateless and avoids
 * mutating the shared mail manager state in the middle of a run.
 *
 * Known consequence: `Mail::fake()` cannot intercept a hand-built Symfony
 * transport — hence the `EmailSender` boundary used by the handlers (tests
 * stub the smtp path through the injected sender closure).
 */
final class SmtpTransportFactory
{
    /**
     * Build the DSN string for the given credentials.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function dsnFor(array $credentials): string
    {
        $dsn = 'smtp://';

        $username = $credentials['username'] ?? null;
        $password = $credentials['password'] ?? null;

        if (is_string($username) && $username !== '') {
            $dsn .= rawurlencode($username);

            if (is_string($password) && $password !== '') {
                $dsn .= ':'.rawurlencode($password);
            }

            $dsn .= '@';
        }

        $dsn .= (string) ($credentials['host'] ?? '');

        $port = $credentials['port'] ?? null;

        if (is_numeric($port)) {
            $dsn .= ':'.(string) $port;
        }

        $encryption = $credentials['encryption'] ?? 'tls';

        if ($encryption === 'tls' || $encryption === 'ssl') {
            $dsn .= '?encryption='.$encryption;
        }

        return $dsn;
    }

    /**
     * Build a Mailer from the given credentials (symfony/mailer v8).
     *
     * @param  array<string, mixed>  $credentials
     */
    public function mailerFor(array $credentials): MailerInterface
    {
        return new Mailer(Transport::fromDsn($this->dsnFor($credentials)));
    }

    /**
     * Open a real SMTP connection to verify host/port/encryption/credentials.
     *
     * @param  array<string, mixed>  $credentials
     *
     * @throws RuntimeException when the connection/authentication fails
     */
    public function probeFor(array $credentials): void
    {
        $transport = Transport::fromDsn($this->dsnFor($credentials));

        if (! $transport instanceof SmtpTransport) {
            throw new LogicException('The smtp DSN did not produce an SmtpTransport.');
        }

        try {
            $transport->start();
        } catch (Throwable $exception) {
            throw new RuntimeException('SMTP connection failed.', previous: $exception);
        } finally {
            $transport->stop();
        }
    }
}
