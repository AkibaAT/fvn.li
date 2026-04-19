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
    from contextlib import closing
    from renpy import store
    from renpy.loader import listdirfiles

    def translate_string(text, language=None):
        if renpy.version_tuple >= (8, 0, 0, 0):
            return renpy.translate_string(text, language=language)
        else:
            return text

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

    # Primary data structure for language statistics
    all_lang_stats = collections.defaultdict(make_lang_stats)

    # Store dialogue lines by language
    dialogue_lines = collections.defaultdict(list)

    # Route graph data
    route_labels = collections.OrderedDict()
    route_edges = []
    route_edge_keys = set()
    route_menu_choices = []
    route_variables = []
    route_variable_changes = []
    route_labels_with_screen_calls = set()
    route_menu_conditions = {}  # id(Menu node) -> enclosing if condition
    route_literal_variables = collections.defaultdict(dict)
    screen_action_exprs = collections.defaultdict(list)

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
        if not isinstance(s, str if sys.version_info[0] >= 3 else unicode):
            s = str(s)

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
            return s

    # Precompile regexes for character extraction
    CHARACTER_TRANSLATED_REGEX = re.compile(
        r"Character\s*\(\s*_\(\s*[\"']((?:\\.|[^\"'])+)[\"']"
    )
    CHARACTER_PLAIN_REGEX = re.compile(
        r"Character\s*\(\s*[\"']((?:\\.|[^\"'])+)[\"']"
    )

    ENDING_PATTERN = re.compile(
        r'(?:^|[_\.])(?:ending|end|credits|game_?over|true_?end|good_?end|bad_?end|normal_?end|route_?end)(?:$|[_\.])',
        re.IGNORECASE
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

    def extract_python_edges(source, from_label, filename, linenumber, condition=None):
        """Extract renpy.jump() and renpy.call() from Python source."""
        if not source or not source.strip():
            return
        try:
            tree = pyast.parse(source)
        except (SyntaxError, ValueError):
            return
        for node in pyast.walk(tree):
            if not isinstance(node, pyast.Expr):
                continue
            call_node = getattr(node, 'value', None)
            if call_node is None or not isinstance(call_node, pyast.Call):
                continue
            func = getattr(call_node, 'func', None)
            if func is None:
                continue
            # Match renpy.jump("label") / renpy.call("label")
            func_name = None
            if isinstance(func, pyast.Attribute) and getattr(func, 'attr', None) in ('jump', 'call'):
                owner = getattr(func, 'value', None)
                if isinstance(owner, pyast.Name) and getattr(owner, 'id', None) == 'renpy':
                    func_name = func.attr
            if not func_name:
                continue
            # Get the first positional argument as the target label
            args = getattr(call_node, 'args', [])
            if not args:
                continue
            arg = args[0]
            target = None
            if isinstance(arg, pyast.Constant):
                target = arg.value
            elif hasattr(pyast, 'Str') and isinstance(arg, pyast.Str):
                target = arg.s
            if target and isinstance(target, str):
                add_route_edge(from_label, target, func_name, filename, linenumber, condition=condition)

    def extract_assignments(source, current_label, filename, linenumber, context_type="python_block"):
        """Parse Python source and extract simple variable assignments."""
        if not source or not source.strip():
            return
        try:
            tree = pyast.parse(source)
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
                    })

    def find_target_in_block(block):
        """Walk a block of AST nodes to find the first Jump or Call target."""
        if not block:
            return None
        for stmt in block:
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
                return {"target": stmt.name, "type": "label"}
        return None

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

            if func_name not in ("Jump", "Call"):
                continue

            if not node.args:
                continue

            first_arg = node.args[0]
            target_value = None

            if isinstance(first_arg, pyast.Constant) and isinstance(first_arg.value, str):
                target_value = first_arg.value
            elif isinstance(first_arg, pyast.Name):
                resolved = variables.get(first_arg.id, None)
                if isinstance(resolved, str):
                    target_value = resolved

            if isinstance(target_value, str):
                targets.append({"target": target_value, "type": "screen_" + func_name.lower()})

        return targets

    def collect_screen_targets():
        collected = collections.defaultdict(list)
        visited_screens = set()

        def add_screen_expr(screen_name, expr):
            existing = collected[screen_name]
            if expr not in existing:
                existing.append(expr)

        def walk_screen_node(screen_name, node):
            if node is None:
                return

            if hasattr(node, "keyword"):
                for key, expr in getattr(node, "keyword", []):
                    if key == "action":
                        add_screen_expr(screen_name, expr)

            if hasattr(node, "children"):
                for child in getattr(node, "children", []):
                    walk_screen_node(screen_name, child)

            if hasattr(node, "entries"):
                for _condition, block in getattr(node, "entries", []):
                    walk_screen_node(screen_name, block)

            block = getattr(node, "block", None)
            if block is not None:
                walk_screen_node(screen_name, block)

            target_name = getattr(node, "target", None)
            if isinstance(target_name, str):
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

        return collected

    def resolve_screen_targets(screen_name, variables):
        targets = []
        for expr in screen_action_exprs.get(screen_name, []):
            for target_info in collect_targets_from_action_expr(expr, variables):
                if target_info not in targets:
                    targets.append(target_info)
        return targets

    def get_call_screen_name(stmt):
        if isinstance(stmt, renpy.ast.UserStatement):
            parsed = stmt.parsed
            if parsed is None:
                parsed = renpy.statements.parse(stmt, stmt.line, stmt.block)
                stmt.parsed = parsed

            name, parsed_data = parsed
            if " ".join(name) == "call screen":
                if not parsed_data.get("expression", False):
                    return parsed_data.get("name", None)

        return None

    def combine_conditions(outer_condition, inner_condition):
        if not outer_condition or outer_condition == "True":
            return inner_condition
        if not inner_condition or inner_condition == "True":
            return outer_condition
        return "(%s) and (%s)" % (outer_condition, inner_condition)

    def handle_call_screen(from_label, screen_name, filename, linenumber, condition=None):
        if not from_label or not screen_name:
            return

        route_labels_with_screen_calls.add(from_label)
        variables = route_literal_variables.get(from_label, {})

        for target_info in resolve_screen_targets(screen_name, variables):
            add_route_edge(
                from_label,
                target_info["target"],
                target_info["type"],
                filename,
                linenumber,
                condition=condition,
            )

    def add_edge_from_statement(from_label, stmt, filename, condition=None):
        if not from_label or stmt is None:
            return

        linenumber = getattr(stmt, "linenumber", 0)
        screen_name = get_call_screen_name(stmt)
        if screen_name:
            handle_call_screen(from_label, screen_name, filename, linenumber, condition=condition)
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
            for branch_condition, sub_block in getattr(stmt, "entries", []):
                if not sub_block:
                    continue
                walk_for_edges(
                    sub_block,
                    from_label,
                    filename,
                    "if",
                    combine_conditions(condition, branch_condition),
                )

    def block_has_terminal(block):
        """Check if a block ends with a jump, call, or return."""
        if not block:
            return False
        for stmt in reversed(block):
            if isinstance(stmt, (renpy.ast.Jump, renpy.ast.Call, renpy.ast.Return)):
                return True
            if isinstance(stmt, renpy.ast.If):
                return is_exhaustive_if(stmt)
            if isinstance(stmt, renpy.ast.Pass):
                continue
            return False
        return False

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

    def ensure_route_label(label_name, filename, linenumber, is_ending=False):
        if not is_route_label(label_name):
            return
        if label_name not in route_labels:
            route_labels[label_name] = {
                "file": filename,
                "line": linenumber,
                "is_ending": bool(is_ending or ENDING_PATTERN.search(label_name)),
            }

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

    def walk_for_edges(block, from_label, filename, from_type="flow", active_condition=None):
        """Recursively walk a block to find all Jump/Call edges and variable changes."""
        if not block:
            return
        for stmt in block:
            screen_name = get_call_screen_name(stmt)
            if screen_name:
                handle_call_screen(
                    from_label,
                    screen_name,
                    filename,
                    getattr(stmt, "linenumber", 0),
                    condition=active_condition,
                )
            elif isinstance(stmt, renpy.ast.Jump):
                add_route_edge(
                    from_label,
                    stmt.target,
                    "jump",
                    filename,
                    getattr(stmt, "linenumber", 0),
                    condition=active_condition,
                )
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
                # Return inside a conditional block = conditional ending
                if active_condition:
                    ending_name = from_label + ":ending"
                    ensure_route_label(ending_name, filename, getattr(stmt, "linenumber", 0), is_ending=True)
                    add_route_edge(
                        from_label,
                        ending_name,
                        "return",
                        filename,
                        getattr(stmt, "linenumber", 0),
                        condition=active_condition,
                    )
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

                # Walk the block even for filtered labels (may contain relevant edges)
                if getattr(stmt, "block", None):
                    walk_for_edges(stmt.block, from_label, nested_filename, "label_block")
            elif isinstance(stmt, renpy.ast.Python):
                source = getattr(stmt.code, "source", "")
                if source:
                    extract_assignments(
                        source, from_label, filename,
                        getattr(stmt, "linenumber", 0), from_type
                    )
                    extract_python_edges(
                        source, from_label, filename,
                        getattr(stmt, "linenumber", 0),
                        condition=active_condition
                    )
            elif isinstance(stmt, renpy.ast.If):
                for i, (condition, sub_block) in enumerate(stmt.entries):
                    if sub_block:
                        walk_for_edges(
                            sub_block,
                            from_label,
                            filename,
                            "if",
                            combine_conditions(active_condition, condition),
                        )
            elif isinstance(stmt, renpy.ast.Menu):
                if active_condition:
                    route_menu_conditions[id(stmt)] = active_condition
            elif hasattr(stmt, 'block') and stmt.block:
                walk_for_edges(stmt.block, from_label, filename, from_type, active_condition)

    def wordcounter():
        """Count words and analyze the game script."""
        all_stmts = list(renpy.game.script.all_stmts)
        all_stmts.sort(key=lambda n: n.filename or "")
        screen_action_exprs.update(collect_screen_targets())

        known_languages = renpy.known_languages()

        # Build translation map: {identifier: {lang: translated_text}}
        # This maps original Say statements to their translations across languages
        say_translations = {}
        # From Translate blocks (Ren'Py 7.x)
        for node in all_stmts:
            if isinstance(node, renpy.ast.Translate) and node.language:
                for stmt in getattr(node, "block", []):
                    if isinstance(stmt, renpy.ast.Say) and stmt.what:
                        ident = getattr(node, "identifier", None)
                        if ident:
                            say_translations.setdefault(ident, {})[node.language] = clean_text(stmt.what)
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

            elif isinstance(node, renpy.ast.Default):
                if is_game_file(node.filename):
                    route_variables.append({
                        "name": node.varname,
                        "default_value": getattr(node.code, "source", "").strip(),
                        "type": "default",
                        "file": node.filename,
                        "line": getattr(node, "linenumber", 0),
                    })

        # Check through all nodes for TranslateSay
        has_translate_say = False
        if hasattr(renpy.ast, "TranslateSay"):
            for node in all_stmts:
                if isinstance(node, renpy.ast.TranslateSay):
                    has_translate_say = True
                    break

        # Find context (current label or scene)
        current_context = {}
        route_context_terminated = False

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

        # Second pass: gather dialogue and menu statistics
        last_say_text = None
        last_say_identifier = None  # for looking up prompt translations
        for node in all_stmts:
            # Track the last Say text for menu prompts
            # Include default-language Say/TranslateSay (language is None), skip translated ones
            if isinstance(node, renpy.ast.Say) and not getattr(node, "language", None):
                last_say_text = clean_text(node.what) if node.what else None
                last_say_identifier = getattr(node, "identifier", None)

            # Track context (labels)
            if isinstance(node, renpy.ast.Label):
                for lang in ['default'] + list(known_languages):
                    current_context[lang] = node.name
                route_context_terminated = False
                if is_game_file(node.filename):
                    ensure_route_label(node.name, node.filename, getattr(node, "linenumber", 0))

                    if getattr(node, "block", None):
                        walk_for_edges(node.block, node.name, node.filename, "label_block")
                        trailing_stmt = node.block[-1]
                        next_stmt = getattr(trailing_stmt, "next", None)
                    else:
                        next_stmt = getattr(node, "next", None)

                    # Follow the .next chain until we find a label, jump, call,
                    # return, or end of statements. This captures implicit
                    # fall-through between labels where non-control statements
                    # (Say, Python, Show, etc.) sit between them.
                    seen_next = set()
                    edge_count_before = len(route_edges)
                    while next_stmt is not None and id(next_stmt) not in seen_next:
                        seen_next.add(id(next_stmt))
                        if isinstance(next_stmt, (renpy.ast.Jump, renpy.ast.Return)):
                            add_edge_from_statement(node.name, next_stmt, node.filename)
                            break
                        if isinstance(next_stmt, renpy.ast.Call):
                            # Calls return — process edge but keep walking
                            add_edge_from_statement(node.name, next_stmt, node.filename)
                        if isinstance(next_stmt, renpy.ast.Label):
                            if is_route_label(next_stmt.name):
                                add_edge_from_statement(node.name, next_stmt, node.filename)
                                break
                            # Filtered label — skip past it and keep walking
                        # Non-terminal control flow: process edges but keep walking
                        if get_call_screen_name(next_stmt):
                            add_edge_from_statement(node.name, next_stmt, node.filename)
                        elif isinstance(next_stmt, renpy.ast.If):
                            add_edge_from_statement(node.name, next_stmt, node.filename)
                            # Only stop if ALL branches have terminal flow (jump/return)
                            # meaning execution can't continue past this If
                            if is_exhaustive_if(next_stmt):
                                break
                        elif isinstance(next_stmt, renpy.ast.Menu):
                            pass  # menus are handled separately in the main loop
                        next_stmt = getattr(next_stmt, "next", None)

            # Older versions (without TranslateSay) - handle both Say and Menu blocks
            if (not has_translate_say and isinstance(node, renpy.ast.Translate)):
                lang = node.language or "default"

                # Handle Say statements in translate blocks
                if (len(node.block) == 1 and isinstance(node.block[0], renpy.ast.Say)):
                    say = node.block[0]
                    all_lang_stats[lang]["filestats"][say.filename].add(say.what)

                    # Clean the text before adding to dialogue lines
                    cleaned_text = clean_text(say.what)
                    # Clean the character id if it exists
                    character_id = clean_text(say.who) if say.who else "narrator"

                    # Handle special Ren'Py characters
                    character_id, should_update_last = resolve_special_character(character_id, lang, last_character)

                    # Update last character for this language (only for non-special characters)
                    if should_update_last:
                        last_character[lang] = character_id

                    # Try to rescue broken game lines
                    if len(character_id) > 50:
                        cleaned_text = character_id + " " + cleaned_text
                        character_id = "narrator"

                    # Add to dialogue lines
                    dialogue_lines[lang].append({
                        "character": character_id,
                        "text": cleaned_text,
                        "file": say.filename,
                        "line": getattr(say, "linenumber", 0),
                        "context": current_context.get(lang, "")
                    })

                    if say.who and say.who in defined_characters:
                        all_lang_stats[lang]["characters"][say.who].add(say.what)
                    else:
                        all_lang_stats[lang]["characters"]["narrator"].add(say.what)
            elif has_translate_say and isinstance(node, renpy.ast.Say):
                if isinstance(node, renpy.ast.TranslateSay) and node.language:
                    lang = node.language
                else:
                    lang = "default"

                all_lang_stats[lang]["filestats"][node.filename].add(node.what)

                # Clean the text before adding to dialogue lines
                cleaned_text = clean_text(node.what)
                # Clean the character id if it exists
                who_var = getattr(node, "who", None)
                character_id = clean_text(who_var) if who_var else "narrator"

                # Handle special Ren'Py characters
                character_id, should_update_last = resolve_special_character(character_id, lang, last_character)

                # Update last character for this language (only for non-special characters)
                if should_update_last:
                    last_character[lang] = character_id

                # Try to rescue broken game lines
                if len(character_id) > 50:
                    cleaned_text = character_id + " " + cleaned_text
                    character_id = "narrator"

                # Add to dialogue lines with character, text, file, and line number
                dialogue_lines[lang].append({
                    "character": character_id,
                    "text": cleaned_text,
                    "file": node.filename,
                    "line": getattr(node, "linenumber", 0),
                    "context": current_context.get(lang, "")
                })

                if who_var and who_var in defined_characters:
                    all_lang_stats[lang]["characters"][who_var].add(node.what)
                else:
                    all_lang_stats[lang]["characters"]["narrator"].add(node.what)
            elif isinstance(node, renpy.ast.Menu):
                # Count menus for all languages (they're the same count)
                for lang in ['default'] + list(known_languages):
                    all_lang_stats[lang]["menu_count"] += 1

                menu_context = current_context.get("default", "")
                if route_context_terminated:
                    menu_context = ""

                # Capture the prompt: menu caption or last dialogue line
                menu_prompt = None
                for item_l, item_c, item_b in node.items:
                    if not item_l and item_b:
                        # Caption item (empty label = menu's own text)
                        for stmt in item_b:
                            if isinstance(stmt, renpy.ast.Say) and stmt.what:
                                menu_prompt = clean_text(stmt.what)
                        break
                if not menu_prompt:
                    menu_prompt = last_say_text

                # Get the enclosing if condition (if this menu is inside an if block)
                enclosing_condition = route_menu_conditions.get(id(node))

                for l, c, b in node.items:
                    if l:  # Only process non-empty choices
                        for lang in ['default'] + list(known_languages):
                            all_lang_stats[lang]["options_count"] += 1

                        original_text = clean_text(l)
                        character_id = "menu_choice"

                        # Combine enclosing if-condition with the choice's own condition
                        effective_condition = combine_conditions(enclosing_condition, c) if enclosing_condition else c

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
                        prompt_translations = {}
                        if last_say_identifier and last_say_identifier in say_translations:
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
                                "file": node.filename,
                                "line": getattr(node, "linenumber", 0),
                            })

                            # Record an edge from the menu's label to the choice target
                            if choice_target:
                                add_route_edge(
                                    menu_context,
                                    choice_target,
                                    "menu_choice",
                                    node.filename,
                                    getattr(node, "linenumber", 0),
                                    choice_text=ensure_unicode(original_text),
                                    condition=ensure_unicode(effective_condition) if effective_condition else None,
                                )

                            # Walk the menu choice block for nested edges
                            choice_context = "menu_choice:" + ensure_unicode(original_text)
                            walk_for_edges(b, menu_context, node.filename, choice_context)

                        # Add menu choice for default language
                        dialogue_lines["default"].append({
                            "character": character_id,
                            "text": original_text,
                            "file": node.filename,
                            "line": getattr(node, "linenumber", 0),
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
                                    "line": getattr(node, "linenumber", 0),
                                    "context": current_context.get(lang, "")
                                })
                                all_lang_stats[lang]["characters"]["menu_choice"].add(translated_text)
                            else:
                                dialogue_lines[lang].append({
                                    "character": character_id,
                                    "text": original_text,
                                    "file": node.filename,
                                    "line": getattr(node, "linenumber", 0),
                                    "context": current_context.get(lang, "")
                                })
                                all_lang_stats[lang]["characters"]["menu_choice"].add(l)
                    else:
                        # Empty caption choice - still walk the block for edges
                        if menu_context and is_game_file(node.filename):
                            walk_for_edges(b, menu_context, node.filename, "menu_block")

            elif isinstance(node, renpy.ast.Return):
                ctx = current_context.get("default", "")
                has_outgoing_route = False
                if ctx:
                    for edge in route_edges:
                        if edge.get("from_label") == ctx:
                            has_outgoing_route = True
                            break
                if (
                    ctx
                    and not route_context_terminated
                    and is_game_file(node.filename)
                    and ctx in route_labels
                    and ctx not in route_labels_with_screen_calls
                    and not has_outgoing_route
                ):
                    route_labels[ctx]["is_ending"] = True
                    route_context_terminated = True
        # Post-process: mark labels as endings if they jump/call back to main_menu
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
        """Generate a JSON report of the collected statistics."""
        result = {
            "languages": {},
            "file_statistics": {},
            "dialogue_lines": {}  # New section for dialogue lines
        }

        # Process language statistics
        for lang, data in all_lang_stats.items():
            total_blocks = 0
            total_words = 0
            for file_count in data["filestats"].values():
                total_blocks += file_count.blocks
                total_words += file_count.words

            lang_report = {
                "blocks": total_blocks,
                "words": total_words,
                "menus": data["menu_count"],
                "options": data["options_count"],
                "characters": {}
            }

            for char_var, char_count in data["characters"].items():
                if char_var == "narrator":
                    display_name = "Narrator"
                elif char_var == "menu_choice":
                    display_name = "Menu Choice"
                else:
                    display_name = defined_characters.get(char_var, {}).get(lang)
                char_info = {
                    "display_name": ensure_unicode(display_name) if display_name else None,
                    "blocks": char_count.blocks,
                    "words": char_count.words
                }
                lang_report["characters"][ensure_unicode(char_var)] = char_info

            result["languages"][ensure_unicode(lang)] = lang_report

        # Process file statistics
        result["file_statistics"] = {
            category: {
                ext: {
                    "count": stats.count,
                    "total_size": stats.total_size
                }
                for ext, stats in extensions.items()
            }
            for category, extensions in file_statistics.items()
        }

        result["file_statistics"]["summary"] = {
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

        # Add dialogue lines to the output
        for lang, lines in dialogue_lines.items():
            processed_lines = []
            for line in lines:
                processed_lines.append({
                    "character": ensure_unicode(line["character"]),
                    "text": ensure_unicode(line["text"]),
                    "file": ensure_unicode(line["file"]),
                    "line": line["line"],
                    "context": ensure_unicode(line["context"]) if line["context"] else None
                })
            result["dialogue_lines"][ensure_unicode(lang)] = processed_lines

        # Add route graph data
        processed_labels = []
        for name, info in route_labels.items():
            processed_labels.append({
                "name": ensure_unicode(name),
                "file": ensure_unicode(info["file"]),
                "line": info["line"],
                "is_ending": info.get("is_ending", False),
            })
        result["route_labels"] = processed_labels

        processed_edges = []
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
            processed_edges.append(processed_edge)
        result["route_edges"] = processed_edges

        processed_choices = []
        for choice in route_menu_choices:
            entry = {
                "from_label": ensure_unicode(choice["from_label"]) if choice["from_label"] else None,
                "text": ensure_unicode(choice["text"]) if choice["text"] else None,
                "condition": ensure_unicode(choice["condition"]) if choice["condition"] else None,
                "target_label": ensure_unicode(choice["target_label"]) if choice["target_label"] else None,
                "edge_type": ensure_unicode(choice["edge_type"]) if choice["edge_type"] else None,
                "prompt": ensure_unicode(choice["prompt"]) if choice.get("prompt") else None,
                "file": ensure_unicode(choice["file"]),
                "line": choice["line"],
            }
            if choice.get("translations"):
                entry["translations"] = choice["translations"]
            if choice.get("prompt_translations"):
                entry["prompt_translations"] = choice["prompt_translations"]
            processed_choices.append(entry)
        result["route_menu_choices"] = processed_choices

        processed_variables = []
        for var in route_variables:
            processed_variables.append({
                "name": ensure_unicode(var["name"]),
                "default_value": ensure_unicode(var["default_value"]) if var["default_value"] else None,
                "type": ensure_unicode(var["type"]),
                "file": ensure_unicode(var["file"]),
                "line": var["line"],
            })
        result["route_variables"] = processed_variables

        processed_var_changes = []
        for vc in route_variable_changes:
            processed_var_changes.append({
                "label": ensure_unicode(vc["label"]) if vc["label"] else None,
                "variable": ensure_unicode(vc["variable"]),
                "operation": ensure_unicode(vc["operation"]),
                "value": ensure_unicode(vc["value"]) if vc["value"] else None,
                "file": ensure_unicode(vc["file"]),
                "line": vc["line"],
                "context": ensure_unicode(vc.get("context", "python_block")),
            })
        result["route_variable_changes"] = processed_var_changes

        with io.open("stats.json", "w", encoding="utf-8") as outfile:
            outfile.write(u"{}".format(json.dumps(result, indent=4, ensure_ascii=False)))

    # Run the wordcounter and then quit
    wordcounter()
    renpy.quit()
