<?php

namespace App\Support;

use App\Enums\IntegrationType;
use App\Models\Integration;
use App\Models\Team;
use App\Models\WorkflowNode;

/**
 * Maps integrations to the non-secret summary shape shared by the
 * settings page and the builder Edit props — never carries credentials
 * (master §12). The `meta` line is derived server-side from the
 * decrypted credentials (baseUrl / host:port) and is non-secret by
 * nature; auth fields (token, username, password, headerValue) never
 * take part in it.
 */
final class IntegrationSummaries
{
    /**
     * Node types that may reference an integration in their config.
     *
     * @var list<string>
     */
    private const array UsageNodeTypes = ['action.http'];

    /**
     * List the team integrations, ordered by name.
     *
     * @return array<int, array{id: int, name: string, type: string, lastTestedAt: string|null, lastTestSucceeded: bool|null, usedByWorkflows: int, meta: string|null}>
     */
    public static function forTeam(Team $team): array
    {
        $usageCounts = self::usageCounts($team);

        return $team->integrations()
            ->orderBy('name')
            ->get()
            ->map(fn (Integration $integration) => self::forIntegration($integration, $usageCounts))
            ->all();
    }

    /**
     * Map one integration to its summary prop shape.
     *
     * @param  array<int, int>  $usageCounts
     * @return array{id: int, name: string, type: string, lastTestedAt: string|null, lastTestSucceeded: bool|null, usedByWorkflows: int, meta: string|null}
     */
    public static function forIntegration(Integration $integration, array $usageCounts): array
    {
        return [
            'id' => $integration->id,
            'name' => $integration->name,
            'type' => $integration->type->value,
            'lastTestedAt' => $integration->last_tested_at?->toISOString(),
            'lastTestSucceeded' => $integration->last_test_succeeded,
            'usedByWorkflows' => $usageCounts[$integration->id] ?? 0,
            'meta' => self::metaFor($integration),
        ];
    }

    /**
     * One non-secret metadata line for the card: baseUrl (generic_http)
     * or host:port (smtp), truncated to 120 characters.
     */
    public static function metaFor(Integration $integration): ?string
    {
        $credentials = $integration->credentials;

        $meta = match ($integration->type) {
            IntegrationType::GenericHttp => $credentials['baseUrl'] ?? null,
            IntegrationType::Smtp => self::smtpMeta($credentials),
        };

        if (! is_string($meta) || $meta === '') {
            return null;
        }

        return mb_substr($meta, 0, 120);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private static function smtpMeta(array $credentials): ?string
    {
        $host = $credentials['host'] ?? null;

        if (! is_string($host) || $host === '') {
            return null;
        }

        $port = $credentials['port'] ?? null;

        return is_numeric($port) ? $host.':'.$port : $host;
    }

    /**
     * Count the distinct workflows referencing each integration. The
     * grouping happens in PHP on the JSON config (portable MariaDB/sqlite
     * — no SQL dialect JSON functions); PHP normalizes numeric array keys
     * to int, so the counts are keyed by integration id.
     *
     * @return array<int, int>
     */
    public static function usageCounts(Team $team): array
    {
        $nodes = WorkflowNode::query()
            ->whereIn('workflow_id', $team->workflows()->select('id'))
            ->whereIn('type', self::UsageNodeTypes)
            ->get(['workflow_id', 'config']);

        /** @var array<int, array<int, true>> $pairs */
        $pairs = [];

        foreach ($nodes as $node) {
            $integrationId = $node->config['integration_id'] ?? null;

            if (is_string($integrationId) && ctype_digit($integrationId)) {
                $pairs[(int) $integrationId][$node->workflow_id] = true;
            }
        }

        return array_map(count(...), $pairs);
    }
}
