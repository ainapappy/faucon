<?php

use App\Services\Ai\AiJsonSchema;

test('the instruction names every field and renders each type in French', function () {
    $instruction = AiJsonSchema::instruction([
        'label' => 'enum:lead,spam,question',
        'montant' => 'number',
        'actif' => 'boolean',
        'nom' => 'text',
    ]);

    expect($instruction)->toContain('JSON')
        ->and($instruction)->toContain('"label"')
        ->and($instruction)->toContain('l’une des valeurs : lead, spam, question')
        ->and($instruction)->toContain('"montant"')
        ->and($instruction)->toContain('un nombre')
        ->and($instruction)->toContain('"actif"')
        ->and($instruction)->toContain('un booléen')
        ->and($instruction)->toContain('"nom"')
        ->and($instruction)->toContain('une chaîne de caractères');
});

test('errors returns an empty list for a conforming payload', function () {
    $errors = AiJsonSchema::errors(
        ['label' => 'enum:lead,spam', 'montant' => 'number', 'actif' => 'boolean', 'nom' => 'text'],
        ['label' => 'lead', 'montant' => 12.5, 'actif' => true, 'nom' => 'Client'],
    );

    expect($errors)->toBe([]);
});

test('errors reports a missing required field', function () {
    $errors = AiJsonSchema::errors(['label' => 'text', 'montant' => 'number'], ['label' => 'a']);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('montant');
});

test('errors rejects a string where a number is expected', function () {
    $errors = AiJsonSchema::errors(['montant' => 'number'], ['montant' => 'douze']);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('montant');
});

test('errors accepts integers and floats for a number field', function () {
    expect(AiJsonSchema::errors(['montant' => 'number'], ['montant' => 42]))->toBe([])
        ->and(AiJsonSchema::errors(['montant' => 'number'], ['montant' => 4.5]))->toBe([]);
});

test('errors rejects a number where text is expected', function () {
    $errors = AiJsonSchema::errors(['nom' => 'text'], ['nom' => 42]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('nom');
});

test('errors rejects a label outside the enum list', function () {
    $errors = AiJsonSchema::errors(['label' => 'enum:lead,spam,question'], ['label' => 'urgent']);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('lead, spam, question');
});

test('errors accepts an enum value ignoring surrounding whitespace', function () {
    expect(AiJsonSchema::errors(['label' => 'enum:lead,spam'], ['label' => ' spam ']))->toBe([]);
});

test('errors rejects a non-boolean value for a boolean field', function () {
    $errors = AiJsonSchema::errors(['actif' => 'boolean'], ['actif' => 'true']);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('actif');
});

test('errors tolerates extra keys beyond the schema', function () {
    $errors = AiJsonSchema::errors(['label' => 'text'], ['label' => 'a', 'extra' => 'ignored']);

    expect($errors)->toBe([]);
});

test('decode parses a plain json object', function () {
    expect(AiJsonSchema::decode('{"label":"lead"}'))->toBe(['label' => 'lead']);
});

test('decode parses a json object wrapped in markdown fences', function () {
    expect(AiJsonSchema::decode("```json\n{\"label\":\"lead\"}\n```"))->toBe(['label' => 'lead'])
        ->and(AiJsonSchema::decode("```\n{\"label\":\"lead\"}\n```"))->toBe(['label' => 'lead']);
});

test('decode returns null for invalid json', function () {
    expect(AiJsonSchema::decode('not json at all'))->toBeNull()
        ->and(AiJsonSchema::decode(null))->toBeNull();
});

test('decode returns null for a json list', function () {
    expect(AiJsonSchema::decode('["lead","spam"]'))->toBeNull();
});

test('demo value is deterministic per type', function () {
    expect(AiJsonSchema::demoValue('text'))->toBe('exemple')
        ->and(AiJsonSchema::demoValue('number'))->toBe(42)
        ->and(AiJsonSchema::demoValue('boolean'))->toBeTrue()
        ->and(AiJsonSchema::demoValue('enum:lead,spam,question'))->toBe('lead');
});
