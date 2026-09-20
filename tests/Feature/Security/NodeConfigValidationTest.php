<?php

use App\Models\Workflow;

test('a select value outside the declared options is rejected at save time', function (string $type, array $config) {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(
        nodes: [['key' => 'n1', 'type' => $type, 'config' => $config]],
    );

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nodes.0.config']);
})->with([
    'http method' => ['action.http', ['method' => 'TOTO']],
    'http failure policy' => ['action.http', ['failure_policy' => 'ignore']],
    'condition operator' => ['logic.condition', ['operator' => '~']],
    'ai model' => ['ai.prompt', ['model' => 'provider/inconnu']],
]);

test('a select error message names the field and the node type', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(
        nodes: [['key' => 'n1', 'type' => 'action.http', 'config' => ['method' => 'TOTO']]],
    );

    $response = $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload);

    expect($response->json('errors')['nodes.0.config'][0])
        ->toBe('La valeur du champ « method » n’est pas une option valide pour le type « action.http ».');
});

test('a range value outside the declared bounds is rejected at save time', function (mixed $temperature) {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(
        nodes: [['key' => 'n1', 'type' => 'ai.prompt', 'config' => ['temperature' => $temperature]]],
    );

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nodes.0.config']);
})->with([
    'above the maximum' => [5],
    'below the minimum' => [-0.5],
    'not numeric' => ['abc'],
]);

test('a range error message names the field and the bounds', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(
        nodes: [['key' => 'n1', 'type' => 'ai.prompt', 'config' => ['temperature' => 5]]],
    );

    $response = $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload);

    expect($response->json('errors')['nodes.0.config'][0])
        ->toBe('La valeur du champ « temperature » doit être comprise entre 0 et 1.');
});

test('valid provided select and range values are accepted at save time', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(
        nodes: [
            ['key' => 'n1', 'type' => 'trigger.manual'],
            ['key' => 'n2', 'type' => 'ai.prompt', 'config' => ['model' => 'fake/demo', 'temperature' => '0.5']],
            ['key' => 'n3', 'type' => 'logic.condition', 'config' => ['operator' => '==', 'value' => 'lead']],
        ],
        edges: [
            ['source' => 'n1', 'target' => 'n2'],
            ['source' => 'n2', 'target' => 'n3'],
        ],
    );

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload)
        ->assertNoContent();
});

test('empty select and range values are treated as absent so the autosave never blocks', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(
        nodes: [
            ['key' => 'n1', 'type' => 'trigger.webhook'],
            ['key' => 'n2', 'type' => 'action.http', 'config' => ['method' => '', 'url' => '', 'failure_policy' => null]],
            ['key' => 'n3', 'type' => 'ai.prompt', 'config' => ['model' => null, 'temperature' => '']],
            ['key' => 'n4', 'type' => 'logic.condition', 'config' => ['operator' => '']],
        ],
        edges: [
            ['source' => 'n1', 'target' => 'n2'],
            ['source' => 'n2', 'target' => 'n3'],
            ['source' => 'n3', 'target' => 'n4'],
        ],
    );

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload)
        ->assertNoContent();
});

test('an invalid literal email recipient is rejected at save time', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(
        nodes: [['key' => 'n1', 'type' => 'action.email', 'config' => ['to' => 'pas-un-email']]],
    );

    $response = $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload);

    $response->assertUnprocessable()->assertJsonValidationErrors(['nodes.0.config']);

    expect($response->json('errors')['nodes.0.config'][0])
        ->toBe('L’adresse destinataire n’est pas une adresse e-mail valide.');
});

test('an oversized literal email recipient is rejected at save time', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(
        nodes: [['key' => 'n1', 'type' => 'action.email', 'config' => ['to' => str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 63)]]],
    );

    $response = $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload);

    $response->assertUnprocessable()->assertJsonValidationErrors(['nodes.0.config']);

    expect($response->json('errors')['nodes.0.config'])
        ->toBe([
            'L’adresse destinataire n’est pas une adresse e-mail valide.',
            'L’adresse destinataire ne doit pas dépasser 255 caractères.',
        ]);
});

test('placeholder and empty email recipients are accepted at save time', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(
        nodes: [
            ['key' => 'n1', 'type' => 'trigger.manual'],
            ['key' => 'n2', 'type' => 'action.email', 'config' => ['to' => '{{ trigger.email }}']],
            ['key' => 'n3', 'type' => 'action.email', 'config' => ['to' => '', 'subject' => '', 'body' => '']],
        ],
        edges: [
            ['source' => 'n1', 'target' => 'n2', 'handle' => 'out'],
            ['source' => 'n1', 'target' => 'n3', 'handle' => 'out'],
        ],
    );

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload)
        ->assertNoContent();
});

test('the strict schedule cron behavior is unchanged at save time', function () {
    [$user, $team] = teamWithMember();
    $workflow = Workflow::factory()->for($team)->create();

    $payload = graphPayload(
        nodes: [['key' => 'n1', 'type' => 'trigger.schedule', 'config' => ['cron' => '']]],
    );

    $this->actingAs($user)
        ->putJson(route('workflows.graph.update', ['current_team' => $team->slug, 'workflow' => $workflow->id]), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nodes.0.config']);
});
