<?php

declare(strict_types=1);

use App\Services\RouteGraphMenuSequencer;

function sequencerGroup(int $menuLine, int $endLine, ?string $menuBranch = null, int $parentChoiceLine = 0): array
{
    return [
        'menu_line' => $menuLine,
        'start_line' => $menuLine,
        'end_line' => $endLine,
        'menu_branch' => $menuBranch,
        'parent_choice_line' => $parentChoiceLine,
        'choices' => [],
    ];
}

it('appends at the chain tail instead of overwriting links claimed by the root fallback', function () {
    // menu A (root); `if y:` menu B; menu C (root). The root fallback chains
    // A→B, so C must append after B — overwriting A's next-link would orphan
    // B (a prev-link nothing points to) and get it deleted as unreachable.
    $groups = [
        sequencerGroup(10, 20),
        sequencerGroup(25, 30, 'if:24:0'),
        sequencerGroup(40, 50),
    ];

    [$previous, $next] = (new RouteGraphMenuSequencer)->menuSequenceLinks($groups, collect());

    expect($previous)->toBe([1 => 0, 2 => 1])
        ->and($next)->toBe([0 => 1, 1 => 2]);
});

it('keeps the previous and next link maps bijective', function () {
    $groups = [
        sequencerGroup(10, 20),
        sequencerGroup(25, 30, 'if:24:0'),
        sequencerGroup(40, 50),
        sequencerGroup(55, 60, 'if:54:0'),
        sequencerGroup(70, 80),
    ];

    [$previous, $next] = (new RouteGraphMenuSequencer)->menuSequenceLinks($groups, collect());

    expect(count($previous))->toBe(count($next));
    foreach ($previous as $index => $previousIndex) {
        expect($next[$previousIndex] ?? null)->toBe($index);
    }
});

it('leaves a group unlinked when the chain tail does not precede it in line order', function () {
    // The chain tail (B) ends after C starts, so C cannot legally follow it;
    // C stays unlinked and falls back to a direct label entry edge.
    $groups = [
        sequencerGroup(10, 20),
        sequencerGroup(25, 90, 'if:24:0'),
        sequencerGroup(40, 50),
    ];

    [$previous, $next] = (new RouteGraphMenuSequencer)->menuSequenceLinks($groups, collect());

    expect($previous)->toBe([1 => 0])
        ->and($next)->toBe([0 => 1]);
});
