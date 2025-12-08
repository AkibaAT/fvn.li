#!/usr/bin/env python3
"""
Convert GameMaker JSON dialogue files to Ren'Py stats JSON format.

This script processes JSON files exported from GameMaker Studio dialogue systems
and converts them to the stats.json format expected by the fvn.li game stats service.
"""

import argparse
import json
import os
import re
import sys
from collections import defaultdict
from pathlib import Path
from typing import Dict, List, Any, Optional, Tuple

# Try to use json5 for better parsing (handles trailing commas, comments, etc.)
try:
    import json5
    JSON_PARSER = json5
except ImportError:
    JSON_PARSER = None


def parse_json_file(file_path: Path) -> dict:
    """Parse a JSON file, handling trailing commas if json5 is available."""
    with open(file_path, 'r', encoding='utf-8-sig') as f:
        content = f.read()

    # Try json5 first if available
    if JSON_PARSER is not None:
        return JSON_PARSER.loads(content)

    # Fallback: strip trailing commas before parsing with standard json
    # This regex removes trailing commas before ] or }
    content = re.sub(r',(\s*[\]\}])', r'\1', content)
    return json.loads(content)


# Character code to display name mapping
# These codes appear before # in dialogue lines (e.g., "r# [rui20] Hello")
CHARACTER_CODES = {
    # Main characters
    'r': 'Roui',
    's': 'Serban',
    'br': 'Brilla',
    'e': 'Emeral',
    'v': 'Vander',
    'a': 'Alek',
    'm': 'Marestail',
    'm_s': 'Marestail',
    'm_s2': 'Marestail',
    'cam': 'Cameya',
    'fem': 'Femi',
    'w': 'Waffle',
    'l': 'Lazy',
    'g': 'Grullo',
    'gr': 'Grullo',
    'cy': 'Candy',
    'f': 'Flynn',
    'fl': 'Flynn',
    'b': 'Bo',
    'bo': 'Bo',

    # Supporting characters
    'rad': 'Radio',
    'bs': 'Black snake',
    'lm': 'Lock Mereoak',
    'lo': 'Lock',
    'dop': 'Doppelganger',
    'do': 'Doppelganger',
    'ws': 'Weird sahash',
    'j': 'Junyver',
    'ras': 'Rasko',
    'ra': 'Rasko',
    'ra_r': 'Rasko',
    'ing': 'Ingrid',
    'i': 'Ingrid',
    'an': 'Anoosh',
    'aa': 'Anoosh',
    'raa': 'Raakel',
    'rr': 'Raakel',
    'ku': 'Kudret',
    'k': 'Kudret',
    'kar': 'Kariom',
    'fe': 'Fel',
    'rob': 'Roban',
    'ro': 'Roban',
    'al': 'Alain',
    'bar': 'Bartender',
    'ph': 'Phollie',
    'fol': 'Phollie',
    'dr': 'Drobeski',
    'd': 'Drobeski',
    'st': 'Stranger',
    'el': 'Elias',
    'vc': 'Voice from the crowd',
    'cro': 'Crowd',
    'rv': "Rasko's volunteer",
    'vol_1': "Rasko's volunteer",
    'vol_2': "Rasko's volunteer",
    'fw': 'Fireworshiper',
    'monk': 'Fireworshiper',
    'jan': 'Janice',
    'ur': 'Urosh',
    'mi': 'Milosh',
    'ch': 'Churi',
    'veg': 'Veglamb',
    'ymi': 'Ymir',
    'anna': 'Anna',
    'pg': 'Pogue',

    # Generic NPCs
    'npc_1': 'NPC',
    'npc_2': 'NPC',
    'npc_3': 'NPC',
    'npc_p': 'NPC',
    'npc_d': 'NPC',
    'n': 'Narrator',
}

# Language directory to ISO code mapping
LANGUAGE_MAP = {
    'en': 'eng',
    'ru': 'rus',
    'it': 'ita',
    'kp': 'kor',  # "kp" appears to be Korean (or Korean-like language code)
}


class DialogueStats:
    """Track dialogue statistics for a character."""

    def __init__(self):
        self.blocks = 0
        self.words = 0

    def add(self, text: str):
        """Add a dialogue line to stats."""
        if text:
            self.blocks += 1
            self.words += len(text.split())


def clean_text(text: str) -> str:
    """Remove animation tags and clean up dialogue text."""
    if not text:
        return text

    # Remove animation tags like [rui20], [bod00], etc.
    text = re.sub(r'\[[\w\d_]+\]', '', text)

    # Remove leading/trailing whitespace
    text = text.strip()

    return text


def parse_dialogue_line(line: str) -> Tuple[Optional[str], str]:
    """
    Parse a dialogue line and extract character code and text.

    Format: "character_code# [animation] text"
    Example: "r# [rui20] Hello there."

    Returns: (character_code, cleaned_text)
    """
    # Match pattern: code# text
    match = re.match(r'^(\w+)#\s*(.*)$', line)
    if match:
        char_code = match.group(1).lower()
        text = match.group(2)
        cleaned_text = clean_text(text)
        return char_code, cleaned_text

    # No character code - treat as narration or description
    return None, clean_text(line)


def get_character_display_name(char_code: str, known_characters: dict) -> str:
    """Get the display name for a character code."""
    if char_code is None:
        return 'Narrator'

    # Check known characters first
    if char_code in known_characters:
        return known_characters[char_code]

    # Check our static mapping
    if char_code in CHARACTER_CODES:
        return CHARACTER_CODES[char_code]

    # Fallback to the code itself (capitalized)
    return char_code.capitalize()


class GameMakerConverter:
    """Convert GameMaker JSON files to stats JSON format."""

    def __init__(self, input_dir: str, stats_only: bool = False):
        self.input_dir = Path(input_dir)
        self.stats_only = stats_only
        self.language_stats = defaultdict(lambda: {
            'blocks': 0,
            'words': 0,
            'menus': 0,
            'options': 0,
            'characters': defaultdict(DialogueStats)
        })
        self.dialogue_lines = defaultdict(list)
        self.character_names = {}  # {lang_code: {char_code: display_name}}
        self.discovered_codes = set()  # Track unknown character codes

    def load_character_names_from_meta(self, meta_path: Path, lang_code: str):
        """Load character display names from Meta.json textbox array."""
        try:
            data = parse_json_file(meta_path)

            if 'textbox' in data and isinstance(data['textbox'], list):
                # The textbox array contains character names in a specific order
                # We'll use this to update display names for this language
                textbox = data['textbox']

                # The textbox array mapping (based on observed order in Meta.json)
                textbox_mapping = [
                    (1, 'r'),      # Roui
                    (2, 's'),      # Serban
                    (3, 'br'),     # Brilla
                    (4, 'e'),      # Emeral
                    (5, 'v'),      # Vander
                    (6, 'a'),      # Alek
                    (7, 'm'),      # Marestail
                    (8, 'cam'),    # Cameya
                    (10, 'fem'),   # Femi
                    (11, 'w'),     # Waffle
                    (12, 'l'),     # Lazy
                    (13, 'g'),     # Grullo
                    (14, 'cy'),    # Candy
                    (15, 'f'),     # Flynn
                    (16, 'b'),     # Bo
                    (17, 'rad'),   # Radio
                    (18, 'bs'),    # Black snake
                    (19, 'lm'),    # Lock Mereoak
                    (20, 'dop'),   # Doppelganger
                    (21, 'ws'),    # Weird sahash
                    (22, 'j'),     # Junyver
                    (23, 'ras'),   # Rasko
                    (24, 'ing'),   # Ingrid
                    (25, 'an'),    # Anoosh
                    (26, 'raa'),   # Raakel
                    (27, 'ku'),    # Kudret
                    (28, 'fe'),    # Fel
                    (29, 'rob'),   # Roban
                    (30, 'al'),    # Alain
                    (31, 'bar'),   # Bartender
                    (32, 'ph'),    # Phollie
                    (33, 'dr'),    # Drobeski
                    (34, 'st'),    # Stranger
                    (36, 'el'),    # Elias
                    (37, 'kar'),   # Kariom
                    (66, 'ur'),    # Urosh
                ]

                if lang_code not in self.character_names:
                    self.character_names[lang_code] = {}

                for idx, char_code in textbox_mapping:
                    if idx < len(textbox) and textbox[idx]:
                        self.character_names[lang_code][char_code] = textbox[idx]

        except Exception as e:
            print(f"Warning: Failed to load Meta.json from {meta_path}: {e}", file=sys.stderr)

    def process_json_file(self, file_path: Path, lang_code: str, category: str):
        """Process a single JSON file."""
        try:
            data = parse_json_file(file_path)

            file_name = str(file_path.relative_to(self.input_dir))

            # Process based on file category
            if category == 'texts':
                self._process_dialogue_file(data, file_name, lang_code)
            elif category == 'mindscape':
                self._process_mindscape_file(data, file_name, lang_code)
            elif category == 'journal':
                self._process_journal_file(data, file_name, lang_code)
            elif category == 'inventory':
                self._process_inventory_file(data, file_name, lang_code)
            elif category == 'meta':
                # Meta files contain UI strings, not dialogue
                pass

        except json.JSONDecodeError as e:
            print(f"Warning: Invalid JSON in {file_path}: {e}", file=sys.stderr)
        except Exception as e:
            print(f"Warning: Failed to process {file_path}: {e}", file=sys.stderr)

    def _process_dialogue_file(self, data: dict, file_name: str, lang_code: str):
        """Process a dialogue JSON file from the texts directory."""
        line_num = 0

        for section_name, section_content in data.items():
            if not isinstance(section_content, list):
                continue

            for item in section_content:
                if not isinstance(item, str):
                    continue

                line_num += 1
                char_code, text = parse_dialogue_line(item)

                if not text:
                    continue

                # Track unknown character codes
                if char_code and char_code not in CHARACTER_CODES:
                    self.discovered_codes.add(char_code)

                # Get character ID (use code or 'narrator')
                character_id = char_code if char_code else 'narrator'

                # Add to dialogue lines (unless in stats-only mode)
                if not self.stats_only:
                    self.dialogue_lines[lang_code].append({
                        'character': character_id,
                        'text': text,
                        'file': file_name,
                        'line': line_num,
                        'context': section_name
                    })

                # Update stats
                self.language_stats[lang_code]['blocks'] += 1
                self.language_stats[lang_code]['words'] += len(text.split())
                self.language_stats[lang_code]['characters'][character_id].add(text)

    def _process_mindscape_file(self, data: dict, file_name: str, lang_code: str):
        """Process a mindscape JSON file (character descriptions)."""
        line_num = 0

        # Get character name from mindscape_name if available
        char_name = None
        if 'mindscape_name' in data and isinstance(data['mindscape_name'], list):
            char_name = data['mindscape_name'][0] if data['mindscape_name'] else None

        # Process mindscape entries (character descriptions)
        if 'mindscape' in data and isinstance(data['mindscape'], list):
            for item in data['mindscape']:
                if not isinstance(item, str) or not item.strip():
                    continue

                line_num += 1
                text = clean_text(item)

                if not text:
                    continue

                # Mindscape entries are narration/description
                character_id = 'narrator'

                if not self.stats_only:
                    self.dialogue_lines[lang_code].append({
                        'character': character_id,
                        'text': text,
                        'file': file_name,
                        'line': line_num,
                        'context': f'mindscape:{char_name}' if char_name else 'mindscape'
                    })

                self.language_stats[lang_code]['blocks'] += 1
                self.language_stats[lang_code]['words'] += len(text.split())
                self.language_stats[lang_code]['characters'][character_id].add(text)

        # Process diary entries
        if 'dairy' in data and isinstance(data['dairy'], list):  # Note: typo "dairy" in source files
            for item in data['dairy']:
                if not isinstance(item, str) or not item.strip():
                    continue

                line_num += 1
                text = clean_text(item)

                if not text:
                    continue

                character_id = 'narrator'

                if not self.stats_only:
                    self.dialogue_lines[lang_code].append({
                        'character': character_id,
                        'text': text,
                        'file': file_name,
                        'line': line_num,
                        'context': f'diary:{char_name}' if char_name else 'diary'
                    })

                self.language_stats[lang_code]['blocks'] += 1
                self.language_stats[lang_code]['words'] += len(text.split())
                self.language_stats[lang_code]['characters'][character_id].add(text)

    def _process_journal_file(self, data: dict, file_name: str, lang_code: str):
        """Process a journal JSON file (quest descriptions)."""
        line_num = 0

        # Get quest name
        quest_name = None
        if 'journal_name' in data and isinstance(data['journal_name'], list):
            quest_name = data['journal_name'][0] if data['journal_name'] else None

        # Process journal entries
        if 'journal' in data and isinstance(data['journal'], list):
            for item in data['journal']:
                if not isinstance(item, str) or not item.strip():
                    continue

                line_num += 1
                text = clean_text(item)

                if not text:
                    continue

                # Journal entries are narration
                character_id = 'narrator'

                if not self.stats_only:
                    self.dialogue_lines[lang_code].append({
                        'character': character_id,
                        'text': text,
                        'file': file_name,
                        'line': line_num,
                        'context': f'journal:{quest_name}' if quest_name else 'journal'
                    })

                self.language_stats[lang_code]['blocks'] += 1
                self.language_stats[lang_code]['words'] += len(text.split())
                self.language_stats[lang_code]['characters'][character_id].add(text)

    def _process_inventory_file(self, data: dict, file_name: str, lang_code: str):
        """Process an inventory JSON file (item descriptions)."""
        if 'item' not in data or not isinstance(data['item'], list):
            return

        items = data['item']
        if len(items) < 4:  # Need at least name, features, and description
            return

        # Item format: [name, feature1, feature2, description, ?, ?]
        item_name = items[0] if items[0] else None

        # Combine item features and description
        line_num = 0
        for idx, item in enumerate(items[1:], 1):
            if not isinstance(item, str) or not item.strip() or item == 'none':
                continue

            line_num += 1
            text = clean_text(item)

            if not text:
                continue

            character_id = 'narrator'

            if not self.stats_only:
                self.dialogue_lines[lang_code].append({
                    'character': character_id,
                    'text': text,
                    'file': file_name,
                    'line': line_num,
                    'context': f'item:{item_name}' if item_name else 'item'
                })

            self.language_stats[lang_code]['blocks'] += 1
            self.language_stats[lang_code]['words'] += len(text.split())
            self.language_stats[lang_code]['characters'][character_id].add(text)

    def convert(self) -> Dict[str, Any]:
        """Convert all JSON files to stats JSON format."""
        print(f"Scanning {self.input_dir} for JSON files...")
        if self.stats_only:
            print("Mode: Stats-only (dialogue text will not be included)")
        else:
            print("Mode: Full export (including dialogue text)")

        # Process each language directory
        for lang_dir in self.input_dir.iterdir():
            if not lang_dir.is_dir():
                continue

            lang_name = lang_dir.name
            if lang_name not in LANGUAGE_MAP:
                print(f"Warning: Unknown language directory '{lang_name}', skipping")
                continue

            lang_code = LANGUAGE_MAP[lang_name]
            print(f"\nProcessing language: {lang_name} ({lang_code})")

            # Load character names from Meta.json first
            meta_path = lang_dir / 'meta' / 'Meta.json'
            if meta_path.exists():
                self.load_character_names_from_meta(meta_path, lang_code)

            # Process each category directory
            categories = ['texts', 'mindscape', 'journal', 'inventory', 'meta']
            for category in categories:
                category_dir = lang_dir / category
                if not category_dir.exists():
                    continue

                # Find all JSON files recursively
                json_files = list(category_dir.rglob('*.json'))
                if json_files:
                    print(f"  Found {len(json_files)} files in {category}/")

                for json_file in json_files:
                    self.process_json_file(json_file, lang_code, category)

        # Report unknown character codes
        if self.discovered_codes:
            print(f"\nDiscovered unknown character codes: {sorted(self.discovered_codes)}")

        # Build final stats structure
        result = {
            'languages': {},
            'file_statistics': {
                'summary': {
                    'total_image': 0,
                    'total_audio': 0,
                    'total_video': 0,
                    'total_other': 0,
                    'total_size': 0
                }
            }
        }

        # Only include dialogue_lines section if not in stats-only mode
        if not self.stats_only:
            result['dialogue_lines'] = {}

        # Convert language stats
        for lang_code, stats in self.language_stats.items():
            result['languages'][lang_code] = {
                'blocks': stats['blocks'],
                'words': stats['words'],
                'menus': stats['menus'],
                'options': stats['options'],
                'characters': {}
            }

            # Get character names for this language
            lang_char_names = self.character_names.get(lang_code, {})

            # Convert character stats
            for char_id, char_stats in stats['characters'].items():
                # Get display name
                if char_id == 'narrator':
                    display_name = 'Narrator'
                elif char_id in lang_char_names:
                    display_name = lang_char_names[char_id]
                elif char_id in CHARACTER_CODES:
                    display_name = CHARACTER_CODES[char_id]
                else:
                    display_name = char_id.capitalize()

                result['languages'][lang_code]['characters'][char_id] = {
                    'display_name': display_name,
                    'blocks': char_stats.blocks,
                    'words': char_stats.words
                }

        # Add dialogue lines (only if not in stats-only mode)
        if not self.stats_only:
            for lang_code, lines in self.dialogue_lines.items():
                result['dialogue_lines'][lang_code] = lines

        return result

    def save_stats(self, output_path: str):
        """Save stats to JSON file."""
        stats = self.convert()

        with open(output_path, 'w', encoding='utf-8') as f:
            json.dump(stats, f, indent=2, ensure_ascii=False)

        # Print summary
        print(f"\nConversion complete!")
        print(f"Output saved to: {output_path}")
        print(f"\nSummary:")
        for lang_code in sorted(stats['languages'].keys()):
            lang_stats = stats['languages'][lang_code]
            print(f"  {lang_code}: {lang_stats['blocks']} blocks, {lang_stats['words']} words, {len(lang_stats['characters'])} characters")


def main():
    """Main entry point."""
    parser = argparse.ArgumentParser(
        description='Convert GameMaker JSON dialogue files to Ren\'Py stats JSON format.',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog='''
Examples:
  # Full export with dialogue text
  python gamemaker_to_stats.py GameMaker/ stats.json

  # Stats-only mode (for commercial games)
  python gamemaker_to_stats.py GameMaker/ stats.json --stats-only
        '''
    )

    parser.add_argument(
        'input_directory',
        help='Directory containing GameMaker language folders (en/, ru/, etc.)'
    )

    parser.add_argument(
        'output_file',
        nargs='?',
        default='stats.json',
        help='Output JSON file path (default: stats.json)'
    )

    parser.add_argument(
        '--stats-only',
        action='store_true',
        help='Export only statistics without dialogue text (useful for commercial games)'
    )

    args = parser.parse_args()

    if not os.path.isdir(args.input_directory):
        print(f"Error: Input directory not found: {args.input_directory}")
        sys.exit(1)

    converter = GameMakerConverter(args.input_directory, stats_only=args.stats_only)
    converter.save_stats(args.output_file)


if __name__ == '__main__':
    main()
