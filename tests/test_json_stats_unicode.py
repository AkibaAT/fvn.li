import ast
import json
import sys
import textwrap
import unittest
from pathlib import Path


SCRIPT_PATH = Path(__file__).resolve().parents[1] / "resources" / "renpy" / "json_stats.rpy"


def extract_decode_unicode_escape_source():
    script = SCRIPT_PATH.read_text(encoding="utf-8")
    start = script.index("    def decode_unicode_escape(s):")
    lines = script[start:].splitlines()
    function_lines = [lines[0]]

    for line in lines[1:]:
        if line.startswith("    ") and not line.startswith("        ") and line.strip():
            break

        function_lines.append(line)

    return textwrap.dedent("\n".join(function_lines))


def load_decode_unicode_escape():
    source = extract_decode_unicode_escape_source()

    namespace = {"sys": sys}
    exec(source, namespace)

    return namespace["decode_unicode_escape"]


class JsonStatsUnicodeEscapeTest(unittest.TestCase):
    def test_decode_failure_returns_json_serializable_string(self):
        decode_unicode_escape = load_decode_unicode_escape()

        for text in [r"\u00e9", r"\u4f60", r"\U0001F600"]:
            decoded = decode_unicode_escape(text)

            self.assertIsInstance(decoded, str)
            json.dumps({"display_name": decoded})

    def test_decode_function_source_tracks_original_value_before_mutating(self):
        tree = ast.parse(extract_decode_unicode_escape_source())

        returns = [
            node.value.id
            for node in ast.walk(tree)
            if isinstance(node, ast.Return) and isinstance(node.value, ast.Name)
        ]

        self.assertIn("original", returns)


if __name__ == "__main__":
    unittest.main()
