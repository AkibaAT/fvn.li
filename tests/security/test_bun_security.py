import json
import subprocess
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


class BunSecurityTest(unittest.TestCase):
    def test_all_bun_builds_pin_the_release_with_updated_certificates(self):
        version = json.loads((ROOT / 'package.json').read_text())['packageManager'].removeprefix('bun@')
        self.assertEqual(version, '1.4.4')
        for name in ['docker/app/Dockerfile', '.ddev/web-build/Dockerfile.bun']:
            self.assertIn(f'ARG BUN_VERSION={version}\n', (ROOT / name).read_text())
        self.assertIn(f'FROM oven/bun:{version}-alpine@sha256:',
                      (ROOT / '.ddev/web-build/discord-bot.Dockerfile').read_text())

    def test_bun_and_system_trust_stores_exclude_recalled_roots(self):
        script = r"""
import assert from 'node:assert/strict';
import { X509Certificate } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { rootCertificates } from 'node:tls';

assert.equal(Bun.version, '1.4.4');
const recalled = new Set([
    '86a1ecba089c4a8d3bbe2734c612ba341d813e043cf9e8a862cd5c57a36bbe6b',
    'd7a7a0fb5d7e2731d771e9484ebcdef71d5f0c3e0a2948782bc83ee0ea699ef4',
]);
const system = readFileSync('/etc/ssl/certs/ca-certificates.crt', 'utf8')
    .match(/-----BEGIN CERTIFICATE-----[\s\S]+?-----END CERTIFICATE-----/g) ?? [];
for (const roots of [rootCertificates, system]) {
    assert.ok(roots.length > 100);
    for (const pem of roots) {
        assert.ok(!recalled.has(new X509Certificate(pem).fingerprint256.replaceAll(':', '').toLowerCase()));
    }
}
"""
        result = subprocess.run(['bun', '--eval', script], cwd=ROOT, capture_output=True, text=True, timeout=15)
        self.assertEqual(result.returncode, 0, result.stderr)


if __name__ == '__main__':
    unittest.main()
