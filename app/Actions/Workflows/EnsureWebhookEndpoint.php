<?php

namespace App\Actions\Workflows;

use App\Models\WebhookEndpoint;
use App\Models\Workflow;
use Illuminate\Support\Str;

/**
 * Keep the webhook endpoint of a workflow in sync with its graph:
 * create it when the graph contains a `trigger.webhook` node, delete it
 * otherwise (D20). Idempotent, safe to call on every save.
 */
final class EnsureWebhookEndpoint
{
    /**
     * Ensure the endpoint matches the graph and return it (null when the
     * graph has no webhook node).
     */
    public function handle(Workflow $workflow): ?WebhookEndpoint
    {
        $hasWebhook = $workflow->nodes()
            ->where('type', 'trigger.webhook')
            ->exists();

        if ($hasWebhook) {
            $endpoint = $workflow->webhookEndpoint()->firstOrNew();

            if (! $endpoint->exists) {
                $token = Str::random(48);

                $endpoint->fill([
                    'token' => $token,
                    'token_hash' => WebhookEndpoint::hashToken($token),
                ])->save();
            }

            return $endpoint;
        }

        $workflow->webhookEndpoint()->delete();

        return null;
    }
}
