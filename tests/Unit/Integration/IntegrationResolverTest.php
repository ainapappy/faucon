<?php

use App\Models\Integration;
use App\Services\Integration\IntegrationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('find returns null for a null id', function () {
    expect((new IntegrationResolver)->find(null))->toBeNull();
});

test('find returns null for an empty id', function () {
    expect((new IntegrationResolver)->find(''))->toBeNull();
});

test('find returns null for an unknown id', function () {
    expect((new IntegrationResolver)->find('999999'))->toBeNull();
});

test('find returns the integration for an existing id', function () {
    $integration = Integration::factory()->genericHttp()->create();

    $resolved = (new IntegrationResolver)->find((string) $integration->id);

    expect($resolved)->toBeInstanceOf(Integration::class)
        ->and($resolved?->id)->toBe($integration->id);
});
