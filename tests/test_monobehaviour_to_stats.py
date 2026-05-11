import importlib.util
import tempfile
import unittest
from pathlib import Path

import yaml


MODULE_PATH = Path(__file__).resolve().parents[1] / "resources" / "renpy" / "monobehaviour_to_stats.py"
SPEC = importlib.util.spec_from_file_location("monobehaviour_to_stats", MODULE_PATH)
monobehaviour_to_stats = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(monobehaviour_to_stats)


class UnityLoaderTest(unittest.TestCase):
    def test_rejects_python_object_apply_tags(self):
        with tempfile.TemporaryDirectory() as temp_dir:
            marker_path = Path(temp_dir) / "pwned.txt"
            payload = (
                "exploit: !!python/object/apply:os.system "
                f"['printf owned > {marker_path}']\n"
            )

            with self.assertRaises(yaml.constructor.ConstructorError):
                yaml.load(payload, Loader=monobehaviour_to_stats.UnityLoader)

            self.assertFalse(marker_path.exists())

    def test_still_accepts_unity_tags(self):
        payload = """%YAML 1.1
%TAG !u! tag:unity3d.com,2011:
--- !u!114 &11400000
MonoBehaviour:
  id:
    guid: 14d75042e9456774b895509f90b306b1
  localizedText:
    translations:
      - language:
          guid: 14d75042e9456774b895509f90b306b1
        value: Hello
"""

        data = yaml.load(payload, Loader=monobehaviour_to_stats.UnityLoader)

        self.assertEqual(
            data["MonoBehaviour"]["localizedText"]["translations"][0]["value"],
            "Hello",
        )


if __name__ == "__main__":
    unittest.main()
