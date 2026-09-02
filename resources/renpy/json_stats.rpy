init 10000 python:
    from __future__ import unicode_literals  # Helps ensure string consistency in Py2
    import ast as pyast
    # Python 2 / old Python 3 compatibility: ast.Constant was added in 3.8.
    # Older versions use ast.Num, ast.Str, ast.NameConstant instead.
    if not hasattr(pyast, 'Constant'):
        pyast.Constant = type(b'Constant', (), {})  # dummy class that never matches isinstance
    import codecs
    import collections
    import io
    import json
    import os
    import re
    import sys
    import textwrap
    from contextlib import closing
    from renpy import store
    from renpy.loader import listdirfiles
    from renpy.translation import translate_string as renpy_translate_string
    try:
        import builtins as py_builtins
    except ImportError:
        import __builtin__ as py_builtins

    def parse_python_source(source, mode="exec"):
        """Parse Ren'Py Python blocks after removing their script indentation."""
        normalized = textwrap.dedent(source)
        lines = normalized.splitlines(True)
        first_code_line = next((line for line in lines if line.strip()), "")
        indent_match = re.match(r"^[ \t]+", first_code_line)

        # A multiline string can contain an unindented line, which prevents
        # textwrap.dedent() from removing the block's real script indentation.
        if indent_match:
            indent = indent_match.group(0)
            normalized = "".join(
                line[len(indent):] if line.startswith(indent) else line
                for line in lines
            )

        return pyast.parse(normalized, mode)

    def translate_string(text, language=None):
        return renpy_translate_string(text, language=language)

    class Count(object):
        def __init__(self):
            self.blocks = 0      # Number of 'Say' statements
            self.words = 0       # Total words

        def add(self, text):
            self.blocks += 1
            self.words += len(text.split())

    class FileStats(object):
        def __init__(self):
            self.total_size = 0  # Total size of files in bytes
            self.count = 0       # Number of files

        def add_file(self, size):
            self.count += 1
            self.total_size += size

    def make_lang_stats():
        return {
            "filestats": collections.defaultdict(Count),
            "menu_count": 0,
            "options_count": 0,
            "characters": collections.defaultdict(Count)
        }

    def clean_text(text):
        """Remove inline Ren'Py text tags, double quotes, and decode escaped characters."""
        if not text:
            return text
        # Remove Ren'Py text tags like {color=#FA8072}[MCC]{/color}
        text = re.sub(r"{[^}]*}", "", text)
        # Trim whitespace
        text = text.strip()
        # Trim double quotes from beginning and end if they match
        if text.startswith('"') and text.endswith('"') and len(text) >= 2:
            text = text[1:-1]
        # Also check for single quotes, just to be thorough
        elif text.startswith("'") and text.endswith("'") and len(text) >= 2:
            text = text[1:-1]
        return text

    def normalize_dialogue_character(character_id, cleaned_text):
        """Keep extracted character IDs within the database-backed import limit."""
        if len(character_id) > 50:
            return "narrator", character_id + " " + cleaned_text

        return character_id, cleaned_text

    def first_statement_line(block, fallback_line):
        """Return the first executable line for a menu choice block."""
        for stmt in statement_block_items(block):
            line = getattr(stmt, "linenumber", 0)
            if line:
                return line
        return fallback_line

    # Primary data structure for language statistics
    all_lang_stats = collections.defaultdict(make_lang_stats)

    # Store dialogue lines by language
    dialogue_lines = collections.defaultdict(list)
    custom_text_assignments = []

    # Route graph data
    route_labels = collections.OrderedDict()
    route_edges = []
    route_edge_keys = set()
    route_menu_choices = []
    route_variables = []
    route_variable_changes = []
    route_return_labels = set()
    route_labels_with_screen_calls = set()
    route_menu_metadata = {}  # id(Menu node) -> enclosing condition and structural scope
    dead_route_menu_ids = set()  # id(Menu node) for menus control flow can never reach
    route_literal_variables = collections.defaultdict(dict)
    route_code_sources = []
    screen_action_exprs = collections.defaultdict(list)
    screen_action_conditions = collections.defaultdict(dict)
    screen_includes = collections.defaultdict(list)
    all_route_label_names = set()

    # File statistics by type
    file_statistics = {
        "image": collections.defaultdict(FileStats),
        "audio": collections.defaultdict(FileStats),
        "video": collections.defaultdict(FileStats),
        "other": collections.defaultdict(FileStats)
    }

    # Keep track of defined characters
    defined_characters = {}

    def get_file_size(filename):
        """Get the actual size of a file using Ren'Py's loader."""
        try:
            with closing(renpy.loader.load(filename)) as f:
                f.seek(0, 2)  # Seek to end
                return f.tell()
        except Exception:
            return 0

    def collect_file_statistics():
        """Collect file statistics using listdirfiles() with accurate archive sizes."""
        image_extensions = {'.jpg', '.jpeg', '.png', '.webp', '.avif', '.svg'}
        audio_extensions = {'.wav', '.mp2', '.mp3', '.ogg', '.opus', '.flac'}
        video_extensions = {'.ogv', '.webm', '.mp4', '.mkv', '.avi'}

        all_files = listdirfiles(common=False)

        for directory, filename in all_files:
            # Skip json_stats.* and .rpa files
            if filename.startswith('json_stats.') or filename.endswith('.rpa'):
                continue
            try:
                ext = os.path.splitext(filename)[1].lower()
                size = get_file_size(filename)

                if ext in image_extensions:
                    file_statistics["image"][ext].add_file(size)
                elif ext in audio_extensions:
                    file_statistics["audio"][ext].add_file(size)
                elif ext in video_extensions:
                    file_statistics["video"][ext].add_file(size)
                else:
                    file_statistics["other"][ext].add_file(size)
            except Exception:
                continue

    def decode_unicode_escape(s):
        """
        Safely decode Unicode escape sequences, handling both Python 2 and 3.
        Also handles cases where the input is already Unicode.
        """
        original = s
        if not isinstance(s, str if sys.version_info[0] >= 3 else unicode):
            s = str(s)
            original = s

        try:
            if sys.version_info[0] >= 3:
                # For Python 3, encode as bytes first if needed
                if isinstance(s, str):
                    s = s.encode('utf-8')
                return s.decode('unicode-escape').encode('latin-1').decode('utf-8')
            else:
                # For Python 2, handle both str and unicode inputs
                if isinstance(s, str):
                    return s.decode('string-escape').decode('utf-8')
                return s.encode('utf-8').decode('string-escape').decode('utf-8')
        except Exception as e:
            # If decoding fails, return original string
            return original

    # Precompile regexes for character extraction
    CHARACTER_TRANSLATED_REGEX = re.compile(
        r"Character\s*\(\s*_\(\s*[\"']((?:\\.|[^\"'])+)[\"']"
    )
    CHARACTER_PLAIN_REGEX = re.compile(
        r"Character\s*\(\s*[\"']((?:\\.|[^\"'])+)[\"']"
    )

    # Labels to exclude from route graph: dev tools, debug, internal Ren'Py call targets, system labels
    SKIP_LABEL_PATTERN = re.compile(
        r'^(?:_call_|dev_|debug|after_load$|after_warp$|before_main_menu$|change_date$|jump_day$|faces_reset$|set_characters_|initialize_bonus_|unlockPersistent$)',
        re.IGNORECASE
    )

    def is_game_file(filename):
        if not filename:
            return False
        fl = filename.replace("\\", "/")
        return not fl.startswith("renpy/common/")

    def is_route_label(label_name):
        """Check if a label should be included in the route graph."""
        if not label_name:
            return False
        if SKIP_LABEL_PATTERN.search(label_name):
            return False
        return True

    def get_renpy_control_call(call_node):
        func = getattr(call_node, 'func', None)
        if func is None:
            return None

        control_calls = (
            'jump',
            'call',
            'call_in_new_context',
            'jump_out_of_context',
            'call_replay',
        )
        if isinstance(func, pyast.Attribute) and getattr(func, 'attr', None) in control_calls:
            owner = getattr(func, 'value', None)
            if isinstance(owner, pyast.Name) and getattr(owner, 'id', None) == 'renpy':
                return 'call' if func.attr.startswith('call') else 'jump'

        return None

    def string_literal_from_ast(node):
        if isinstance(node, pyast.Constant) and isinstance(node.value, str):
            return node.value
        if hasattr(pyast, 'Str') and isinstance(node, pyast.Str):
            return node.s
        return None

    def is_dynamic_string_suffix(node):
        if isinstance(node, pyast.Call):
            func = getattr(node, 'func', None)
            return isinstance(func, pyast.Name) and getattr(func, 'id', None) == 'str'
        return isinstance(node, (pyast.Name, pyast.Attribute, pyast.Subscript))

    def natural_label_sort_key(label):
        match = re.search(r'(\d+)$', label)
        if match:
            return (label[:match.start(1)], int(match.group(1)), label)
        return (label, -1, label)

    def dynamic_route_targets_from_arg(arg):
        static_target = string_literal_from_ast(arg)
        if static_target:
            return [static_target]

        if not isinstance(arg, pyast.BinOp) or not isinstance(arg.op, pyast.Add):
            return []

        prefix = string_literal_from_ast(arg.left)
        if not prefix or not is_dynamic_string_suffix(arg.right):
            return []

        prefix_pattern = re.compile(r'^%s\d+$' % re.escape(prefix))
        return sorted(
            [label for label in all_route_label_names if prefix_pattern.match(label)],
            key=natural_label_sort_key
        )

    def extract_python_edges(source, from_label, filename, linenumber, condition=None):
        """Extract renpy.jump() and renpy.call() from Python source."""
        if not source or not source.strip():
            return False
        try:
            tree = parse_python_source(source)
        except (SyntaxError, ValueError):
            return False

        found_terminal_jump = False
        top_level_call_ids = set(
            id(getattr(stmt, 'value', None))
            for stmt in getattr(tree, 'body', [])
            if isinstance(stmt, pyast.Expr) and isinstance(getattr(stmt, 'value', None), pyast.Call)
        )
        for node in pyast.walk(tree):
            if not isinstance(node, pyast.Call):
                continue
            func_name = get_renpy_control_call(node)
            if not func_name:
                continue
            # Get the first positional argument as the target label
            args = getattr(node, 'args', [])
            if not args:
                continue
            for target in dynamic_route_targets_from_arg(args[0]):
                add_route_edge(from_label, target, func_name, filename, linenumber, condition=condition)
            if func_name == 'jump' and id(node) in top_level_call_ids:
                found_terminal_jump = True

        return found_terminal_jump

    def python_source_has_terminal_jump(source):
        if not source or not source.strip():
            return False
        try:
            tree = parse_python_source(source)
        except (SyntaxError, ValueError):
            return False

        for node in getattr(tree, 'body', []):
            if not isinstance(node, pyast.Expr):
                continue
            call_node = getattr(node, 'value', None)
            if call_node is not None and isinstance(call_node, pyast.Call) and get_renpy_control_call(call_node) == 'jump':
                return True

        return False

    def python_source_termination(source):
        """Return the explicit game termination performed by Python code."""
        if not source or not source.strip():
            return None
        try:
            tree = parse_python_source(source)
        except (SyntaxError, ValueError):
            return None

        for node in pyast.walk(tree):
            if not isinstance(node, pyast.Call):
                continue
            func = getattr(node, "func", None)
            if not isinstance(func, pyast.Attribute):
                continue
            owner = getattr(func, "value", None)
            if not isinstance(owner, pyast.Name) or getattr(owner, "id", None) != "renpy":
                continue
            if getattr(func, "attr", None) == "quit":
                return "quit"
            if getattr(func, "attr", None) == "full_restart":
                return "main_menu"

        return None

    def extract_assignments(source, current_label, filename, linenumber, context_type="python_block", condition=None, condition_stack=None):
        """Parse Python source and extract simple variable assignments."""
        if not source or not source.strip():
            return
        condition_stack = condition_stack or []
        try:
            tree = parse_python_source(source)
        except (SyntaxError, ValueError):
            return
        for node in pyast.walk(tree):
            if isinstance(node, pyast.Assign):
                for target in node.targets:
                    if isinstance(target, pyast.Name):
                        literal_value = None
                        if isinstance(node.value, pyast.Constant):
                            literal_value = node.value.value
                        elif hasattr(pyast, 'Num') and isinstance(node.value, pyast.Num):
                            literal_value = node.value.n
                        elif hasattr(pyast, 'Str') and isinstance(node.value, pyast.Str):
                            literal_value = node.value.s
                        elif hasattr(pyast, 'NameConstant') and isinstance(node.value, pyast.NameConstant):
                            literal_value = node.value.value

                        if isinstance(literal_value, (str, int, float, bool)) or literal_value is None:
                            route_literal_variables[current_label][target.id] = literal_value
                        elif target.id in route_literal_variables[current_label]:
                            del route_literal_variables[current_label][target.id]

                        route_variable_changes.append({
                            "label": current_label,
                            "variable": target.id,
                            "operation": "=",
                            "value": pyast.dump(node.value) if hasattr(pyast, "dump") else "",
                            "file": filename,
                            "line": linenumber,
                            "context": context_type,
                            "condition": condition,
                            "condition_stack": list(condition_stack),
                        })

                        if (
                            isinstance(literal_value, (str if sys.version_info[0] >= 3 else basestring)) and
                            context_type == "label_block" and
                            current_label and
                            is_game_file(filename)
                        ):
                            cleaned_text = clean_text(literal_value)
                            if cleaned_text:
                                character_id, cleaned_text = normalize_dialogue_character(target.id, cleaned_text)
                                custom_text_assignments.append({
                                    "character": character_id,
                                    "text": cleaned_text,
                                    "file": filename,
                                    "line": linenumber,
                                    "context": current_label,
                                })
            elif isinstance(node, pyast.AugAssign):
                if isinstance(node.target, pyast.Name):
                    if node.target.id in route_literal_variables[current_label]:
                        del route_literal_variables[current_label][node.target.id]
                    op_map = {
                        pyast.Add: "+=", pyast.Sub: "-=", pyast.Mult: "*=",
                        pyast.Div: "/=", pyast.Mod: "%=", pyast.BitAnd: "&=",
                        pyast.BitOr: "|=", pyast.BitXor: "^=",
                        pyast.LShift: "<<=", pyast.RShift: ">>=",
                        pyast.Pow: "**=", pyast.FloorDiv: "//=",
                    }
                    op_str = op_map.get(type(node.op), "?=")
                    route_variable_changes.append({
                        "label": current_label,
                        "variable": node.target.id,
                        "operation": op_str,
                        "value": pyast.dump(node.value) if hasattr(pyast, "dump") else "",
                        "file": filename,
                        "line": linenumber,
                        "context": context_type,
                        "condition": condition,
                        "condition_stack": list(condition_stack),
                    })

    def find_target_in_block(block):
        """Walk a block of AST nodes to find the first Jump or Call target."""
        block_items = statement_block_items(block)
        if not block_items:
            return None
        for stmt in block_items:
            screen_name = get_call_screen_name(stmt)
            if screen_name:
                targets = resolve_screen_targets(screen_name, {})
                if targets:
                    return targets[0]
            if isinstance(stmt, renpy.ast.Jump):
                return {"target": stmt.target, "type": "jump"}
            if isinstance(stmt, renpy.ast.Call):
                return {"target": stmt.label, "type": "call"}
            if isinstance(stmt, renpy.ast.Label):
                return {"target": stmt.name, "type": "flow"}
        return None

    def is_menu_caption_item(label, block):
        """Ren'Py parser stores caption rows as (label, "True", None)."""
        return label is not None and block is None

    def is_menu_choice_item(label, block):
        """Ren'Py parser stores selectable rows as (label, condition, block)."""
        return label is not None and block is not None

    def collect_targets_from_action_expr(expr, variables):
        if not expr:
            return []

        try:
            tree = pyast.parse(expr, mode="eval")
        except Exception:
            return []

        targets = []

        for node in pyast.walk(tree):
            if not isinstance(node, pyast.Call):
                continue

            func_name = None
            if isinstance(node.func, pyast.Name):
                func_name = node.func.id
            elif isinstance(node.func, pyast.Attribute):
                func_name = node.func.attr

            if func_name in ("Jump", "Call", "Start"):
                if not node.args:
                    continue

                first_arg = node.args[0]
                target_value = None

                literal_arg = string_literal_from_ast(first_arg)
                if isinstance(literal_arg, str):
                    target_value = literal_arg
                elif isinstance(first_arg, pyast.Name):
                    resolved = variables.get(first_arg.id, None)
                    if isinstance(resolved, str):
                        target_value = resolved

                if target_value is None:
                    target_value = dynamic_label_expression_from_ast(first_arg)

                if isinstance(target_value, str):
                    edge_type = "screen_" + ("jump" if func_name == "Start" else func_name.lower())
                    targets.append({"target": target_value, "type": edge_type})

            # A screen that returns a label name hands control to whichever label
            # called it, which then jumps to the returned value.
            if func_name == "Return" and node.args:
                returned = string_literal_from_ast(node.args[0])
                if isinstance(returned, str) and returned in route_labels:
                    targets.append({"target": returned, "type": "screen_jump"})
                    mark_externally_invoked(returned)

            if func_name == "Function" and len(node.args) >= 2:
                callback = node.args[0]
                first_callback_arg = node.args[1]

                if not isinstance(callback, pyast.Name) or callback.id != "locked_call":
                    continue
                callback_target = string_literal_from_ast(first_callback_arg)
                if not isinstance(callback_target, str):
                    continue

                targets.append({"target": callback_target, "type": "screen_call"})

        return targets

    def dynamic_label_expression_from_ast(node):
        """Keep a dynamic screen-action target for the later binding pass.

        Screen actions often jump through an object attribute, for example
        ``Jump(event.story_label)``. The binding collector can resolve that
        attribute to every literal label assigned to it, but only if the
        unresolved expression first becomes a route edge.
        """
        parts = []
        while True:
            if isinstance(node, pyast.Name):
                parts.append(node.id)
                return ".".join(reversed(parts))
            if isinstance(node, pyast.Attribute):
                parts.append(node.attr)
                node = node.value
                continue
            if isinstance(node, pyast.Subscript):
                node = node.value
                continue
            if isinstance(node, pyast.Call):
                node = node.func
                continue
            return None

    def collect_screens_from_action_expr(expr):
        if not expr:
            return []

        try:
            tree = pyast.parse(expr, mode="eval")
        except Exception:
            return []

        screens = []

        for node in pyast.walk(tree):
            if not isinstance(node, pyast.Call):
                continue

            func_name = None
            if isinstance(node.func, pyast.Name):
                func_name = node.func.id
            elif isinstance(node.func, pyast.Attribute):
                func_name = node.func.attr

            if func_name not in ("Show", "ShowTransient", "ToggleScreen"):
                continue
            if not node.args:
                continue

            first_arg = node.args[0]
            screen_name = string_literal_from_ast(first_arg)
            if isinstance(screen_name, str):
                screens.append(screen_name)

        return screens

    def collect_screen_targets():
        collected = collections.defaultdict(list)
        visited_screens = set()

        def add_screen_expr(screen_name, expr, condition=None):
            existing = collected[screen_name]
            if expr not in existing:
                existing.append(expr)
            conditions = screen_action_conditions[screen_name].setdefault(expr, [])
            if condition not in conditions:
                conditions.append(condition)
            for included_screen in collect_screens_from_action_expr(expr):
                add_screen_include(screen_name, included_screen)

        def add_screen_include(screen_name, included_screen):
            existing = screen_includes[screen_name]
            if included_screen not in existing:
                existing.append(included_screen)

        def walk_screen_node(screen_name, node, condition=None):
            if node is None:
                return

            if hasattr(node, "keyword"):
                for key, expr in getattr(node, "keyword", []):
                    if key == "action":
                        add_screen_expr(screen_name, expr, condition)

            if hasattr(node, "children"):
                for child in getattr(node, "children", []):
                    walk_screen_node(screen_name, child, condition)

            if hasattr(node, "entries"):
                entries = getattr(node, "entries", [])
                for effective_condition, block in iter_if_entries_with_effective_conditions(entries, condition):
                    walk_screen_node(screen_name, block, effective_condition)

            block = getattr(node, "block", None)
            if block is not None:
                walk_screen_node(screen_name, block, condition)

            target_name = getattr(node, "target", None)
            if isinstance(target_name, str):
                add_screen_include(screen_name, target_name)
                nested_screen = renpy.display.screen.get_screen_variant(target_name)
                if nested_screen is not None and nested_screen.ast is not None:
                    walk_screen_ast(target_name, nested_screen.ast)

        def walk_screen_ast(screen_name, ast_root):
            if screen_name in visited_screens or ast_root is None:
                return

            visited_screens.add(screen_name)
            walk_screen_node(screen_name, ast_root)

        for (screen_name, _variant), screen in renpy.display.screen.screens.items():
            if screen_name in visited_screens:
                continue
            if getattr(screen, "ast", None) is None:
                continue
            walk_screen_ast(screen_name, screen.ast)

        for screen_name, targets in list(collected.items()):
            if screen_name.startswith("examine_"):
                screen_condition = 'CURRENT_AREA.get_examine_screen() == %s' % json.dumps(screen_name)
                for expr in targets:
                    if expr not in collected["examine"]:
                        collected["examine"].append(expr)
                    conditions = screen_action_conditions[screen_name].get(expr, [None])
                    examine_conditions = screen_action_conditions["examine"].setdefault(expr, [])
                    for condition in conditions:
                        effective_condition = combine_conditions(screen_condition, condition)
                        if effective_condition not in examine_conditions:
                            examine_conditions.append(effective_condition)

        return collected

    def resolve_screen_targets(screen_name, variables, visited_screens=None):
        if visited_screens is None:
            visited_screens = set()
        if screen_name in visited_screens:
            return []

        visited_screens.add(screen_name)
        targets = []

        for expr in screen_action_exprs.get(screen_name, []):
            conditions = screen_action_conditions[screen_name].get(expr, [None])
            for condition in conditions:
                for target_info in collect_targets_from_action_expr(expr, variables):
                    conditioned_target = dict(target_info)
                    conditioned_target["condition"] = condition
                    conditioned_target["screen_name"] = screen_name
                    if conditioned_target not in targets:
                        targets.append(conditioned_target)

        for included_screen in screen_includes.get(screen_name, []):
            for target_info in resolve_screen_targets(included_screen, variables, visited_screens):
                if target_info not in targets:
                    targets.append(target_info)

        return targets

    def get_screen_statement_name(stmt, expected_name):
        if isinstance(stmt, renpy.ast.UserStatement):
            parsed = stmt.parsed
            if parsed is None:
                parsed = renpy.statements.parse(stmt, stmt.line, stmt.block)
                stmt.parsed = parsed

            name, parsed_data = parsed
            if " ".join(name) == expected_name:
                if not parsed_data.get("expression", False):
                    return parsed_data.get("name", None)

        return None

    def get_call_screen_name(stmt):
        return get_screen_statement_name(stmt, "call screen")

    def get_show_screen_name(stmt):
        return get_screen_statement_name(stmt, "show screen")

    # Nested branches combine into ever longer condition strings. Past a few
    # hundred characters they are useless to a reader and, since every edge
    # carries one and dedupes on it, they dominate memory.
    MAX_CONDITION_LENGTH = 400

    def bounded_condition(condition):
        if condition and len(condition) > MAX_CONDITION_LENGTH:
            return None
        return condition

    def combine_conditions(outer_condition, inner_condition):
        if not outer_condition or outer_condition == "True":
            return bounded_condition(inner_condition)
        if not inner_condition or inner_condition == "True":
            return bounded_condition(outer_condition)
        return bounded_condition("(%s) and (%s)" % (outer_condition, inner_condition))

    def is_true_condition(condition):
        return condition is True or condition == "True"

    def negate_condition_group(conditions):
        if any(is_true_condition(condition) for condition in conditions):
            return "False"

        conditions = [condition for condition in conditions if condition]
        if not conditions:
            return None

        return bounded_condition("not (" + " or ".join("(%s)" % condition for condition in conditions) + ")")

    def iter_if_entries_with_condition_parts(entries, outer_condition=None):
        prior_conditions = []

        for branch_condition, sub_block in entries:
            if is_true_condition(branch_condition):
                local_condition = negate_condition_group(prior_conditions)
            elif prior_conditions:
                local_condition = combine_conditions(
                    negate_condition_group(prior_conditions),
                    branch_condition
                )
            else:
                local_condition = branch_condition

            yield combine_conditions(outer_condition, local_condition), local_condition, sub_block
            prior_conditions.append(branch_condition)

    def iter_if_entries_with_effective_conditions(entries, outer_condition=None):
        for effective_condition, _local_condition, sub_block in iter_if_entries_with_condition_parts(entries, outer_condition):
            yield effective_condition, sub_block

    def handle_call_screen(from_label, screen_name, filename, linenumber, condition=None):
        if not from_label or not screen_name:
            return

        route_labels_with_screen_calls.add(from_label)
        add_screen_route_edges(from_label, screen_name, filename, linenumber, condition)

    def handle_show_screen(from_label, screen_name, filename, linenumber, condition=None):
        if not from_label or not screen_name:
            return

        add_screen_route_edges(from_label, screen_name, filename, linenumber, condition)

    def add_screen_route_edges(from_label, screen_name, filename, linenumber, condition=None):
        variables = route_literal_variables.get(from_label, {})

        for target_info in resolve_screen_targets(screen_name, variables):
            target = target_info["target"]
            target_condition = target_info.get("condition")

            if target not in all_route_label_names:
                # A screen such as a map can jump through an attribute whose
                # possible label values are resolved later. Represent the
                # screen once as the shared choice hub instead of multiplying
                # every caller by every possible dynamic target.
                screen_hub = "screen:" + target_info.get("screen_name", screen_name)
                ensure_route_label(screen_hub, filename, linenumber)
                add_route_edge(
                    from_label,
                    screen_hub,
                    "screen",
                    filename,
                    linenumber,
                    condition=condition,
                )
                add_route_edge(
                    screen_hub,
                    target,
                    target_info["type"],
                    filename,
                    linenumber,
                    condition=target_condition,
                )
                continue

            add_route_edge(
                from_label,
                target,
                target_info["type"],
                filename,
                linenumber,
                condition=combine_conditions(condition, target_condition),
            )

    def add_edge_from_statement(from_label, stmt, filename, condition=None):
        if not from_label or stmt is None:
            return

        linenumber = getattr(stmt, "linenumber", 0)
        screen_name = get_call_screen_name(stmt)
        if screen_name:
            handle_call_screen(from_label, screen_name, filename, linenumber, condition=condition)
            return

        screen_name = get_show_screen_name(stmt)
        if screen_name:
            handle_show_screen(from_label, screen_name, filename, linenumber, condition=condition)
            return

        if isinstance(stmt, renpy.ast.Jump):
            add_route_edge(from_label, stmt.target, "jump", filename, linenumber, condition=condition)
            return

        if isinstance(stmt, renpy.ast.Call):
            add_route_edge(from_label, stmt.label, "call", filename, linenumber, condition=condition)
            return

        if isinstance(stmt, renpy.ast.Label):
            next_filename = getattr(stmt, "filename", filename)
            next_line = getattr(stmt, "linenumber", 0)
            ensure_route_label(stmt.name, next_filename, next_line)
            add_route_edge(from_label, stmt.name, "flow", next_filename, next_line, condition=condition)
            return

        if isinstance(stmt, renpy.ast.If):
            for branch_condition, sub_block in iter_if_entries_with_effective_conditions(
                getattr(stmt, "entries", []),
                condition
            ):
                if not sub_block:
                    continue
                walk_for_edges(
                    sub_block,
                    from_label,
                    filename,
                    "if",
                    branch_condition,
                )

    def is_ast_statement(value):
        if value is None:
            return False

        ast_node = getattr(renpy.ast, "Node", None)
        if ast_node is not None and isinstance(value, ast_node):
            return True

        module_name = getattr(value.__class__, "__module__", "")
        return module_name.startswith("renpy.ast") and hasattr(value, "linenumber")

    def statement_block_items(block):
        """Return the statements of a Ren'Py AST block.

        Ren'Py exposes a block as a list. A single statement is not a block: its
        "next" continues in execution order past the end of the block and on
        through the rest of the script, so following that chain would pull in
        the remainder of the game for every block examined.
        """
        if block is None:
            return []
        if is_ast_statement(block):
            return [block]
        try:
            items = py_builtins.list(block)
        except TypeError:
            return []
        except Exception:
            return []
        if not items:
            return []
        return [stmt for stmt in items if is_ast_statement(stmt)]

    def annotate_following_statement_conditions(block, active_condition=None):
        """
        Annotate statements guarded by earlier terminal branches in the same block.
        """
        block_items = statement_block_items(block)
        if not block_items:
            return

        current_condition = active_condition

        for stmt in block_items:
            if isinstance(stmt, renpy.ast.Menu) and current_condition:
                existing = route_menu_metadata.get(id(stmt), {})
                route_menu_metadata[id(stmt)] = dict(existing, enclosing_condition=combine_conditions(
                    existing.get("enclosing_condition"),
                    current_condition
                ))

            if isinstance(stmt, renpy.ast.If):
                terminal_conditions = []

                for branch_condition, sub_block in iter_if_entries_with_effective_conditions(
                    getattr(stmt, "entries", []),
                    current_condition
                ):
                    if not sub_block:
                        continue

                    annotate_following_statement_conditions(sub_block, branch_condition)

                    if block_has_terminal(sub_block):
                        terminal_conditions.append(branch_condition)

                if terminal_conditions:
                    remaining_condition = negate_condition_group(terminal_conditions)
                    if remaining_condition == "False":
                        return

                    current_condition = combine_conditions(current_condition, remaining_condition)

                continue

            nested_block = getattr(stmt, "block", None)
            if nested_block:
                annotate_following_statement_conditions(nested_block, current_condition)

    def remaining_condition_after_terminal_branches(stmt, active_condition=None):
        if not isinstance(stmt, renpy.ast.If):
            return None

        terminal_conditions = []
        for branch_condition, sub_block in iter_if_entries_with_effective_conditions(
            getattr(stmt, "entries", []),
            active_condition
        ):
            if sub_block and block_has_terminal(sub_block):
                terminal_conditions.append(branch_condition)

        return negate_condition_group(terminal_conditions)

    def add_dynamic_label_value(values, key, label_name, condition=None):
        if not key or not label_name or label_name not in route_labels:
            return

        existing = values[key]
        entry = {
            "label": label_name,
            "condition": condition,
        }
        if entry not in existing:
            existing.append(entry)

    def hint_route_condition(func_name, label_name):
        registry = {
            "add_wyatt_hint": ("BUDDY_SYSTEM", "BUDDY_SYSTEM_HINTS"),
            "add_howie_hint": ("HOWIE_SYSTEM", "HOWIE_SYSTEM_HINTS"),
        }.get(func_name)
        if not registry or not label_name:
            return None

        system_variable, hints_variable = registry

        return "%s is True and call_lock == 0 and %s in %s.values()" % (
            system_variable,
            json.dumps(label_name),
            hints_variable,
        )

    def collect_dynamic_label_values():
        values = collections.defaultdict(list)

        def literal_string(node):
            if isinstance(node, pyast.Constant) and isinstance(node.value, str):
                return node.value
            if hasattr(pyast, "Str") and isinstance(node, pyast.Str):
                return node.s
            return None

        sources = list(route_code_sources)
        for expressions in screen_action_exprs.values():
            sources.extend(expressions)

        for source in sources:
            if not source or not source.strip():
                continue

            try:
                tree = parse_python_source(source)
            except Exception:
                try:
                    tree = parse_python_source(source, mode="eval")
                except Exception:
                    continue

            for node in pyast.walk(tree):
                if isinstance(node, pyast.Call):
                    func_name = None
                    if isinstance(node.func, pyast.Name):
                        func_name = node.func.id
                    elif isinstance(node.func, pyast.Attribute):
                        func_name = node.func.attr

                    for keyword in getattr(node, "keywords", []):
                        key = getattr(keyword, "arg", None)
                        label_name = literal_string(getattr(keyword, "value", None))
                        if key and key.lower().endswith("label"):
                            add_dynamic_label_value(values, key, label_name)
                            if key == "conclusion_label":
                                add_dynamic_label_value(values, "conclude_label", label_name)

                    args = getattr(node, "args", [])
                    if func_name == "PitchSequence" and len(args) >= 2:
                        label_name = literal_string(args[1])
                        add_dynamic_label_value(values, "outro_label", label_name)
                        add_dynamic_label_value(values, "sequence.outro_label", label_name)

                    if func_name == "Call" and len(args) >= 2:
                        target = literal_string(args[0])
                        passed_label = literal_string(args[1])
                        if target == "pitch_v2_conclude":
                            add_dynamic_label_value(values, "conclude_label", passed_label)

                    if func_name == "add_label_to_move_queue" and len(args) >= 2:
                        add_dynamic_label_value(values, "queued_label", literal_string(args[1]))

                    if func_name in ("add_howie_hint", "add_wyatt_hint") and len(args) >= 2:
                        label_name = literal_string(args[1])
                        add_dynamic_label_value(
                            values,
                            "hint_label",
                            label_name,
                            hint_route_condition(func_name, label_name),
                        )

                elif isinstance(node, pyast.Assign):
                    label_name = literal_string(getattr(node, "value", None))
                    if not label_name:
                        continue
                    for target in getattr(node, "targets", []):
                        key = None
                        if isinstance(target, pyast.Name):
                            key = target.id
                        elif isinstance(target, pyast.Attribute):
                            key = target.attr
                        if key and key.lower().endswith("label"):
                            add_dynamic_label_value(values, key, label_name)

        return values

    def add_dynamic_label_edges(from_label, dynamic_keys, values, edge_type, filename="", linenumber=0):
        if from_label not in route_labels:
            return

        source_info = route_labels.get(from_label, {})
        edge_file = filename or source_info.get("file", "")
        edge_line = linenumber or source_info.get("line", 0)

        for key in dynamic_keys:
            for value in values.get(key, []):
                add_route_edge(
                    from_label,
                    value["label"],
                    edge_type,
                    edge_file,
                    edge_line,
                    condition=value.get("condition"),
                )

    def collect_label_bindings():
        """Map names to the labels they can hold at runtime.

        Scripts routinely hand control to a label through a variable, an
        attribute or a getter. Recording which known label names flow into which
        identifiers lets those targets be resolved instead of being recorded as
        the expression's source text.
        """
        bindings = {"values": collections.defaultdict(list),
                    "returns": collections.defaultdict(list),
                    "params": {},
                    "aliases": collections.defaultdict(list)}

        def literal(node):
            return string_literal_from_ast(node)

        def add_value(key, label_name):
            if key and label_name and label_name in route_labels \
                    and label_name not in bindings["values"][key]:
                bindings["values"][key].append(label_name)

        def add_return(key, name):
            if key and name and name not in bindings["returns"][key]:
                bindings["returns"][key].append(name)

        def target_key(node):
            if isinstance(node, pyast.Name):
                return node.id
            if isinstance(node, pyast.Attribute):
                return node.attr
            return None

        def callee_name(call):
            func = getattr(call, "func", None)
            if isinstance(func, pyast.Name):
                return func.id
            if isinstance(func, pyast.Attribute):
                return func.attr
            return None

        def parameter_names(args):
            names = [getattr(a, "arg", getattr(a, "id", None)) for a in getattr(args, "args", [])]
            names += [getattr(a, "arg", None) for a in getattr(args, "kwonlyargs", [])]
            return [n for n in names if n and n != "self"]

        sources = list(route_code_sources)
        for expressions in screen_action_exprs.values():
            sources.extend(expressions)

        trees = []
        for source in sources:
            if not source or not source.strip():
                continue
            for parse_mode in ("exec", "eval"):
                try:
                    trees.append(parse_python_source(source, parse_mode))
                    break
                except Exception:
                    continue

        # Signatures first: binding a positional argument needs the parameter
        # names of the callable, which may be defined in another source.
        for tree in trees:
            for node in pyast.walk(tree):
                if isinstance(node, pyast.FunctionDef):
                    bindings["params"][node.name] = parameter_names(node.args)
                elif isinstance(node, pyast.ClassDef):
                    for sub in node.body:
                        if isinstance(sub, pyast.FunctionDef) and sub.name == "__init__":
                            bindings["params"][node.name] = parameter_names(sub.args)

        for tree in trees:
            for node in pyast.walk(tree):
                if isinstance(node, pyast.FunctionDef):
                    parameters = parameter_names(node.args)
                    bindings["params"][node.name] = parameters
                    parameter_set = set(parameters)

                    for sub in pyast.walk(node):
                        if isinstance(sub, pyast.Return):
                            returned = literal(sub.value)
                            if returned:
                                add_value(node.name, returned)
                            else:
                                key = target_key(sub.value)
                                if key:
                                    add_return(node.name, key)
                                elif isinstance(sub.value, pyast.Call):
                                    add_return(node.name, callee_name(sub.value))
                        elif isinstance(sub, pyast.Assign):
                            source_name = sub.value.id if isinstance(sub.value, pyast.Name) else None
                            if source_name in parameter_set:
                                for assign_target in sub.targets:
                                    name = target_key(assign_target)
                                    if name and name != source_name:
                                        bindings["aliases"][(node.name, source_name)].append(name)

                elif isinstance(node, pyast.ClassDef):
                    for sub in node.body:
                        if isinstance(sub, pyast.FunctionDef) and sub.name == "__init__":
                            bindings["params"][node.name] = parameter_names(sub.args)

                elif isinstance(node, pyast.Assign):
                    for assign_target in node.targets:
                        key = target_key(assign_target)
                        if not key:
                            continue
                        direct = literal(node.value)
                        if direct:
                            add_value(key, direct)
                        elif isinstance(node.value, pyast.Call):
                            add_return(key, callee_name(node.value))
                        elif isinstance(node.value, (pyast.Name, pyast.Attribute)):
                            add_return(key, target_key(node.value))

                elif isinstance(node, pyast.Call):
                    name = callee_name(node)
                    for keyword in getattr(node, "keywords", []):
                        if not getattr(keyword, "arg", None):
                            continue
                        value = literal(keyword.value)
                        add_value(keyword.arg, value)
                        for alias in bindings["aliases"].get((name, keyword.arg), []):
                            add_value(alias, value)

                    for param, arg in zip(bindings["params"].get(name, []), getattr(node, "args", [])):
                        value = literal(arg)
                        add_value(param, value)
                        for alias in bindings["aliases"].get((name, param), []):
                            add_value(alias, value)

        return bindings

    def resolve_label_expression(expression, bindings, depth=0):
        """Labels an expression used as a jump or call target can evaluate to."""
        if not expression or depth > 4:
            return []
        if expression in route_labels:
            return [expression]

        try:
            tree = pyast.parse(expression.strip(), mode="eval")
        except Exception:
            return []

        keys = []

        def gather(node, level):
            if level > 4:
                return
            if isinstance(node, pyast.Name):
                keys.append(node.id)
            elif isinstance(node, pyast.Attribute):
                keys.append(node.attr)
            elif isinstance(node, pyast.Subscript):
                gather(getattr(node, "value", None), level + 1)
            elif isinstance(node, pyast.Call):
                func = getattr(node, "func", None)
                if isinstance(func, pyast.Name):
                    keys.append(func.id)
                elif isinstance(func, pyast.Attribute):
                    keys.append(func.attr)
            elif isinstance(node, pyast.IfExp):
                gather(node.body, level + 1)
                gather(node.orelse, level + 1)
            elif isinstance(node, pyast.BoolOp):
                for value in node.values:
                    gather(value, level + 1)

        gather(tree.body, 0)

        seen = set()
        labels = []
        pending = list(keys)
        while pending:
            key = pending.pop(0)
            if not key or key in seen:
                continue
            seen.add(key)
            for label_name in bindings["values"].get(key, []):
                if label_name not in labels:
                    labels.append(label_name)
            for alias in bindings["returns"].get(key, []):
                pending.append(alias)

        return labels

    def resolve_expression_route_edges(bindings):
        """Replace edges that point at expression text with the labels they reach."""
        resolved_edges = []

        for edge in route_edges:
            target = edge.get("to_label")
            if not target or target in route_labels:
                resolved_edges.append(edge)
                continue

            matches = resolve_label_expression(target, bindings)
            if not matches:
                # The target never resolves to a label; keeping it would add a
                # node named after the expression.
                continue

            for label_name in matches:
                replacement = dict(edge)
                replacement["to_label"] = label_name
                replacement["edge_type"] = "dynamic_" + (
                    "call" if edge.get("edge_type") == "call" else "jump"
                )
                key = (
                    replacement["from_label"],
                    label_name,
                    replacement["edge_type"],
                    replacement.get("choice_text", ""),
                    replacement.get("condition", ""),
                )
                if key in route_edge_keys:
                    continue
                route_edge_keys.add(key)
                resolved_edges.append(replacement)

        del route_edges[:]
        route_edges.extend(resolved_edges)

    def add_inferred_dynamic_route_edges():
        dynamic_label_values = collect_dynamic_label_values()

        external_sources = list(route_code_sources)
        for expressions in screen_action_exprs.values():
            external_sources.extend(expressions)
        for source in external_sources:
            scan_external_invocations(source)

        add_dynamic_label_edges("move_to", ("intro_label", "queued_label", "setup_label"), dynamic_label_values, "dynamic_call")
        add_dynamic_label_edges("pitch_v2_debrief", ("debrief_label",), dynamic_label_values, "dynamic_call")
        add_dynamic_label_edges("pitch_v2_conclude", ("conclude_label",), dynamic_label_values, "dynamic_call")
        add_dynamic_label_edges("pitch_station_conclude", ("conclusion_label",), dynamic_label_values, "dynamic_call")
        add_dynamic_label_edges("pitch_v2_exit", ("outro_label", "sequence.outro_label"), dynamic_label_values, "dynamic_call")
        add_dynamic_label_edges("hub", ("hint_label",), dynamic_label_values, "dynamic_call")

        if "pitch_start" in route_labels and "pitch_v2_debrief" in route_labels:
            source_info = route_labels.get("pitch_start", {})
            add_route_edge("pitch_start", "pitch_v2_debrief", "dynamic_call", source_info.get("file", ""), source_info.get("line", 0))

        talk_prefixes = []

        for label_name, info in route_labels.items():
            if label_name.endswith("_hub") and label_name != "hub":
                talk_prefixes.append(label_name[:-4])

        for prefix in talk_prefixes:
            hub_label = prefix + "_hub"
            hub_exit_label = prefix + "_hub_exit"
            hub_info = route_labels.get(hub_label, {})
            hub_exit_info = route_labels.get(hub_exit_label, hub_info)

            add_route_edge("go_to_talk_from_interaction_hub", hub_label, "dynamic_call", hub_info.get("file", ""), hub_info.get("line", 0))
            add_route_edge("exit_talk_screen", hub_exit_label, "dynamic_call", hub_exit_info.get("file", ""), hub_exit_info.get("line", 0))
            add_route_edge("hub_exit_return", hub_exit_label, "dynamic_call", hub_exit_info.get("file", ""), hub_exit_info.get("line", 0))

            for label_name, info in route_labels.items():
                if not label_name.startswith(prefix + "_"):
                    continue
                if label_name in (hub_label, hub_exit_label):
                    continue
                if label_name.endswith(":ending"):
                    continue

                add_route_edge("call_topic_label", label_name, "dynamic_call", info.get("file", ""), info.get("line", 0))

    def statement_terminates_flow(stmt):
        """Check if control can never continue to the statement after this one."""
        if isinstance(stmt, (renpy.ast.Jump, renpy.ast.Return)):
            return True
        if isinstance(stmt, renpy.ast.Python):
            source = getattr(stmt.code, "source", "")
            return python_source_has_terminal_jump(source) or python_source_termination(source) is not None
        if isinstance(stmt, renpy.ast.If):
            return is_exhaustive_if(stmt)
        if isinstance(stmt, renpy.ast.Menu):
            return is_terminal_menu(stmt)
        return False

    def block_has_terminal(block):
        """Check if control never falls off the end of the block.

        Any unconditional terminal statement ends the block, no matter how
        many unreachable statements the script keeps after it.
        """
        block_items = statement_block_items(block)
        for stmt in block_items:
            if statement_terminates_flow(stmt):
                return True
        for stmt in block_items[::-1]:
            if isinstance(stmt, renpy.ast.Pass):
                continue
            # A block that ends in a call is treated as handed off to the
            # called scene rather than falling through.
            return isinstance(stmt, renpy.ast.Call)
        return False

    def is_terminal_menu(stmt):
        """Check if every selectable menu choice has terminal flow.

        Caption rows carry no block and are never selected, so they must not
        count against the menu being terminal.
        """
        items = getattr(stmt, "items", [])
        has_choice = False

        for item_l, _item_c, item_b in items:
            if not is_menu_choice_item(item_l, item_b):
                continue

            has_choice = True
            if not block_has_terminal(item_b):
                return False

        return has_choice

    def is_exhaustive_if(stmt):
        """Check if an If statement has terminal flow in ALL branches including else."""
        entries = getattr(stmt, "entries", [])
        if not entries:
            return False
        # Must have an else branch (condition is True/always)
        has_else = False
        for condition, sub_block in entries:
            if condition == "True" or condition is True:
                has_else = True
            if not block_has_terminal(sub_block):
                return False
        return has_else

    def mark_menus_dead(stmt):
        """Record every menu inside stmt as unreachable."""
        if isinstance(stmt, renpy.ast.Menu):
            dead_route_menu_ids.add(id(stmt))
            for _item_l, _item_c, item_b in getattr(stmt, "items", []):
                for sub in statement_block_items(item_b):
                    mark_menus_dead(sub)
            return
        if isinstance(stmt, renpy.ast.If):
            for _condition, sub_block in getattr(stmt, "entries", []):
                for sub in statement_block_items(sub_block):
                    mark_menus_dead(sub)
            return
        for sub in statement_block_items(getattr(stmt, "block", None)):
            mark_menus_dead(sub)

    def mark_dead_route_menus(block):
        """Record menus that sit after flow has already left the block.

        Statements after an unconditional jump, return, or exhaustive
        terminal branch never run, so their menus must not become route
        choices. A label makes the statements after it reachable again
        because it is a jump target.
        """
        alive = True
        for stmt in statement_block_items(block):
            if isinstance(stmt, renpy.ast.Label):
                alive = True
            if not alive:
                mark_menus_dead(stmt)
                continue
            if isinstance(stmt, renpy.ast.If):
                for _condition, sub_block in getattr(stmt, "entries", []):
                    mark_dead_route_menus(sub_block)
            elif isinstance(stmt, renpy.ast.Menu):
                for _item_l, _item_c, item_b in getattr(stmt, "items", []):
                    mark_dead_route_menus(item_b)
            elif statement_block_items(getattr(stmt, "block", None)):
                mark_dead_route_menus(stmt.block)
            if statement_terminates_flow(stmt):
                alive = False

    def mark_menus_dead_after(stmt):
        """Record menus between a terminal statement and the next label as unreachable."""
        seen = set()
        stmt = getattr(stmt, "next", None)
        while stmt is not None and id(stmt) not in seen:
            seen.add(id(stmt))
            if isinstance(stmt, renpy.ast.Label):
                return
            mark_menus_dead(stmt)
            stmt = getattr(stmt, "next", None)

    def ensure_route_label(label_name, filename, linenumber, is_ending=False):
        if not is_route_label(label_name):
            return
        if label_name not in route_labels:
            route_labels[label_name] = {
                "file": filename,
                "line": linenumber,
                "is_ending": bool(is_ending),
            }

    def add_explicit_ending(from_label, filename, linenumber, condition=None, edge_type="quit"):
        """Record control flow that explicitly reaches the main menu or quits."""
        if not from_label or from_label not in route_labels:
            return

        if not condition:
            route_labels[from_label]["is_ending"] = True
            return

        ending_name = from_label + ":ending"
        ensure_route_label(ending_name, filename, linenumber, is_ending=True)
        add_route_edge(
            from_label,
            ending_name,
            edge_type,
            filename,
            linenumber,
            condition=condition,
        )

    def mark_externally_invoked(label_name):
        """Record that code outside the script flow enters this label.

        Class methods, screen callbacks and other Python that is not inside a
        label can hand control to one by name. No script edge leads there, so
        the label is an entry point of its own rather than an orphan.
        """
        if label_name in route_labels:
            route_labels[label_name]["externally_invoked"] = True

    def scan_external_invocations(source):
        """Find renpy.call()/renpy.jump() targets in Python outside any label."""
        if not source or not source.strip():
            return

        tree = None
        for parse_mode in ("exec", "eval"):
            try:
                tree = parse_python_source(source, parse_mode)
                break
            except Exception:
                continue
        if tree is None:
            return

        for node in pyast.walk(tree):
            if not isinstance(node, pyast.Call):
                continue

            args = getattr(node, "args", [])

            if args and get_renpy_control_call(node):
                for target in dynamic_route_targets_from_arg(args[0]):
                    mark_externally_invoked(target)
                continue

            # Screen actions that enter a label, including one returned to the
            # caller of the screen.
            func = getattr(node, "func", None)
            action = None
            if isinstance(func, pyast.Name):
                action = func.id
            elif isinstance(func, pyast.Attribute):
                action = func.attr

            if args and action in ("Jump", "Call", "Start", "Return"):
                returned = string_literal_from_ast(args[0])
                if isinstance(returned, str):
                    mark_externally_invoked(returned)
                continue

            # A label name handed to any function is a reference to it from code
            # the script flow does not reach.
            for argument in args:
                literal_argument = string_literal_from_ast(argument)
                if isinstance(literal_argument, str):
                    mark_externally_invoked(literal_argument)
            for keyword in getattr(node, "keywords", []):
                literal_argument = string_literal_from_ast(getattr(keyword, "value", None))
                if isinstance(literal_argument, str):
                    mark_externally_invoked(literal_argument)

    def add_route_edge(from_label, to_label, edge_type, filename, linenumber, choice_text=None, condition=None):
        if not from_label or not to_label:
            return
        if not is_route_label(from_label) or not is_route_label(to_label):
            return

        edge_key = (
            from_label,
            to_label,
            edge_type,
            choice_text if choice_text is not None else "",
            condition if condition is not None else "",
        )

        if edge_key in route_edge_keys:
            return

        route_edge_keys.add(edge_key)

        edge = {
            "from_label": from_label,
            "to_label": to_label,
            "edge_type": edge_type,
            "file": filename,
            "line": linenumber,
        }

        if choice_text is not None:
            edge["choice_text"] = choice_text
        if condition is not None:
            edge["condition"] = condition

        route_edges.append(edge)

    def menu_branch_key(branch_path):
        if not branch_path:
            return None

        return "/".join(branch_path)

    def walk_for_edges(
        block,
        from_label,
        filename,
        from_type="flow",
        active_condition=None,
        branch_path=None,
        parent_menu_line=0,
        parent_choice_line=0,
        condition_stack=None
    ):
        """Recursively walk a block to find all Jump/Call edges and variable changes."""
        block_items = statement_block_items(block)
        if not block_items:
            return
        branch_path = branch_path or []
        condition_stack = condition_stack or []
        for stmt in block_items:
            screen_name = get_call_screen_name(stmt)
            if screen_name:
                handle_call_screen(
                    from_label,
                    screen_name,
                    filename,
                    getattr(stmt, "linenumber", 0),
                    condition=active_condition,
                )
            else:
                screen_name = get_show_screen_name(stmt)
                if screen_name:
                    handle_show_screen(
                        from_label,
                        screen_name,
                        filename,
                        getattr(stmt, "linenumber", 0),
                        condition=active_condition,
                    )
                    continue
            if isinstance(stmt, renpy.ast.Jump):
                add_route_edge(
                    from_label,
                    stmt.target,
                    "jump",
                    filename,
                    getattr(stmt, "linenumber", 0),
                    condition=active_condition,
                )
                return
            elif isinstance(stmt, renpy.ast.Call):
                add_route_edge(
                    from_label,
                    stmt.label,
                    "call",
                    filename,
                    getattr(stmt, "linenumber", 0),
                    condition=active_condition,
                )
            elif isinstance(stmt, renpy.ast.Return):
                return
            elif isinstance(stmt, renpy.ast.Label):
                nested_filename = getattr(stmt, "filename", filename)
                nested_line = getattr(stmt, "linenumber", 0)

                if is_route_label(stmt.name):
                    ensure_route_label(stmt.name, nested_filename, nested_line)
                    add_route_edge(
                        from_label,
                        stmt.name,
                        "flow",
                        nested_filename,
                        nested_line,
                        condition=active_condition,
                    )

                    # Subsequent statements belong to this label, not the parent
                    from_label = stmt.name
                    filename = nested_filename
                    active_condition = None
                    condition_stack = []

                # Walk the block even for filtered labels (may contain relevant edges)
                if statement_block_items(getattr(stmt, "block", None)):
                    walk_for_edges(stmt.block, from_label, nested_filename, "label_block")
            elif isinstance(stmt, renpy.ast.Python):
                source = getattr(stmt.code, "source", "")
                if source:
                    route_code_sources.append(source)
                    extract_assignments(
                        source, from_label, filename,
                        getattr(stmt, "linenumber", 0), from_type,
                        active_condition,
                        condition_stack
                    )
                    termination = python_source_termination(source)
                    if termination:
                        add_explicit_ending(
                            from_label,
                            filename,
                            getattr(stmt, "linenumber", 0),
                            condition=active_condition,
                            edge_type=termination,
                        )
                        return
                    if extract_python_edges(
                        source, from_label, filename,
                        getattr(stmt, "linenumber", 0),
                        condition=active_condition
                    ):
                        return
            elif isinstance(stmt, renpy.ast.If):
                for entry_index, (condition, local_condition, sub_block) in enumerate(iter_if_entries_with_condition_parts(
                    stmt.entries,
                    active_condition
                )):
                    if sub_block:
                        branch_segment = "if:%s:%s" % (
                            getattr(stmt, "linenumber", 0),
                            entry_index
                        )
                        next_condition_stack = list(condition_stack)
                        if local_condition and not is_true_condition(local_condition):
                            next_condition_stack.append(local_condition)
                        walk_for_edges(
                            sub_block,
                            from_label,
                            filename,
                            from_type,
                            condition,
                            branch_path + [branch_segment],
                            parent_menu_line,
                            parent_choice_line,
                            next_condition_stack,
                        )
            elif isinstance(stmt, renpy.ast.Menu):
                existing = route_menu_metadata.get(id(stmt), {})
                route_menu_metadata[id(stmt)] = {
                    "enclosing_condition": combine_conditions(existing.get("enclosing_condition"), active_condition),
                    "branch_key": existing.get("branch_key") or menu_branch_key(branch_path),
                    "parent_menu_line": existing.get("parent_menu_line") or parent_menu_line,
                    "parent_choice_line": existing.get("parent_choice_line") or parent_choice_line,
                    "branch_path": existing.get("branch_path") or list(branch_path),
                    "condition_stack": existing.get("condition_stack") or list(condition_stack),
                }
            elif statement_block_items(getattr(stmt, "block", None)):
                walk_for_edges(
                    stmt.block,
                    from_label,
                    filename,
                    from_type,
                    active_condition,
                    branch_path,
                    parent_menu_line,
                    parent_choice_line,
                    condition_stack,
                )

    def wordcounter():
        """Count words and analyze the game script."""
        all_stmts = list(renpy.game.script.all_stmts)
        all_stmts.sort(key=lambda n: n.filename or "")
        screen_action_exprs.update(collect_screen_targets())

        known_languages = renpy.known_languages()

        # Build translation map: {identifier: {lang: translated_text}}
        # This maps original Say statements to their translations across languages
        say_translations = {}
        source_say_identifiers = {}
        # From Translate blocks (Ren'Py 7.x)
        for node in all_stmts:
            if isinstance(node, renpy.ast.Translate):
                ident = getattr(node, "identifier", None)
                for stmt in getattr(node, "block", []):
                    if isinstance(stmt, renpy.ast.Say) and stmt.what and ident:
                        if node.language:
                            say_translations.setdefault(ident, {})[node.language] = clean_text(stmt.what)
                        else:
                            source_say_identifiers[id(stmt)] = ident
        # From TranslateSay nodes (Ren'Py 8.x)
        if hasattr(renpy.ast, "TranslateSay"):
            for node in all_stmts:
                if isinstance(node, renpy.ast.TranslateSay) and node.language and node.what:
                    ident = getattr(node, "identifier", None)
                    if ident:
                        say_translations.setdefault(ident, {})[node.language] = clean_text(node.what)

        # First pass: identify characters and variable defaults
        for node in all_stmts:
            if isinstance(node, renpy.ast.Define):
                varname = node.varname
                code_str = getattr(node.code, "source", "").strip()
                if is_game_file(node.filename):
                    route_code_sources.append(code_str)

                display_name = None

                # Look for Character(_("<Name>"), ...)
                match = CHARACTER_TRANSLATED_REGEX.search(code_str)
                if match:
                    display_name = match.group(1)
                    translated_display_name = translate_string(display_name, None)
                    if translated_display_name:
                        display_name = translated_display_name
                else:
                    # Fallback to Character("Name", ...)
                    match = CHARACTER_PLAIN_REGEX.search(code_str)
                    if match:
                        display_name = match.group(1)

                if not display_name or not display_name.strip():
                    display_name = varname

                # Apply the cleaning function
                display_name = clean_text(display_name)
                try:
                    display_name = decode_unicode_escape(display_name)
                except Exception:
                    # If decoding fails, use the original display name
                    pass

                defined_characters[varname] = {}
                defined_characters[varname]["default"] = translate_string(display_name, None)
                for lang in known_languages:
                    defined_characters[varname][lang] = translate_string(display_name, lang)

            elif isinstance(node, renpy.ast.Python) or (
                hasattr(renpy.ast, "EarlyPython") and isinstance(node, renpy.ast.EarlyPython)
            ):
                if is_game_file(node.filename):
                    python_source = getattr(node.code, "source", "")
                    if python_source:
                        route_code_sources.append(python_source)

            elif isinstance(node, renpy.ast.Default):
                default_value = getattr(node.code, "source", "").strip()
                if is_game_file(node.filename):
                    route_code_sources.append(default_value)
                    route_variables.append({
                        "name": node.varname,
                        "default_value": default_value,
                        "type": "default",
                        "file": node.filename,
                        "line": getattr(node, "linenumber", 0),
                    })

        # Translate blocks and TranslateSay nodes can coexist in one game. The
        # Say statements inside Translate blocks also appear in all_stmts, so
        # remember them to avoid counting them again as default-language lines.
        translated_block_say_ids = set()
        translated_language_say_ids = set()
        for node in all_stmts:
            if isinstance(node, renpy.ast.Translate):
                for stmt in getattr(node, "block", []) or []:
                    if isinstance(stmt, renpy.ast.Say):
                        translated_block_say_ids.add(id(stmt))
                        if node.language:
                            translated_language_say_ids.add(id(stmt))

        for node in all_stmts:
            if isinstance(node, renpy.ast.Label) and is_game_file(node.filename) and is_route_label(node.name):
                all_route_label_names.add(node.name)

        # Find context (current label or scene)
        current_context = {}

        # Track the last character who spoke in each language for extend statements
        last_character = {}

        def resolve_special_character(character_id, lang, last_character):
            """
            Resolve special Ren'Py characters to appropriate character assignments.

            Args:
                character_id: The original character ID from the dialogue
                lang: The language code
                last_character: Dictionary tracking last character per language

            Returns:
                tuple: (resolved_character_id, should_update_last_character)
            """
            # Handle extend statements - assign to previous character
            if character_id == "extend":
                if lang in last_character and last_character[lang]:
                    return last_character[lang], False
                else:
                    # Fallback to narrator if no previous character
                    return "narrator", False
            elif character_id in ["centered", "vcentered", "nvl_narrator", "wait"]:
                # These special characters should be treated as narrator
                return "narrator", False
            else:
                # Regular character - should update last_character tracking
                return character_id, True

        def record_say(lang, say):
            """Record one Say statement in its owning language."""
            all_lang_stats[lang]["filestats"][say.filename].add(say.what)

            cleaned_text = clean_text(say.what)
            who_var = getattr(say, "who", None)
            character_id = clean_text(who_var) if who_var else "narrator"
            character_id, should_update_last = resolve_special_character(character_id, lang, last_character)

            if should_update_last:
                last_character[lang] = character_id

            character_id, cleaned_text = normalize_dialogue_character(character_id, cleaned_text)
            dialogue_lines[lang].append({
                "character": character_id,
                "text": cleaned_text,
                "file": say.filename,
                "line": getattr(say, "linenumber", 0),
                "context": current_context.get(lang, "")
            })

            if who_var and who_var in defined_characters:
                all_lang_stats[lang]["characters"][who_var].add(say.what)
            else:
                all_lang_stats[lang]["characters"]["narrator"].add(say.what)

        # Second pass: gather dialogue and menu statistics
        last_say_text = None
        last_say_identifier = None  # for looking up prompt translations
        for node in all_stmts:
            # Track the last Say text for menu prompts
            # Include default-language Say/TranslateSay (language is None), skip translated ones
            if (isinstance(node, renpy.ast.Say)
                    and id(node) not in translated_language_say_ids
                    and not getattr(node, "language", None)):
                last_say_text = clean_text(node.what) if node.what else None
                last_say_identifier = source_say_identifiers.get(id(node), getattr(node, "identifier", None))

            # Track context (labels)
            if isinstance(node, renpy.ast.Label):
                is_current_route_label = is_route_label(node.name)
                if is_current_route_label:
                    for lang in ['default'] + list(known_languages):
                        current_context[lang] = node.name

                if is_game_file(node.filename):
                    route_context = node.name if is_current_route_label else current_context.get("default", "")
                    if is_current_route_label:
                        ensure_route_label(node.name, node.filename, getattr(node, "linenumber", 0))

                    if route_context and getattr(node, "block", None):
                        annotate_following_statement_conditions(node.block)
                        mark_dead_route_menus(node.block)
                        walk_for_edges(node.block, route_context, node.filename, "label_block")
                        if block_has_terminal(node.block):
                            next_stmt = None
                            next_condition = None
                        else:
                            block_items = statement_block_items(node.block)
                            trailing_stmt = block_items[-1] if block_items else None
                            next_stmt = getattr(trailing_stmt, "next", None)
                            next_condition = remaining_condition_after_terminal_branches(trailing_stmt)
                    else:
                        next_stmt = getattr(node, "next", None)
                        next_condition = None

                    # Follow the .next chain until we find a label, jump, call,
                    # return, or end of statements. This captures implicit
                    # fall-through between labels where non-control statements
                    # (Say, Python, Show, etc.) sit between them.
                    seen_next = set()
                    edge_count_before = len(route_edges)
                    while next_stmt is not None and id(next_stmt) not in seen_next:
                        seen_next.add(id(next_stmt))
                        if isinstance(next_stmt, (renpy.ast.Jump, renpy.ast.Return)):
                            add_edge_from_statement(route_context, next_stmt, node.filename, condition=next_condition)
                            mark_menus_dead_after(next_stmt)
                            break
                        if isinstance(next_stmt, renpy.ast.Call):
                            # Calls return; process edge but keep walking
                            add_edge_from_statement(route_context, next_stmt, node.filename, condition=next_condition)
                        if isinstance(next_stmt, renpy.ast.Label):
                            if is_route_label(next_stmt.name):
                                add_edge_from_statement(route_context, next_stmt, node.filename, condition=next_condition)
                                break
                            # Filtered label; skip past it and keep walking
                        # Non-terminal control flow: process edges but keep walking
                        if get_call_screen_name(next_stmt) or get_show_screen_name(next_stmt):
                            add_edge_from_statement(route_context, next_stmt, node.filename, condition=next_condition)
                        elif isinstance(next_stmt, renpy.ast.If):
                            add_edge_from_statement(route_context, next_stmt, node.filename, condition=next_condition)
                            # Only stop if ALL branches have terminal flow (jump/return)
                            # meaning execution can't continue past this If
                            remaining_condition = remaining_condition_after_terminal_branches(next_stmt, next_condition)
                            if remaining_condition == "False":
                                mark_menus_dead_after(next_stmt)
                                break
                            next_condition = combine_conditions(next_condition, remaining_condition)
                            if is_exhaustive_if(next_stmt):
                                mark_menus_dead_after(next_stmt)
                                break
                        elif isinstance(next_stmt, renpy.ast.Menu):
                            if next_condition:
                                existing = route_menu_metadata.get(id(next_stmt), {})
                                route_menu_metadata[id(next_stmt)] = dict(existing, enclosing_condition=combine_conditions(
                                    existing.get("enclosing_condition"),
                                    next_condition
                                ))
                        next_stmt = getattr(next_stmt, "next", None)

            if isinstance(node, renpy.ast.Translate):
                lang = node.language or "default"
                for say in getattr(node, "block", []) or []:
                    if isinstance(say, renpy.ast.Say):
                        record_say(lang, say)
            elif isinstance(node, renpy.ast.Say) and id(node) not in translated_block_say_ids:
                if (hasattr(renpy.ast, "TranslateSay")
                        and isinstance(node, renpy.ast.TranslateSay)
                        and node.language):
                    lang = node.language
                else:
                    lang = "default"
                record_say(lang, node)
            elif isinstance(node, renpy.ast.Menu):
                # Count menus for all languages (they're the same count)
                for lang in ['default'] + list(known_languages):
                    all_lang_stats[lang]["menu_count"] += 1

                menu_context = current_context.get("default", "")
                if id(node) in dead_route_menu_ids:
                    menu_context = ""
                menu_line = getattr(node, "linenumber", 0)

                # Capture the prompt exactly like Ren'Py's parser/runtime:
                # caption rows have a string label and block=None, while
                # selectable choices always have a block.
                menu_caption_labels = [
                    item_l
                    for item_l, item_c, item_b in node.items
                    if is_menu_caption_item(item_l, item_b) and item_l
                ]
                menu_prompt = "\n".join(clean_text(label) for label in menu_caption_labels) or last_say_text

                caption_prompt_translations = {}
                caption_texts_by_language = {
                    "default": [clean_text(label) for label in menu_caption_labels]
                }
                if menu_caption_labels:
                    for lang in known_languages:
                        translated_parts = []
                        has_translation = False

                        for caption_label in menu_caption_labels:
                            translated = translate_string(caption_label, lang)
                            if translated and translated != caption_label:
                                has_translation = True
                                translated_parts.append(clean_text(translated))
                            else:
                                translated_parts.append(clean_text(caption_label))

                        caption_texts_by_language[lang] = translated_parts
                        if has_translation:
                            caption_prompt_translations[lang] = "\n".join(translated_parts)

                    # Captions are visible menu questions, but they are not
                    # selectable options. Keep them in dialogue/word totals and
                    # separately use the rows with blocks as the option count.
                    for lang, caption_texts in caption_texts_by_language.items():
                        for caption_text in caption_texts:
                            all_lang_stats[lang]["filestats"][node.filename].add(caption_text)
                            all_lang_stats[lang]["characters"]["menu_choice"].add(caption_text)
                            dialogue_lines[lang].append({
                                "character": "menu_choice",
                                "text": caption_text,
                                "file": node.filename,
                                "line": menu_line,
                                "context": current_context.get(lang, "")
                            })

                menu_metadata = route_menu_metadata.get(id(node), {})

                # Get the enclosing if condition (if this menu is inside an if block)
                enclosing_condition = menu_metadata.get("enclosing_condition")
                menu_branch = menu_metadata.get("branch_key")
                parent_menu_line = menu_metadata.get("parent_menu_line") or 0
                parent_choice_line = menu_metadata.get("parent_choice_line") or 0
                menu_branch_path = menu_metadata.get("branch_path") or []
                menu_condition_stack = menu_metadata.get("condition_stack") or []

                for l, c, b in node.items:
                    if is_menu_choice_item(l, b):
                        for lang in ['default'] + list(known_languages):
                            all_lang_stats[lang]["options_count"] += 1

                        original_text = clean_text(l)
                        character_id = "menu_choice"
                        choice_line = first_statement_line(b, getattr(node, "linenumber", 0))

                        # Combine enclosing if-condition with the choice's own condition
                        choice_condition = c
                        effective_condition = combine_conditions(enclosing_condition, choice_condition) if enclosing_condition else choice_condition

                        # Determine the target of this menu choice
                        target_info = find_target_in_block(b)
                        choice_target = target_info["target"] if target_info else None
                        choice_type = target_info["type"] if target_info else None

                        # Collect translations for this choice
                        choice_translations = {}
                        for lang in known_languages:
                            translated = translate_string(l, lang)
                            if translated and translated != l:
                                choice_translations[lang] = clean_text(translated)

                        # Get prompt translations via Say identifier
                        prompt_translations = caption_prompt_translations
                        if not prompt_translations and last_say_identifier and last_say_identifier in say_translations:
                            prompt_translations = say_translations[last_say_identifier]

                        # Store route menu choice data
                        if menu_context and is_game_file(node.filename):
                            route_menu_choices.append({
                                "from_label": menu_context,
                                "text": ensure_unicode(original_text),
                                "condition": ensure_unicode(effective_condition) if effective_condition else None,
                                "target_label": ensure_unicode(choice_target) if choice_target else None,
                                "edge_type": ensure_unicode(choice_type) if choice_type else None,
                                "prompt": ensure_unicode(menu_prompt) if menu_prompt else None,
                                "translations": choice_translations,
                                "prompt_translations": prompt_translations,
                                "enclosing_condition": ensure_unicode(enclosing_condition) if enclosing_condition else None,
                                "choice_condition": ensure_unicode(choice_condition) if choice_condition else None,
                                "menu_branch": ensure_unicode(menu_branch) if menu_branch else None,
                                "menu_condition_stack": [ensure_unicode(condition_part) for condition_part in menu_condition_stack],
                                "parent_menu_line": parent_menu_line,
                                "parent_choice_line": parent_choice_line,
                                "file": node.filename,
                                "menu_line": menu_line,
                                "line": choice_line,
                            })

                            # Record an edge from the menu's label to the choice target
                            if choice_target:
                                add_route_edge(
                                    menu_context,
                                    choice_target,
                                    "menu_choice",
                                    node.filename,
                                    choice_line,
                                    choice_text=ensure_unicode(original_text),
                                    condition=ensure_unicode(effective_condition) if effective_condition else None,
                                )

                            # Walk the menu choice block for nested edges
                            choice_context = "menu_choice:" + ensure_unicode(original_text)
                            choice_branch_path = list(menu_branch_path)
                            choice_branch_path.append("menu:%s:choice:%s" % (menu_line, choice_line))
                            walk_for_edges(
                                b,
                                menu_context,
                                node.filename,
                                choice_context,
                                effective_condition,
                                choice_branch_path,
                                menu_line,
                                choice_line,
                                menu_condition_stack,
                            )

                        # Add menu choice for default language
                        dialogue_lines["default"].append({
                            "character": character_id,
                            "text": original_text,
                            "file": node.filename,
                            "line": choice_line,
                            "context": current_context.get("default", "")
                        })
                        all_lang_stats["default"]["characters"]["menu_choice"].add(l)

                        # Add translated menu choices for each language
                        for lang in known_languages:
                            translated_text = translate_string(l, lang)
                            if translated_text and translated_text != l:
                                cleaned_translated_text = clean_text(translated_text)
                                dialogue_lines[lang].append({
                                    "character": character_id,
                                    "text": cleaned_translated_text,
                                    "file": node.filename,
                                    "line": choice_line,
                                    "context": current_context.get(lang, "")
                                })
                                all_lang_stats[lang]["characters"]["menu_choice"].add(translated_text)
                            else:
                                dialogue_lines[lang].append({
                                    "character": character_id,
                                    "text": original_text,
                                    "file": node.filename,
                                    "line": choice_line,
                                    "context": current_context.get(lang, "")
                                })
                                all_lang_stats[lang]["characters"]["menu_choice"].add(l)
                    elif b:
                        if menu_context and is_game_file(node.filename):
                            walk_for_edges(
                                b,
                                menu_context,
                                node.filename,
                                "menu_block",
                                enclosing_condition,
                                menu_branch_path,
                                parent_menu_line,
                                parent_choice_line,
                                menu_condition_stack,
                            )

            elif isinstance(node, renpy.ast.Return):
                ctx = current_context.get("default", "")
                if ctx and is_game_file(node.filename):
                    route_return_labels.add(ctx)
        add_inferred_dynamic_route_edges()
        label_bindings = collect_label_bindings()
        resolve_expression_route_edges(label_bindings)

        # A return from Ren'Py's entry label unwinds to the main menu. Returns
        # anywhere else may unwind only a label, screen, or dynamically invoked
        # callback, so they are not safe to present as story endings.
        for start_label in ("start", "labels.start"):
            if start_label in route_return_labels and start_label in route_labels:
                route_labels[start_label]["is_ending"] = True

        for label_name in route_return_labels:
            if (
                label_name not in ("start", "labels.start")
                and label_name in route_labels
                and not route_labels[label_name].get("is_ending", False)
            ):
                route_labels[label_name]["returns_to_caller"] = True

        # Explicit flow back to Ren'Py's main menu is an ending.
        main_menu_targets = {"main_menu", "_main_menu"}
        for edge in route_edges:
            if edge.get("to_label") in main_menu_targets:
                from_label = edge.get("from_label")
                if from_label and from_label in route_labels:
                    route_labels[from_label]["is_ending"] = True

        collect_file_statistics()
        report_stats()

    def ensure_unicode(s):
        """Convert string to Unicode if it isn't already."""
        if sys.version_info[0] >= 3:
            return s
        elif isinstance(s, str):
            return s.decode('utf-8')
        return s

    def ensure_unicode_dict(d):
        """Recursively convert all strings in a dict to Unicode."""
        result = {}
        for k, v in d.items():
            if isinstance(k, str):
                k = k.decode('utf-8')
            if isinstance(v, dict):
                v = ensure_unicode_dict(v)
            elif isinstance(v, str):
                v = v.decode('utf-8')
            result[k] = v
        return result

    def report_stats():
        """Write a newline-delimited JSON report of the collected statistics.

        One record per line, aggregates first and dialogue lines last. Records are
        encoded and written individually and the collected lines are freed as they
        go, so peak memory does not scale with the size of the script.
        """
        has_dialogue_blocks = any(
            file_count.blocks > 0
            for data in all_lang_stats.values()
            for file_count in data["filestats"].values()
        )

        if not has_dialogue_blocks and custom_text_assignments:
            for line in custom_text_assignments:
                all_lang_stats["default"]["filestats"][line["file"]].add(line["text"])
                all_lang_stats["default"]["characters"][line["character"]].add(line["text"])
                dialogue_lines["default"].append(line)

        outfile = io.open("stats.ndjson", "w", encoding="utf-8")
        try:
            def emit(record):
                outfile.write(u"{}\n".format(
                    json.dumps(record, ensure_ascii=False, separators=(u",", u":"), sort_keys=False)
                ))

            emit({"type": "meta", "schema": "fvn.renpy_stats.v1"})

            report_language_stats(emit)
            report_file_statistics(emit)
            report_route_graph(emit)
            # Dialogue lines are written last and consumers rely on that: it lets
            # them read the aggregate sections without walking the large one.
            report_dialogue_lines(emit)
        finally:
            outfile.close()

    def report_language_stats(emit):
        """Emit one record per language, then one per character within it."""
        for lang, data in all_lang_stats.items():
            total_blocks = 0
            total_words = 0
            for file_count in data["filestats"].values():
                total_blocks += file_count.blocks
                total_words += file_count.words

            emit({
                "type": "languages",
                "key": ensure_unicode(lang),
                "entry": {
                    "blocks": total_blocks,
                    "words": total_words,
                    "menus": data["menu_count"],
                    "options": data["options_count"],
                },
            })

            for char_var, char_count in data["characters"].items():
                if char_var == "narrator":
                    display_name = "Narrator"
                elif char_var == "menu_choice":
                    display_name = "Menu Choice"
                else:
                    display_name = defined_characters.get(char_var, {}).get(lang)
                emit({
                    "type": "characters",
                    "language": ensure_unicode(lang),
                    "key": ensure_unicode(char_var),
                    "entry": {
                        "display_name": ensure_unicode(display_name) if display_name else None,
                        "blocks": char_count.blocks,
                        "words": char_count.words,
                    },
                })

    def report_file_statistics(emit):
        """Emit the file inventory as a single record; it is small by nature."""
        entry = {
            category: {
                ext: {
                    "count": stats.count,
                    "total_size": stats.total_size
                }
                for ext, stats in extensions.items()
            }
            for category, extensions in file_statistics.items()
        }

        entry["summary"] = {
            "total_image": sum(stats.count for stats in file_statistics["image"].values()),
            "total_audio": sum(stats.count for stats in file_statistics["audio"].values()),
            "total_video": sum(stats.count for stats in file_statistics["video"].values()),
            "total_other": sum(stats.count for stats in file_statistics["other"].values()),
            "total_size": sum(
                stats.total_size
                for category in file_statistics.values()
                for stats in category.values()
            )
        }

        emit({"type": "file_statistics", "entry": entry})

    def report_dialogue_lines(emit):
        """Emit one record per dialogue line, freeing each language as it goes.

        This is by far the largest section. Lines are popped off the collected
        list while being written so that the peak holds one copy of the corpus
        rather than the collected lines plus a processed duplicate.
        """
        for lang in list(dialogue_lines.keys()):
            language = ensure_unicode(lang)
            lines = dialogue_lines.pop(lang)
            # Reversed so that popping from the end stays O(1) while the records
            # are still emitted in collection order, which consumers rely on.
            lines.reverse()

            while lines:
                line = lines.pop()
                emit({
                    "type": "dialogue_lines",
                    "language": language,
                    "entry": {
                        "character": ensure_unicode(line["character"]),
                        "text": ensure_unicode(line["text"]),
                        "file": ensure_unicode(line["file"]),
                        "line": line["line"],
                        "context": ensure_unicode(line["context"]) if line["context"] else None
                    },
                })

    def report_route_graph(emit):
        """Emit the route graph sections, one record per entry."""
        for name, info in route_labels.items():
            emit({"type": "route_labels", "entry": {
                "name": ensure_unicode(name),
                "file": ensure_unicode(info["file"]),
                "line": info["line"],
                "is_ending": info.get("is_ending", False),
                "returns_to_caller": info.get("returns_to_caller", False),
                "externally_invoked": info.get("externally_invoked", False),
            }})

        for edge in route_edges:
            processed_edge = {
                "from_label": ensure_unicode(edge["from_label"]) if edge["from_label"] else None,
                "to_label": ensure_unicode(edge["to_label"]) if edge["to_label"] else None,
                "edge_type": ensure_unicode(edge.get("edge_type", "flow")),
                "file": ensure_unicode(edge["file"]),
                "line": edge["line"],
            }
            if "choice_text" in edge:
                processed_edge["choice_text"] = ensure_unicode(edge["choice_text"])
            if "condition" in edge and edge["condition"]:
                processed_edge["condition"] = ensure_unicode(edge["condition"])
            emit({"type": "route_edges", "entry": processed_edge})

        for choice in route_menu_choices:
            entry = {
                "from_label": ensure_unicode(choice["from_label"]) if choice["from_label"] else None,
                "text": ensure_unicode(choice["text"]) if choice["text"] else None,
                "condition": ensure_unicode(choice["condition"]) if choice["condition"] else None,
                "enclosing_condition": ensure_unicode(choice.get("enclosing_condition")) if choice.get("enclosing_condition") else None,
                "choice_condition": ensure_unicode(choice.get("choice_condition")) if choice.get("choice_condition") else None,
                "menu_branch": ensure_unicode(choice.get("menu_branch")) if choice.get("menu_branch") else None,
                "menu_condition_stack": [ensure_unicode(condition_part) for condition_part in choice.get("menu_condition_stack", [])],
                "parent_menu_line": choice.get("parent_menu_line", 0),
                "parent_choice_line": choice.get("parent_choice_line", 0),
                "target_label": ensure_unicode(choice["target_label"]) if choice["target_label"] else None,
                "edge_type": ensure_unicode(choice["edge_type"]) if choice["edge_type"] else None,
                "prompt": ensure_unicode(choice["prompt"]) if choice.get("prompt") else None,
                "menu_line": choice.get("menu_line", 0),
                "file": ensure_unicode(choice["file"]),
                "line": choice["line"],
            }
            if choice.get("translations"):
                entry["translations"] = choice["translations"]
            if choice.get("prompt_translations"):
                entry["prompt_translations"] = choice["prompt_translations"]
            emit({"type": "route_menu_choices", "entry": entry})

        for var in route_variables:
            emit({"type": "route_variables", "entry": {
                "name": ensure_unicode(var["name"]),
                "default_value": ensure_unicode(var["default_value"]) if var["default_value"] else None,
                "type": ensure_unicode(var["type"]),
                "file": ensure_unicode(var["file"]),
                "line": var["line"],
            }})

        for vc in route_variable_changes:
            emit({"type": "route_variable_changes", "entry": {
                "label": ensure_unicode(vc["label"]) if vc["label"] else None,
                "variable": ensure_unicode(vc["variable"]),
                "operation": ensure_unicode(vc["operation"]),
                "value": ensure_unicode(vc["value"]) if vc["value"] else None,
                "file": ensure_unicode(vc["file"]),
                "line": vc["line"],
                "context": ensure_unicode(vc.get("context", "python_block")),
                "condition": ensure_unicode(vc.get("condition")) if vc.get("condition") else None,
                "condition_stack": [ensure_unicode(condition_part) for condition_part in vc.get("condition_stack", [])],
            }})

    # Run the wordcounter and then quit
    wordcounter()
    renpy.quit()
