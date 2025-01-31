init 10000 python:
    from renpy import store
    import codecs
    import collections
    import io
    import json
    import re
    import os
    from renpy.loader import listdirfiles, archives

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

    # Primary data structure for language statistics
    all_lang_stats = collections.defaultdict(
        lambda: {
            "filestats": collections.defaultdict(Count),
            "menu_count": 0,
            "options_count": 0,
            "characters": collections.defaultdict(Count)
        }
    )

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
        """Get the actual size of a file using Ren'Py's loader"""
        try:
            f = renpy.loader.load(filename)
            f.seek(0, 2)  # Seek to end
            size = f.tell()  # Get position (size)
            f.close()
            return size
        except Exception:
            return 0

    def collect_file_statistics():
        """Collect file statistics using listdirfiles() with accurate archive sizes"""
        # Common file extensions
        image_extensions = {'.jpg', '.jpeg', '.png', '.webp', '.avif', '.svg'}
        audio_extensions = {'.wav', '.mp2', '.mp3', '.ogg', '.opus', '.flac'}
        video_extensions = {'.ogv', '.webm', '.mp4', '.mkv', '.avi'}

        # Get all files from both common and game directories
        all_files = listdirfiles(common=False)

        for directory, filename in all_files:
            # Skip json_stats.*, *.rpa files
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

    def wordcounter():
        """Count words and analyze game script"""
        # Pull the entire AST
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
                match = re.search(
                    r"Character\s*\(\s*_\(\s*[\"']((?:\\.|[^\"'])+)[\"']",
                    code_str
                )
                if match:
                    display_name = match.group(1)
                    translated_display_name = translate_string(display_name, None)
                    if translated_display_name:
                        display_name = translated_display_name
                else:
                    # Fall back to Character("Name", ...)
                    match = re.search(
                        r"Character\s*\(\s*[\"']((?:\\.|[^\"'])+)[\"']",
                        code_str
                    )
                    if match:
                        display_name = match.group(1)

                if not display_name or not display_name.strip():
                    display_name = varname

                display_name = re.sub(r"{[^}]*}", "", display_name).strip()
                display_name = codecs.decode(display_name, 'unicode_escape')

                defined_characters[varname] = {}
                defined_characters[varname]["default"] = translate_string(display_name, None)
                for lang in known_languages:
                    defined_characters[varname][lang] = translate_string(display_name, lang)

        hasTranslateSay = getattr(renpy.ast, "TranslateSay", None)

        # Second pass: gather dialogue and menu statistics
        for node in all_stmts:
            # Older versions of Ren'Py don't have a TranslateSay node
            if not hasTranslateSay and isinstance(node, renpy.ast.Translate) and len(node.block) == 1 and isinstance(node.block[0], renpy.ast.Say):
                lang = node.language or "default"
                say = node.block[0]
                all_lang_stats[lang]["filestats"][say.filename].add(say.what)
                if say.who and say.who in defined_characters:
                    all_lang_stats[lang]["characters"][say.who].add(say.what)
                else:
                    all_lang_stats[lang]["characters"]["narrator"].add(say.what)
            elif hasTranslateSay and isinstance(node, renpy.ast.Say):
                if hasattr(renpy.ast, "TranslateSay") and isinstance(node, renpy.ast.TranslateSay) and node.language:
                    lang = node.language
                else:
                    lang = "default"

                all_lang_stats[lang]["filestats"][node.filename].add(node.what)

                who_var = getattr(node, "who", None)
                if who_var:
                    if who_var in defined_characters:
                        all_lang_stats[lang]["characters"][who_var].add(node.what)
                else:
                    all_lang_stats[lang]["characters"]["narrator"].add(node.what)
            elif isinstance(node, renpy.ast.Menu):
                all_lang_stats["default"]["menu_count"] += 1
                for l, c, b in node.items:
                    all_lang_stats["default"]["options_count"] += 1

        # Collect file statistics
        collect_file_statistics()

        # Generate JSON report
        report_stats()

    def report_stats():
        """Generate JSON report of collected statistics"""
        result = {
            "languages": {},
            "file_statistics": {}
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
                lang_report["characters"][char_var] = {
                    "display_name": defined_characters[char_var][lang] if char_var != "narrator" else "Narrator",
                    "blocks": char_count.blocks,
                    "words": char_count.words
                }

            result["languages"][lang] = lang_report

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

        # Add summary totals
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

        # Write JSON report
        with io.open("stats.json", "w", encoding="utf-8") as outfile:
            outfile.write(
                u"{}".format(json.dumps(result, indent=4, ensure_ascii=False))
            )

    # Run the wordcounter, then quit
    wordcounter()
    renpy.quit()
