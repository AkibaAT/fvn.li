#!/usr/bin/env python3
"""
Convert Unity MonoBehaviour YAML files to Ren'Py stats JSON format.

This script processes YAML files from Unity dialogue systems and converts them
to the stats.json format expected by the fvn.li game stats service.
"""

import argparse
import json
import os
import re
import sys
from collections import defaultdict
from pathlib import Path
from typing import Dict, List, Any

try:
    import yaml
except ImportError:
    print("Error: PyYAML is required. Install it with: pip install pyyaml")
    sys.exit(1)


SafeUnityBaseLoader = getattr(yaml, 'CSafeLoader', yaml.SafeLoader)


# Custom YAML loader for Unity files
class UnityLoader(SafeUnityBaseLoader):
    """Custom YAML loader that handles Unity's custom tags."""
    pass


def unity_constructor(loader, tag_suffix, node):
    """Constructor for Unity YAML tags."""
    if isinstance(node, yaml.MappingNode):
        return loader.construct_mapping(node)
    elif isinstance(node, yaml.SequenceNode):
        return loader.construct_sequence(node)
    else:
        return loader.construct_scalar(node)


# Register Unity tag handler
UnityLoader.add_multi_constructor('tag:unity3d.com,2011:', unity_constructor)


# Language GUID to ISO code mapping
# These are common Unity language GUIDs - may need to be updated based on the specific game
LANGUAGE_GUID_MAP = {
    '14d75042e9456774b895509f90b306b1': 'eng',  # English
    'c67cf525393c014418a0a3c4dee0a999': 'hun',  # Hungarian
    '134725e2caa8aa5418d0ee78aca7ecab': 'zho',  # Chinese Simplified
    'cc07302d96b0bc8419b6b9a962b43fbb': 'jpn',  # Japanese
    '6ef338731b997bd4899dfeadfcbd4520': 'spa',  # Spanish
    'dea889ccb63df23419e3a597e3cb260d': 'zho',  # Chinese Traditional (using same as simplified for now)
    'b2aa11790d5d3d5449b8f7c5be5f654d': 'kor',  # Korean
    '383395b723a4cd946a8371a57ca60e4d': 'por',  # Portuguese
    'bcf66203a8b4fd1438613d1760c0f804': 'deu',  # German
    '3db804138c290b945a93f2759bc37e15': 'fra',  # French
    'e599042bd4a16b343ac0739bcb9a0cd0': 'rus',  # Russian
    'cbb6102a59e69ac4595a399d51498dbb': 'pol',  # Polish
}


class DialogueStats:
    """Track dialogue statistics for a language."""

    def __init__(self):
        self.blocks = 0
        self.words = 0

    def add(self, text: str):
        """Add a dialogue line to stats."""
        if text:
            self.blocks += 1
            self.words += len(text.split())


class MonoBehaviourConverter:
    """Convert Unity MonoBehaviour YAML files to stats JSON."""

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
        self.character_names = {}  # {char_guid: {lang_code: display_name}}
        self.character_species = {}  # {char_guid: species_name_english}

    def extract_guid(self, guid_ref) -> str:
        """Extract GUID from Unity fileID reference."""
        # Can be a dict like: {fileID: 11400000, guid: 14d75042e9456774b895509f90b306b1, type: 2}
        # Or a string like: "{fileID: 11400000, guid: 14d75042e9456774b895509f90b306b1, type: 2}"
        if isinstance(guid_ref, dict):
            return guid_ref.get('guid')
        elif isinstance(guid_ref, str):
            match = re.search(r'guid:\s*([a-f0-9]+)', guid_ref)
            if match:
                return match.group(1)
        return None

    def map_language(self, guid: str) -> str:
        """Map language GUID to ISO code."""
        return LANGUAGE_GUID_MAP.get(guid, f'q{guid[:3]}')  # Use 'q' prefix for unknown languages

    def extract_character_names(self, file_path: Path):
        """Extract character names from a character/item data file."""
        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                content = f.read()

            # Parse YAML
            data = yaml.load(content, Loader=UnityLoader)

            if not data or 'MonoBehaviour' not in data:
                return

            mono = data['MonoBehaviour']

            # Get the character/item ID
            if 'id' not in mono:
                return

            char_guid = self.extract_guid(mono['id'])
            if not char_guid:
                return

            # Look for name fields (prefer fullname, then shortName, then localizedName)
            name_field = None
            for field_name in ['fullname', 'shortName', 'localizedName']:
                if field_name in mono and mono[field_name]:
                    name_field = mono[field_name]
                    break

            # Extract names for each language if name field exists
            if name_field and 'translations' in name_field:
                char_lang_names = {}
                for translation in name_field['translations']:
                    name = translation.get('value', '').strip()
                    if not name:
                        continue

                    lang_ref = translation.get('language')
                    if not lang_ref:
                        continue

                    lang_guid = self.extract_guid(lang_ref)
                    if not lang_guid:
                        continue

                    lang_code = self.map_language(lang_guid)
                    char_lang_names[lang_code] = name

                if char_lang_names:
                    self.character_names[char_guid] = char_lang_names

            # Extract species name (English only)
            if 'speciesName' in mono and mono['speciesName']:
                species_field = mono['speciesName']
                if 'translations' in species_field:
                    for translation in species_field['translations']:
                        species = translation.get('value', '').strip()
                        if not species:
                            continue

                        lang_ref = translation.get('language')
                        if not lang_ref:
                            continue

                        lang_guid = self.extract_guid(lang_ref)
                        if not lang_guid:
                            continue

                        # Only store English version
                        lang_code = self.map_language(lang_guid)
                        if lang_code == 'eng':
                            self.character_species[char_guid] = species
                            break

        except Exception as e:
            # Silently skip files that can't be parsed
            pass

    def process_yaml_file(self, file_path: Path):
        """Process a single YAML file."""
        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                content = f.read()

            # Parse YAML using custom Unity loader
            data = yaml.load(content, Loader=UnityLoader)

            if not data or 'MonoBehaviour' not in data:
                return

            mono = data['MonoBehaviour']

            # Get file identifier for tracking
            file_name = file_path.name

            # Check if this file has dialogue lines
            if 'lines' in mono:
                self._process_dialogue_lines(mono['lines'], file_name)

        except Exception as e:
            print(f"Warning: Failed to process {file_path.name}: {e}", file=sys.stderr)

    def _process_dialogue_lines(self, lines: List[Dict], file_name: str):
        """Process dialogue lines from a YAML file."""
        for line_num, line in enumerate(lines, 1):
            if 'localizedText' not in line:
                continue

            localized_text = line['localizedText']

            # Get character ID
            character_guid = None
            if 'characterId' in line and line['characterId']:
                character_guid = self.extract_guid(line['characterId'])

            character_id = character_guid if character_guid else 'narrator'

            # Process translations
            if 'translations' in localized_text:
                for translation in localized_text['translations']:
                    text = translation.get('value', '').strip()
                    if not text:
                        continue

                    # Get language
                    lang_ref = translation.get('language')
                    if not lang_ref:
                        continue

                    lang_guid = self.extract_guid(lang_ref)
                    if not lang_guid:
                        continue

                    lang_code = self.map_language(lang_guid)

                    # Add to dialogue lines (unless in stats-only mode)
                    if not self.stats_only:
                        self.dialogue_lines[lang_code].append({
                            'character': character_id,
                            'text': text,
                            'file': file_name,
                            'line': line_num,
                            'context': None
                        })

                    # Update character stats
                    if text:
                        self.language_stats[lang_code]['blocks'] += 1
                        self.language_stats[lang_code]['words'] += len(text.split())
                        self.language_stats[lang_code]['characters'][character_id].add(text)

    def convert(self) -> Dict[str, Any]:
        """Convert all YAML files to stats JSON format."""
        # Find all .asset files
        print(f"Scanning {self.input_dir} for YAML files...")
        if self.stats_only:
            print("Mode: Stats-only (dialogue text will not be included)")
        else:
            print("Mode: Full export (including dialogue text)")

        asset_files = list(self.input_dir.glob('*.asset'))
        print(f"Found {len(asset_files)} .asset files")

        # First pass: extract character names from data files
        print("Extracting character names...")
        for file_path in asset_files:
            self.extract_character_names(file_path)

        print(f"Found {len(self.character_names)} character definitions")

        # Second pass: check which files have localizedText for dialogue
        files_with_text = []
        for file_path in asset_files:
            try:
                with open(file_path, 'r', encoding='utf-8') as f:
                    content = f.read()
                    if 'localizedText:' in content:
                        files_with_text.append(file_path)
            except Exception:
                continue

        print(f"Found {len(files_with_text)} files with localizedText")

        # Process each file with dialogue
        for i, file_path in enumerate(files_with_text, 1):
            if i % 10 == 0:
                print(f"Processing {i}/{len(files_with_text)}...")
            self.process_yaml_file(file_path)

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

            # Convert character stats
            for char_id, char_stats in stats['characters'].items():
                # Get the display name for this character in this language
                display_name = None
                if char_id == 'narrator':
                    display_name = 'Narrator'
                elif char_id in self.character_names:
                    # Use the extracted localized name
                    display_name = self.character_names[char_id].get(lang_code)
                    # Fallback to English if this language doesn't have a translation
                    if not display_name:
                        display_name = self.character_names[char_id].get('eng', char_id)

                # Final fallback to formatted GUID
                if not display_name:
                    display_name = char_id.replace('_', ' ').title()

                char_data = {
                    'display_name': display_name,
                    'blocks': char_stats.blocks,
                    'words': char_stats.words
                }

                # Add species if available (only on first language to avoid duplication)
                if lang_code == 'eng' and char_id in self.character_species:
                    char_data['species'] = self.character_species[char_id]

                result['languages'][lang_code]['characters'][char_id] = char_data

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
        description='Convert Unity MonoBehaviour YAML files to Ren\'Py stats JSON format.',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog='''
Examples:
  # Full export with dialogue text
  python monobehaviour_to_stats.py MonoBehaviour/ stats.json

  # Stats-only mode (for commercial games)
  python monobehaviour_to_stats.py MonoBehaviour/ stats.json --stats-only
        '''
    )

    parser.add_argument(
        'input_directory',
        help='Directory containing MonoBehaviour .asset files'
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

    converter = MonoBehaviourConverter(args.input_directory, stats_only=args.stats_only)
    converter.save_stats(args.output_file)


if __name__ == '__main__':
    main()
