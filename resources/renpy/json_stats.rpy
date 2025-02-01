init 10000 python:
    from __future__ import unicode_literals  # Helps ensure string consistency in Py2.
    import codecs
    import collections
    import io
    import json
    import os
    import re
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

    # Primary data structure for language statistics.
    all_lang_stats = collections.defaultdict(make_lang_stats)

    # File statistics by type.
    file_statistics = {
        "image": collections.defaultdict(FileStats),
        "audio": collections.defaultdict(FileStats),
        "video": collections.defaultdict(FileStats),
        "other": collections.defaultdict(FileStats)
    }

    # Keep track of defined characters.
    defined_characters = {}

    def get_file_size(filename):
        """Get the actual size of a file using Ren'Py's loader."""
        try:
            # Use a context manager to ensure the file is closed.
            with closing(renpy.loader.load(filename)) as f:
                f.seek(0, 2)  # Seek to end.
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
            # Skip json_stats.* and .rpa files.
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

    # Helper to decode escaped Unicode sequences in a way that works in both Python 2 and 3.
    def decode_unicode_escape(s):
        import sys
        if sys.version_info[0] < 3:
            return codecs.decode(s, 'unicode_escape')
        else:
            try:
                # Encoding with latin-1 ensures code points 0–255 pass through unchanged.
                return s.encode('latin-1').decode('unicode_escape')
            except Exception:
                return s

    # Precompile regexes for character extraction.
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

        # First pass: identify characters.
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

                # Remove inline Ren'Py text tags and decode escaped characters.
                display_name = re.sub(r"{[^}]*}", "", display_name).strip()
                display_name = decode_unicode_escape(display_name)

                defined_characters[varname] = {}
                defined_characters[varname]["default"] = translate_string(display_name, None)
                for lang in known_languages:
                    defined_characters[varname][lang] = translate_string(display_name, lang)

        has_translate_say = hasattr(renpy.ast, "TranslateSay")

        # Second pass: gather dialogue and menu statistics.
        for node in all_stmts:
            # Older versions (without TranslateSay).
            if (not has_translate_say and isinstance(node, renpy.ast.Translate) and
                    len(node.block) == 1 and isinstance(node.block[0], renpy.ast.Say)):
                lang = node.language or "default"
                say = node.block[0]
                all_lang_stats[lang]["filestats"][say.filename].add(say.what)
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
                who_var = getattr(node, "who", None)
                if who_var and who_var in defined_characters:
                    all_lang_stats[lang]["characters"][who_var].add(node.what)
                else:
                    all_lang_stats[lang]["characters"]["narrator"].add(node.what)
            elif isinstance(node, renpy.ast.Menu):
                all_lang_stats["default"]["menu_count"] += 1
                for l, c, b in node.items:
                    all_lang_stats["default"]["options_count"] += 1

        collect_file_statistics()
        report_stats()

    def report_stats():
        """Generate a JSON report of the collected statistics."""
        result = {
            "languages": {},
            "file_statistics": {}
        }

        # Process language statistics.
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
                lang_report["characters"][char_var] = {
                    "display_name": display_name,
                    "blocks": char_count.blocks,
                    "words": char_count.words
                }

            result["languages"][lang] = lang_report

        # Process file statistics.
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

        with io.open("stats.json", "w", encoding="utf-8") as outfile:
            outfile.write(json.dumps(result, indent=4, ensure_ascii=False))

    # Run the wordcounter and then quit.
    wordcounter()
    renpy.quit()
