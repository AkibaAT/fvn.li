#!/usr/bin/env python3
"""Remove recalled embedded Bun roots without changing the executable layout."""

import hashlib
import re
import ssl
import struct
import sys
import urllib.request
from pathlib import Path

BUN_VERSION = '1.4.3'
DER_SHA256 = '9c211f519356eff61365ff890856135b1b8ee298775b144ce6089782ae603f90'
RECALLED = {
    '86a1ecba089c4a8d3bbe2734c612ba341d813e043cf9e8a862cd5c57a36bbe6b',
    'd7a7a0fb5d7e2731d771e9484ebcdef71d5f0c3e0a2948782bc83ee0ea699ef4',
}


def patch(binary, bundle, der_bundle):
    if not binary.startswith(b'\x7fELF'):
        raise ValueError('Expected a Linux Bun executable')
    trusted = {hashlib.sha256(ssl.PEM_cert_to_DER_cert(pem.decode())).hexdigest()
               for pem in re.findall(rb'-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----', bundle, re.S)}
    if not trusted or trusted & RECALLED:
        raise ValueError('The system CA bundle must already exclude the recalled roots')
    count = struct.unpack_from('<I', der_bundle)[0]
    if not 1 <= count <= 1000:
        raise ValueError('Unexpected DER certificate count')
    offsets = struct.unpack_from(f'<{count + 1}I', der_bundle, 4)
    data = der_bundle[4 * (count + 2):]
    if offsets[0] != 0 or offsets[-1] != len(data) or any(a >= b for a, b in zip(offsets, offsets[1:])):
        raise ValueError('Unexpected DER bundle layout')
    roots = [data[start:end] for start, end in zip(offsets, offsets[1:])]
    if not any(hashlib.sha256(root).hexdigest() in RECALLED for root in roots):
        raise ValueError('No recalled roots found; review Bun and remove this temporary patch')
    replacements = [root for root in roots if hashlib.sha256(root).hexdigest() in trusted]
    if not replacements:
        raise ValueError('No embedded root matches the maintained system CA bundle')
    replacement = min(replacements, key=len)
    # shortcut: Bun 1.4.3 embeds DER roots; remove this patch when stable Bun ships the CA revocations.
    # Keep the count: Bun's compiler can fold it into executable code. Duplicate an already trusted root.
    roots = [replacement if hashlib.sha256(root).hexdigest() in RECALLED else root for root in roots]
    new_offsets = [0]
    for root in roots:
        new_offsets.append(new_offsets[-1] + len(root))
    updated = struct.pack(f'<{count + 2}I', count, *new_offsets) + b''.join(roots)
    if len(updated) > len(der_bundle):
        raise ValueError('Replacement does not fit the embedded DER bundle')
    updated += b'\0' * (len(der_bundle) - len(updated))
    original_count, patched_count = binary.count(der_bundle), binary.count(updated)
    if original_count == 1 and patched_count == 0:
        return binary.replace(der_bundle, updated)
    # setup-bun can cache the executable after this patch has been applied.
    if original_count == 0 and patched_count == 1:
        return binary
    raise ValueError('Expected exactly one original or already-patched DER bundle in Linux Bun')


if __name__ == '__main__':
    url = f'https://raw.githubusercontent.com/oven-sh/bun/bun-v{BUN_VERSION}/packages/bun-usockets/root_certs.der'
    with urllib.request.urlopen(url, timeout=30) as response:
        der_bundle = response.read()
    if hashlib.sha256(der_bundle).hexdigest() != DER_SHA256:
        raise ValueError('Bun upstream DER bundle checksum mismatch')
    binary_path = Path(sys.argv[1])
    original = binary_path.read_bytes()
    patched = patch(original, Path('/etc/ssl/certs/ca-certificates.crt').read_bytes(), der_bundle)
    if patched == original:
        print(f'Bun CA patch already applied and verified: {hashlib.sha256(patched).hexdigest()}')
    else:
        binary_path.write_bytes(patched)
        print(f'Bun CA patch: {hashlib.sha256(original).hexdigest()} -> {hashlib.sha256(patched).hexdigest()}')
