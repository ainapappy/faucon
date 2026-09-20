<?php

namespace App\Services\Workflow\Handlers\Action;

use App\Data\Workflow\NodeContext;
use App\Data\Workflow\NodeResult;
use App\Enums\IntegrationType;
use App\Models\Integration;
use App\Services\Integration\EmailSender;
use App\Services\Integration\IntegrationResolver;
use App\Services\Workflow\Exception\NodeExecutionException;
use App\Services\Workflow\NodeHandler;
use Throwable;

/**
 * Handler of the `action.email` node type: sends one plain-text email
 * through the EmailSender boundary, with strictly interpolated fields.
 */
final class EmailHandler implements NodeHandler
{
    public function __construct(
        private readonly EmailSender $sender,
        private readonly IntegrationResolver $integrations,
    ) {}

    public function type(): string
    {
        return 'action.email';
    }

    /**
     * Tolerant on purpose (phase 3 model): empty or placeholder fields are
     * accepted at save time and fail at execution when they matter.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public function validate(array $config): array
    {
        return [];
    }

    /**
     * Execute the node: interpolate, validate the recipient, send.
     *
     * @throws NodeExecutionException
     */
    public function execute(NodeContext $context): NodeResult
    {
        $to = $context->interpolate((string) ($context->config['to'] ?? ''));

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new NodeExecutionException(
                reason: 'invalid_recipient',
                userMessage: __('L’adresse destinataire interpolée n’est pas une adresse e-mail valide.'),
            );
        }

        $subject = $context->interpolate((string) ($context->config['subject'] ?? ''));
        $body = $context->interpolate((string) ($context->config['body'] ?? ''));
        $integration = $this->resolveIntegration($context->config['integration_id'] ?? null);

        try {
            $this->sender->send($to, $subject, $body, $integration);
        } catch (Throwable $exception) {
            throw new NodeExecutionException(
                reason: 'email_send_failed',
                userMessage: __('L’envoi de l’e-mail a échoué.'),
                technicalDetail: $exception->getMessage(),
            );
        }

        return new NodeResult(['sent' => true, 'to' => $to]);
    }

    /**
     * Resolve the referenced integration and enforce the smtp type.
     *
     * @throws NodeExecutionException integration_not_found|invalid_integration_type
     */
    private function resolveIntegration(mixed $configValue): ?Integration
    {
        $id = is_string($configValue) && $configValue !== '' ? $configValue : null;

        if ($id === null) {
            return null;
        }

        $integration = $this->integrations->find($id);

        if ($integration === null) {
            throw new NodeExecutionException(
                reason: 'integration_not_found',
                userMessage: __('L’intégration référencée par ce node n’existe plus.'),
            );
        }

        if ($integration->type !== IntegrationType::Smtp) {
            throw new NodeExecutionException(
                reason: 'invalid_integration_type',
                userMessage: __('Le type d’intégration référencé ne convient pas pour ce node.'),
            );
        }

        return $integration;
    }
}
