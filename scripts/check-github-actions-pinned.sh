#!/usr/bin/env bash
set -euo pipefail

status=0

while IFS=: read -r file line content; do
    ref="${content##*@}"
    ref="${ref%% *}"
    ref="${ref%%#*}"
    ref="${ref%\"}"
    ref="${ref%\'}"

    if [[ ! "$ref" =~ ^[0-9a-f]{40}$ ]]; then
        echo "$file:$line uses an unpinned action ref: $content" >&2
        status=1
    fi
done < <(git grep -nE 'uses:[[:space:]]*[^[:space:]]+@' -- '.github/workflows/*.yml' '.github/workflows/*.yaml' || true)

exit "$status"
