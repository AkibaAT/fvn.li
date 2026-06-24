<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\VersionRouteEdge;
use App\Models\VersionRouteLabel;
use App\Models\VersionRouteMenuChoice;
use App\Models\VersionRouteVariableChange;
use App\Services\RouteGraphService;
use Illuminate\Database\Eloquent\Model;

uses()->group('route-graph');

beforeEach(function () {
    config()->set('scout.driver', 'null');

    $this->originalEventDispatcher = Model::getEventDispatcher();
    Model::unsetEventDispatcher();

    $this->game = Game::withoutEvents(fn () => Game::factory()->create([
        'name' => 'Route Graph Test',
        'slug' => 'route-graph-test',
    ]));

    $this->version = GameVersion::withoutEvents(fn () => GameVersion::factory()->create([
        'game_id' => $this->game->id,
        'version' => '1.0.0',
    ]));
});

afterEach(function () {
    if ($this->originalEventDispatcher) {
        Model::setEventDispatcher($this->originalEventDispatcher);
    }
});

function createRouteLabel(GameVersion $version, string $name, int $line = 1, bool $isEnding = false): VersionRouteLabel
{
    return VersionRouteLabel::create([
        'game_version_id' => $version->id,
        'name' => $name,
        'file_path' => 'game/script.rpy',
        'line_number' => $line,
        'is_ending' => $isEnding,
    ]);
}

function createRouteChoice(
    GameVersion $version,
    string $from,
    string $text,
    int $line,
    ?string $target = null,
    ?string $condition = 'True',
    ?string $prompt = null,
    ?int $menuLine = null,
    ?string $enclosingCondition = null,
    ?string $choiceCondition = null,
    ?string $menuBranch = null,
    int $parentMenuLine = 0,
    int $parentChoiceLine = 0,
    array $menuConditionStack = [],
    ?string $edgeType = null
): VersionRouteMenuChoice {
    $choiceCondition ??= $condition;
    $effectiveCondition = $enclosingCondition && $choiceCondition && $choiceCondition !== 'True'
        ? '(' . $enclosingCondition . ') and (' . $choiceCondition . ')'
        : ($enclosingCondition ?: $choiceCondition);

    return VersionRouteMenuChoice::create([
        'game_version_id' => $version->id,
        'from_label' => $from,
        'prompt' => $prompt,
        'menu_line' => $menuLine ?? $line,
        'text' => $text,
        'condition' => $effectiveCondition,
        'enclosing_condition' => $enclosingCondition,
        'choice_condition' => $choiceCondition,
        'menu_branch' => $menuBranch,
        'menu_condition_stack' => $menuConditionStack,
        'parent_menu_line' => $parentMenuLine,
        'parent_choice_line' => $parentChoiceLine,
        'target_label' => $target,
        'edge_type' => $target ? ($edgeType ?? 'jump') : null,
        'file_path' => 'game/script.rpy',
        'line_number' => $line,
    ]);
}

function createRouteEdge(GameVersion $version, string $from, string $to, string $type = 'flow', int $line = 1, ?string $condition = null): VersionRouteEdge
{
    return VersionRouteEdge::create([
        'game_version_id' => $version->id,
        'from_label' => $from,
        'to_label' => $to,
        'edge_type' => $type,
        'condition' => $condition,
        'file_path' => 'game/script.rpy',
        'line_number' => $line,
    ]);
}

function createRouteVariableChange(
    GameVersion $version,
    string $label,
    string $context,
    string $variable = 'route_flag',
    string $value = 'Constant(value=True)',
    int $line = 1,
    ?string $condition = null,
    array $conditionStack = [],
    string $operation = '='
): VersionRouteVariableChange {
    return VersionRouteVariableChange::create([
        'game_version_id' => $version->id,
        'label' => $label,
        'variable_name' => $variable,
        'operation' => $operation,
        'value' => $value,
        'file_path' => 'game/script.rpy',
        'line_number' => $line,
        'context' => $context,
        'condition' => $condition,
        'condition_stack' => $conditionStack,
    ]);
}

test('buildGraph refreshes stale cached graph revisions', function () {
    createRouteLabel($this->version, 'start');

    $this->version->route_graph_data = [
        'graph_revision' => 1,
        'has_graph_data' => true,
        'nodes' => [['id' => 'stale']],
        'edges' => [],
    ];
    $this->version->saveQuietly();

    $graph = app(RouteGraphService::class)->buildGraph($this->version->fresh());

    expect($graph['graph_revision'])->toBe(28)
        ->and(collect($graph['nodes'])->pluck('id'))->toContain('start')
        ->and(collect($graph['nodes'])->pluck('id'))->not->toContain('stale')
        ->and($this->version->fresh()->route_graph_data['graph_revision'])->toBe(28);
});

test('developer gated route choices are excluded while production developer false paths remain', function () {
    createRouteLabel($this->version, 'start', 1);
    createRouteLabel($this->version, 'introcutscene', 20, true);
    createRouteLabel($this->version, 'debug_jump_table', 40);

    createRouteEdge($this->version, 'start', 'introcutscene', 'jump', 10, 'config.developer == False');
    createRouteChoice($this->version, 'start', 'Debug menu', 30, 'debug_jump_table', 'not ((config.developer == False))');
    createRouteEdge($this->version, 'start', 'debug_jump_table', 'menu_choice', 30, 'not ((config.developer == False))');

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $edges = collect($graph['edges']);
    $nodesById = collect($graph['nodes'])->keyBy('id');

    expect($nodesById)->toHaveKey('start')
        ->and($nodesById)->toHaveKey('introcutscene')
        ->and($nodesById)->not->toHaveKey('debug_jump_table')
        ->and($nodesById->keys()->filter(fn (string $id) => str_starts_with($id, 'start:choice_'))->count())->toBe(0)
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'start' && $edge['target'] === 'introcutscene'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'start' && $edge['target'] === 'debug_jump_table'))->toBeFalse();
});

test('computed route graphs include precomputed GraphViz layout positions', function () {
    createRouteLabel($this->version, 'start');
    createRouteLabel($this->version, 'good_end', 20, true);
    createRouteEdge($this->version, 'start', 'good_end', 'jump', 10, 'route_flag');

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $layoutNodes = $graph['layout']['nodes'];

    expect($graph['layout']['engine'])->toBe('graphviz-dot')
        ->and($graph['layout']['width'])->toBeGreaterThan(0)
        ->and($graph['layout']['height'])->toBeGreaterThan(0)
        ->and($layoutNodes)->toHaveKey('start')
        ->and($layoutNodes)->toHaveKey('good_end')
        ->and($layoutNodes)->toHaveKey('condition:start:if%20route_flag');

    foreach ($graph['nodes'] as $node) {
        expect($layoutNodes[$node['id']]['x'])->toBeNumeric()
            ->and($layoutNodes[$node['id']]['y'])->toBeNumeric()
            ->and($layoutNodes[$node['id']]['width'])->toBeGreaterThan(0)
            ->and($layoutNodes[$node['id']]['height'])->toBeGreaterThan(0);
    }
});

test('layout includes missing target condition nodes for unresolved edges', function () {
    createRouteLabel($this->version, 'start');
    createRouteEdge($this->version, 'start', 'missing_target', 'jump', 10);

    $graph = app(RouteGraphService::class)->computeGraph($this->version);

    expect(collect($graph['nodes'])->firstWhere('id', 'missing_target')['is_unresolved'] ?? false)->toBeTrue()
        ->and($graph['layout']['nodes'])->toHaveKey('condition:start:missing%20target');
});

test('layout merges duplicate condition nodes from the same source', function () {
    createRouteLabel($this->version, 'start');
    createRouteLabel($this->version, 'branch_a');
    createRouteLabel($this->version, 'branch_b');
    createRouteEdge($this->version, 'start', 'branch_a', 'jump', 10, 'route_flag');
    createRouteEdge($this->version, 'start', 'branch_b', 'jump', 20, 'route_flag');

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $conditionNodes = collect(array_keys($graph['layout']['nodes']))
        ->filter(fn (string $nodeId) => $nodeId === 'condition:start:if%20route_flag')
        ->values();

    expect($conditionNodes)->toHaveCount(1);
});

test('localized chapter select menus collapse to hub nodes instead of expanding every choice', function () {
    createRouteLabel($this->version, 'chapitreselec', 100);

    for ($i = 1; $i <= 12; $i++) {
        $target = 'chapitre' . $i;
        createRouteLabel($this->version, $target, 100 + $i);
        createRouteChoice($this->version, 'chapitreselec', 'Chapitre ' . $i, 120, $target, 'routea');
        createRouteEdge($this->version, 'chapitreselec', $target, 'menu_choice', 120, 'routea');
        createRouteEdge($this->version, 'chapitreselec', $target, 'jump', 121);
    }

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $nodesById = collect($graph['nodes'])->keyBy('id');

    expect($nodesById['chapitreselec']['node_type'])->toBe('hub')
        ->and($nodesById->keys()->contains(fn (string $id) => str_starts_with($id, 'chapitreselec:choice_')))->toBeFalse();
});

test('targetless choices only use continuation edges from their own menu segment', function () {
    createRouteLabel($this->version, 'setup', 1);
    createRouteLabel($this->version, 'after_second_menu', 30);

    createRouteChoice($this->version, 'setup', 'Yes', 10);
    createRouteChoice($this->version, 'setup', 'No', 10);
    createRouteChoice($this->version, 'setup', 'A', 20);
    createRouteChoice($this->version, 'setup', 'B', 20);

    createRouteVariableChange($this->version, 'setup', 'menu_choice:Yes', 'route_flag', line: 11);
    createRouteVariableChange($this->version, 'setup', 'menu_choice:No', 'route_flag', line: 12);
    createRouteVariableChange($this->version, 'setup', 'menu_choice:A', 'route_flag', line: 21);
    createRouteVariableChange($this->version, 'setup', 'menu_choice:B', 'route_flag', line: 22);

    createRouteEdge($this->version, 'setup', 'after_second_menu', 'flow', 25);

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $edges = collect($graph['edges']);
    $nodesById = collect($graph['nodes'])->keyBy('id');

    expect($edges->contains(fn (array $edge) => $edge['source'] === 'setup:choice_0' && $edge['target'] === 'after_second_menu'))->toBeFalse()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'setup:choice_2' && $edge['target'] === 'after_second_menu'))->toBeTrue()
        ->and($nodesById['setup']['variable_changes'])->toBe([])
        ->and($nodesById['setup:choice_0']['variable_changes'])->toHaveCount(1)
        ->and($nodesById['setup:choice_0']['variable_changes'][0]['variable'])->toBe('route_flag');
});

test('pure flavor targetless choices are hidden from route graph', function () {
    createRouteLabel($this->version, 'scene', 1);
    createRouteLabel($this->version, 'after_menu', 100);

    createRouteChoice($this->version, 'scene', 'Hold his hand.', 10, prompt: 'He offered his hand.', menuLine: 9);
    createRouteChoice($this->version, 'scene', 'Change subject.', 20, prompt: 'He offered his hand.', menuLine: 9);
    createRouteEdge($this->version, 'scene', 'after_menu', 'jump', 90);

    $graph = app(RouteGraphService::class)->computeGraph($this->version);

    expect(collect($graph['nodes'])->pluck('label'))->not->toContain('Hold his hand.')
        ->and(collect($graph['nodes'])->pluck('label'))->not->toContain('Change subject.');
});

test('targetless choices with route state changes can use later compatible label exits', function () {
    createRouteLabel($this->version, 'scene', 1);
    createRouteLabel($this->version, 'route_exit', 100);
    createRouteLabel($this->version, 'other_exit', 110);

    createRouteChoice($this->version, 'scene', 'Hold his hand.', 10, condition: 'lionroute == True', prompt: 'He offered his hand.', menuLine: 9);
    createRouteChoice($this->version, 'scene', 'Follow quietly.', 20, condition: 'lionroute == True', prompt: 'He offered his hand.', menuLine: 9);
    createRouteChoice($this->version, 'scene', 'Ask about dinner.', 40, prompt: 'Later question.', menuLine: 39);
    createRouteChoice($this->version, 'scene', 'Stay quiet.', 50, prompt: 'Later question.', menuLine: 39);
    createRouteVariableChange($this->version, 'scene', 'menu_choice:Hold his hand.', 'lionlove', line: 11);
    createRouteVariableChange($this->version, 'scene', 'menu_choice:Follow quietly.', 'lionroute_seen', line: 21);
    createRouteEdge($this->version, 'scene', 'route_exit', 'jump', 90, 'lionroute == True');
    createRouteEdge($this->version, 'scene', 'other_exit', 'jump', 95, 'bearroute == True');

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $edges = collect($graph['edges']);

    expect($edges->contains(fn (array $edge) => $edge['source'] === 'scene:choice_0' && $edge['target'] === 'route_exit'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene:choice_1' && $edge['target'] === 'route_exit'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene:choice_0' && $edge['target'] === 'other_exit'))->toBeFalse()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene:choice_1' && $edge['target'] === 'other_exit'))->toBeFalse()
        ->and($edges->first(fn (array $edge) => $edge['source'] === 'scene' && $edge['target'] === 'scene:choice_0')['condition'])->toBe('lionroute == True')
        ->and($edges->first(fn (array $edge) => $edge['source'] === 'scene:choice_0' && $edge['target'] === 'route_exit')['condition'])->toBeNull();
});

test('multiple menu prompts in one label are kept as separate menu nodes', function () {
    createRouteLabel($this->version, 'scene', 1);
    createRouteLabel($this->version, 'route_exit', 200);

    createRouteChoice($this->version, 'scene', 'Medicine', 10, condition: 'True', prompt: 'What does Roswell need...?', menuLine: 9);
    createRouteChoice($this->version, 'scene', 'Tea.', 20, condition: 'True', prompt: 'What does Roswell need...?', menuLine: 9);
    createRouteVariableChange($this->version, 'scene', 'menu_choice:Medicine', 'medicine_seen', 'Constant(value=True)', 11);
    createRouteVariableChange($this->version, 'scene', 'menu_choice:Tea.', 'tea_seen', 'Constant(value=True)', 21);

    createRouteChoice($this->version, 'scene', 'Movie?', 100, condition: 'True', prompt: 'What are we watching?', menuLine: 99, enclosingCondition: 'lionroute == True');
    createRouteChoice($this->version, 'scene', 'You can pick.', 110, condition: 'True', prompt: 'What are we watching?', menuLine: 99, enclosingCondition: 'lionroute == True');
    createRouteVariableChange($this->version, 'scene', 'menu_choice:You can pick.', 'lionlove', 'Constant(value=1)', 111);
    createRouteEdge($this->version, 'scene', 'route_exit', 'jump', 150, 'lionroute == True');

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $nodes = collect($graph['nodes'])->keyBy('id');
    $edges = collect($graph['edges']);

    expect($nodes['scene:menu_10']['menu_prompt'])->toBe('What does Roswell need...?')
        ->and($nodes['scene:menu_100']['menu_prompt'])->toBe('What are we watching?')
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene' && $edge['target'] === 'scene:menu_100'))->toBeFalse()
        ->and($edges->first(fn (array $edge) => $edge['source'] === 'scene:choice_0' && $edge['target'] === 'scene:menu_100')['condition'])->toBe('lionroute == True')
        ->and($edges->first(fn (array $edge) => $edge['source'] === 'scene:choice_1' && $edge['target'] === 'scene:menu_100')['condition'])->toBe('lionroute == True')
        ->and($edges->first(fn (array $edge) => $edge['source'] === 'scene:menu_100' && $edge['target'] === 'scene:choice_3')['condition'])->toBeNull();
});

test('conditional single menus keep their menu entry condition visible', function () {
    createRouteLabel($this->version, 'scene', 1);
    createRouteLabel($this->version, 'trust', 100);
    createRouteLabel($this->version, 'doubt', 110);

    createRouteChoice($this->version, 'scene', 'Trust Jack.', 10, 'trust', 'True', menuLine: 9, enclosingCondition: 'OzPast2 == True');
    createRouteChoice($this->version, 'scene', 'Doubt Jack.', 20, 'doubt', 'True', menuLine: 9, enclosingCondition: 'OzPast2 == True');
    createRouteEdge($this->version, 'scene', 'trust', 'jump', 50, 'not ((OzPast2 == True))');

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $nodes = collect($graph['nodes'])->keyBy('id');
    $edges = collect($graph['edges']);

    expect($nodes)->toHaveKey('scene')
        ->and($nodes)->toHaveKey('scene:menu_10')
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene' && $edge['target'] === 'scene:menu_10' && $edge['condition'] === 'OzPast2 == True'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene' && $edge['target'] === 'trust' && $edge['condition'] === 'not ((OzPast2 == True))'))->toBeTrue()
        ->and($edges->first(fn (array $edge) => $edge['source'] === 'scene:menu_10' && $edge['target'] === 'scene:choice_0')['condition'])->toBeNull();
});

test('menus in sibling conditional branches keep separate entry conditions', function () {
    createRouteLabel($this->version, 'scene', 1);
    createRouteLabel($this->version, 'lunch', 100);

    createRouteChoice($this->version, 'scene', 'Dean.', 10, condition: 'True', prompt: 'First branch?', menuLine: 9, enclosingCondition: 'boarroute == True or bearroute == True', menuBranch: 'if:20:0');
    createRouteChoice($this->version, 'scene', 'Roswell.', 20, condition: 'True', prompt: 'First branch?', menuLine: 9, enclosingCondition: 'boarroute == True or bearroute == True', menuBranch: 'if:20:0');
    createRouteVariableChange($this->version, 'scene', 'menu_choice:Dean.', 'dean_seen', 'Constant(value=True)', 11);
    createRouteEdge($this->version, 'scene', 'lunch', 'jump', 30, 'boarroute == True or bearroute == True');

    createRouteChoice($this->version, 'scene', 'Tyson.', 50, condition: 'True', prompt: 'Second branch?', menuLine: 49, enclosingCondition: 'lionroute == True or wolfroute == True', menuBranch: 'if:40:0');
    createRouteChoice($this->version, 'scene', 'Hoss.', 60, condition: 'True', prompt: 'Second branch?', menuLine: 49, enclosingCondition: 'lionroute == True or wolfroute == True', menuBranch: 'if:40:0');
    createRouteVariableChange($this->version, 'scene', 'menu_choice:Tyson.', 'tyson_seen', 'Constant(value=True)', 51);
    createRouteEdge($this->version, 'scene', 'lunch', 'jump', 80, 'lionroute == True or wolfroute == True');

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $edges = collect($graph['edges']);

    expect($edges->contains(fn (array $edge) => $edge['source'] === 'scene' && $edge['target'] === 'scene:menu_10' && $edge['condition'] === 'boarroute == True or bearroute == True'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene' && $edge['target'] === 'scene:menu_50' && $edge['condition'] === 'lionroute == True or wolfroute == True'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene:choice_0' && $edge['target'] === 'scene:menu_50'))->toBeFalse()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene:choice_1' && $edge['target'] === 'scene:menu_50'))->toBeFalse();
});

test('conditioned menu choices do not borrow the enclosing branch else exit', function () {
    createRouteLabel($this->version, 'scene', 1);
    createRouteLabel($this->version, 'lunch', 100);
    createRouteLabel($this->version, 'scene:ending', 110, true);

    $branchCondition = 'crocroute == True or dragonroute == True';

    createRouteChoice($this->version, 'scene', 'Sal', 10, condition: 'True', prompt: 'Cook with...?', menuLine: 9, enclosingCondition: $branchCondition, menuBranch: 'if:5:0');
    createRouteChoice($this->version, 'scene', 'Orlando', 20, condition: 'True', prompt: 'Cook with...?', menuLine: 9, enclosingCondition: $branchCondition, menuBranch: 'if:5:0');
    createRouteVariableChange($this->version, 'scene', 'menu_choice:Sal', 'croclove', 'Constant(value=1)', 11);
    createRouteVariableChange($this->version, 'scene', 'menu_choice:Orlando', 'dragonlove', 'Constant(value=1)', 21);

    createRouteEdge($this->version, 'scene', 'lunch', 'jump', 100, $branchCondition);
    createRouteEdge($this->version, 'scene', 'scene:ending', 'return', 110, 'not ((' . $branchCondition . '))');

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $edges = collect($graph['edges']);

    expect($edges->contains(fn (array $edge) => $edge['source'] === 'scene:choice_0' && $edge['target'] === 'lunch'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene:choice_1' && $edge['target'] === 'lunch'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene:choice_0' && $edge['target'] === 'scene:ending'))->toBeFalse()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene:choice_1' && $edge['target'] === 'scene:ending'))->toBeFalse();
});

test('targetless choices do not repeat weaker continuation conditions already implied by the menu gate', function () {
    createRouteLabel($this->version, 'scene', 1);
    createRouteLabel($this->version, 'dinner', 100);

    $menuCondition = '(dragonroute == True) and (SENTENCE == True)';

    createRouteChoice($this->version, 'scene', 'Yes.', 10, 'dinner', condition: 'True', prompt: 'Follow Orlando?', menuLine: 9, enclosingCondition: $menuCondition, menuBranch: 'if:5:0');
    createRouteChoice($this->version, 'scene', 'No.', 20, condition: 'True', prompt: 'Follow Orlando?', menuLine: 9, enclosingCondition: $menuCondition, menuBranch: 'if:5:0');
    createRouteVariableChange($this->version, 'scene', 'menu_choice:Yes.', 'SENTENCE2', 'Constant(value=True)', 11);
    createRouteEdge($this->version, 'scene', 'dinner', 'jump', 15, $menuCondition);
    createRouteEdge($this->version, 'scene', 'dinner', 'flow', 50, 'dragonroute == True');

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $edges = collect($graph['edges']);

    expect($edges->first(fn (array $edge) => $edge['source'] === 'scene:choice_1' && $edge['target'] === 'dinner')['condition'])->toBeNull();
});

test('nested menu condition stacks share outer condition nodes with bypass flow', function () {
    createRouteLabel($this->version, 'scene', 1);
    createRouteLabel($this->version, 'dinner', 100);

    $stack = ['dragonroute == True', 'SENTENCE == True'];
    $menuCondition = '(dragonroute == True) and (SENTENCE == True)';

    createRouteChoice($this->version, 'scene', 'Yes.', 10, 'dinner', condition: 'True', prompt: 'Follow Orlando?', menuLine: 9, enclosingCondition: $menuCondition, menuBranch: 'if:5:0/if:8:0', menuConditionStack: $stack);
    createRouteChoice($this->version, 'scene', 'No.', 20, condition: 'True', prompt: 'Follow Orlando?', menuLine: 9, enclosingCondition: $menuCondition, menuBranch: 'if:5:0/if:8:0', menuConditionStack: $stack);
    createRouteVariableChange($this->version, 'scene', 'menu_choice:Yes.', 'SENTENCE2', 'Constant(value=True)', 11);
    createRouteEdge($this->version, 'scene', 'dinner', 'flow', 50, 'dragonroute == True');

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $nodes = collect($graph['nodes']);
    $edges = collect($graph['edges']);
    $outerConditionNode = $nodes->first(fn (array $node) => ($node['node_type'] ?? null) === 'condition' && ($node['label'] ?? null) === 'if dragonroute == True');
    $innerConditionNode = $nodes->first(fn (array $node) => ($node['node_type'] ?? null) === 'condition' && ($node['label'] ?? null) === 'if SENTENCE == True');

    expect($outerConditionNode)->not->toBeNull()
        ->and($innerConditionNode)->not->toBeNull()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene' && $edge['target'] === $outerConditionNode['id'] && $edge['condition'] === null))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === $outerConditionNode['id'] && $edge['target'] === $innerConditionNode['id'] && $edge['condition'] === null))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === $innerConditionNode['id'] && $edge['target'] === 'scene:menu_10' && $edge['condition'] === null))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === $outerConditionNode['id'] && $edge['target'] === 'dinner' && $edge['condition'] === null))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene' && $edge['target'] === 'scene:menu_10'))->toBeFalse();
});

test('nested menus use their parent choice instead of splitting sibling choices', function () {
    createRouteLabel($this->version, 'scene', 1);

    createRouteChoice($this->version, 'scene', 'First.', 10, condition: 'True', prompt: 'Pick one.', menuLine: 9, enclosingCondition: 'route_a == True', menuBranch: 'if:5:0');
    createRouteChoice($this->version, 'scene', 'Second.', 50, condition: 'True', prompt: 'Pick one.', menuLine: 9, enclosingCondition: 'route_a == True', menuBranch: 'if:5:0');
    createRouteVariableChange($this->version, 'scene', 'menu_choice:First.', 'first_seen', 'Constant(value=True)', 11);

    createRouteChoice($this->version, 'scene', 'Yes', 20, condition: 'True', prompt: 'Nested?', menuLine: 19, enclosingCondition: '(route_a == True) and (extra == True)', menuBranch: 'if:5:0/menu:9:choice:10/if:18:0', parentMenuLine: 9, parentChoiceLine: 10);
    createRouteChoice($this->version, 'scene', 'No', 30, condition: 'True', prompt: 'Nested?', menuLine: 19, enclosingCondition: '(route_a == True) and (extra == True)', menuBranch: 'if:5:0/menu:9:choice:10/if:18:0', parentMenuLine: 9, parentChoiceLine: 10);
    createRouteVariableChange($this->version, 'scene', 'menu_choice:Yes', 'yes_seen', 'Constant(value=True)', 21);

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $nodes = collect($graph['nodes'])->keyBy('id');
    $edges = collect($graph['edges']);

    expect($nodes)->toHaveKey('scene:menu_10')
        ->and($nodes)->toHaveKey('scene:menu_20')
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene:menu_10' && $edge['target'] === 'scene:choice_0'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene:menu_10' && $edge['target'] === 'scene:choice_1'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene:choice_0' && $edge['target'] === 'scene:menu_20' && $edge['condition'] === '(route_a == True) and (extra == True)'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene:choice_1' && $edge['target'] === 'scene:menu_20'))->toBeFalse();
});

test('nested targetless menus keep their parent menu and resume the outer menu sequence', function () {
    createRouteLabel($this->version, 'Day5Dinner', 1880);
    createRouteLabel($this->version, 'startday6', 2600);

    createRouteChoice($this->version, 'Day5Dinner', 'Dean', 1980, condition: 'True', prompt: 'Who to sit next to?', menuLine: 1977);
    createRouteChoice($this->version, 'Day5Dinner', 'Tyson', 2073, condition: 'True', prompt: 'Who to sit next to?', menuLine: 1977);

    createRouteChoice($this->version, 'Day5Dinner', 'Get Closer', 1987, condition: 'True', prompt: 'Whoops!', menuLine: 1985, enclosingCondition: 'bearroute == True', menuBranch: 'menu:1977:choice:1980/if:1984:0', parentMenuLine: 1977, parentChoiceLine: 1980, menuConditionStack: ['bearroute == True']);
    createRouteChoice($this->version, 'Day5Dinner', 'Retreat', 2011, condition: 'True', prompt: 'Whoops!', menuLine: 1985, enclosingCondition: 'bearroute == True', menuBranch: 'menu:1977:choice:1980/if:1984:0', parentMenuLine: 1977, parentChoiceLine: 1980, menuConditionStack: ['bearroute == True']);
    createRouteVariableChange($this->version, 'Day5Dinner', 'menu_choice:Get Closer', 'bearlove', 'Constant(value=1)', 1988);

    createRouteChoice($this->version, 'Day5Dinner', 'Horror', 2182, condition: 'True', prompt: 'What movie should we watch?', menuLine: 2179);
    createRouteChoice($this->version, 'Day5Dinner', 'Comedy', 2195, condition: 'True', prompt: 'What movie should we watch?', menuLine: 2179);
    createRouteVariableChange($this->version, 'Day5Dinner', 'menu_choice:Comedy', 'bearlove', 'Constant(value=2)', 2196);

    createRouteEdge($this->version, 'Day5Dinner', 'startday6', 'jump', 2439, 'bearroute == True');

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $nodes = collect($graph['nodes'])->keyBy('id');
    $edges = collect($graph['edges']);

    expect($nodes)->toHaveKey('Day5Dinner:menu_1980')
        ->and($nodes['Day5Dinner:menu_1980']['menu_prompt'])->toBe('Who to sit next to?')
        ->and($nodes)->toHaveKey('Day5Dinner:menu_1987')
        ->and($nodes['Day5Dinner:menu_1987']['menu_prompt'])->toBe('Whoops!')
        ->and($nodes)->toHaveKey('Day5Dinner:menu_2182')
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'Day5Dinner' && $edge['target'] === 'Day5Dinner:menu_1987'))->toBeFalse()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'Day5Dinner:choice_0' && $edge['target'] === 'Day5Dinner:menu_1987' && $edge['condition'] === 'bearroute == True'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'Day5Dinner:choice_0' && $edge['target'] === 'Day5Dinner:menu_2182' && $edge['condition'] === 'not ((bearroute == True))'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'Day5Dinner:choice_2' && $edge['target'] === 'Day5Dinner:menu_2182'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'Day5Dinner:choice_2' && $edge['target'] === 'startday6'))->toBeFalse();
});

test('day five dinner keeps sequential menus and final kiss gate in script order', function () {
    createRouteLabel($this->version, 'Day5Dinner', 1880);
    createRouteLabel($this->version, 'startday6', 2600);

    createRouteChoice($this->version, 'Day5Dinner', 'Dean', 1980, condition: 'True', prompt: 'Who to sit next to?', menuLine: 1977);
    createRouteChoice($this->version, 'Day5Dinner', 'Sal', 2034, condition: 'crocroute == True or dragonroute == True', prompt: 'Who to sit next to?', menuLine: 1977);
    createRouteChoice($this->version, 'Day5Dinner', 'Tyson', 2073, condition: 'True', prompt: 'Who to sit next to?', menuLine: 1977);
    createRouteChoice($this->version, 'Day5Dinner', 'Hoss', 2114, condition: 'True', prompt: 'Who to sit next to?', menuLine: 1977);

    createRouteChoice($this->version, 'Day5Dinner', 'Get Closer', 1987, condition: 'True', prompt: 'Whoops!', menuLine: 1985, enclosingCondition: 'bearroute == True', menuBranch: 'menu:1977:choice:1980/if:1984:0', parentMenuLine: 1977, parentChoiceLine: 1980, menuConditionStack: ['bearroute == True']);
    createRouteChoice($this->version, 'Day5Dinner', 'Retreat', 2011, condition: 'True', prompt: 'Whoops!', menuLine: 1985, enclosingCondition: 'bearroute == True', menuBranch: 'menu:1977:choice:1980/if:1984:0', parentMenuLine: 1977, parentChoiceLine: 1980, menuConditionStack: ['bearroute == True']);
    createRouteVariableChange($this->version, 'Day5Dinner', 'menu_choice:Get Closer', 'bearlove', 'Num(n=1)', 1987, 'bearroute == True', ['bearroute == True'], '+=');

    createRouteVariableChange($this->version, 'Day5Dinner', 'menu_choice:Sal', 'croclove', 'Num(n=1)', 2045, '(crocroute == True or dragonroute == True) and (crocroute == True)', ['crocroute == True'], '+=');
    createRouteVariableChange($this->version, 'Day5Dinner', 'menu_choice:Tyson', 'wolflove', 'Num(n=1)', 2080, 'wolfroute == True', ['wolfroute == True'], '+=');
    createRouteVariableChange($this->version, 'Day5Dinner', 'menu_choice:Hoss', 'lionlove', 'Num(n=1)', 2122, 'lionroute == True', ['lionroute == True'], '+=');

    createRouteChoice($this->version, 'Day5Dinner', 'Horror', 2182, condition: 'True', prompt: 'What movie should we watch?', menuLine: 2179);
    createRouteChoice($this->version, 'Day5Dinner', 'Comedy', 2195, condition: 'True', prompt: 'What movie should we watch?', menuLine: 2179);
    createRouteChoice($this->version, 'Day5Dinner', 'Action', 2210, condition: 'True', prompt: 'What movie should we watch?', menuLine: 2179);
    createRouteChoice($this->version, 'Day5Dinner', 'Romance', 2220, condition: 'True', prompt: 'What movie should we watch?', menuLine: 2179);
    createRouteVariableChange($this->version, 'Day5Dinner', 'menu_choice:Horror', 'MovieType', "Str(s='Horror')", 2192);
    createRouteVariableChange($this->version, 'Day5Dinner', 'menu_choice:Horror', 'wolflove', 'Num(n=1)', 2193, operation: '+=');
    createRouteVariableChange($this->version, 'Day5Dinner', 'menu_choice:Comedy', 'MovieType', "Str(s='Comedy')", 2203);
    createRouteVariableChange($this->version, 'Day5Dinner', 'menu_choice:Comedy', 'bearlove', 'Num(n=2)', 2204, operation: '+=');
    createRouteVariableChange($this->version, 'Day5Dinner', 'menu_choice:Action', 'MovieType', "Str(s='Action')", 2213);
    createRouteVariableChange($this->version, 'Day5Dinner', 'menu_choice:Action', 'croclove', 'Num(n=2)', 2214, operation: '+=');

    createRouteChoice($this->version, 'Day5Dinner', 'Hold his hand', 2228, condition: 'True', prompt: "I didn't register it happening at the time, but I at some point became aware of Dean's hand touching mine.", menuLine: 2226, enclosingCondition: 'bearroute == True', menuBranch: 'menu:2179:choice:2220/if:2224:0', parentMenuLine: 2179, parentChoiceLine: 2220, menuConditionStack: ['bearroute == True']);
    createRouteChoice($this->version, 'Day5Dinner', 'Do nothing', 2232, condition: 'True', prompt: "I didn't register it happening at the time, but I at some point became aware of Dean's hand touching mine.", menuLine: 2226, enclosingCondition: 'bearroute == True', menuBranch: 'menu:2179:choice:2220/if:2224:0', parentMenuLine: 2179, parentChoiceLine: 2220, menuConditionStack: ['bearroute == True']);
    createRouteVariableChange($this->version, 'Day5Dinner', 'menu_choice:Hold his hand', 'bearlove', 'Num(n=1)', 2228, 'bearroute == True', ['bearroute == True'], '+=');
    createRouteVariableChange($this->version, 'Day5Dinner', 'menu_choice:Romance', 'wolflove', 'Num(n=1)', 2238, 'wolfroute == True', ['wolfroute == True'], '+=');
    createRouteVariableChange($this->version, 'Day5Dinner', 'menu_choice:Romance', 'MovieType', "Str(s='Romance')", 2240);

    $kissCondition = '((bearroute == True) and (MovieType == "Romance")) and (bearlove >= 5)';
    createRouteChoice($this->version, 'Day5Dinner', 'Yes', 2362, condition: 'True', prompt: 'Kiss Dean?', menuLine: 2359, enclosingCondition: $kissCondition, menuBranch: 'if:2271:0/if:2345:0/if:2355:0', menuConditionStack: ['bearroute == True', 'MovieType == "Romance"', 'bearlove >= 5']);
    createRouteChoice($this->version, 'Day5Dinner', 'No', 2377, condition: 'True', prompt: 'Kiss Dean?', menuLine: 2359, enclosingCondition: $kissCondition, menuBranch: 'if:2271:0/if:2345:0/if:2355:0', menuConditionStack: ['bearroute == True', 'MovieType == "Romance"', 'bearlove >= 5']);
    createRouteVariableChange($this->version, 'Day5Dinner', 'menu_choice:Yes', 'DeanKiss', "Name(id='True', ctx=Load())", 2362, $kissCondition, ['bearroute == True', 'MovieType == "Romance"', 'bearlove >= 5']);

    createRouteEdge($this->version, 'Day5Dinner', 'startday6', 'jump', 2439, 'bearroute == True');
    createRouteEdge($this->version, 'Day5Dinner', 'startday6', 'jump', 2522, 'boarroute == True');
    createRouteEdge($this->version, 'Day5Dinner', 'startday6', 'jump', 2531, 'not ((boarroute == True))');

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $nodes = collect($graph['nodes'])->keyBy('id');
    $edges = collect($graph['edges']);

    expect($nodes['Day5Dinner:menu_1980']['menu_prompt'])->toBe('Who to sit next to?')
        ->and($nodes['Day5Dinner:menu_2182']['menu_prompt'])->toBe('What movie should we watch?')
        ->and($nodes['Day5Dinner:menu_2362']['menu_prompt'])->toBe('Kiss Dean?')
        ->and($nodes['Day5Dinner:choice_1']['var_summary'])->toBe('if crocroute == True: croclove += 1')
        ->and($nodes['Day5Dinner:choice_2']['var_summary'])->toBe('if wolfroute == True: wolflove += 1')
        ->and($nodes['Day5Dinner:choice_3']['var_summary'])->toBe('if lionroute == True: lionlove += 1')
        ->and($nodes['Day5Dinner:choice_9']['var_summary'])->toBe("if wolfroute == True: wolflove += 1, MovieType = 'Romance'")
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'Day5Dinner' && $edge['target'] === 'Day5Dinner:menu_2362'))->toBeFalse()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'Day5Dinner:choice_9' && $edge['target'] === 'Day5Dinner:menu_2228' && $edge['condition'] === 'bearroute == True'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'Day5Dinner:choice_10' && $edge['target'] !== 'startday6'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'Day5Dinner' && $edge['target'] === 'startday6'))->toBeFalse()
        ->and($edges->first(fn (array $edge) => $edge['target'] === 'Day5Dinner:menu_2362')['source'])->not->toBe('Day5Dinner');
});

test('sequential repeated menu choices only inherit variable changes from their own block', function () {
    createRouteLabel($this->version, 'scene', 1);

    createRouteChoice($this->version, 'scene', 'No', 10, condition: 'True', prompt: 'First question?', menuLine: 9, enclosingCondition: 'crocroute == True');
    createRouteChoice($this->version, 'scene', 'Yes', 20, condition: 'True', prompt: 'First question?', menuLine: 9, enclosingCondition: 'crocroute == True');
    createRouteChoice($this->version, 'scene', 'No', 40, condition: 'True', prompt: 'Second question?', menuLine: 39, enclosingCondition: 'crocroute == True');
    createRouteChoice($this->version, 'scene', 'Yes', 50, condition: 'True', prompt: 'Second question?', menuLine: 39, enclosingCondition: 'crocroute == True');
    createRouteVariableChange($this->version, 'scene', 'menu_choice:No', 'first_seen', 'Constant(value=True)', 11);
    createRouteVariableChange($this->version, 'scene', 'menu_choice:Yes', 'croclove', 'Constant(value=1)', 51);

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $nodes = collect($graph['nodes'])->keyBy('id');
    $edges = collect($graph['edges']);

    expect($nodes)->toHaveKey('scene:menu_10')
        ->and($nodes)->toHaveKey('scene:menu_40')
        ->and($nodes['scene:choice_0']['choice_text'])->toBe('No')
        ->and($nodes['scene:choice_0']['variable_changes'])->toHaveCount(1)
        ->and($nodes['scene:choice_1']['variable_changes'])->toHaveCount(0)
        ->and($nodes['scene:choice_2']['variable_changes'])->toHaveCount(0)
        ->and($nodes['scene:choice_3']['variable_changes'])->toHaveCount(1)
        ->and(collect($graph['nodes'])->filter(fn (array $node) => str_starts_with((string) ($node['var_summary'] ?? ''), 'croclove =')))->toHaveCount(1)
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene' && $edge['target'] === 'scene:menu_10' && $edge['condition'] === 'crocroute == True'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene' && $edge['target'] === 'scene:menu_40'))->toBeFalse()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene:choice_0' && $edge['target'] === 'scene:menu_40' && $edge['condition'] === null))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene:choice_1' && $edge['target'] === 'scene:menu_40' && $edge['condition'] === null))->toBeTrue();
});

test('unconditioned targetless flavor choices do not borrow later conditional exits', function () {
    createRouteLabel($this->version, 'scene', 1);
    createRouteLabel($this->version, 'route_exit', 100);

    createRouteChoice($this->version, 'scene', 'Medicine', 10, condition: 'True', prompt: 'What does Roswell need...?', menuLine: 9);
    createRouteChoice($this->version, 'scene', 'Tea.', 20, condition: 'True', prompt: 'What does Roswell need...?', menuLine: 9);
    createRouteEdge($this->version, 'scene', 'route_exit', 'jump', 80, 'boarroute == True');

    $graph = app(RouteGraphService::class)->computeGraph($this->version);

    expect(collect($graph['nodes'])->pluck('label'))->not->toContain('Medicine')
        ->and(collect($graph['edges'])->contains(fn (array $edge) => str_contains($edge['source'], ':choice_')))->toBeFalse();
});

test('targetless choices on ending labels route to a synthetic ending node', function () {
    createRouteLabel($this->version, 'start', 1, true);

    createRouteChoice($this->version, 'start', 'Sword', 10, menuLine: 9);
    createRouteChoice($this->version, 'start', 'Axe', 20, menuLine: 9);
    createRouteVariableChange($this->version, 'start', 'menu_choice:Sword', 'sword', 'Constant(value=1)', 11);
    createRouteVariableChange($this->version, 'start', 'menu_choice:Axe', 'axe', 'Constant(value=1)', 21);

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $edges = collect($graph['edges']);
    $nodesById = collect($graph['nodes'])->keyBy('id');

    expect($graph['endings'])->toBe(['start:ending'])
        ->and($nodesById)->toHaveKey('start:ending')
        ->and($nodesById['start']['is_ending'])->toBeFalse()
        ->and($nodesById['start:ending']['is_ending'])->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'start:choice_0' && $edge['target'] === 'start:ending' && $edge['edge_type'] === 'return'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'start:choice_1' && $edge['target'] === 'start:ending' && $edge['edge_type'] === 'return'))->toBeTrue();
});

test('targetless choices with continuation edges are hidden without route effects', function () {
    createRouteLabel($this->version, 'town', 1);
    createRouteLabel($this->version, 'nap', 40);

    createRouteChoice($this->version, 'town', 'Look around', 10, menuLine: 9);
    createRouteChoice($this->version, 'town', 'Sleep it off', 30, menuLine: 9);
    createRouteEdge($this->version, 'town', 'nap', 'jump', 35);

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $edges = collect($graph['edges']);
    $nodesById = collect($graph['nodes'])->keyBy('id');

    expect($nodesById)->not->toHaveKey('town:choice_0')
        ->and($nodesById)->not->toHaveKey('town:choice_1')
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'town' && $edge['target'] === 'nap'))->toBeTrue();
});

test('disconnected route components are not bridged into false start paths', function () {
    createRouteLabel($this->version, 'start', 1, true);
    createRouteLabel($this->version, 'unreachable_intro', 20);
    createRouteLabel($this->version, 'unreachable_end', 30, true);
    createRouteEdge($this->version, 'unreachable_intro', 'unreachable_end', 'jump', 25);

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $edges = collect($graph['edges']);
    $nodesById = collect($graph['nodes'])->keyBy('id');

    expect($edges->contains(fn (array $edge) => str_ends_with($edge['id'], ':bridge')))->toBeFalse()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'start' && $edge['target'] === 'unreachable_intro'))->toBeFalse()
        ->and($nodesById)->toHaveKey('start')
        ->and($nodesById)->not->toHaveKey('unreachable_intro')
        ->and($nodesById)->not->toHaveKey('unreachable_end')
        ->and($graph['endings'])->toBe(['start']);
});

test('route graph endpoint only includes unreachable script nodes for admins and game owners', function () {
    $this->game->update(['itch_id' => 12345]);
    createRouteLabel($this->version, 'start', 1, true);
    createRouteLabel($this->version, 'unfinished_intro', 20);
    createRouteLabel($this->version, 'unfinished_end', 30, true);
    createRouteEdge($this->version, 'unfinished_intro', 'unfinished_end', 'jump', 25);

    $url = route('browser-api.games.version.route-graph', [
        'game' => $this->game->slug,
        'version' => $this->version->id,
    ]) . '?include_unreachable=1';

    $this->getJson($url)
        ->assertOk()
        ->assertJsonPath('includes_unreachable', false)
        ->assertJsonMissing(['id' => 'unfinished_intro']);

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)
        ->getJson($url)
        ->assertOk()
        ->assertJsonPath('includes_unreachable', true)
        ->assertJsonFragment(['id' => 'unfinished_intro'])
        ->assertJsonFragment(['id' => 'unfinished_end']);

    $owner = User::factory()->create();
    SocialAccount::factory()->create([
        'user_id' => $owner->id,
        'provider_name' => 'itchio',
        'itchio_game_ids' => [12345],
    ]);

    $this->actingAs($owner)
        ->getJson($url)
        ->assertOk()
        ->assertJsonPath('includes_unreachable', true)
        ->assertJsonFragment(['id' => 'unfinished_intro']);
});

test('repeated choice text in separate menu blocks scopes variable changes by menu line', function () {
    createRouteLabel($this->version, 'outsideguild', 804);
    createRouteLabel($this->version, 'towntime', 829);
    createRouteLabel($this->version, 'domenic_route', 914);
    createRouteLabel($this->version, 'postoffice', 920);

    createRouteChoice($this->version, 'outsideguild', 'Play along.', 809, 'towntime', 'tcrashout1 == 1');
    createRouteChoice($this->version, 'outsideguild', "I'm not wasting my time.", 809, 'domenic_route', 'tcrashout1 == 1');
    createRouteChoice($this->version, 'outsideguild', 'Play along.', 820, 'towntime', 'not ((tcrashout1 == 1))');
    createRouteChoice($this->version, 'outsideguild', "I'm not wasting my time.", 820, 'postoffice', 'not ((tcrashout1 == 1))');

    createRouteVariableChange($this->version, 'outsideguild', 'menu_choice:Play along.', 'oceal_affection', 'Constant(value=1)', 811);
    createRouteVariableChange($this->version, 'outsideguild', 'menu_choice:Play along.', 'oceal_affection', 'Constant(value=1)', 822);

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $playChoices = collect($graph['nodes'])
        ->filter(fn (array $node) => ($node['parent_label'] ?? null) === 'outsideguild' && ($node['choice_text'] ?? null) === 'Play along.')
        ->values();

    expect($playChoices)->toHaveCount(2)
        ->and($playChoices[0]['variable_changes'])->toHaveCount(1)
        ->and($playChoices[1]['variable_changes'])->toHaveCount(1);
});

test('menu choice block jumps are not kept as extra label level routes', function () {
    createRouteLabel($this->version, 'chapter', 1);
    createRouteLabel($this->version, 'branch_a', 100);
    createRouteLabel($this->version, 'branch_b', 200);

    createRouteChoice($this->version, 'chapter', 'A', 10, 'branch_a', 'flag == 1');
    createRouteChoice($this->version, 'chapter', 'B', 20, 'branch_b', 'not ((flag == 1))');

    createRouteEdge($this->version, 'chapter', 'branch_a', 'jump', 45);
    createRouteEdge($this->version, 'chapter', 'branch_b', 'jump', 95);

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $edges = collect($graph['edges']);

    expect($edges->contains(fn (array $edge) => $edge['source'] === 'chapter' && $edge['target'] === 'branch_a'))->toBeFalse()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'chapter' && $edge['target'] === 'branch_b'))->toBeFalse()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'chapter:choice_0' && $edge['target'] === 'branch_a'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'chapter:choice_1' && $edge['target'] === 'branch_b'))->toBeTrue();
});

test('menu choices targeting inline labels suppress duplicate label-level flow edges', function () {
    createRouteLabel($this->version, 'startday6', 1);
    createRouteLabel($this->version, 'Day6VaultVisit', 316);
    createRouteLabel($this->version, 'day6morning', 339);

    $elseCondition = 'not ((boarroute == True))';

    createRouteChoice($this->version, 'startday6', 'Visit the Vault.', 316, 'Day6VaultVisit', condition: 'True', prompt: 'After tossing and turning...', menuLine: 313, enclosingCondition: $elseCondition, menuBranch: 'if:21:1', menuConditionStack: [$elseCondition], edgeType: 'label');
    createRouteChoice($this->version, 'startday6', 'Stay in Bed.', 323, condition: 'True', prompt: 'After tossing and turning...', menuLine: 313, enclosingCondition: $elseCondition, menuBranch: 'if:21:1', menuConditionStack: [$elseCondition]);
    createRouteEdge($this->version, 'startday6', 'day6morning', 'jump', 305, 'boarroute == True');
    createRouteEdge($this->version, 'startday6', 'Day6VaultVisit', 'menu_choice', 316, $elseCondition);
    createRouteEdge($this->version, 'startday6', 'Day6VaultVisit', 'flow', 316, $elseCondition);
    createRouteEdge($this->version, 'startday6', 'day6morning', 'jump', 339, $elseCondition);

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $edges = collect($graph['edges']);

    expect($edges->contains(fn (array $edge) => $edge['source'] === 'startday6' && $edge['target'] === 'Day6VaultVisit'))->toBeFalse()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'startday6:choice_0' && $edge['target'] === 'Day6VaultVisit' && $edge['edge_type'] === 'choice_target'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'startday6:choice_1' && $edge['target'] === 'day6morning'))->toBeTrue();
});

test('missing route targets are surfaced as unresolved nodes', function () {
    createRouteLabel($this->version, 'start');
    createRouteEdge($this->version, 'start', 'missing_label', 'jump', 10);

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $nodesById = collect($graph['nodes'])->keyBy('id');

    expect($nodesById)->toHaveKey('missing_label')
        ->and($nodesById['missing_label']['is_unresolved'])->toBeTrue();
});

test('real labels referenced as source or target are never marked as unresolved', function () {
    // Regression guard: only nodes that genuinely lack a `label` statement in
    // the original script may be flagged dangling. A label referenced by edges
    // (even only as a target, or only as a source) is a real node and must not
    // be mislabeled as unresolved.
    createRouteLabel($this->version, 'start');
    createRouteLabel($this->version, 'middle'); // referenced as both source and target
    createRouteLabel($this->version, 'sink'); // referenced only as a target
    createRouteLabel($this->version, 'source_only'); // referenced only as a source
    createRouteEdge($this->version, 'start', 'middle', 'jump', 5);
    createRouteEdge($this->version, 'middle', 'sink', 'jump', 10);
    createRouteEdge($this->version, 'source_only', 'middle', 'jump', 15);

    $graph = app(RouteGraphService::class)->computeGraph($this->version, includeUnreachable: true);
    $nodesById = collect($graph['nodes'])->keyBy('id');

    foreach (['start', 'middle', 'sink', 'source_only'] as $realLabel) {
        expect($nodesById)->toHaveKey($realLabel)
            ->and($nodesById[$realLabel]['is_unresolved'] ?? false)->toBeFalse();
    }

    $anyRealLabelFlagged = collect($graph['nodes'])
        ->contains(fn (array $node) => ! empty($node['is_unresolved']) && in_array($node['id'], ['start', 'middle', 'sink', 'source_only'], true));

    expect($anyRealLabelFlagged)->toBeFalse();
});

test('high fan in trivial return helpers are collapsed out of the playable route graph', function () {
    createRouteLabel($this->version, 'start');
    createRouteLabel($this->version, 'scene_a');
    createRouteLabel($this->version, 'scene_b');
    createRouteLabel($this->version, 'scene_c');
    createRouteLabel($this->version, 'next_a');
    createRouteLabel($this->version, 'next_b');
    createRouteLabel($this->version, 'next_c');
    createRouteLabel($this->version, 'random_animation')->forceFill([
        'returns_to_caller' => true,
    ])->save();
    createRouteVariableChange($this->version, 'random_animation', 'label_block', 'chosen_animation', "Call(func=Attribute(value=Attribute(value=Name(id='renpy', ctx=Load()), attr='random', ctx=Load()), attr='choice', ctx=Load()))");

    createRouteEdge($this->version, 'start', 'scene_a');
    createRouteEdge($this->version, 'scene_a', 'scene_b');
    createRouteEdge($this->version, 'scene_b', 'scene_c');
    createRouteEdge($this->version, 'scene_a', 'random_animation', 'call', 10);
    createRouteEdge($this->version, 'scene_b', 'random_animation', 'call', 20);
    createRouteEdge($this->version, 'scene_c', 'random_animation', 'call', 30);
    createRouteEdge($this->version, 'random_animation', 'chosen_animation', 'call', 31);
    createRouteEdge($this->version, 'scene_a', 'next_a', 'jump', 11);
    createRouteEdge($this->version, 'scene_b', 'next_b', 'jump', 21);
    createRouteEdge($this->version, 'scene_c', 'next_c', 'jump', 31);

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $nodes = collect($graph['nodes'])->keyBy('id');
    $edges = collect($graph['edges']);

    expect($nodes)->not->toHaveKey('random_animation')
        ->and($nodes)->not->toHaveKey('chosen_animation')
        ->and($edges->contains(fn (array $edge) => $edge['target'] === 'random_animation'))->toBeFalse()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'random_animation'))->toBeFalse()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'scene_a' && $edge['target'] === 'next_a'))->toBeTrue();
});

test('menu choice targets are rewritten when trivial return helpers are collapsed', function () {
    createRouteLabel($this->version, 'menu_start');
    createRouteLabel($this->version, 'caller_a');
    createRouteLabel($this->version, 'caller_b');
    createRouteLabel($this->version, 'caller_c');
    createRouteLabel($this->version, 'real_continuation');
    createRouteLabel($this->version, 'return_helper')->forceFill([
        'returns_to_caller' => true,
    ])->save();

    createRouteChoice($this->version, 'menu_start', 'Use helper path', 20, 'return_helper');
    createRouteEdge($this->version, 'menu_start', 'return_helper', 'menu_choice', 20);
    createRouteEdge($this->version, 'caller_a', 'return_helper', 'call', 10);
    createRouteEdge($this->version, 'caller_b', 'return_helper', 'call', 11);
    createRouteEdge($this->version, 'caller_c', 'return_helper', 'call', 12);
    createRouteEdge($this->version, 'return_helper', 'real_continuation', 'jump', 13, 'helper_ready');

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $edges = collect($graph['edges']);

    expect($edges->contains(fn (array $edge) => $edge['source'] === 'menu_start:choice_0' && $edge['target'] === 'return_helper'))->toBeFalse()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'menu_start:choice_0' && $edge['target'] === 'real_continuation' && $edge['edge_type'] === 'choice_target' && $edge['condition'] === 'helper_ready'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['target'] === 'return_helper'))->toBeFalse();
});

test('expanded labels keep earlier returns to ending labels', function () {
    createRouteLabel($this->version, 'chapter', 1);
    createRouteLabel($this->version, 'chapter:ending', 20, true);
    createRouteLabel($this->version, 'next_chapter', 60);
    createRouteLabel($this->version, 'chapter_select', 61);

    createRouteChoice($this->version, 'chapter', 'Play scene', 50, condition: 'routev');
    createRouteVariableChange($this->version, 'chapter', 'menu_choice:Play scene', 'scene_seen', line: 51);

    createRouteEdge($this->version, 'chapter', 'chapter:ending', 'return', 20, 'routea');
    createRouteEdge($this->version, 'chapter', 'next_chapter', 'jump', 60, 'routev');
    createRouteEdge($this->version, 'chapter', 'chapter_select', 'flow', 61);

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $edges = collect($graph['edges']);

    expect($edges->contains(fn (array $edge) => $edge['source'] === 'chapter' && $edge['target'] === 'chapter:ending'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'chapter:choice_0' && $edge['target'] === 'next_chapter'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'chapter' && $edge['target'] === 'next_chapter'))->toBeFalse();
});

test('parallel conditional edges keep unique ids', function () {
    createRouteLabel($this->version, 'chapter');
    createRouteLabel($this->version, 'ending', isEnding: true);

    createRouteEdge($this->version, 'chapter', 'ending', 'jump', 10, 'routea');
    createRouteEdge($this->version, 'chapter', 'ending', 'jump', 20, 'routem');

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $edges = collect($graph['edges'])->where('source', 'chapter')->where('target', 'ending')->values();

    expect($edges)->toHaveCount(2)
        ->and($edges->pluck('condition')->all())->toBe(['routea', 'routem'])
        ->and($edges->pluck('id')->unique())->toHaveCount(2);
});

test('multi target conditional branches keep fallthrough else condition', function () {
    createRouteLabel($this->version, 'chapter');
    createRouteLabel($this->version, 'route_a');
    createRouteLabel($this->version, 'route_b');
    createRouteLabel($this->version, 'fallback');

    createRouteEdge($this->version, 'chapter', 'route_a', 'jump', 10, 'routea');
    createRouteEdge($this->version, 'chapter', 'route_b', 'jump', 20, 'routem');
    createRouteEdge($this->version, 'chapter', 'fallback', 'flow', 30);

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $fallbackEdge = collect($graph['edges'])->first(fn (array $edge) => $edge['source'] === 'chapter' && $edge['target'] === 'fallback');

    expect($fallbackEdge)->not->toBeNull()
        ->and($fallbackEdge['condition'])->toBe('not ((routea) or (routem))');
});

test('unconditional jumps suppress later adjacent label fallthrough edges', function () {
    createRouteLabel($this->version, 'Lockerroomshowergo', 947);
    createRouteLabel($this->version, 'afterchoice5', 966);
    createRouteLabel($this->version, 'afterchoice6', 1027);

    createRouteEdge($this->version, 'Lockerroomshowergo', 'afterchoice6', 'jump', 962);
    createRouteEdge($this->version, 'Lockerroomshowergo', 'afterchoice5', 'flow', 966);

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $edges = collect($graph['edges'])->where('source', 'Lockerroomshowergo');
    $node = collect($graph['nodes'])->firstWhere('id', 'Lockerroomshowergo');

    expect($edges->contains(fn (array $edge) => $edge['target'] === 'afterchoice6' && $edge['edge_type'] === 'jump'))->toBeTrue()
        ->and($edges->contains(fn (array $edge) => $edge['target'] === 'afterchoice5'))->toBeFalse()
        ->and($node['outgoing_count'])->toBe(1);
});

test('menu choice jumps do not suppress later targetless continuation choices', function () {
    createRouteLabel($this->version, 'start', 1);
    createRouteLabel($this->version, 'explore', 10);
    createRouteLabel($this->version, 'vault', 40);
    createRouteLabel($this->version, 'after_exploring', 100);

    createRouteEdge($this->version, 'start', 'explore', 'jump', 5);

    createRouteChoice($this->version, 'explore', 'Explore Basement.', 20, 'vault', 'basementnotexplored');
    createRouteEdge($this->version, 'explore', 'vault', 'menu_choice', 20, 'basementnotexplored');
    createRouteEdge($this->version, 'explore', 'vault', 'jump', 30);

    createRouteChoice($this->version, 'explore', 'Finish Exploring.', 90);
    createRouteEdge($this->version, 'explore', 'after_exploring', 'flow', 100);

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $edges = collect($graph['edges']);
    $nodesById = collect($graph['nodes'])->keyBy('id');

    expect($nodesById)->toHaveKey('after_exploring')
        ->and($nodesById)->toHaveKey('explore:choice_1')
        ->and($nodesById['explore:choice_1']['choice_text'])->toBe('Finish Exploring.')
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'explore:choice_1' && $edge['target'] === 'after_exploring'))->toBeTrue();
});

test('empty input retry self loops are hidden from route graph flow', function () {
    createRouteLabel($this->version, 'start', 1);
    createRouteLabel($this->version, 'coffeename', 10);
    createRouteLabel($this->version, 'waitforfriends', 200);

    createRouteEdge($this->version, 'start', 'coffeename', 'jump', 5);
    createRouteEdge($this->version, 'coffeename', 'coffeename', 'jump', 7, 'coffeename == ""');
    createRouteEdge($this->version, 'coffeename', 'waitforfriends', 'jump', 203);

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $edges = collect($graph['edges'])->where('source', 'coffeename')->values();

    expect($edges)->toHaveCount(1)
        ->and($edges[0]['target'])->toBe('waitforfriends')
        ->and($edges[0]['condition'])->toBeNull();
});

test('literal true conditions are omitted from graph output', function () {
    createRouteLabel($this->version, 'start', 1);
    createRouteLabel($this->version, 'menu', 10);
    createRouteLabel($this->version, 'next', 30);

    createRouteEdge($this->version, 'start', 'menu', 'jump', 5);
    createRouteChoice($this->version, 'menu', 'Continue', 20, 'next', 'True');
    createRouteEdge($this->version, 'menu', 'next', 'menu_choice', 20, 'True');

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $choiceNode = collect($graph['nodes'])->firstWhere('id', 'menu:choice_0');
    $choiceEdge = collect($graph['edges'])->first(fn (array $edge) => $edge['source'] === 'menu' && $edge['target'] === 'menu:choice_0');

    expect($choiceNode['condition'])->toBeNull()
        ->and($choiceEdge['condition'])->toBeNull();
});
