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

it('processes mixed Translate blocks and TranslateSay nodes without default-language duplicates', function () {
    $script = file_get_contents(base_path('resources/renpy/json_stats.rpy'));

    expect($script)
        ->not->toContain('has_translate_say')
        ->toContain('translated_block_say_ids.add(id(stmt))')
        ->toContain('if isinstance(node, renpy.ast.Translate):')
        ->toContain('elif isinstance(node, renpy.ast.Say) and id(node) not in translated_block_say_ids:')
        ->toContain('and isinstance(node, renpy.ast.TranslateSay)')
        ->toContain('record_say(lang, node)');
});

it('keeps old RenPy string and prompt translations in route maps', function () {
    $script = file_get_contents(base_path('resources/renpy/json_stats.rpy'));

    expect($script)
        ->not->toContain('renpy.version_tuple >= (8, 0, 0, 0)')
        ->toContain('from renpy.translation import translate_string as renpy_translate_string')
        ->toContain('return renpy_translate_string(text, language=language)')
        ->toContain('source_say_identifiers[id(stmt)] = ident')
        ->toContain('translated_language_say_ids.add(id(stmt))')
        ->toContain('and id(node) not in translated_language_say_ids')
        ->toContain('last_say_identifier = source_say_identifiers.get(id(node), getattr(node, "identifier", None))');
});

it('keeps runtime hint availability conditions on inferred hub edges', function () {
    $script = file_get_contents(base_path('resources/renpy/json_stats.rpy'));

    expect($script)
        ->toContain('"add_wyatt_hint": ("BUDDY_SYSTEM", "BUDDY_SYSTEM_HINTS")')
        ->toContain('"add_howie_hint": ("HOWIE_SYSTEM", "HOWIE_SYSTEM_HINTS")')
        ->toContain('hint_route_condition(func_name, label_name)')
        ->toContain('"condition": condition')
        ->toContain('condition=value.get("condition")');
});

it('keeps the active area condition on dynamically included examine screen edges', function () {
    $script = file_get_contents(base_path('resources/renpy/json_stats.rpy'));

    expect($script)
        ->toContain('screen_action_conditions = collections.defaultdict(dict)')
        ->toContain('screen_condition = \'CURRENT_AREA.get_examine_screen() == %s\' % json.dumps(screen_name)')
        ->toContain('effective_condition = combine_conditions(screen_condition, condition)')
        ->toContain('condition=combine_conditions(condition, target_condition)');
});

it('keeps dynamic screen action targets for the label binding pass', function () {
    $script = file_get_contents(base_path('resources/renpy/json_stats.rpy'));

    expect($script)
        ->toContain('target_value = dynamic_label_expression_from_ast(first_arg)')
        ->toContain('def dynamic_label_expression_from_ast(node):')
        ->toContain('while True:')
        ->toContain('return ".".join(reversed(parts))')
        ->toContain('def parse_python_source(source, mode="exec"):')
        ->toContain('first_code_line = next((line for line in lines if line.strip()), "")')
        ->toContain('line[len(indent):] if line.startswith(indent) else line')
        ->toContain('return pyast.parse(normalized, mode)')
        ->toContain('trees.append(parse_python_source(source, parse_mode))')
        ->toContain('tree = pyast.parse(expression.strip(), mode="eval")')
        ->toContain('screen_hub = "screen:" + target_info.get("screen_name", screen_name)')
        ->toContain('add_screen_route_edges(from_label, screen_name, filename, linenumber, condition)')
        ->toContain('resolve_expression_route_edges(label_bindings)');
});

it('only recognizes explicit main menu or process termination as route endings', function () {
    $script = file_get_contents(base_path('resources/renpy/json_stats.rpy'));

    expect($script)
        ->not->toContain('ENDING_PATTERN')
        ->not->toContain('Return inside a conditional block = conditional ending')
        ->toContain('def python_source_termination(source):')
        ->toContain('if getattr(func, "attr", None) == "quit":')
        ->toContain('if getattr(func, "attr", None) == "full_restart":')
        ->toContain('termination = python_source_termination(source)')
        ->toContain('def add_explicit_ending(')
        ->toContain('for start_label in ("start", "labels.start"):')
        ->toContain('main_menu_targets = {"main_menu", "_main_menu"}')
        ->toContain('and not route_labels[label_name].get("is_ending", False)')
        ->toContain('route_labels[label_name]["returns_to_caller"] = True');
});

it('excludes menus that control flow can never reach from route data', function () {
    $script = file_get_contents(base_path('resources/renpy/json_stats.rpy'));

    expect($script)
        ->not->toContain('route_context_terminated')
        ->toContain('dead_route_menu_ids = set()')
        ->toContain('def statement_terminates_flow(stmt):')
        ->toContain('def mark_dead_route_menus(block):')
        ->toContain('mark_dead_route_menus(node.block)')
        ->toContain('mark_menus_dead_after(next_stmt)')
        ->toContain('if id(node) in dead_route_menu_ids:');
});

it('detects terminal flow anywhere in a block and ignores menu captions', function () {
    $script = file_get_contents(base_path('resources/renpy/json_stats.rpy'));

    expect($script)
        ->toContain('def block_has_terminal(block):')
        ->toContain('if statement_terminates_flow(stmt):')
        ->toContain('return isinstance(stmt, renpy.ast.Call)');

    $terminalMenuPattern = '/def is_terminal_menu\(stmt\):.*?' .
        'if not is_menu_choice_item\(item_l, item_b\):\s*continue/s';

    expect(preg_match($terminalMenuPattern, $script))->toBe(1);
});

it('counts visible menu captions as dialogue without treating them as options', function () {
    $script = file_get_contents(base_path('resources/renpy/json_stats.rpy'));

    expect($script)
        ->toContain('caption_texts_by_language = {')
        ->toContain('for lang, caption_texts in caption_texts_by_language.items():')
        ->toContain('all_lang_stats[lang]["filestats"][node.filename].add(caption_text)')
        ->toContain('all_lang_stats[lang]["characters"]["menu_choice"].add(caption_text)')
        ->toContain('"text": caption_text')
        ->toContain('if is_menu_choice_item(l, b):');
});
