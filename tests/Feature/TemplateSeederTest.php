<?php

use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Services\Workflow\WorkflowGraphMapper;
use App\Services\Workflow\WorkflowValidator;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the seeder creates the three system templates with fixed names and categories', function () {
    $this->seed(TemplateSeeder::class);

    $templates = WorkflowTemplate::query()->get();

    expect($templates)->toHaveCount(3);

    $expected = [
        'Support client prioritaire' => 'Support',
        'Traitement de leads (IA)' => 'Ventes',
        'Veille du matin' => 'Données',
    ];

    foreach ($expected as $name => $category) {
        $template = WorkflowTemplate::query()->where('name', $name)->firstOrFail();

        expect($template->category)->toBe($category)
            ->and($template->team_id)->toBeNull()
            ->and($template->origin->value)->toBe('system')
            ->and($template->description)->not->toBeNull()
            ->and($template->graph['nodes'])->not->toBeEmpty()
            ->and($template->graph['edges'])->not->toBeEmpty();
    }
});

test('each seeded template passes the workflow validator', function () {
    $this->seed(TemplateSeeder::class);

    $validator = app(WorkflowValidator::class);

    foreach (WorkflowTemplate::query()->get() as $template) {
        [$nodes, $edges] = WorkflowGraphMapper::fromSnapshot($template->graph);

        expect($validator->validate($nodes, $edges))->toBe([]);
    }
});

test('replaying the seeder duplicates nothing and keeps the graphs', function () {
    $this->seed(TemplateSeeder::class);

    $graphsBefore = WorkflowTemplate::query()->orderBy('id')->get()->map(fn (WorkflowTemplate $t) => $t->graph)->all();
    $countBefore = WorkflowTemplate::query()->count();

    $this->seed(TemplateSeeder::class);

    $graphsAfter = WorkflowTemplate::query()->orderBy('id')->get()->map(fn (WorkflowTemplate $t) => $t->graph)->all();

    expect(WorkflowTemplate::query()->count())->toBe($countBefore)
        ->and($graphsAfter)->toBe($graphsBefore)
        ->and($countBefore)->toBe(3);
});

test('the full database seeder runs and the gallery serves the three templates', function () {
    $this->seed();

    expect(WorkflowTemplate::query()->whereNull('team_id')->count())->toBe(3);

    $user = User::query()->where('email', DemoUserSeeder::DemoEmail)->firstOrFail();

    $this->actingAs($user)
        ->get(route('templates.index', ['current_team' => DemoUserSeeder::TeamSlug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('templates/Index', false)
            ->has('templates', 3)
            ->where('templates.0.name', 'Support client prioritaire')
        );
});
