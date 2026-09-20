<?php

use App\Enums\TemplateOrigin;
use App\Models\User;
use App\Models\WorkflowTemplate;
use Illuminate\Support\Facades\Schema;

test('the workflow_templates table exists with its columns and indexes', function () {
    expect(Schema::hasTable('workflow_templates'))->toBeTrue();

    $template = WorkflowTemplate::factory()->create([
        'name' => 'Support client prioritaire',
        'description' => 'A ready to use template',
        'category' => 'Support',
        'graph' => ['nodes' => [], 'edges' => []],
    ]);

    expect($template->name)->toBe('Support client prioritaire')
        ->and($template->description)->toBe('A ready to use template')
        ->and($template->category)->toBe('Support')
        ->and($template->origin)->toBe(TemplateOrigin::Team)
        ->and($template->graph)->toBe(['nodes' => [], 'edges' => []]);

    $indexedColumns = collect(Schema::getIndexes('workflow_templates'))
        ->map(fn (array $index): array => $index['columns'])
        ->values()
        ->all();

    expect(in_array(['category'], $indexedColumns, true))->toBeTrue()
        ->and(in_array(['origin'], $indexedColumns, true))->toBeTrue()
        ->and(in_array(['team_id', 'category'], $indexedColumns, true))->toBeTrue();
});

test('a team deletion cascades its templates', function () {
    $template = WorkflowTemplate::factory()->team()->create();

    $template->team->forceDelete();

    expect(WorkflowTemplate::query()->whereKey($template->id)->exists())->toBeFalse();
});

test('deleting the creator keeps the template with a null created_by', function () {
    $user = User::factory()->create();
    $template = WorkflowTemplate::factory()->team()->create(['created_by' => $user->id]);

    $user->delete();

    expect($template->fresh()->created_by)->toBeNull()
        ->and(WorkflowTemplate::query()->whereKey($template->id)->exists())->toBeTrue();
});

test('origin is cast to the enum and graph to an array', function () {
    $system = WorkflowTemplate::factory()->system()->create();
    $team = WorkflowTemplate::factory()->team()->create();

    expect($system->origin)->toBe(TemplateOrigin::System)
        ->and($system->origin->isSystem())->toBeTrue()
        ->and($system->fresh()->origin->value)->toBe('system')
        ->and($team->origin)->toBe(TemplateOrigin::Team)
        ->and($team->origin->isSystem())->toBeFalse()
        ->and($system->graph)->toBeArray();
});

test('a system template has no team and the system origin', function () {
    $template = WorkflowTemplate::factory()->system()->create();

    expect($template->team_id)->toBeNull()
        ->and($template->team)->toBeNull()
        ->and($template->origin)->toBe(TemplateOrigin::System);
});

test('a team template has both its team and the team origin', function () {
    [$user, $team] = teamWithMember();

    $template = WorkflowTemplate::factory()->team()->create(['team_id' => $team->id]);

    expect($template->team_id)->toBe($team->id)
        ->and($template->origin)->toBe(TemplateOrigin::Team);
});

test('a template belongs to its team and creator', function () {
    $user = User::factory()->create();
    $template = WorkflowTemplate::factory()->team()->create(['created_by' => $user->id]);

    expect($template->team)->not->toBeNull()
        ->and($template->creator->is($user))->toBeTrue();
});

test('visibleFor returns system templates and the given team templates only', function () {
    $system = WorkflowTemplate::factory()->system()->create();
    [$user, $team] = teamWithMember();
    $mine = WorkflowTemplate::factory()->team()->create(['team_id' => $team->id]);
    $foreign = WorkflowTemplate::factory()->team()->create();

    $visible = WorkflowTemplate::query()->visibleFor($team)->get()->pluck('id');

    expect($visible)->toContain($system->id)
        ->and($visible)->toContain($mine->id)
        ->and($visible)->not->toContain($foreign->id);
});

test('visibleFor stays combinable with a key lookup', function () {
    $system = WorkflowTemplate::factory()->system()->create();
    [$user, $team] = teamWithMember();
    WorkflowTemplate::factory()->team()->create();

    $resolved = WorkflowTemplate::query()->visibleFor($team)->whereKey($system->id)->first();

    expect($resolved)->not->toBeNull()
        ->and($resolved->is($system))->toBeTrue();
});

test('the factory default graph is a valid minimal payload', function () {
    $template = WorkflowTemplate::factory()->create();

    $graph = $template->graph;

    expect($graph)->toHaveKeys(['nodes', 'edges'])
        ->and($graph['nodes'])->toHaveCount(2)
        ->and($graph['nodes'][0])->toHaveKeys(['key', 'type', 'name', 'config', 'positionX', 'positionY'])
        ->and($graph['nodes'][0]['type'])->toBe('trigger.manual')
        ->and($graph['edges'])->toHaveCount(1)
        ->and($graph['edges'][0])->toHaveKeys(['sourceNodeKey', 'targetNodeKey', 'sourceHandle']);
});
