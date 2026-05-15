#!/usr/bin/env python3
import json
import sys
from pathlib import Path

DEPENDENCY_FIELDS = (
    "dependencies",
    "devDependencies",
    "optionalDependencies",
    "peerDependencies",
)

BLOCKED_SPEC_PREFIXES = (
    "file:",
    "git+",
    "github:",
    "gitlab:",
    "bitbucket:",
    "link:",
    "portal:",
    "workspace:",
)


def is_blocked_spec(spec: str) -> bool:
    normalized = spec.strip().lower()

    if normalized.startswith(BLOCKED_SPEC_PREFIXES):
        return True

    if normalized.startswith("git@"):
        return True

    if normalized.startswith("http://") or normalized.startswith("https://"):
        return True

    return False


def check_package_json(path: Path) -> list[str]:
    if not path.exists():
        return []

    package = json.loads(path.read_text(encoding="utf-8"))
    findings = []

    for field in DEPENDENCY_FIELDS:
        dependencies = package.get(field, {})
        if not isinstance(dependencies, dict):
            continue

        for name, spec in dependencies.items():
            if isinstance(spec, str) and is_blocked_spec(spec):
                findings.append(f"{path}:{field}.{name} uses blocked dependency source {spec!r}")

    return findings


def main() -> int:
    paths = [Path("package.json"), Path("docker/social-images/package.json")]
    findings = []

    for path in paths:
        findings.extend(check_package_json(path))

    if findings:
        for finding in findings:
            print(finding, file=sys.stderr)
        return 1

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
