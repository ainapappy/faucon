<?php

use App\Data\Workflow\NodeContext;
use App\Mail\WorkflowActionEmail;
use App\Models\Integration;
use App\Services\Integration\EmailSender;
use App\Services\Integration\IntegrationResolver;
use App\Services\Integration\SmtpTransportFactory;
use App\Services\Workflow\Exception\NodeExecutionException;
use App\Services\Workflow\ExecutionContext;
use App\Services\Workflow\Handlers\Action\EmailHandler;
use Illuminate\Support\Facades\Mail;

/**
 * Build a node context for the action.email handler.
 *
 * @param  array<string, mixed>  $config
 * @param  array<string, mixed>  $input
 */
function emailNodeContext(array $config = [], array $input = []): NodeContext
{
    return new NodeContext(
        nodeKey: 'n9',
        nodeType: 'action.email',
        nodeName: 'Email',
        config: $config,
        input: $input,
        execution: new ExecutionContext,
    );
}

/**
 * A resolver stubbed in memory (no database involved).
 */
function emailResolverReturning(?Integration $integration): IntegrationResolver
{
    return new IntegrationResolver(fn (string $id): ?Integration => $integration);
}

/**
 * The real handler wired to the real EmailSender: the default path goes
 * through the application mailer, intercepted by Mail::fake().
 */
function emailHandler(?IntegrationResolver $resolver = null): EmailHandler
{
    return new EmailHandler(
        new EmailSender(new SmtpTransportFactory),
        $resolver ?? emailResolverReturning(null),
    );
}

test('email sends through the application mailer with interpolated fields', function () {
    Mail::fake();

    $context = emailNodeContext([
        'to' => '{{ trigger.email }}',
        'subject' => 'Bienvenue {{ trigger.name }}',
        'body' => 'Bonjour {{ trigger.name }}, votre compte est prêt.',
    ]);
    $context->execution->setVariable('trigger', [
        'email' => 'client@example.com',
        'name' => 'Camille',
    ]);

    $result = emailHandler()->execute($context);

    expect($result->output)->toBe(['sent' => true, 'to' => 'client@example.com']);

    Mail::assertSent(WorkflowActionEmail::class, function (WorkflowActionEmail $mail): bool {
        return $mail->hasTo('client@example.com')
            && $mail->hasSubject('Bienvenue Camille')
            && $mail->body === 'Bonjour Camille, votre compte est prêt.';
    });
});

test('email validates the configuration tolerantly', function () {
    expect(emailHandler()->validate([]))->toBe([])
        ->and(emailHandler()->validate(['to' => '', 'subject' => '', 'body' => '']))->toBe([])
        ->and(emailHandler()->validate(['to' => '{{ trigger.email }}']))->toBe([]);
});

test('a missing placeholder path in the recipient fails the node', function () {
    Mail::fake();

    $context = emailNodeContext(['to' => '{{ trigger.missing }}']);
    $context->execution->setVariable('trigger', []);

    $execute = fn (): mixed => emailHandler()->execute($context);

    expect($execute)->toThrow(fn (NodeExecutionException $exception) => $exception->reason === 'path_not_found');
});

test('an interpolated non-email recipient fails the node without echoing the address', function () {
    Mail::fake();

    $context = emailNodeContext([
        'to' => '{{ trigger.contact }}',
        'subject' => 'Sujet',
        'body' => 'Corps',
    ]);
    $context->execution->setVariable('trigger', ['contact' => 'pas-un-email']);

    $execute = fn (): mixed => emailHandler()->execute($context);

    expect($execute)->toThrow(function (NodeExecutionException $exception): bool {
        return $exception->reason === 'invalid_recipient'
            && $exception->getMessage() === 'L’adresse destinataire interpolée n’est pas une adresse e-mail valide.'
            && ! str_contains($exception->getMessage(), 'pas-un-email');
    });

    Mail::assertNothingSent();
});

test('a throwing sender fails the node with a populated technical detail', function () {
    Mail::fake();

    $smtp = (new Integration)->forceFill([
        'type' => 'smtp',
        'credentials' => ['host' => 'smtp.exemple.com', 'port' => 587],
    ]);

    $sender = new EmailSender(
        new SmtpTransportFactory,
        smtpSender: function (string $to, string $subject, string $body, array $credentials): void {
            throw new RuntimeException('relay.example.net: connection refused (secret-relay-name)');
        },
    );

    $handler = new EmailHandler($sender, emailResolverReturning($smtp));

    $context = emailNodeContext([
        'to' => 'client@example.com',
        'subject' => 'Sujet',
        'body' => 'Corps',
        'integration_id' => '42',
    ]);

    $execute = fn (): mixed => $handler->execute($context);

    expect($execute)->toThrow(function (NodeExecutionException $exception): bool {
        return $exception->reason === 'email_send_failed'
            && $exception->getMessage() === 'L’envoi de l’e-mail a échoué.'
            && $exception->technicalDetail() !== ''
            && str_contains($exception->technicalDetail(), 'connection refused');
    });
});

test('an smtp integration is passed to the sender with its credentials and never echoed', function () {
    Mail::fake();

    $smtp = (new Integration)->forceFill([
        'type' => 'smtp',
        'credentials' => ['host' => 'smtp.exemple.com', 'port' => 587, 'encryption' => 'tls', 'password' => 'secret-smtp-pass'],
    ]);

    /** @var list<array<string, mixed>> $captured */
    $captured = [];

    $sender = new EmailSender(
        new SmtpTransportFactory,
        smtpSender: function (string $to, string $subject, string $body, array $credentials) use (&$captured): void {
            $captured[] = ['to' => $to, 'subject' => $subject, 'body' => $body, 'credentials' => $credentials];
        },
    );

    $handler = new EmailHandler($sender, emailResolverReturning($smtp));

    $context = emailNodeContext([
        'to' => 'client@example.com',
        'subject' => 'Sujet',
        'body' => 'Corps',
        'integration_id' => '42',
    ]);

    $result = $handler->execute($context);

    expect($captured)->toHaveCount(1)
        ->and($captured[0]['credentials']['host'])->toBe('smtp.exemple.com')
        ->and($result->output)->toBe(['sent' => true, 'to' => 'client@example.com'])
        ->and(json_encode($result->output))->not->toContain('secret-smtp-pass');
});

test('a generic_http integration on the email node fails with invalid_integration_type', function () {
    Mail::fake();

    $generic = (new Integration)->forceFill([
        'type' => 'generic_http',
        'credentials' => ['baseUrl' => 'https://api.exemple.com', 'auth' => 'none'],
    ]);

    $handler = new EmailHandler(new EmailSender(new SmtpTransportFactory), emailResolverReturning($generic));

    $context = emailNodeContext([
        'to' => 'client@example.com',
        'integration_id' => '42',
    ]);

    $execute = fn (): mixed => $handler->execute($context);

    expect($execute)->toThrow(fn (NodeExecutionException $exception) => $exception->reason === 'invalid_integration_type');
});

test('an integration id that no longer resolves fails with integration_not_found', function () {
    Mail::fake();

    $handler = new EmailHandler(new EmailSender(new SmtpTransportFactory), emailResolverReturning(null));

    $context = emailNodeContext([
        'to' => 'client@example.com',
        'integration_id' => '42',
    ]);

    $execute = fn (): mixed => $handler->execute($context);

    expect($execute)->toThrow(fn (NodeExecutionException $exception) => $exception->reason === 'integration_not_found');
});
