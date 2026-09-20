<?php

namespace App\Http\Controllers\Workflows;

use App\Actions\Workflows\StartWorkflowRun;
use App\Enums\ExecutionTrigger;
use App\Enums\WorkflowStatus;
use App\Http\Controllers\Controller;
use App\Models\WebhookEndpoint;
use App\Models\WebhookRequest;
use App\Models\Workflow;
use App\Models\WorkflowNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The public webhook endpoint (POST /webhooks/{token}, D19).
 *
 * The flow is: hash lookup, eligibility, payload bounds, idempotence, then
 * a QUEUED run through StartWorkflowRun — the endpoint answers 202 with the
 * execution id and never blocks on the engine (phase 7). Every
 * ineligibility answers the same 404 (no state leak); no log ever
 * carries the token or the url; the idempotence journal guarantees a
 * single dispatch per X-Request-Id.
 */
final class WebhookController extends Controller
{
    private const NotFoundBody = ['message' => 'Not found.'];

    /**
     * Decoded payload, memoized between the bounds check and the dispatch.
     *
     * @var array<string, mixed>|null
     */
    private ?array $payload = null;

    public function __construct(
        private readonly StartWorkflowRun $startWorkflowRun,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $token = (string) $request->route('token');

        $endpoint = WebhookEndpoint::query()
            ->where('token_hash', WebhookEndpoint::hashToken($token))
            ->first();

        if ($endpoint === null) {
            return $this->notFound();
        }

        $workflow = $endpoint->workflow()->with(['nodes', 'edges'])->first();

        if ($workflow === null
            || $workflow->status !== WorkflowStatus::Active
            || ! $this->hasWebhookNode($workflow)) {
            return $this->notFound();
        }

        $payloadError = $this->payloadError($request);

        if ($payloadError !== null) {
            return $payloadError;
        }

        $requestId = trim((string) $request->header('X-Request-Id', ''));

        if (mb_strlen($requestId) > 255) {
            return response()->json([
                'message' => __('L’identifiant de requête ne doit pas dépasser 255 caractères.'),
            ], 422);
        }

        if ($requestId !== '') {
            $inserted = WebhookRequest::query()->insertOrIgnore([
                'token_hash' => $endpoint->token_hash,
                'request_id_hash' => hash('sha256', $requestId),
                'received_at' => now(),
            ]);

            if ($inserted === 0) {
                return response()->json(['status' => 'duplicate']);
            }
        }

        $execution = $this->startWorkflowRun->handle(
            $workflow,
            ExecutionTrigger::Webhook,
            $this->payload ?? [],
            null,
        );

        return response()->json([
            'status' => 'pending',
            'execution_id' => $execution->id,
        ], 202);
    }

    /**
     * The identical 404 for every ineligibility (unknown token, draft,
     * soft-deleted workflow, missing webhook node).
     */
    private function notFound(): JsonResponse
    {
        return response()->json(self::NotFoundBody, 404);
    }

    /**
     * Enforce the payload bounds (D6) and memoize the decoded object.
     */
    private function payloadError(Request $request): ?JsonResponse
    {
        $content = (string) $request->getContent();
        $maxBytes = (int) config('workflows.webhook.max_payload_bytes', 65536);

        $contentLength = (int) $request->header('Content-Length', '0');

        if ($contentLength > $maxBytes || strlen($content) > $maxBytes) {
            return response()->json([
                'message' => __('La charge utile est trop volumineuse.'),
            ], 413);
        }

        if (trim($content) === '') {
            $this->payload = [];

            return null;
        }

        $depth = max(1, (int) config('workflows.webhook.max_json_depth', 10));

        $decoded = json_decode($content, true, $depth);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $message = json_last_error() === JSON_ERROR_DEPTH
                ? __('Le JSON est trop profond (:n niveaux max).', ['n' => $depth])
                : __('Le payload doit être un JSON valide.');

            return response()->json(['message' => $message], 422);
        }

        if (! is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            return response()->json([
                'message' => __('Le payload doit être un objet JSON.'),
            ], 422);
        }

        $this->payload = $decoded;

        return null;
    }

    /**
     * Whether the loaded graph still contains a webhook trigger node.
     */
    private function hasWebhookNode(Workflow $workflow): bool
    {
        return $workflow->nodes->contains(
            fn (WorkflowNode $node): bool => $node->type === 'trigger.webhook',
        );
    }
}
