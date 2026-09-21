<?php

namespace App\Http\Controllers\Templates;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\WorkflowTemplate;
use App\Services\Workflow\NodeCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowTemplateController extends Controller
{
    /**
     * Display the template gallery: the system templates plus the current
     * team's own — never another team's. Order by id: the seed order is the
     * display order (featured card = first system template).
     */
    public function index(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [WorkflowTemplate::class, $currentTeam]);

        $templates = WorkflowTemplate::query()
            ->visibleFor($currentTeam)
            ->orderBy('id')
            ->get()
            ->map(fn (WorkflowTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'category' => $template->category,
                'origin' => $template->origin->value,
                'nodesCount' => count($template->graph['nodes'] ?? []),
                'graph' => $this->previewGraph($template->graph),
            ])
            ->all();

        return Inertia::render('templates/Index', [
            'templates' => $templates,
            'nodeTypes' => NodeCatalog::all(),
            'permissions' => $request->user()->toTeamPermissions($currentTeam),
        ]);
    }

    /**
     * Project the stored graph to the preview-only shape consumed by the
     * front `buildPreviewLayout` (nodes key/type/name/position, edges
     * source/target) — node configs and edge handles never leave the server.
     *
     * Positions pass through as stored: the `graph` column is JSON (no
     * scalar cast), the builder stores whole numbers, and the gallery
     * contract pins them strictly — a float coercion would rewrite 100 as
     * 100.0 on the wire and inflate the payload instead of shrinking it.
     *
     * @param  array{nodes: list<array{key: string, type: string, name: string, config: array<string, mixed>, positionX: int|float, positionY: int|float}>, edges: list<array{sourceNodeKey: string, targetNodeKey: string, sourceHandle: string|null}>}  $graph
     * @return array{nodes: list<array{key: string, type: string, name: string, positionX: int|float, positionY: int|float}>, edges: list<array{sourceNodeKey: string, targetNodeKey: string}>}
     */
    private function previewGraph(array $graph): array
    {
        return [
            'nodes' => array_map(
                fn (array $node): array => [
                    'key' => $node['key'],
                    'type' => $node['type'],
                    'name' => $node['name'],
                    'positionX' => $node['positionX'],
                    'positionY' => $node['positionY'],
                ],
                $graph['nodes'],
            ),
            'edges' => array_map(
                fn (array $edge): array => [
                    'sourceNodeKey' => $edge['sourceNodeKey'],
                    'targetNodeKey' => $edge['targetNodeKey'],
                ],
                $graph['edges'],
            ),
        ];
    }
}
