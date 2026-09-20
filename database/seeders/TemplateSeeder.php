<?php

namespace Database\Seeders;

use App\Enums\TemplateOrigin;
use App\Models\WorkflowTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds the three ready-to-use SYSTEM templates of phase 9 (gallery
 * « Système » section), written directly in the snapshot shape (builder
 * payload, camelCase): spaced canvas positions (x: 100/420/740/1060),
 * edges on the declared output handles (`out`, `true`/`false`), AI nodes
 * on `fake/demo` (always enabled) and interpolations on the REAL output
 * keys of the handlers (`classification.label`, `resume.text`,
 * `http.body`). No `integration_id`: a system template is by definition
 * outside any team context.
 *
 * Idempotent: firstOrCreate on origin+name, graph written at creation
 * only — a replay never duplicates or overwrites. Every template passes
 * WorkflowValidator over fromSnapshot() — proven by tests.
 */
class TemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    /** T1 — webhook urgent triage (Support). */
    public const string SupportTemplate = 'Support client prioritaire';

    /** T2 — AI lead routing (Ventes). */
    public const string LeadsTemplate = 'Traitement de leads (IA)';

    /** T3 — daily morning briefing (Données). */
    public const string WatchTemplate = 'Veille du matin';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedSupportTemplate();
        $this->seedLeadsTemplate();
        $this->seedWatchTemplate();
    }

    /**
     * T1 « Support client prioritaire » : webhook > condition
     * ({{ trigger.type }} == urgent) — true > email immédiat à l'équipe.
     */
    private function seedSupportTemplate(): void
    {
        $this->seedSystemTemplate(
            name: self::SupportTemplate,
            category: 'Support',
            description: 'Un webhook reçoit un message client ; s’il est marqué urgent, un e-mail part immédiatement à l’équipe.',
            graph: [
                'nodes' => [
                    ['key' => 'webhook', 'type' => 'trigger.webhook', 'name' => 'Webhook', 'config' => [], 'positionX' => 100, 'positionY' => 200],
                    ['key' => 'condition', 'type' => 'logic.condition', 'name' => 'Condition', 'config' => ['expression' => '{{ trigger.type }}', 'operator' => '==', 'value' => 'urgent'], 'positionX' => 420, 'positionY' => 200],
                    ['key' => 'email', 'type' => 'action.email', 'name' => 'Email', 'config' => ['to' => '{{ trigger.email }}', 'subject' => 'Demande urgente', 'body' => 'Message : {{ trigger.message }}'], 'positionX' => 740, 'positionY' => 200],
                ],
                'edges' => [
                    ['sourceNodeKey' => 'webhook', 'targetNodeKey' => 'condition', 'sourceHandle' => 'out'],
                    ['sourceNodeKey' => 'condition', 'targetNodeKey' => 'email', 'sourceHandle' => 'true'],
                ],
            ],
        );
    }

    /**
     * T2 « Traitement de leads (IA) » : manuel > data.input > classification
     * IA > condition sur le label — true > email de bienvenue,
     * false > sortie (archivage).
     */
    private function seedLeadsTemplate(): void
    {
        $this->seedSystemTemplate(
            name: self::LeadsTemplate,
            category: 'Ventes',
            description: 'Capture un lead, l’IA le classe, puis la branche adaptée : e-mail de bienvenue ou archivage.',
            graph: [
                'nodes' => [
                    ['key' => 'manuel', 'type' => 'trigger.manual', 'name' => 'Manuel', 'config' => [], 'positionX' => 100, 'positionY' => 200],
                    ['key' => 'formulaire', 'type' => 'data.input', 'name' => 'Lead', 'config' => ['name' => 'lead'], 'positionX' => 420, 'positionY' => 200],
                    ['key' => 'classification', 'type' => 'ai.classification', 'name' => 'Classification', 'config' => ['model' => 'fake/demo', 'prompt' => 'Classe ce lead : {{ formulaire.payload }}', 'labels' => 'lead, spam, question', 'temperature' => 0.2, 'max_tokens' => 512], 'positionX' => 740, 'positionY' => 200],
                    ['key' => 'route', 'type' => 'logic.condition', 'name' => 'Route', 'config' => ['expression' => '{{ classification.label }}', 'operator' => '==', 'value' => 'lead'], 'positionX' => 1060, 'positionY' => 200],
                    ['key' => 'email', 'type' => 'action.email', 'name' => 'Email', 'config' => ['to' => '{{ trigger.email }}', 'subject' => 'Bienvenue', 'body' => 'Merci pour votre message !'], 'positionX' => 1380, 'positionY' => 120],
                    ['key' => 'sortie', 'type' => 'data.output', 'name' => 'Archivé', 'config' => [], 'positionX' => 1380, 'positionY' => 320],
                ],
                'edges' => [
                    ['sourceNodeKey' => 'manuel', 'targetNodeKey' => 'formulaire', 'sourceHandle' => 'out'],
                    ['sourceNodeKey' => 'formulaire', 'targetNodeKey' => 'classification', 'sourceHandle' => 'out'],
                    ['sourceNodeKey' => 'classification', 'targetNodeKey' => 'route', 'sourceHandle' => 'out'],
                    ['sourceNodeKey' => 'route', 'targetNodeKey' => 'email', 'sourceHandle' => 'true'],
                    ['sourceNodeKey' => 'route', 'targetNodeKey' => 'sortie', 'sourceHandle' => 'false'],
                ],
            ],
        );
    }

    /**
     * T3 « Veille du matin » : cron 8 h > requête HTTP (politique
     * continue) > résumé IA > email de synthèse.
     */
    private function seedWatchTemplate(): void
    {
        $this->seedSystemTemplate(
            name: self::WatchTemplate,
            category: 'Données',
            description: 'Chaque matin à 8 h, récupère un flux, le résume avec l’IA et envoie la synthèse par e-mail.',
            graph: [
                'nodes' => [
                    ['key' => 'cron', 'type' => 'trigger.schedule', 'name' => 'Planifié', 'config' => ['cron' => '0 8 * * *'], 'positionX' => 100, 'positionY' => 200],
                    ['key' => 'http', 'type' => 'action.http', 'name' => 'Requête HTTP', 'config' => ['method' => 'GET', 'url' => 'https://example.com/feed.xml', 'failure_policy' => 'continue'], 'positionX' => 420, 'positionY' => 200],
                    ['key' => 'resume', 'type' => 'ai.summarization', 'name' => 'Résumé', 'config' => ['model' => 'fake/demo', 'prompt' => 'Résume ce contenu : {{ http.body }}', 'temperature' => 0.3, 'max_tokens' => 512], 'positionX' => 740, 'positionY' => 200],
                    ['key' => 'email', 'type' => 'action.email', 'name' => 'Email', 'config' => ['to' => 'equipe@exemple.com', 'subject' => 'Veille du matin', 'body' => '{{ resume.text }}'], 'positionX' => 1060, 'positionY' => 200],
                ],
                'edges' => [
                    ['sourceNodeKey' => 'cron', 'targetNodeKey' => 'http', 'sourceHandle' => 'out'],
                    ['sourceNodeKey' => 'http', 'targetNodeKey' => 'resume', 'sourceHandle' => 'out'],
                    ['sourceNodeKey' => 'resume', 'targetNodeKey' => 'email', 'sourceHandle' => 'out'],
                ],
            ],
        );
    }

    /**
     * firstOrCreate the system template on origin+name; the graph is only
     * written at creation (idempotence guard).
     *
     * @param  array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}  $graph
     */
    private function seedSystemTemplate(string $name, string $category, string $description, array $graph): void
    {
        WorkflowTemplate::query()->firstOrCreate(
            ['origin' => TemplateOrigin::System->value, 'name' => $name],
            ['category' => $category, 'description' => $description, 'graph' => $graph],
        );
    }
}
