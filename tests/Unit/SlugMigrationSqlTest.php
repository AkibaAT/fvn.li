<?php

function simulateSlugMigrationSlug(string $name, int $id, array $existingSlugs = []): string
{
    $baseSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
    $baseSlug = trim($baseSlug, '-');

    if ($baseSlug === '') {
        $baseSlug = 'game-'.$id;
    }

    $newSlug = $baseSlug;
    $counter = 1;

    while (in_array($newSlug, $existingSlugs, true)) {
        $newSlug = $baseSlug.'-'.$counter;
        $counter++;
    }

    return $newSlug;
}

it('falls back to a stable id slug when a game name has no slug characters', function () {
    $migration = file_get_contents(base_path('database/migrations/2025_11_17_195709_fix_null_game_slugs_and_add_constraint.php'));

    expect($migration)
        ->toContain("IF base_slug IS NULL OR base_slug = '' THEN")
        ->toContain("base_slug := 'game-' || p_id::TEXT;");

    expect(simulateSlugMigrationSlug('日本語!!!', 42))->toBe('game-42');
    expect(simulateSlugMigrationSlug('日本語!!!', 42, ['game-42']))->toBe('game-42-1');
});
