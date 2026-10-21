import importlib.util
import json
import ssl
import struct
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('patch_bun_ca', ROOT / '.ddev/web-build/patch-bun-ca.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
FIXTURES = Path(__file__).parent / 'fixtures'
RECALLED = (FIXTURES / 'emSign_ECC_Root_CA_-_G3.pem').read_bytes()
TRUSTED = (FIXTURES / 'ISRG_Root_X2.pem').read_bytes()


def der_bundle(*certificates):
    roots = [ssl.PEM_cert_to_DER_cert(pem.decode()) for pem in certificates]
    offsets = [0]
    for root in roots:
        offsets.append(offsets[-1] + len(root))
    return struct.pack(f'<{len(roots) + 2}I', len(roots), *offsets) + b''.join(roots)


class BunCaPatchTest(unittest.TestCase):
    def test_every_bun_build_uses_the_release_the_ca_patch_targets(self):
        self.assertEqual(json.loads((ROOT / "package.json").read_text())["packageManager"], f"bun@{module.BUN_VERSION}")
        for name in ['docker/app/Dockerfile', '.ddev/web-build/Dockerfile.bun']:
            self.assertIn(f'ARG BUN_VERSION={module.BUN_VERSION}\n', (ROOT / name).read_text())
        self.assertIn(f'FROM oven/bun:{module.BUN_VERSION}-alpine@sha256:',
                      (ROOT / '.ddev/web-build/discord-bot.Dockerfile').read_text())

    def test_replaces_recalled_data_without_changing_count_or_executable_layout(self):
        blob = der_bundle(RECALLED, TRUSTED)
        original = b'\x7fELFbefore' + blob + b'after'
        patched = module.patch(original, TRUSTED, blob)
        expected = der_bundle(TRUSTED, TRUSTED)
        self.assertEqual(len(patched), len(original))
        self.assertEqual(patched, b'\x7fELFbefore' + expected + b'\0' * (len(blob) - len(expected)) + b'after')
        self.assertNotIn(ssl.PEM_cert_to_DER_cert(RECALLED.decode()), patched)

    def test_rejects_unexpected_layout_or_unmaintained_trust(self):
        blob = der_bundle(RECALLED, TRUSTED)
        binary = b'\x7fELF' + blob
        for candidate, bundle, source in [
            (b'not-ELF' + blob, TRUSTED, blob),
            (binary + blob, TRUSTED, blob),
            (binary, TRUSTED, b'unknown'),
            (binary, TRUSTED + RECALLED, blob),
            (binary, b'', blob),
            (b'\x7fELF' + der_bundle(TRUSTED), TRUSTED, der_bundle(TRUSTED)),
            (b'\x7fELF' + der_bundle(RECALLED), TRUSTED, der_bundle(RECALLED)),
        ]:
            with self.subTest(candidate_length=len(candidate), bundle_length=len(bundle)):
                with self.assertRaises(ValueError):
                    module.patch(candidate, bundle, source)

    def test_cached_patched_binary_is_unchanged_and_only_the_exact_output_is_accepted(self):
        blob = der_bundle(RECALLED, TRUSTED)
        original = b'\x7fELFbefore' + blob + b'after'
        patched = module.patch(original, TRUSTED, blob)
        self.assertEqual(module.patch(patched, TRUSTED, blob), patched)
        corrupted = patched.replace(der_bundle(TRUSTED, TRUSTED), b'x' * len(blob), 1)
        for candidate, bundle in [
            (patched + blob, TRUSTED),
            (patched + patched, TRUSTED),
            (corrupted, TRUSTED),
            (patched, TRUSTED + RECALLED),
            (patched, b''),
        ]:
            with self.subTest(candidate_length=len(candidate), bundle_length=len(bundle)):
                with self.assertRaises(ValueError):
                    module.patch(candidate, bundle, blob)


if __name__ == '__main__':
    unittest.main()
