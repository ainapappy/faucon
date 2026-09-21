<?php

use App\Services\Workflow\Exception\NodeExecutionException;
use App\Services\Workflow\Interpolator;

test('it resolves a simple top level path', function () {
    $resolved = (new Interpolator)->resolve(['email' => 'client@example.com'], 'email');

    expect($resolved)->toBe('client@example.com');
});

test('it resolves a nested path', function () {
    $resolved = (new Interpolator)->resolve([
        'trigger' => ['contact' => ['email' => 'client@example.com']],
    ], 'trigger.contact.email');

    expect($resolved)->toBe('client@example.com');
});

test('it resolves a numeric index within a listed array', function () {
    $resolved = (new Interpolator)->resolve([
        'items' => [['id' => 'a'], ['id' => 'b']],
    ], 'items.0.id');

    expect($resolved)->toBe('a');
});

test('it returns null when the path is missing', function () {
    $resolved = (new Interpolator)->resolve(['email' => 'client@example.com'], 'phone');

    expect($resolved)->toBeNull();
});

test('interpolate renders the resolved value in a template', function () {
    $rendered = (new Interpolator)->interpolate([
        'trigger' => ['email' => 'client@example.com'],
    ], 'Contact : {{ trigger.email }}');

    expect($rendered)->toBe('Contact : client@example.com');
});

test('interpolate throws path_not_found when a placeholder path is missing', function () {
    $interpolate = fn (): string => (new Interpolator)->interpolate(
        ['trigger' => ['email' => 'client@example.com']],
        'Contact : {{ trigger.phone }}',
    );

    expect($interpolate)
        ->toThrow(fn (NodeExecutionException $exception) => $exception->reason === 'path_not_found');
});

test('value throws path_not_found when the single placeholder is missing', function () {
    $value = fn (): mixed => (new Interpolator)->value(['trigger' => []], '{{ trigger.phone }}');

    expect($value)
        ->toThrow(fn (NodeExecutionException $exception) => $exception->reason === 'path_not_found');
});

test('interpolate renders an existing null value as an empty string without error', function () {
    $rendered = (new Interpolator)->interpolate(['middle_name' => null], 'Hi {{ middle_name }}!');

    expect($rendered)->toBe('Hi !');
});

test('interpolate casts booleans and null as native strings inline', function () {
    $interpolator = new Interpolator;
    $variables = ['flag' => true, 'off' => false, 'nothing' => null];

    expect($interpolator->interpolate($variables, '{{ flag }}'))->toBe('1')
        ->and($interpolator->interpolate($variables, '{{ off }}'))->toBe('')
        ->and($interpolator->interpolate($variables, 'x{{ nothing }}y'))->toBe('xy');
});

test('interpolate encodes arrays as JSON inline', function () {
    $rendered = (new Interpolator)->interpolate([
        'tags' => ['lead', 'vip'],
    ], 'Tags : {{ tags }}');

    expect($rendered)->toBe('Tags : ["lead","vip"]');
});

test('value returns the raw structure for a single placeholder template', function () {
    $payload = ['email' => 'client@example.com', 'tags' => ['lead']];
    $value = (new Interpolator)->value(['trigger' => $payload], '{{ trigger }}');

    expect($value)->toBe($payload);
});

test('value returns the raw scalar for a single placeholder template', function () {
    $value = (new Interpolator)->value(['trigger' => ['score' => 42]], '{{ trigger.score }}');

    expect($value)->toBe(42);
});

test('interpolate interpolates surrounding text for a placeholder among text', function () {
    $value = (new Interpolator)->value(['trigger' => ['name' => 'Aina']], 'Bonjour {{ trigger.name }}');

    expect($value)->toBe('Bonjour Aina');
});

test('interpolate renders escaped placeholders literally', function () {
    $rendered = (new Interpolator)->interpolate(['x' => 'value'], 'Syntaxe : @{{ x }}');

    expect($rendered)->toBe('Syntaxe : {{ x }}');
});

test('interpolate keeps an unclosed delimiter as literal text', function () {
    $rendered = (new Interpolator)->interpolate(['x' => 'value'], 'Ooops {{ x');

    expect($rendered)->toBe('Ooops {{ x');
});

test('interpolate keeps an empty placeholder as literal text', function () {
    $rendered = (new Interpolator)->interpolate(['x' => 'value'], 'Ooops {{ }}');

    expect($rendered)->toBe('Ooops {{ }}');
});

test('it resolves a path containing dashes like client node keys', function () {
    $resolved = (new Interpolator)->resolve([
        '3f2a9c1e-8b7d-4c2a-9e1f-5a6b7c8d9e0f' => ['email' => 'client@example.com'],
    ], '3f2a9c1e-8b7d-4c2a-9e1f-5a6b7c8d9e0f.email');

    expect($resolved)->toBe('client@example.com');
});

test('it does not resolve wild segments such as stars or paths past a scalar', function () {
    $interpolator = new Interpolator;
    $variables = ['items' => [['id' => 'a'], ['id' => 'b']]];

    expect($interpolator->resolve($variables, 'items.*.id'))->toBeNull()
        ->and($interpolator->resolve($variables, 'items.*'))->toBeNull()
        ->and($interpolator->resolve($variables, 'items.0.id.extra'))->toBeNull();
});

test('interpolate renders an integer zero inline instead of an empty string', function () {
    $rendered = (new Interpolator)->interpolate(['score' => 0], 'Score : {{ score }}');

    expect($rendered)->toBe('Score : 0');
});

test('value returns the integer zero untouched for a single placeholder', function () {
    $value = (new Interpolator)->value(['trigger' => ['score' => 0]], '{{ trigger.score }}');

    expect($value)->toBe(0);
});

test('interpolate renders an empty template as an empty string', function () {
    $rendered = (new Interpolator)->interpolate(['x' => 'value'], '');

    expect($rendered)->toBe('');
});

test('interpolate renders placeholders surrounded by unicode and emoji', function () {
    $rendered = (new Interpolator)->interpolate(
        ['contact' => ['email' => 'client@example.com']],
        '📧 Contact 🚀 {{ contact.email }} — fin ✅',
    );

    expect($rendered)->toBe('📧 Contact 🚀 client@example.com — fin ✅');
});
