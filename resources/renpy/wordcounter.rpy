###############################################################################################################
### WORD COUNTER with Multi-Language & Character Tracking #####################################################
###############################################################################################################

## Preferences ################################################################################################

# Name of the folder your script files are in, if any (for example: "script/")
define script_folder_path = ""

###############################################################################################################
### The Code ##################################################################################################
###############################################################################################################

init 10000 python:

    from renpy import store
    import collections
    import io
    import json
    import re

    class Count(object):
        def __init__(self):
            self.blocks = 0      # Number of 'Say' statements
            self.words = 0       # Total words

        def add(self, text):
            self.blocks += 1
            self.words += len(text.split())

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
                match = re.search(r"Character\s*\(\s*_\(\s*[\"']([^\"']+)[\"']", code_str)
                if match:
                    display_name = match.group(1)
                    translated_display_name = renpy.translate_string(display_name, None)
                else:
                    # 2) Next, fall back to Character("Name", ...)
                    match = re.search(r"Character\s*\(\s*[\"']([^\"']+)[\"']", code_str)
                    if match:
                        display_name = match.group(1)

                # If neither pattern matched, default to the variable name
                if not display_name:
                    display_name = varname

                defined_characters[varname] = {}
                defined_characters[varname]["default"] = renpy.translate_string(display_name, None)
                for lang in known_languages:
                    defined_characters[varname][lang] = renpy.translate_string(display_name, lang)


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

        # Finally, generate a JSON report
        report_stats()

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
        #   }
        # }
        #
        result = {"languages": {}}

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

        # Write out to JSON
        with io.open("stats.json", "w", encoding="utf-8") as outfile:
            outfile.write(
                u"{}".format(json.dumps(result, indent=4, ensure_ascii=False))
            )

    # Run the wordcounter, then quit
    wordcounter()
    renpy.quit()
