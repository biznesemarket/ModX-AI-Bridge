#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

fail=0

check_pinned_ref() {
  local kind="$1" spec="$2" ref
  ref="${spec##*@}"
  if [[ "$ref" != "$spec" && "$ref" =~ ^[0-9a-f]{40}$ ]]; then
    return 0
  fi
  echo "UNPINNED ${kind}: ${spec}" >&2
  fail=1
}

check_image_by_digest() {
  local kind="$1" spec="$2"
  if [[ "$spec" == *"@sha256:"* ]]; then
    return 0
  fi
  echo "UNPINNED ${kind}: ${spec}" >&2
  fail=1
}

while IFS= read -r spec; do
  check_pinned_ref "action" "$spec"
done < <(grep -rhoE 'uses:[[:space:]]*[^[:space:]]+' .github/workflows | sed -E 's/uses:[[:space:]]*//')

while IFS= read -r spec; do
  check_image_by_digest "compose image" "$spec"
done < <(grep -hoE '^[[:space:]]*image:[[:space:]]*[^[:space:]]+' docker-compose.yml deploy/docker/docker-compose.production.yml | sed -E 's/^[[:space:]]*image:[[:space:]]*//')

while IFS= read -r spec; do
  check_image_by_digest "Dockerfile base image" "$spec"
done < <(grep -hoE '^FROM[[:space:]]+[^[:space:]]+' docker/modx/Dockerfile | sed -E 's/^FROM[[:space:]]+//')

while IFS= read -r spec; do
  check_image_by_digest "Dockerfile --from image" "$spec"
done < <(grep -hoE -- '--from=[^[:space:]]+' docker/modx/Dockerfile | sed -E 's/^--from=//')

if [[ "$fail" -ne 0 ]]; then
  echo "SUPPLY CHAIN PINS: FAIL" >&2
  exit 1
fi

echo "SUPPLY CHAIN PINS: PASS"
