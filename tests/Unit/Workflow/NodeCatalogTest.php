<?php

use App\Enums\NodeCategory;
use App\Services\Workflow\NodeCatalog;

test('the catalog exposes exactly seventeen node types', function () {
    expect(count(NodeCatalog::all()))->toBe(17);
});

test('node type ids match the category dot type format', function () {
    foreach (array_keys(NodeCatalog::all()) as $type) {
        expect($type)->toMatch('/^[a-z]+\.[a-z_]+$/');
    }
});

test('the catalog distributes 3/3/2/5/4 types across the five categories', function () {
    $counts = array_map(fn (NodeCategory $category): int => count(NodeCatalog::typesForCategory($category)), NodeCategory::cases());

    expect($counts)->toBe([3, 3, 2, 5, 4]);
});

test('the data.http_request type is gone from the catalog', function () {
    expect(NodeCatalog::has('data.http_request'))->toBeFalse()
        ->and(NodeCatalog::definitionFor('data.http_request'))->toBeNull();
});

test('the action.http definition carries the six hardened fields', function () {
    $definition = NodeCatalog::definitionFor('action.http');

    expect($definition)->not->toBeNull()
        ->and($definition->type)->toBe('action.http')
        ->and($definition->category)->toBe(NodeCategory::Action)
        ->and($definition->label)->toBe('Requête HTTP')
        ->and($definition->description)->toBe('Appel sortant protégé (garde-fous SSRF)')
        ->and($definition->icon)->toBe('globe')
        ->and($definition->input)->toBeTrue()
        ->and(array_column($definition->outputs, 'id'))->toBe(['out'])
        ->and(array_column($definition->fields, 'key'))->toBe(['method', 'url', 'headers', 'body', 'integration_id', 'failure_policy'])
        ->and($definition->fields[0]['options'])->toBe(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])
        ->and($definition->fields[1]['type'])->toBe('text')
        ->and($definition->fields[1]['mono'])->toBeTrue()
        ->and($definition->fields[2]['type'])->toBe('textarea')
        ->and($definition->fields[2]['mono'])->toBeTrue()
        ->and($definition->fields[4]['type'])->toBe('integration')
        ->and($definition->fields[5]['options'])->toBe(['fail', 'continue']);
});

test('the manual trigger has one output and no configuration fields', function () {
    $definition = NodeCatalog::definitionFor('trigger.manual');

    expect($definition)->not->toBeNull()
        ->and($definition->label)->toBe('Manuel')
        ->and($definition->input)->toBeFalse()
        ->and($definition->outputs)->toHaveCount(1)
        ->and($definition->fields)->toHaveCount(0);
});

test('the condition node exposes the true and false outputs', function () {
    $definition = NodeCatalog::definitionFor('logic.condition');

    expect($definition)->not->toBeNull()
        ->and(array_column($definition->outputs, 'id'))->toBe(['true', 'false']);
});

test('the output node terminates the graph with one input, no outputs and no fields', function () {
    $definition = NodeCatalog::definitionFor('data.output');

    expect($definition)->not->toBeNull()
        ->and($definition->label)->toBe('Sortie')
        ->and($definition->description)->toBe('Termine le run et expose le résultat final')
        ->and($definition->icon)->toBe('arrow-right-to-line')
        ->and($definition->input)->toBeTrue()
        ->and($definition->outputs)->toBe([])
        ->and($definition->fields)->toBe([]);
});

test('the output type belongs to the data category and is not a trigger', function () {
    expect(NodeCatalog::categoryFor('data.output'))->toBe(NodeCategory::Data)
        ->and(NodeCatalog::typesForCategory(NodeCategory::Trigger))->not->toContain('data.output');
});

test('the condition node exposes the expression, operator and value fields', function () {
    $definition = NodeCatalog::definitionFor('logic.condition');

    expect($definition)->not->toBeNull()
        ->and(array_column($definition->fields, 'key'))->toBe(['expression', 'operator', 'value'])
        ->and($definition->fields[0]['placeholder'])->toBe('{{ trigger.label }}')
        ->and($definition->fields[1]['type'])->toBe('select')
        ->and($definition->fields[1]['options'])->toBe(['==', '!=', 'contains', 'empty'])
        ->and($definition->fields[2]['type'])->toBe('text');
});

test('unknown types have no definition', function () {
    expect(NodeCatalog::definitionFor('trigger.unknown'))->toBeNull()
        ->and(NodeCatalog::has('trigger.unknown'))->toBeFalse();
});

test('every definition carries the mockup metadata', function () {
    foreach (NodeCatalog::all() as $definition) {
        expect($definition->label)->not->toBeEmpty()
            ->and($definition->description)->not->toBeEmpty()
            ->and($definition->icon)->not->toBeEmpty();
    }
});

test('the category for a type resolves through the catalog', function () {
    expect(NodeCatalog::categoryFor('ai.summarization'))->toBe(NodeCategory::Ai)
        ->and(NodeCatalog::categoryFor('trigger.unknown'))->toBeNull();
});

test('the trigger types are resolvable for the trigger node relation', function () {
    expect(NodeCatalog::typesForCategory(NodeCategory::Trigger))->toBe([
        'trigger.webhook',
        'trigger.schedule',
        'trigger.manual',
    ]);
});

test('the webhook trigger declares no configuration fields', function () {
    $definition = NodeCatalog::definitionFor('trigger.webhook');

    expect($definition)->not->toBeNull()
        ->and($definition->fields)->toBe([]);
});

test('the email action carries the integration field', function () {
    $definition = NodeCatalog::definitionFor('action.email');

    expect($definition)->not->toBeNull()
        ->and(array_column($definition->fields, 'key'))->toBe(['to', 'subject', 'body', 'integration_id'])
        ->and($definition->fields[3]['type'])->toBe('integration');
});

test('the ai.summary type is gone from the catalog', function () {
    expect(NodeCatalog::has('ai.summary'))->toBeFalse()
        ->and(NodeCatalog::definitionFor('ai.summary'))->toBeNull();
});

test('the five ai type ids are present', function () {
    foreach (['ai.prompt', 'ai.classification', 'ai.extraction', 'ai.summarization', 'ai.generation'] as $type) {
        expect(NodeCatalog::has($type))->toBeTrue()
            ->and(NodeCatalog::categoryFor($type))->toBe(NodeCategory::Ai);
    }
});

test('the ai.prompt definition carries the mockup fields in display order', function () {
    $definition = NodeCatalog::definitionFor('ai.prompt');

    expect($definition)->not->toBeNull()
        ->and($definition->label)->toBe('Prompt')
        ->and($definition->description)->toBe('Interroge un modèle IA avec un prompt libre')
        ->and($definition->icon)->toBe('pen-line')
        ->and(array_column($definition->fields, 'key'))->toBe(['model', 'prompt', 'temperature', 'max_tokens'])
        ->and($definition->fields[1]['label'])->toBe('Prompt')
        ->and($definition->fields[1]['type'])->toBe('textarea')
        ->and($definition->fields[2]['type'])->toBe('range')
        ->and($definition->fields[2]['min'])->toBe(0.0)
        ->and($definition->fields[2]['max'])->toBe(1.0)
        ->and($definition->fields[2]['step'])->toBe(0.1)
        ->and($definition->fields[3]['type'])->toBe('text')
        ->and($definition->fields[3]['mono'])->toBeTrue();
});

test('the ai.classification definition keeps its mockup description and adds the ai fields', function () {
    $definition = NodeCatalog::definitionFor('ai.classification');

    expect($definition)->not->toBeNull()
        ->and($definition->label)->toBe('Classification')
        ->and($definition->description)->toBe('Catégorise un contenu (sortie structurée)')
        ->and($definition->icon)->toBe('bot')
        ->and(array_column($definition->fields, 'key'))->toBe(['model', 'prompt', 'labels', 'temperature', 'max_tokens'])
        ->and($definition->fields[2]['label'])->toBe('Étiquettes')
        ->and($definition->fields[2]['placeholder'])->toBe('lead, spam, question')
        ->and($definition->fields[2]['mono'])->toBeTrue();
});

test('the ai.extraction definition carries the fields textarea with its types hint', function () {
    $definition = NodeCatalog::definitionFor('ai.extraction');

    expect($definition)->not->toBeNull()
        ->and($definition->label)->toBe('Extraction')
        ->and($definition->description)->toBe('Extrait des champs structurés (JSON) d’un contenu')
        ->and($definition->icon)->toBe('scan-text')
        ->and(array_column($definition->fields, 'key'))->toBe(['model', 'prompt', 'fields', 'temperature', 'max_tokens'])
        ->and($definition->fields[1]['label'])->toBe('Contenu à analyser')
        ->and($definition->fields[2]['type'])->toBe('textarea')
        ->and($definition->fields[2]['mono'])->toBeTrue()
        ->and($definition->fields[2]['placeholder'])->toBe("nom: text\nmontant: number");
});

test('the ai.summarization definition replaces ai.summary with the mockup texts', function () {
    $definition = NodeCatalog::definitionFor('ai.summarization');

    expect($definition)->not->toBeNull()
        ->and($definition->label)->toBe('Résumé')
        ->and($definition->description)->toBe('Condense un contenu long')
        ->and($definition->icon)->toBe('file-text')
        ->and(array_column($definition->fields, 'key'))->toBe(['model', 'prompt', 'temperature', 'max_tokens'])
        ->and($definition->fields[1]['label'])->toBe('Consigne')
        ->and($definition->fields[1]['type'])->toBe('textarea');
});

test('the ai.generation definition carries the instructions prompt and full field set', function () {
    $definition = NodeCatalog::definitionFor('ai.generation');

    expect($definition)->not->toBeNull()
        ->and($definition->label)->toBe('Génération')
        ->and($definition->description)->toBe('Produit un texte à partir du contexte')
        ->and($definition->icon)->toBe('sparkles')
        ->and(array_column($definition->fields, 'key'))->toBe(['model', 'prompt', 'temperature', 'max_tokens'])
        ->and($definition->fields[1]['label'])->toBe('Instructions')
        ->and($definition->fields[1]['type'])->toBe('textarea');
});

test('every ai model select exposes the composite provider slash model options', function () {
    config([
        'ai.providers.fake.models' => ['demo'],
        'ai.providers.openai.enabled' => true,
        'ai.providers.openai.models' => ['gpt-4o-mini'],
    ]);
    NodeCatalog::flush();

    try {
        $options = NodeCatalog::definitionFor('ai.classification')->fields[0]['options'];

        expect($options)->toBe(['fake/demo', 'openai/gpt-4o-mini']);
    } finally {
        config([
            'ai.providers.fake.models' => ['demo'],
            'ai.providers.openai.enabled' => false,
            'ai.providers.openai.models' => ['gpt-4o-mini', 'gpt-4o'],
        ]);
        NodeCatalog::flush();
    }
});

test('disabled providers never appear in the model options', function () {
    NodeCatalog::flush();

    $options = NodeCatalog::definitionFor('ai.prompt')->fields[0]['options'];

    expect($options)->toBe(['fake/demo'])
        ->and(NodeCatalog::aiModelOptions())->toBe(['fake/demo']);
});

test('flush restores the options after the config is reset', function () {
    config(['ai.providers.openai.enabled' => true, 'ai.providers.openai.models' => ['gpt-4o-mini']]);
    NodeCatalog::flush();

    $withOpenAi = NodeCatalog::aiModelOptions();

    config(['ai.providers.openai.enabled' => false, 'ai.providers.openai.models' => ['gpt-4o-mini', 'gpt-4o']]);
    NodeCatalog::flush();

    expect($withOpenAi)->toContain('openai/gpt-4o-mini')
        ->and(NodeCatalog::aiModelOptions())->not->toContain('openai/gpt-4o-mini');
});

test('no api key ever leaks into the catalog definitions or options', function () {
    config(['ai.providers.openai.key' => 'sk-leak-sentinel-1234567890']);
    NodeCatalog::flush();

    try {
        expect(json_encode(NodeCatalog::all(), JSON_UNESCAPED_UNICODE))
            ->not->toContain('sk-leak-sentinel');
    } finally {
        config(['ai.providers.openai.key' => null]);
        NodeCatalog::flush();
    }
});
