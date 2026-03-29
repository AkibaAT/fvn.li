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

function createRouteChoice(GameVersion $version, string $from, string $text, int $line, ?string $target = null, ?string $condition = 'True'): VersionRouteMenuChoice
{
    return VersionRouteMenuChoice::create([
        'game_version_id' => $version->id,
        'from_label' => $from,
        'text' => $text,
        'condition' => $condition,
        'target_label' => $target,
        'edge_type' => $target ? 'jump' : null,
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

function createRouteVariableChange(GameVersion $version, string $label, string $context, string $variable = 'route_flag', string $value = 'Constant(value=True)', int $line = 1): VersionRouteVariableChange
{
    return VersionRouteVariableChange::create([
        'game_version_id' => $version->id,
        'label' => $label,
        'variable_name' => $variable,
        'operation' => '=',
        'value' => $value,
        'file_path' => 'game/script.rpy',
        'line_number' => $line,
        'context' => $context,
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

    expect($graph['graph_revision'])->toBe(11)
        ->and(collect($graph['nodes'])->pluck('id'))->toContain('start')
        ->and(collect($graph['nodes'])->pluck('id'))->not->toContain('stale')
        ->and($this->version->fresh()->route_graph_data['graph_revision'])->toBe(11);
});

test('localized chapter select menus collapse to hub nodes instead of expanding every choice', function () {
    createRouteLabel($this->version, 'chapitreselec', 100);

    for ($i = 1; $i <= 12; $i++) {
        $target = 'chapitre'.$i;
        createRouteLabel($this->version, $target, 100 + $i);
        createRouteChoice($this->version, 'chapitreselec', 'Chapitre '.$i, 120, $target, 'routea');
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

    foreach (['Yes', 'No', 'A', 'B'] as $choice) {
        createRouteVariableChange($this->version, 'setup', 'menu_choice:'.$choice, 'route_flag');
    }

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

test('targetless choices on ending labels route to a synthetic ending node', function () {
    createRouteLabel($this->version, 'start', 1, true);

    createRouteChoice($this->version, 'start', 'Sword', 10);
    createRouteChoice($this->version, 'start', 'Axe', 20);
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

test('targetless choices with continuation edges are meaningful even without variable changes', function () {
    createRouteLabel($this->version, 'town', 1);
    createRouteLabel($this->version, 'nap', 40);

    createRouteChoice($this->version, 'town', 'Look around', 10);
    createRouteChoice($this->version, 'town', 'Sleep it off', 30);
    createRouteEdge($this->version, 'town', 'nap', 'jump', 35);

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $edges = collect($graph['edges']);
    $nodesById = collect($graph['nodes'])->keyBy('id');

    expect($nodesById)->toHaveKey('town:choice_0')
        ->and($nodesById['town:choice_0']['choice_text'])->toBe('Sleep it off')
        ->and($edges->contains(fn (array $edge) => $edge['source'] === 'town:choice_0' && $edge['target'] === 'nap'))->toBeTrue()
        ->and($nodesById->has('town:choice_1'))->toBeFalse();
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

    $url = route('react-api.games.version.route-graph', [
        'game' => $this->game->slug,
        'version' => $this->version->id,
    ]).'?include_unreachable=1';

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

test('missing route targets are surfaced as unresolved nodes', function () {
    createRouteLabel($this->version, 'start');
    createRouteEdge($this->version, 'start', 'missing_label', 'jump', 10);

    $graph = app(RouteGraphService::class)->computeGraph($this->version);
    $nodesById = collect($graph['nodes'])->keyBy('id');

    expect($nodesById)->toHaveKey('missing_label')
        ->and($nodesById['missing_label']['is_unresolved'])->toBeTrue();
});

test('expanded labels keep earlier returns to ending labels', function () {
    createRouteLabel($this->version, 'chapter', 1);
    createRouteLabel($this->version, 'chapter:ending', 20, true);
    createRouteLabel($this->version, 'next_chapter', 60);
    createRouteLabel($this->version, 'chapter_select', 61);

    createRouteChoice($this->version, 'chapter', 'Play scene', 50, condition: 'routev');
    createRouteVariableChange($this->version, 'chapter', 'menu_choice:Play scene', 'scene_seen');

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
