<?php

declare(strict_types=1);

it('normalizes custom text assignment character ids before they become stats keys', function () {
    $script = file_get_contents(base_path('resources/renpy/json_stats.rpy'));

    expect($script)->toContain('def normalize_dialogue_character(character_id, cleaned_text):');

    $customAssignmentPattern = '/cleaned_text = clean_text\(literal_value\).*?' .
        'if cleaned_text:.*?' .
        'character_id, cleaned_text = normalize_dialogue_character\(target\.id, cleaned_text\).*?' .
        'custom_text_assignments\.append\(\{\s*' .
        '"character": character_id,/s';

    expect(preg_match($customAssignmentPattern, $script))->toBe(1);
});

it('processes ordinary say nodes even when TranslateSay is unavailable', function () {
    $script = file_get_contents(base_path('resources/renpy/json_stats.rpy'));

    expect($script)->not->toContain('elif has_translate_say and isinstance(node, renpy.ast.Say):')
        ->and($script)->toContain('elif isinstance(node, renpy.ast.Say):');

    $sayBranchPattern = '/elif isinstance\(node, renpy\.ast\.Say\):\s*' .
        'if has_translate_say and isinstance\(node, renpy\.ast\.TranslateSay\) and node\.language:/s';

    expect(preg_match($sayBranchPattern, $script))->toBe(1);
});
