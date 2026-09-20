<?php

namespace Database\Seeders;

use App\Enums\WorkflowStatus;
use App\Models\Team;
use App\Models\WebhookEndpoint;
use App\Models\Workflow;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds three ready-to-run demo workflows for the « Équipe Démo » team,
 * with complete graphs (nodes at spaced canvas positions, edges on the
 * declared output handles) and `fake/demo` AI nodes — no real API key
 * involved, everything runs offline through the fake provider.
 *
 * Idempotent: workflows are firstOrCreate on team+name and a graph is only
 * populated when the workflow has no node yet, so a replay never duplicates
 * nodes, edges or the endpoint.
 */
class DemoWorkflowSeeder extends Seeder
{
    /**
     * The fixed 48-character webhook token of the demo endpoint (dev/test
     * only — never a real secret). Deterministic so the webhook URL survives
     * re-seeding in development.
     */
    public const string WebhookToken = 'faucon-demo-webhook-token-0000000000000000000000';

    /** The client-request triage workflow (active, classification branch). */
    public const string TriageWorkflow = 'Tri des demandes clients';

    /** The article-summary workflow (draft, minimal linear graph). */
    public const string SummaryWorkflow = 'Résumé d\'article';

    /** The webhook-to-extraction workflow (active, fixed-token endpoint). */
    public const string WebhookExtractionWorkflow = 'Webhook → extraction';

    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $team = Team::query()
            ->where('slug', DemoUserSeeder::TeamSlug)
            ->firstOrFail();

        $this->seedTriageWorkflow($team);
        $this->seedSummaryWorkflow($team);
        $this->seedWebhookExtractionWorkflow($team);
    }

    /**
     * « Tri des demandes clients » (actif) : manuel → classification
     * (fake/demo) → condition sur le label ; la branche true rédige une
     * réponse commerciale, chaque branche finit sur sa propre sortie.
     * Reprend les formes éprouvées par le test e2e de classification.
     */
    private function seedTriageWorkflow(Team $team): void
    {
        $workflow = $this->seedWorkflow(
            $team,
            self::TriageWorkflow,
            WorkflowStatus::Active,
            'Triage automatique des demandes clients par classification IA.',
        );

        $this->seedGraph($workflow, nodes: [
            [
                'key' => 'manuel',
                'type' => 'trigger.manual',
                'name' => 'Manuel',
                'config' => [],
                'x' => 100,
                'y' => 240,
            ],
            [
                'key' => 'classification',
                'type' => 'ai.classification',
                'name' => 'Classification',
                'config' => [
                    'model' => 'fake/demo',
                    'prompt' => 'Classe la demande client : {{ trigger.message }}'
                        .' Étiquettes attendues : lead, spam ou question.',
                    'labels' => 'lead, spam, question',
                    'temperature' => 0.2,
                    'max_tokens' => 512,
                ],
                'x' => 420,
                'y' => 240,
            ],
            [
                'key' => 'triage',
                'type' => 'logic.condition',
                'name' => 'Triage',
                'config' => [
                    'expression' => '{{ classification.label }}',
                    'operator' => '==',
                    'value' => 'lead',
                ],
                'x' => 740,
                'y' => 240,
            ],
            [
                'key' => 'reponse',
                'type' => 'ai.generation',
                'name' => 'Réponse commerciale',
                'config' => [
                    'model' => 'fake/demo',
                    'prompt' => 'Rédige une réponse commerciale adaptée à ce lead : {{ trigger.message }}',
                    'temperature' => 0.6,
                    'max_tokens' => 1024,
                ],
                'x' => 1060,
                'y' => 120,
            ],
            [
                'key' => 'sortie-lead',
                'type' => 'data.output',
                'name' => 'Sortie lead',
                'config' => [],
                'x' => 1380,
                'y' => 120,
            ],
            [
                'key' => 'sortie-autre',
                'type' => 'data.output',
                'name' => 'Sortie autre',
                'config' => [],
                'x' => 1060,
                'y' => 360,
            ],
        ], edges: [
            ['source' => 'manuel', 'target' => 'classification', 'handle' => 'out'],
            ['source' => 'classification', 'target' => 'triage', 'handle' => 'out'],
            ['source' => 'triage', 'target' => 'reponse', 'handle' => 'true'],
            ['source' => 'reponse', 'target' => 'sortie-lead', 'handle' => 'out'],
            ['source' => 'triage', 'target' => 'sortie-autre', 'handle' => 'false'],
        ]);
    }

    /**
     * « Résumé d'article » (brouillon) : manuel → résumé (fake/demo) →
     * sortie, graphe linéaire minimal.
     */
    private function seedSummaryWorkflow(Team $team): void
    {
        $workflow = $this->seedWorkflow(
            $team,
            self::SummaryWorkflow,
            WorkflowStatus::Draft,
            'Résume un article en trois points via l\'IA fake.',
        );

        $this->seedGraph($workflow, nodes: [
            [
                'key' => 'manuel',
                'type' => 'trigger.manual',
                'name' => 'Manuel',
                'config' => [],
                'x' => 100,
                'y' => 200,
            ],
            [
                'key' => 'resume',
                'type' => 'ai.summarization',
                'name' => 'Résumé',
                'config' => [
                    'model' => 'fake/demo',
                    'prompt' => 'Résume cet article en trois points : {{ trigger.article }}',
                    'temperature' => 0.3,
                    'max_tokens' => 512,
                ],
                'x' => 420,
                'y' => 200,
            ],
            [
                'key' => 'sortie',
                'type' => 'data.output',
                'name' => 'Sortie',
                'config' => [],
                'x' => 740,
                'y' => 200,
            ],
        ], edges: [
            ['source' => 'manuel', 'target' => 'resume', 'handle' => 'out'],
            ['source' => 'resume', 'target' => 'sortie', 'handle' => 'out'],
        ]);
    }

    /**
     * « Webhook → extraction » (actif) : webhook → extraction (fake/demo,
     * champs nom/montant) → condition contains sur le nom extrait → sortie,
     * avec l'endpoint webhook à token fixe attaché.
     */
    private function seedWebhookExtractionWorkflow(Team $team): void
    {
        $workflow = $this->seedWorkflow(
            $team,
            self::WebhookExtractionWorkflow,
            WorkflowStatus::Active,
            'Extrait le nom et le montant d\'un payload webhook entrant.',
        );

        $this->seedGraph($workflow, nodes: [
            [
                'key' => 'entree',
                'type' => 'trigger.webhook',
                'name' => 'Entrée webhook',
                'config' => [],
                'x' => 100,
                'y' => 200,
            ],
            [
                'key' => 'extraction',
                'type' => 'ai.extraction',
                'name' => 'Extraction',
                'config' => [
                    'model' => 'fake/demo',
                    'prompt' => 'Extrais le nom et le montant du contenu : {{ trigger.contenu }}',
                    'fields' => "nom: text\nmontant: number",
                    'temperature' => 0.1,
                    'max_tokens' => 512,
                ],
                'x' => 420,
                'y' => 200,
            ],
            [
                'key' => 'filtre',
                'type' => 'logic.condition',
                'name' => 'Filtre',
                'config' => [
                    'expression' => '{{ extraction.structured.nom }}',
                    'operator' => 'contains',
                    'value' => 'exemple',
                ],
                'x' => 740,
                'y' => 200,
            ],
            [
                'key' => 'sortie',
                'type' => 'data.output',
                'name' => 'Sortie',
                'config' => [],
                'x' => 1060,
                'y' => 200,
            ],
        ], edges: [
            ['source' => 'entree', 'target' => 'extraction', 'handle' => 'out'],
            ['source' => 'extraction', 'target' => 'filtre', 'handle' => 'out'],
            ['source' => 'filtre', 'target' => 'sortie', 'handle' => 'out'],
        ]);

        $this->seedWebhookEndpoint($workflow);
    }

    /**
     * firstOrCreate the workflow on team+name (via the has-many relation so
     * the non-fillable `team_id` is set by the relation itself).
     */
    private function seedWorkflow(Team $team, string $name, WorkflowStatus $status, string $description): Workflow
    {
        return $team->workflows()->firstOrCreate(
            ['name' => $name],
            ['description' => $description, 'status' => $status],
        );
    }

    /**
     * Attach the fixed-token webhook endpoint to the workflow, at most one.
     */
    private function seedWebhookEndpoint(Workflow $workflow): void
    {
        if ($workflow->webhookEndpoint()->exists()) {
            return;
        }

        $workflow->webhookEndpoint()->create([
            'token' => self::WebhookToken,
            'token_hash' => WebhookEndpoint::hashToken(self::WebhookToken),
        ]);
    }

    /**
     * Create the nodes (spaced canvas positions) and the edges (declared
     * output handles) of a graph, only when the workflow has no node yet —
     * the idempotence guard of the whole seeder.
     *
     * @param  array<int, array{key: string, type: string, name: string, config: array<string, mixed>, x: int, y: int}>  $nodes
     * @param  array<int, array{source: string, target: string, handle: string}>  $edges
     */
    private function seedGraph(Workflow $workflow, array $nodes, array $edges): void
    {
        if ($workflow->nodes()->exists()) {
            return;
        }

        $nodesByKey = [];

        foreach ($nodes as $node) {
            $nodesByKey[$node['key']] = $workflow->nodes()->create([
                'key' => $node['key'],
                'type' => $node['type'],
                'name' => $node['name'],
                'config' => $node['config'] === [] ? null : $node['config'],
                'position_x' => $node['x'],
                'position_y' => $node['y'],
            ]);
        }

        foreach ($edges as $edge) {
            $workflow->edges()->create([
                'source_node_key' => $nodesByKey[$edge['source']]->key,
                'target_node_key' => $nodesByKey[$edge['target']]->key,
                'source_handle' => $edge['handle'],
            ]);
        }
    }
}
