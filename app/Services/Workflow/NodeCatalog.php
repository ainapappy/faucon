<?php

namespace App\Services\Workflow;

use App\Data\Workflow\NodeDefinition;
use App\Enums\NodeCategory;

/**
 * Canonical catalog of workflow node types (`category.type` ids).
 *
 * Single source of truth shared by validation (SaveWorkflowGraphRequest),
 * the triggerNode relation and the Inertia props (`nodeTypes`).
 */
final class NodeCatalog
{
    /**
     * The fourteen node type definitions (mirrors the builder mockup).
     *
     * Memoized once per request. PHP 8.4 does not allow `new` in class
     * constants or static property initializers, hence this lazy builder.
     *
     * @var array<string, NodeDefinition>|null
     */
    private static ?array $definitions = null;

    /**
     * Get the node type definitions, building them on first access.
     *
     * @return array<string, NodeDefinition>
     */
    private static function definitions(): array
    {
        return self::$definitions ??= [
            'trigger.webhook' => new NodeDefinition(
                type: 'trigger.webhook',
                category: NodeCategory::Trigger,
                label: 'Webhook',
                description: 'Appel HTTP entrant, idempotent et rate-limité',
                icon: 'webhook',
                input: false,
                outputs: [
                    ['id' => 'out', 'label' => null, 'position' => 0.5],
                ],
                fields: [
                    ['key' => 'method', 'label' => 'Méthode', 'type' => 'select', 'required' => false, 'placeholder' => null, 'options' => ['POST', 'GET'], 'min' => null, 'max' => null, 'step' => null, 'mono' => false],
                    ['key' => 'path', 'label' => 'Chemin', 'type' => 'text', 'required' => false, 'placeholder' => 'hooks/leads', 'options' => null, 'min' => null, 'max' => null, 'step' => null, 'mono' => false],
                ],
            ),
            'trigger.schedule' => new NodeDefinition(
                type: 'trigger.schedule',
                category: NodeCategory::Trigger,
                label: 'Planifié',
                description: 'Exécution récurrente (expression cron)',
                icon: 'calendar-clock',
                input: false,
                outputs: [
                    ['id' => 'out', 'label' => null, 'position' => 0.5],
                ],
                fields: [
                    ['key' => 'cron', 'label' => 'Expression cron', 'type' => 'text', 'required' => false, 'placeholder' => '0 9 * * 1', 'options' => null, 'min' => null, 'max' => null, 'step' => null, 'mono' => true],
                ],
            ),
            'trigger.manual' => new NodeDefinition(
                type: 'trigger.manual',
                category: NodeCategory::Trigger,
                label: 'Manuel',
                description: 'Lancement depuis l’interface',
                icon: 'play',
                input: false,
                outputs: [
                    ['id' => 'out', 'label' => null, 'position' => 0.5],
                ],
                fields: [],
            ),
            'data.input' => new NodeDefinition(
                type: 'data.input',
                category: NodeCategory::Data,
                label: 'Entrée',
                description: 'Données injectées dans le workflow',
                icon: 'braces',
                input: true,
                outputs: [
                    ['id' => 'out', 'label' => null, 'position' => 0.5],
                ],
                fields: [
                    ['key' => 'name', 'label' => 'Nom de la variable', 'type' => 'text', 'required' => false, 'placeholder' => 'payload', 'options' => null, 'min' => null, 'max' => null, 'step' => null, 'mono' => true],
                ],
            ),
            'data.transform' => new NodeDefinition(
                type: 'data.transform',
                category: NodeCategory::Data,
                label: 'Transformation',
                description: 'Restructure les variables du contexte',
                icon: 'shuffle',
                input: true,
                outputs: [
                    ['id' => 'out', 'label' => null, 'position' => 0.5],
                ],
                fields: [
                    ['key' => 'expression', 'label' => 'Expression', 'type' => 'textarea', 'required' => false, 'placeholder' => '{{ trigger.email }}', 'options' => null, 'min' => null, 'max' => null, 'step' => null, 'mono' => true],
                ],
            ),
            'data.http_request' => new NodeDefinition(
                type: 'data.http_request',
                category: NodeCategory::Data,
                label: 'Requête HTTP',
                description: 'Appel sortant protégé (garde-fous SSRF)',
                icon: 'globe',
                input: true,
                outputs: [
                    ['id' => 'out', 'label' => null, 'position' => 0.5],
                ],
                fields: [
                    ['key' => 'method', 'label' => 'Méthode', 'type' => 'select', 'required' => false, 'placeholder' => null, 'options' => ['GET', 'POST', 'PUT'], 'min' => null, 'max' => null, 'step' => null, 'mono' => false],
                    ['key' => 'url', 'label' => 'URL', 'type' => 'text', 'required' => false, 'placeholder' => 'https://api.exemple.com/v1/…', 'options' => null, 'min' => null, 'max' => null, 'step' => null, 'mono' => true],
                ],
            ),
            'logic.condition' => new NodeDefinition(
                type: 'logic.condition',
                category: NodeCategory::Logic,
                label: 'Condition',
                description: 'Deux branches : true / false',
                icon: 'git-branch',
                input: true,
                outputs: [
                    ['id' => 'true', 'label' => 'true', 'position' => 0.36],
                    ['id' => 'false', 'label' => 'false', 'position' => 0.68],
                ],
                fields: [
                    ['key' => 'expression', 'label' => 'Expression', 'type' => 'text', 'required' => false, 'placeholder' => '{{ ai.label }} == "lead"', 'options' => null, 'min' => null, 'max' => null, 'step' => null, 'mono' => true],
                ],
            ),
            'logic.filter' => new NodeDefinition(
                type: 'logic.filter',
                category: NodeCategory::Logic,
                label: 'Filtre',
                description: 'Ne laisse passer que les éléments valides',
                icon: 'filter',
                input: true,
                outputs: [
                    ['id' => 'out', 'label' => null, 'position' => 0.5],
                ],
                fields: [
                    ['key' => 'expression', 'label' => 'Condition d’inclusion', 'type' => 'text', 'required' => false, 'placeholder' => null, 'options' => null, 'min' => null, 'max' => null, 'step' => null, 'mono' => true],
                ],
            ),
            'ai.classification' => new NodeDefinition(
                type: 'ai.classification',
                category: NodeCategory::Ai,
                label: 'Classification',
                description: 'Catégorise un contenu (sortie structurée)',
                icon: 'bot',
                input: true,
                outputs: [
                    ['id' => 'out', 'label' => null, 'position' => 0.5],
                ],
                fields: [
                    ['key' => 'model', 'label' => 'Modèle', 'type' => 'select', 'required' => false, 'placeholder' => null, 'options' => ['gpt-4o-mini', 'claude-haiku', 'claude-sonnet'], 'min' => null, 'max' => null, 'step' => null, 'mono' => false],
                    ['key' => 'prompt', 'label' => 'Prompt', 'type' => 'textarea', 'required' => false, 'placeholder' => 'Classe ce message parmi…', 'options' => null, 'min' => null, 'max' => null, 'step' => null, 'mono' => false],
                    ['key' => 'labels', 'label' => 'Étiquettes', 'type' => 'text', 'required' => false, 'placeholder' => 'lead, spam, question', 'options' => null, 'min' => null, 'max' => null, 'step' => null, 'mono' => true],
                ],
            ),
            'ai.generation' => new NodeDefinition(
                type: 'ai.generation',
                category: NodeCategory::Ai,
                label: 'Génération',
                description: 'Produit un texte à partir du contexte',
                icon: 'sparkles',
                input: true,
                outputs: [
                    ['id' => 'out', 'label' => null, 'position' => 0.5],
                ],
                fields: [
                    ['key' => 'model', 'label' => 'Modèle', 'type' => 'select', 'required' => false, 'placeholder' => null, 'options' => ['gpt-4o', 'claude-sonnet', 'claude-haiku'], 'min' => null, 'max' => null, 'step' => null, 'mono' => false],
                    ['key' => 'prompt', 'label' => 'Prompt', 'type' => 'textarea', 'required' => false, 'placeholder' => null, 'options' => null, 'min' => null, 'max' => null, 'step' => null, 'mono' => false],
                    ['key' => 'temperature', 'label' => 'Température', 'type' => 'range', 'required' => false, 'placeholder' => null, 'options' => null, 'min' => 0.0, 'max' => 1.0, 'step' => 0.1, 'mono' => false],
                ],
            ),
            'ai.summary' => new NodeDefinition(
                type: 'ai.summary',
                category: NodeCategory::Ai,
                label: 'Résumé',
                description: 'Condense un contenu long',
                icon: 'file-text',
                input: true,
                outputs: [
                    ['id' => 'out', 'label' => null, 'position' => 0.5],
                ],
                fields: [
                    ['key' => 'model', 'label' => 'Modèle', 'type' => 'select', 'required' => false, 'placeholder' => null, 'options' => ['gpt-4o-mini', 'claude-haiku'], 'min' => null, 'max' => null, 'step' => null, 'mono' => false],
                    ['key' => 'prompt', 'label' => 'Consigne', 'type' => 'textarea', 'required' => false, 'placeholder' => null, 'options' => null, 'min' => null, 'max' => null, 'step' => null, 'mono' => false],
                ],
            ),
            'action.email' => new NodeDefinition(
                type: 'action.email',
                category: NodeCategory::Action,
                label: 'Email',
                description: 'Envoie un e-mail transactionnel',
                icon: 'mail',
                input: true,
                outputs: [],
                fields: [
                    ['key' => 'to', 'label' => 'Destinataire', 'type' => 'text', 'required' => false, 'placeholder' => '{{ trigger.email }}', 'options' => null, 'min' => null, 'max' => null, 'step' => null, 'mono' => true],
                    ['key' => 'subject', 'label' => 'Sujet', 'type' => 'text', 'required' => false, 'placeholder' => null, 'options' => null, 'min' => null, 'max' => null, 'step' => null, 'mono' => false],
                    ['key' => 'body', 'label' => 'Corps', 'type' => 'textarea', 'required' => false, 'placeholder' => null, 'options' => null, 'min' => null, 'max' => null, 'step' => null, 'mono' => false],
                ],
            ),
            'action.message' => new NodeDefinition(
                type: 'action.message',
                category: NodeCategory::Action,
                label: 'Message',
                description: 'Poste un message (Slack, webhook…)',
                icon: 'message-square',
                input: true,
                outputs: [],
                fields: [
                    ['key' => 'channel', 'label' => 'Canal', 'type' => 'text', 'required' => false, 'placeholder' => '#ventes', 'options' => null, 'min' => null, 'max' => null, 'step' => null, 'mono' => false],
                    ['key' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => false, 'placeholder' => null, 'options' => null, 'min' => null, 'max' => null, 'step' => null, 'mono' => false],
                ],
            ),
            'action.delay' => new NodeDefinition(
                type: 'action.delay',
                category: NodeCategory::Action,
                label: 'Temporisation',
                description: 'Attend avant de continuer',
                icon: 'timer',
                input: true,
                outputs: [
                    ['id' => 'out', 'label' => null, 'position' => 0.5],
                ],
                fields: [
                    ['key' => 'seconds', 'label' => 'Durée (secondes)', 'type' => 'text', 'required' => false, 'placeholder' => null, 'options' => null, 'min' => null, 'max' => null, 'step' => null, 'mono' => true],
                ],
            ),
        ];
    }

    /**
     * Get all node type definitions, keyed by type id.
     *
     * @return array<string, NodeDefinition>
     */
    public static function all(): array
    {
        return self::definitions();
    }

    /**
     * Determine whether the given type id exists in the catalog.
     */
    public static function has(string $type): bool
    {
        return isset(self::definitions()[$type]);
    }

    /**
     * Get the definition for the given type id, if any.
     */
    public static function definitionFor(string $type): ?NodeDefinition
    {
        return self::definitions()[$type] ?? null;
    }

    /**
     * Get all node type ids for the given category.
     *
     * @return array<int, string>
     */
    public static function typesForCategory(NodeCategory $category): array
    {
        return array_keys(array_filter(
            self::definitions(),
            fn (NodeDefinition $definition): bool => $definition->category === $category,
        ));
    }

    /**
     * Get the category for the given type id, if any.
     */
    public static function categoryFor(string $type): ?NodeCategory
    {
        $definition = self::definitions()[$type] ?? null;

        return $definition?->category;
    }
}
