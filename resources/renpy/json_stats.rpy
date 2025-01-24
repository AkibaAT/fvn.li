init 10000 python:
    from renpy import store
    import codecs
    import collections
    import io
    import json
    import re
    import os

    def translate_string(text, language=None):
        if renpy.version_tuple >= (8, 0, 0, 0):
            # We are running Ren'Py 8 or later
            return renpy.translate_string(text, language=language)
        else:
            # Fallback: Just return the original text
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

    # Primary data structure: for each language, we collect:
    #   {
    #       "filestats": { filename: Count() },
    #       "menu_count": int,
    #       "options_count": int,
    #       "characters": { char_varname: Count() }
    #   }
    #
    # We'll treat the default language as "default".
    all_lang_stats = collections.defaultdict(
        lambda: {
            "filestats": collections.defaultdict(Count),
            "menu_count": 0,
            "options_count": 0,
            "characters": collections.defaultdict(Count)
        }
    )

    # Separate data structure for file statistics
    file_statistics = {
        "images": collections.defaultdict(FileStats),  # Extension -> FileStats
        "audio": collections.defaultdict(FileStats),
        "video": collections.defaultdict(FileStats),
        "other": collections.defaultdict(FileStats)
    }

    # Keep a dictionary of defined characters: varname -> display name (optional)
    defined_characters = {}

    def wordcounter():
        # Pull the entire AST
        all_stmts = list(renpy.game.script.all_stmts)
        all_stmts.sort(key=lambda n: n.filename or "")

        known_languages = renpy.known_languages()

        # First pass: identify which variables are characters by searching Define statements
        for node in all_stmts:
            if isinstance(node, renpy.ast.Define):
                # node.varname is the variable name being defined
                # node.code is the string expression on the right side
                # e.g. "Character('Eileen', ...)" or "Character(\"Eileen\")"
                varname = node.varname
                # Safely extract the source string from the PyCode object
                code_str = getattr(node.code, "source", "")  # Fallback to "" if no source
                code_str = code_str.strip()

                # We want to handle both:
                #   Character("Name", ...)
                #   Character(_("Name"), ...)
                # So we can try multiple regex patterns in sequence:

                display_name = None

                # 1) First, look for something like Character(_("<Name>"), ...)
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
                    # 2) Next, fall back to Character("Name", ...)
                    match = re.search(
                        r"Character\s*\(\s*[\"']((?:\\.|[^\"'])+)[\"']",
                        code_str
                    )
                    if match:
                        display_name = match.group(1)

                # If neither pattern matched, default to the variable name
                if not display_name or not display_name.strip():
                    display_name = varname

                display_name = re.sub(r"{[^}]*}", "", display_name).strip()
                display_name = codecs.decode(display_name, 'unicode_escape')

                defined_characters[varname] = {}
                defined_characters[varname]["default"] = translate_string(display_name, None)
                for lang in known_languages:
                    defined_characters[varname][lang] = translate_string(display_name, lang)

        # Second pass: gather stats from each statement
        for node in all_stmts:
            # 1) Dialogue lines ("Say" or "TranslateSay")
            if isinstance(node, renpy.ast.Say):
                # Check if it's a translated line
                if hasattr(renpy.ast, "TranslateSay") and isinstance(node, renpy.ast.TranslateSay) and node.language:
                    lang = node.language
                else:
                    lang = "default"

                # Add to file stats
                all_lang_stats[lang]["filestats"][node.filename].add(node.what)

                # If there's a .who attached, try to see if it matches one of our known character varnames
                # or is a direct string.  Depending on your project, node.who could be a Python expression
                # or a string referring to the character object. We'll do a simple approach here:
                who_var = getattr(node, "who", None)
                if who_var:
                    # If who_var is literally the same as a known define (like 'e'), track stats under that
                    if who_var in defined_characters:
                        all_lang_stats[lang]["characters"][who_var].add(node.what)
                else:
                    all_lang_stats[lang]["characters"]["narrator"].add(node.what)

            # 2) Menus
            elif isinstance(node, renpy.ast.Menu):
                # Menus typically aren't stored under a specific language, unless they're inside a translate block.
                # For simplicity, we’ll log these to default. You can customize if you want multi-language menus.
                all_lang_stats["default"]["menu_count"] += 1
                for l, c, b in node.items:
                    all_lang_stats["default"]["options_count"] += 1

            # 3) Translate blocks
            elif isinstance(node, renpy.ast.Translate):
                # If you want to handle entire translated menus or other statements,
                # you'd parse inside these blocks. For now, we rely on TranslateSay for lines,
                # so there's nothing special to do here.
                pass

        # Collect file statistics
        collect_file_statistics()

        # Finally, generate a JSON report
        report_stats()

    def read_rpa_archive(rpa_path):
        """
        Reads an RPA file and returns a list of tuples (filename, size).
        Correctly handles hexadecimal offsets.
        """
        import zipfile

        try:
            with open(rpa_path, 'rb') as f:
                # Read RPA header to find ZIP offset
                header = f.read(8)
                if not header.startswith(b'RPA-3.0 '):
                    return []

                # Read 8-byte hexadecimal offset (not 12-byte)
                offset_hex = f.read(8).decode('ascii').strip()
                offset = int(offset_hex, 16)  # Convert from HEXADECIMAL
                f.seek(offset)

                # Open the ZIP part
                with zipfile.ZipFile(f) as z:
                    return [(info.filename, info.file_size) for info in z.infolist()]
        except:
            return []

    def collect_file_statistics():
        # Common file extensions
        image_extensions = {'.jpg', '.jpeg', '.png', '.webp', '.avif', '.svg'}
        audio_extensions = {'.wav', '.mp2', '.mp3', '.ogg', '.opus', '.flac'}
        video_extensions = {'.ogv', '.webm', '.mp4', '.mkv', '.avi'}

        for root, dirs, files in os.walk(renpy.config.gamedir):
            for file in files:
                filepath = os.path.join(root, file)
                ext = os.path.splitext(file)[1].lower()

                # Check if file is an RPA archive
                if ext == '.rpa':
                    # Process RPA contents without extracting
                    for (internal_file, size) in read_rpa_archive(filepath):
                        internal_ext = os.path.splitext(internal_file)[1].lower()
                        if internal_ext in image_extensions:
                            file_statistics["images"][internal_ext].add_file(size)
                        elif internal_ext in audio_extensions:
                            file_statistics["audio"][internal_ext].add_file(size)
                        elif internal_ext in video_extensions:
                            file_statistics["video"][internal_ext].add_file(size)
                        else:
                            file_statistics["other"][internal_ext].add_file(size)
                else:
                    # Process regular files
                    try:
                        size = os.path.getsize(filepath)
                        if ext in image_extensions:
                            file_statistics["images"][ext].add_file(size)
                        elif ext in audio_extensions:
                            file_statistics["audio"][ext].add_file(size)
                        elif ext in video_extensions:
                            file_statistics["video"][ext].add_file(size)
                        else:
                            file_statistics["other"][ext].add_file(size)
                    except:
                        continue

    def report_stats():
        # We'll create a JSON structure of the form:
        #
        # {
        #   "languages": {
        #       "default": {
        #           "blocks": ...,
        #           "words": ...,
        #           "menus": ...,
        #           "options": ...,
        #           "characters": {
        #               "e": { "blocks":..., "words":..., "characters":... },
        #               "m": { ... },
        #               ...
        #           }
        #       },
        #       "french": { ... },
        #       "italian": { ... }
        #   },
        #   "file_statistics": {
        #       "images": { ... },
        #       "audio": { ... },
        #       "video": { ... },
        #       "other": { ... },
        #       "summary": { ... }
        #   }
        # }
        #
        result = {
            "languages": {},
            "file_statistics": {}
        }

        for lang, data in all_lang_stats.items():
            # Aggregate file-level counts
            total_blocks = 0
            total_words = 0
            for file_count in data["filestats"].values():
                total_blocks += file_count.blocks
                total_words += file_count.words

            # Put it all together
            lang_report = {
                "blocks": total_blocks,
                "words": total_words,
                "menus": data["menu_count"],
                "options": data["options_count"],
                "characters": {}
            }

            # Add character stats
            for char_var, char_count in data["characters"].items():
                lang_report["characters"][char_var] = {
                    "display_name": defined_characters[char_var][lang] if char_var != "narrator" else "Narrator",
                    "blocks": char_count.blocks,
                    "words": char_count.words
                }

            result["languages"][lang] = lang_report

        # Add file statistics
        result["file_statistics"] = {
            category: {
                ext: {
                    "count": stats.count,
                    "total_size": stats.total_size  # Report size in bytes
                }
                for ext, stats in extensions.items()
            }
            for category, extensions in file_statistics.items()
        }

        # Add total counts
        result["file_statistics"]["summary"] = {
            "total_images": sum(stats.count for stats in file_statistics["images"].values()),
            "total_audio": sum(stats.count for stats in file_statistics["audio"].values()),
            "total_video": sum(stats.count for stats in file_statistics["video"].values()),
            "total_other": sum(stats.count for stats in file_statistics["other"].values()),
            "total_size": sum(
                stats.total_size
                for category in file_statistics.values()
                for stats in category.values()
            )  # Report total size in bytes
        }

        # Write out to JSON
        with io.open("stats.json", "w", encoding="utf-8") as outfile:
            outfile.write(
                u"{}".format(json.dumps(result, indent=4, ensure_ascii=False))
            )

    # Run the wordcounter, then quit
    wordcounter()
    renpy.quit()
