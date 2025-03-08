init 10000 python:
    from __future__ import unicode_literals  # Helps ensure string consistency in Py2
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

    def wordcounter():
        """Count words and analyze the game script."""
        all_stmts = list(renpy.game.script.all_stmts)
        all_stmts.sort(key=lambda n: n.filename or "")

        known_languages = renpy.known_languages()

        # First pass: identify characters
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

        has_translate_say = hasattr(renpy.ast, "TranslateSay")

        # Find context (current label or scene)
        current_context = {}

        # Second pass: gather dialogue and menu statistics
        for node in all_stmts:
            # Track context (labels)
            if isinstance(node, renpy.ast.Label):
                # Update context
                for lang in ['default'] + list(known_languages):
                    current_context[lang] = node.name

            # Older versions (without TranslateSay)
            if (not has_translate_say and isinstance(node, renpy.ast.Translate) and
                    len(node.block) == 1 and isinstance(node.block[0], renpy.ast.Say)):
                lang = node.language or "default"
                say = node.block[0]
                all_lang_stats[lang]["filestats"][say.filename].add(say.what)

                # Clean the text before adding to dialogue lines
                cleaned_text = clean_text(say.what)
                # Clean the character id if it exists
                character_id = clean_text(say.who) if say.who else "narrator"

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
                all_lang_stats["default"]["menu_count"] += 1
                for l, c, b in node.items:
                    all_lang_stats["default"]["options_count"] += 1
                    # Also track menu choices as dialogue
                    if l:  # Only add non-empty choices
                        # Clean the text before adding
                        cleaned_text = clean_text(l)
                        dialogue_lines["default"].append({
                            "character": "menu_choice",
                            "text": cleaned_text,
                            "file": node.filename,
                            "line": getattr(node, "linenumber", 0),
                            "context": current_context.get("default", "")
                        })

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
                display_name = (defined_characters.get(char_var, {}).get(lang)
                                if char_var != "narrator" else "Narrator")
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

        with io.open("stats.json", "w", encoding="utf-8") as outfile:
            outfile.write(u"{}".format(json.dumps(result, indent=4, ensure_ascii=False)))

    # Run the wordcounter and then quit
    wordcounter()
    renpy.quit()
